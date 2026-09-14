<?php

declare(strict_types=1);

namespace App\Services\Prayer;

use App\Enums\PrayerName;
use App\Enums\ScheduleSource;
use App\Models\PrayerSchedule;
use App\Repositories\Contracts\PrayerScheduleRepositoryInterface;
use App\Repositories\Contracts\SettingRepositoryInterface;
use App\Support\Helpers\DateHelper;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Owns everything about which prayer happens at what time.
 */
class PrayerScheduleService
{
    public function __construct(
        private readonly PrayerScheduleRepositoryInterface $schedules,
        private readonly PrayerTimeCalculator $calculator,
        private readonly OfficialScheduleClient $official,
        /*
         * The repository, not SettingService — that one already depends on this
         * class, and taking it here would close the loop and make the container
         * fail to resolve either. PrayerTimeCalculator reaches settings the same
         * way for the same reason.
         */
        private readonly SettingRepositoryInterface $settings,
    ) {}

    /**
     * Fill in any missing days in the window.
     *
     * The published Kemenag schedule is preferred, and the local astronomical
     * calculation fills whatever it could not supply.
     *
     * That order is deliberate, and it is the reverse of what this used to do.
     * Calculating locally was chosen so the adhan would not depend on anyone
     * else's server — but the schedule is generated here and *stored*, so by the
     * time the adhan plays the API has long been out of the picture. What
     * calculating locally actually bought was a class of bug that the official
     * figures cannot have: it computes from coordinates, and wrong coordinates
     * silently shift every prayer. That is exactly what went wrong once already.
     *
     * So: official when reachable, calculated when not, and the row records
     * which. An outage costs about a minute of precision on the days it covers,
     * not a schedule.
     *
     * Days the board has overridden by hand are left alone unless `$force`.
     *
     * @return int Number of days written.
     */
    public function generateRange(CarbonImmutable $from, CarbonImmutable $to, bool $force = false): int
    {
        $method = $this->calculator->method();
        $officialByDate = $this->officialTimingsBetween($from, $to);
        $written = 0;

        for ($cursor = $from->startOfDay(); $cursor->lessThanOrEqualTo($to); $cursor = $cursor->addDay()) {
            $existing = $this->schedules->forDate($cursor);

            if ($existing !== null && ! $force) {
                continue;
            }

            $timings = $officialByDate[$cursor->toDateString()] ?? null;
            $source = $timings === null ? ScheduleSource::Calculated : ScheduleSource::Official;

            $this->schedules->storeTimings(
                $cursor,
                $timings ?? $this->calculator->calculate($cursor),
                $method,
                $force,
                $source,
            );

            $written++;
        }

        return $written;
    }

    /**
     * The official schedule for every month the window touches.
     *
     * Fetched a month at a time because that is how the API answers; asking per
     * day would be thirty requests for something it already sends as one.
     *
     * Returns an empty array when no city is configured or the API is
     * unreachable — the caller then falls back per day, so a partial answer is
     * used as far as it goes rather than discarded.
     *
     * @return array<string, array<string, string>>
     */
    private function officialTimingsBetween(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $cityId = (string) ($this->settings->get(
            'prayer.official_city_id',
            config('dkm.official_schedule.city_id'),
        ) ?? '');

        if ($cityId === '') {
            return [];
        }

        $collected = [];

        for (
            $month = $from->startOfMonth();
            $month->lessThanOrEqualTo($to->startOfMonth());
            $month = $month->addMonth()
        ) {
            $collected = [...$collected, ...($this->official->monthlyTimings($cityId, $month) ?? [])];
        }

        return $collected;
    }

    /**
     * Keep the configured horizon of days always populated, so the player never
     * asks for a day that does not exist yet.
     */
    public function ensureHorizon(): int
    {
        $from = DateHelper::today();
        $to = $from->addDays((int) config('dkm.prayer.generate_days_ahead'));

        return $this->generateRange($from, $to);
    }

