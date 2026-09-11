<?php

declare(strict_types=1);

namespace App\Services\Device;

use App\DataTransferObjects\Device\PlaybackItemData;
use App\Enums\AudioTrackType;
use App\Enums\PlaybackStatus;
use App\Enums\PlaybackType;
use App\Enums\PrayerName;
use App\Models\AudioTrack;
use App\Models\AudioZone;
use App\Models\PrayerSchedule;
use App\Models\ZonePrayerSetting;
use App\Repositories\Contracts\AudioTrackRepositoryInterface;
use App\Repositories\Contracts\PlaybackLogRepositoryInterface;
use App\Services\Prayer\PrayerScheduleService;
use App\Support\Helpers\DateHelper;
use Carbon\CarbonImmutable;

/**
 * Turns "what should this room play today?" into a concrete, timestamped list.
 *
 * The plan is computed server-side and handed to the browser player, which
 * caches it. Two consequences matter:
 *
 *  - After a power cut the player reloads, reads its cached plan, and resumes
 *    without anyone restarting a local server.
 *  - Every adhan slot is written to `playback_logs` as `pending` up front, so a
 *    slot that is never reported back can be flagged as `missed` instead of
 *    silently disappearing.
 */
class PlaybackPlanBuilder
{
    public function __construct(
        private readonly PrayerScheduleService $prayerSchedules,
        private readonly AudioTrackRepositoryInterface $tracks,
        private readonly PlaybackLogRepositoryInterface $playbackLogs,
    ) {}

    /**
     * Build the plan for one zone on one day.
     *
     * @param  bool  $persist  Whether to reserve `playback_logs` rows for monitoring.
     * @return array<int, PlaybackItemData>
     */
    public function build(AudioZone $zone, ?CarbonImmutable $date = null, bool $persist = true): array
    {
        $date ??= DateHelper::today();
        $schedule = $this->prayerSchedules->forDate($date);

        $items = [
            ...$this->buildPrayerItems($zone, $schedule, $date),
            ...$this->buildMurottalItems($zone, $schedule, $date),
        ];

        $selfTest = $this->buildSelfTestItem($zone, $date);

        if ($selfTest !== null) {
            $items[] = $selfTest;
        }

        usort(
            $items,
            static fn (PlaybackItemData $a, PlaybackItemData $b): int => $a->scheduledAt <=> $b->scheduledAt,
        );

        if ($persist) {
            $this->reserveMonitoringSlots($zone, $items);
        }

        return $items;
    }

    /**
     * Tarhim, adhan, and iqamah for every prayer the zone has enabled.
     *
     * @return array<int, PlaybackItemData>
     */
    private function buildPrayerItems(AudioZone $zone, PrayerSchedule $schedule, CarbonImmutable $date): array
    {
        if (! $zone->is_adhan_enabled) {
            return [];
        }

        $items = [];
        $settings = $zone->prayerSettings->keyBy(static fn (ZonePrayerSetting $setting): string => $setting->prayer->value);

        foreach (PrayerName::withAdhan() as $prayer) {
            $setting = $settings->get($prayer->value);

            if ($setting !== null && ! $setting->is_adhan_enabled) {
                continue;
            }

            $volume = $setting->volume ?? $zone->default_volume;
            $adhanAt = $schedule->momentFor($prayer)->addMinutes($setting->offset_minutes ?? 0);

            if ($setting?->is_tarhim_enabled) {
                $tarhimTrack = $setting->tarhimTrack ?? $this->tracks->defaultForType(AudioTrackType::Tarhim);

                if ($tarhimTrack !== null) {
                    $tarhimAt = $adhanAt->subMinutes($setting->tarhim_lead_minutes);

                    $items[] = $this->makeItem(
                        date: $date,
                        type: PlaybackType::Tarhim,
                        prayer: $prayer,
                        at: $tarhimAt,
                        track: $tarhimTrack,
                        volume: $volume,
                    );
                }
            }

            $adhanTrack = $setting?->adhanTrack ?? $this->defaultAdhanTrack($prayer);

            if ($adhanTrack !== null) {
                $items[] = $this->makeItem(
                    date: $date,
                    type: PlaybackType::Adhan,
                    prayer: $prayer,
                    at: $adhanAt,
                    track: $adhanTrack,
                    volume: $volume,
                );
            }

            if ($setting?->is_iqamah_enabled) {
                $iqamahTrack = $setting->iqamahTrack ?? $this->tracks->defaultForType(AudioTrackType::Iqamah);

                if ($iqamahTrack !== null) {
                    $items[] = $this->makeItem(
                        date: $date,
                        type: PlaybackType::Iqamah,
                        prayer: $prayer,
                        at: $adhanAt->addMinutes($setting->iqamah_delay_minutes),
                        track: $iqamahTrack,
                        volume: $volume,
                    );
                }
            }
        }

        return $items;
    }

