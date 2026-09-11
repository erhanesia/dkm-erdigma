<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Enums\NotificationKind;
use App\Enums\NotificationStatus;
use App\Enums\PrayerName;
use App\Models\FridaySchedule;
use App\Models\PrayerSchedule;
use App\Models\WhatsappNotification;
use App\Services\Friday\FridayScheduleService;
use App\Services\Prayer\PrayerScheduleService;
use App\Services\SettingService;
use App\Support\Helpers\DateHelper;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Decides which prayer reminders are due, composes them, and sends them.
 *
 * Called every minute by the scheduler. The work of not sending twice is done by
 * the database: a row is claimed with a unique key before the request goes out,
 * so a slow WOW response, an overlapping run or a restart cannot put the same
 * reminder in the group again.
 */
class PrayerAnnouncementService
{
    public function __construct(
        private readonly PrayerScheduleService $prayerSchedules,
        private readonly FridayScheduleService $fridaySchedules,
        private readonly SettingService $settings,
        private readonly WowWebhookClient $wow,
    ) {}

    /**
     * Send whatever is due right now.
     *
     * @return array<int, WhatsappNotification>
     */
    public function announceDue(?CarbonImmutable $now = null): array
    {
        $now ??= DateHelper::now();
        $sent = [];

        foreach ($this->duePrayers($now) as $prayer) {
            $notification = $this->announce($prayer['prayer'], $prayer['date'], $prayer['at']);

            if ($notification !== null) {
                $sent[] = $notification;
            }
        }

        return $sent;
    }

    /**
     * Prayers whose reminder window contains this moment.
     *
     * The window has a tail: `grace_minutes` covers a scheduler that ran late.
     * It is deliberately short — a reminder that arrives after the prayer has
     * started is worse than no reminder, because it tells people something they
     * can no longer act on.
     *
     * @return array<int, array{prayer: PrayerName, date: CarbonImmutable, at: CarbonImmutable}>
     */
    public function duePrayers(CarbonImmutable $now): array
    {
        $lead = (int) config('dkm.whatsapp.announcement.lead_minutes');
        $grace = (int) config('dkm.whatsapp.announcement.grace_minutes');

        /** @var array<int, string> $announceable */
        $announceable = config('dkm.whatsapp.announcement.prayers');

        $schedule = $this->prayerSchedules->forDate($now);
        $due = [];

        foreach (PrayerName::cases() as $prayer) {
            if (! in_array($prayer->value, $announceable, true)) {
                continue;
            }

            $at = $schedule->momentFor($prayer);
            $fireAt = $at->subMinutes($lead);

            // `$now` is inside [fireAt, fireAt + grace].
            $isDue = $now->greaterThanOrEqualTo($fireAt)
                && $now->lessThan($fireAt->addMinutes($grace + 1));

            if ($isDue) {
                $due[] = ['prayer' => $prayer, 'date' => $now->startOfDay(), 'at' => $at];
            }
        }

        return $due;
    }

    /**
     * Claim, compose and send one reminder.
     *
     * Returns null when this one was already claimed by an earlier run, which is
     * the normal outcome for every minute of the grace window after the first.
     */
    public function announce(PrayerName $prayer, CarbonImmutable $date, CarbonImmutable $at): ?WhatsappNotification
    {
        $isFridayPrayer = $prayer === PrayerName::Dhuhr && $date->isFriday();
        $kind = $isFridayPrayer ? NotificationKind::FridayReminder : NotificationKind::PrayerReminder;

        $friday = $isFridayPrayer ? $this->fridaySchedules->forDate($date) : null;

        $message = $isFridayPrayer
            ? $this->composeFriday($at, $friday)
            : $this->composePrayer($prayer, $at);

        /*
         * The unique index on (kind, reference_date, prayer) is what makes this
         * safe. `firstOrCreate` either inserts the claim or hands back the row a
         * previous run already made — and in the second case there is nothing
         * left to do.
         */
        $notification = WhatsappNotification::query()->firstOrCreate(
            [
                'kind' => $kind->value,
                'reference_date' => $date->toDateString(),
                'prayer' => $prayer->value,
            ],
            [
                'status' => NotificationStatus::Pending->value,
                'message' => $message,
                'attempts' => 0,
            ],
        );

        if (! $notification->wasRecentlyCreated) {
            return null;
        }

        return $this->deliver($notification);
    }