    /**
     * Recalculate every upcoming day that is not a manual override.
     *
     * Called after the coordinates or calculation method change, so a corrected
     * setting reaches the players the same day instead of at the horizon edge.
     */
    public function regenerateAutomatic(): int
    {
        $from = DateHelper::today();
        $to = $from->addDays((int) config('dkm.prayer.generate_days_ahead'));
        $method = $this->calculator->method();
        $officialByDate = $this->officialTimingsBetween($from, $to);
        $written = 0;

        for ($cursor = $from; $cursor->lessThanOrEqualTo($to); $cursor = $cursor->addDay()) {
            $existing = $this->schedules->forDate($cursor);

            if ($existing?->is_manual_override === true) {
                continue;
            }

            // Same order as generateRange. Without this, saving the settings
            // page would quietly replace official rows with calculated ones.
            $timings = $officialByDate[$cursor->toDateString()] ?? null;

            $this->schedules->storeTimings(
                $cursor,
                $timings ?? $this->calculator->calculate($cursor),
                $method,
                true,
                $timings === null ? ScheduleSource::Calculated : ScheduleSource::Official,
            );

            $written++;
        }

        return $written;
    }

    /**
     * Today's schedule, generating it on the spot if the scheduler has not run.
     */
    public function forDate(CarbonImmutable|string $date): PrayerSchedule
    {
        $carbon = DateHelper::toCarbon($date)->startOfDay();
        $schedule = $this->schedules->forDate($carbon);

        if ($schedule !== null) {
            return $schedule;
        }

        // Nothing stored for that day, which means the scheduler has not run.
        // Generating through the normal path keeps the official source in play
        // rather than silently producing a calculated row.
        $this->generateRange($carbon, $carbon);

        return $this->schedules->forDate($carbon) ?? $this->schedules->storeTimings(
            $carbon,
            $this->calculator->calculate($carbon),
            $this->calculator->method(),
        );
    }

    public function today(): PrayerSchedule
    {
        return $this->forDate(DateHelper::today());
    }

    /**
     * @return Collection<int, PrayerSchedule>
     */
    public function forMonth(CarbonImmutable $month): Collection
    {
        return $this->forRange($month->startOfMonth(), $month->endOfMonth());
    }

    /**
     * Every day in the window, generating any that are missing first.
     *
     * A week, or a printed roster, can start in one month and end in the next;
     * reading it a month at a time left the days on the far side without times.
     *
     * @return Collection<int, PrayerSchedule>
     */
    public function forRange(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $this->generateRange($from, $to);

        return $this->schedules->between($from, $to);
    }

    /**
     * The next prayer from now, rolling into tomorrow after Isha.
     *
     * @return array{prayer: PrayerName, at: CarbonImmutable, countdown_seconds: int}
     */
    public function nextPrayer(): array
    {
        $now = DateHelper::now();
        $next = $this->today()->nextPrayerAfter($now);

        if ($next === null) {
            $tomorrow = $this->forDate(DateHelper::today()->addDay());
            $next = [
                'prayer' => PrayerName::Fajr,
                'at' => $tomorrow->momentFor(PrayerName::Fajr),
            ];
        }

        return [
            'prayer' => $next['prayer'],
            'at' => $next['at'],
            'countdown_seconds' => max(0, $now->diffInSeconds($next['at'], false)),
        ];
    }

    /**
     * Replace one day's timings by hand and mark it as an override so the
     * generator will not overwrite it on the next run.
     *
     * @param  array<string, string>  $timings
     */
    public function overrideDay(CarbonImmutable|string $date, array $timings, int $userId): PrayerSchedule
    {
        $carbon = DateHelper::toCarbon($date)->startOfDay();

        return $this->schedules->updateOrCreate(
            ['date' => $carbon->toDateString()],
            [
                ...$timings,
                'is_manual_override' => true,
                'calculation_method' => 'MANUAL',
                'updated_by' => $userId,
            ],
        );
    }

    /**
     * Drop the override and fall back to the calculated times.
     */
    public function resetDay(CarbonImmutable|string $date): PrayerSchedule
    {
        $carbon = DateHelper::toCarbon($date)->startOfDay();

        return $this->schedules->updateOrCreate(
            ['date' => $carbon->toDateString()],
            [
                ...$this->calculator->calculate($carbon),
                'is_manual_override' => false,
                'calculation_method' => $this->calculator->method(),
            ],
        );
    }
}