    /**
     * Tilawah windows, trimmed so they never talk over an adhan.
     *
     * @return array<int, PlaybackItemData>
     */
    private function buildMurottalItems(AudioZone $zone, PrayerSchedule $schedule, CarbonImmutable $date): array
    {
        if (! $zone->is_murottal_enabled) {
            return [];
        }

        $items = [];

        foreach ($zone->murottalSchedules as $murottal) {
            if (! $murottal->is_active || ! $murottal->runsOn($date)) {
                continue;
            }

            $track = $murottal->track ?? $this->tracks->defaultForType(AudioTrackType::Murottal);

            if ($track === null) {
                continue;
            }

            $startsAt = DateHelper::combine($date, (string) $murottal->start_time);
            $endsAt = DateHelper::combine($date, (string) $murottal->end_time);

            if ($endsAt->lessThanOrEqualTo($startsAt)) {
                continue;
            }

            if ($murottal->stops_before_adhan) {
                $endsAt = $this->trimBeforeNextAdhan($schedule, $startsAt, $endsAt);

                if ($endsAt === null) {
                    continue;
                }
            }

            $items[] = new PlaybackItemData(
                referenceKey: $this->referenceKey($date, PlaybackType::Murottal, 'murottal-'.$murottal->id),
                type: PlaybackType::Murottal,
                prayer: null,
                scheduledAt: $startsAt,
                endsAt: $endsAt,
                trackId: $track->id,
                trackTitle: $track->title,
                streamUrl: $this->streamUrl($track),
                volume: $murottal->volume,
                isLoop: $murottal->is_loop,
                stopsBeforeAdhan: $murottal->stops_before_adhan,
            );
        }

        return $items;
    }

    /**
     * A short daily tone that proves the speakers still work, verified by the
     * player's own level measurement. Without it a broken amplifier would only
     * be discovered at the next adhan, in front of everyone.
     */
    private function buildSelfTestItem(AudioZone $zone, CarbonImmutable $date): ?PlaybackItemData
    {
        $time = config('dkm.playback.self_test_time');

        if (blank($time) || ! $zone->is_active) {
            return null;
        }

        $track = $this->tracks->defaultForType(AudioTrackType::TestTone);

        if ($track === null) {
            return null;
        }

        return $this->makeItem(
            date: $date,
            type: PlaybackType::SelfTest,
            prayer: null,
            at: DateHelper::combine($date, (string) $time),
            track: $track,
            volume: min(40, $zone->default_volume),
        );
    }

    /**
     * Cut a murottal window short if an adhan falls inside it.
     */
    private function trimBeforeNextAdhan(PrayerSchedule $schedule, CarbonImmutable $startsAt, CarbonImmutable $endsAt): ?CarbonImmutable
    {
        foreach (PrayerName::withAdhan() as $prayer) {
            $adhanAt = $schedule->momentFor($prayer);

            if ($adhanAt->betweenIncluded($startsAt, $endsAt)) {
                $trimmed = $adhanAt->subMinute();

                return $trimmed->greaterThan($startsAt) ? $trimmed : null;
            }
        }

        return $endsAt;
    }

    private function defaultAdhanTrack(PrayerName $prayer): ?AudioTrack
    {
        if ($prayer === PrayerName::Fajr) {
            return $this->tracks->defaultForType(AudioTrackType::AdhanFajr)
                ?? $this->tracks->defaultForType(AudioTrackType::Adhan);
        }

        return $this->tracks->defaultForType(AudioTrackType::Adhan);
    }

    private function makeItem(
        CarbonImmutable $date,
        PlaybackType $type,
        ?PrayerName $prayer,
        CarbonImmutable $at,
        AudioTrack $track,
        int $volume,
    ): PlaybackItemData {
        $suffix = $prayer?->value ?? $type->value;

        return new PlaybackItemData(
            referenceKey: $this->referenceKey($date, $type, $suffix),
            type: $type,
            prayer: $prayer,
            scheduledAt: $at,
            endsAt: $track->duration_seconds === null ? null : $at->addSeconds($track->duration_seconds),
            trackId: $track->id,
            trackTitle: $track->title,
            streamUrl: $this->streamUrl($track),
            volume: $volume,
        );
    }

    /**
     * Stable per-day identifier, e.g. `2026-09-01:adhan:maghrib`.
     */
    private function referenceKey(CarbonImmutable $date, PlaybackType $type, string $suffix): string
    {
        return $date->toDateString().':'.$type->value.':'.$suffix;
    }

    /**
     * Device-authenticated streaming endpoint. The player fetches this with its
     * token, decodes it, and caches the bytes so later playbacks work offline.
     */
    private function streamUrl(AudioTrack $track): string
    {
        return route('api.v1.audio-tracks.content', ['audioTrack' => $track->id]);
    }

    /**
     * Reserve a `pending` row for everything worth monitoring. Murottal is
     * excluded — a missed background recitation is not an incident, a missed
     * adhan is.
     *
     * @param  array<int, PlaybackItemData>  $items
     */
    private function reserveMonitoringSlots(AudioZone $zone, array $items): void
    {
        foreach ($items as $item) {
            if ($item->type === PlaybackType::Murottal) {
                continue;
            }

            $this->playbackLogs->ensureSlot($zone->id, $item->referenceKey, [
                'audio_track_id' => $item->trackId,
                'type' => $item->type->value,
                'prayer' => $item->prayer?->value,
                'scheduled_at' => $item->scheduledAt,
                'status' => PlaybackStatus::Pending->value,
                'volume' => $item->volume,
            ]);
        }
    }
}