    /**
     * Push one claimed notification to WOW and record what happened.
     */
    public function deliver(WhatsappNotification $notification): WhatsappNotification
    {
        if (! $this->wow->isConfigured()) {
            // Not a failure. The message was composed correctly; there is simply
            // nowhere to send it yet, and the row records that plainly.
            $notification->forceFill([
                'status' => NotificationStatus::Skipped,
                'error' => 'WOW belum dikonfigurasi.',
            ])->save();

            return $notification;
        }

        $result = $this->wow->send($notification->message);

        $notification->forceFill([
            'status' => $result['ok'] ? NotificationStatus::Sent : NotificationStatus::Failed,
            'payload' => $result['payload'],
            'response' => $result['body'],
            'error' => $result['error'],
            'attempts' => $notification->attempts + 1,
            'sent_at' => $result['ok'] ? DateHelper::now() : null,
        ])->save();

        if (! $result['ok']) {
            Log::warning('Pengiriman WhatsApp gagal.', [
                'notification_id' => $notification->id,
                'error' => $result['error'],
            ]);
        }

        return $notification;
    }

    /**
     * The ordinary reminder.
     *
     * Kept short on purpose: it lands in a work group, and a wall of text five
     * times a day is how a useful notification becomes one people mute.
     */
    public function composePrayer(PrayerName $prayer, CarbonImmutable $at): string
    {
        $lead = (int) config('dkm.whatsapp.announcement.lead_minutes');

        return implode("\n", [
            '🕌 *'.$prayer->label().'* — '.$at->format('H:i').' WIB',
            '',
            'Waktu '.$prayer->label().' tinggal '.$lead.' menit lagi.',
            'Mari bersiap menuju '.$this->settings->mosqueName().'.',
            '',
            '_Pesan otomatis dari sistem DKM Erdigma._',
        ]);
    }

    /**
     * The Friday reminder, which carries the roster.
     *
     * Whoever is on duty is exactly what people want to know before Friday
     * prayer, and it is the one thing the ordinary reminder cannot say.
     */
    public function composeFriday(CarbonImmutable $at, ?FridaySchedule $friday): string
    {
        $lead = (int) config('dkm.whatsapp.announcement.lead_minutes');

        $lines = [
            '🕌 *Sholat Jumat* — '.$at->format('H:i').' WIB',
            DateHelper::formatLongDate($at),
            '',
        ];

        if ($friday === null) {
            $lines[] = 'Petugas Jumat pekan ini belum diumumkan.';
        } else {
            if ($friday->theme) {
                $lines[] = '📖 Tema: *'.$friday->theme.'*';
                $lines[] = '';
            }

            $lines[] = '👤 Khatib: *'.$friday->khatibName().'*';

            if ($friday->external_khatib_origin) {
                $lines[] = '     '.$friday->external_khatib_origin;
            }

            $lines[] = '👤 Imam: *'.($friday->imam?->name ?? 'belum ditentukan').'*';
            $lines[] = '📢 Muadzin: *'.($friday->muadzin?->name ?? 'belum ditentukan').'*';

            if ($friday->location) {
                $lines[] = '📍 '.$friday->location;
            }
        }

        $lines[] = '';
        $lines[] = 'Sholat Jumat dimulai '.$lead.' menit lagi.';
        $lines[] = '';
        $lines[] = '_Pesan otomatis dari sistem DKM Erdigma._';

        return implode("\n", $lines);
    }

    /**
     * Composes without sending — used by the settings page to show exactly what
     * the group will receive, so nobody has to wait for Maghrib to find out.
     *
     * @return array<string, string>
     */
    public function previews(): array
    {
        $today = DateHelper::today();
        $schedule = $this->prayerSchedules->forDate($today);
        $nextFriday = $today->isFriday() ? $today : $today->next(CarbonImmutable::FRIDAY);

        return [
            'prayer' => $this->composePrayer(
                PrayerName::Maghrib,
                $this->momentOf($schedule, PrayerName::Maghrib),
            ),
            'friday' => $this->composeFriday(
                $this->momentOf($this->prayerSchedules->forDate($nextFriday), PrayerName::Dhuhr),
                $this->fridaySchedules->forDate($nextFriday),
            ),
        ];
    }

    private function momentOf(PrayerSchedule $schedule, PrayerName $prayer): CarbonImmutable
    {
        return $schedule->momentFor($prayer);
    }
}
