<?php

declare(strict_types=1);

namespace App\Support\Helpers;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use GeniusTS\HijriDate\Hijri;

/**
 * Single source of truth for date/time presentation across the application.
 *
 * Every module needs Indonesian day names, Hijri dates, and "waktu lalu"
 * strings, so they live here instead of being re-implemented per controller.
 */
final class DateHelper
{
    /** @var array<int, string> */
    private const DAY_NAMES = [
        CarbonInterface::SUNDAY => 'Minggu',
        CarbonInterface::MONDAY => 'Senin',
        CarbonInterface::TUESDAY => 'Selasa',
        CarbonInterface::WEDNESDAY => 'Rabu',
        CarbonInterface::THURSDAY => 'Kamis',
        CarbonInterface::FRIDAY => "Jum'at",
        CarbonInterface::SATURDAY => 'Sabtu',
    ];

    /** @var array<int, string> */
    private const MONTH_NAMES = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    /** @var array<int, string> */
    private const HIJRI_MONTH_NAMES = [
        1 => 'Muharram', 2 => 'Safar', 3 => "Rabi'ul Awal", 4 => "Rabi'ul Akhir",
        5 => 'Jumadil Awal', 6 => 'Jumadil Akhir', 7 => 'Rajab', 8 => "Sya'ban",
        9 => 'Ramadhan', 10 => 'Syawal', 11 => 'Dzulqa\'dah', 12 => 'Dzulhijjah',
    ];

    /**
     * Normalise anything date-like into an immutable Carbon in app timezone.
     */
    public static function toCarbon(CarbonInterface|\DateTimeInterface|string|null $value = null): CarbonImmutable
    {
        if ($value === null) {
            return CarbonImmutable::now(self::timezone());
        }

        if ($value instanceof CarbonImmutable) {
            return $value->setTimezone(self::timezone());
        }

        return CarbonImmutable::parse($value)->setTimezone(self::timezone());
    }

    public static function timezone(): string
    {
        return (string) config('dkm.prayer.timezone', config('app.timezone', 'Asia/Jakarta'));
    }

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::timezone());
    }

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::today(self::timezone());
    }

    public static function dayName(CarbonInterface|\DateTimeInterface|string|null $date = null): string
    {
        return self::DAY_NAMES[self::toCarbon($date)->dayOfWeek] ?? '';
    }

    public static function monthName(int $month): string
    {
        return self::MONTH_NAMES[$month] ?? '';
    }

    /**
     * `Sep`, for date badges.
     *
     * A calendar-tile badge is sized to two digits; "September" spelled out is
     * three times that and spills over the edge, so anywhere a month sits inside
     * a fixed tile uses this instead.
     */
    public static function shortMonthName(int $month): string
    {
        // Mei and Juni are already short; truncating them reads worse than
        // leaving them whole.
        $name = self::MONTH_NAMES[$month] ?? '';

        return mb_strlen($name) <= 4 ? $name : mb_substr($name, 0, 3);
    }

    /**
     * `12 Januari 2026`
     */
    public static function formatDate(CarbonInterface|\DateTimeInterface|string|null $date = null): string
    {
        $carbon = self::toCarbon($date);

        return sprintf('%d %s %d', $carbon->day, self::monthName($carbon->month), $carbon->year);
    }

    /**
     * `Senin, 12 Januari 2026`
     */
    public static function formatLongDate(CarbonInterface|\DateTimeInterface|string|null $date = null): string
    {
        return self::dayName($date).', '.self::formatDate($date);
    }

    /**
     * `12 Januari 2026 14:30`
     */
    public static function formatDateTime(CarbonInterface|\DateTimeInterface|string|null $date = null): string
    {
        return self::formatDate($date).' '.self::toCarbon($date)->format('H:i');
    }

    /**
     * `14:30`
     */
    public static function formatTime(CarbonInterface|\DateTimeInterface|string|null $date = null): string
    {
        return self::toCarbon($date)->format('H:i');
    }

    /**
     * `Januari 2026`
     */
    public static function formatMonthYear(CarbonInterface|\DateTimeInterface|string|null $date = null): string
    {
        $carbon = self::toCarbon($date);

        return self::monthName($carbon->month).' '.$carbon->year;
    }

    /**
     * `12 Rajab 1447 H`
     */
    public static function formatHijri(CarbonInterface|\DateTimeInterface|string|null $date = null): string
    {
        $hijri = Hijri::convertToHijri(self::toCarbon($date)->toDateTimeString());

        return sprintf(
            '%d %s %d H',
            $hijri->day,
            self::HIJRI_MONTH_NAMES[$hijri->month] ?? '',
            $hijri->year,
        );
    }

    /**
     * `3 menit lalu`, `2 jam lagi`
     */
    public static function diffForHumans(CarbonInterface|\DateTimeInterface|string|null $date = null): string
    {
        return self::toCarbon($date)->locale('id')->diffForHumans();
    }

    /**
     * Human readable duration: `1 jam 5 menit`, `45 detik`.
     */
    public static function humanDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.' detik';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        $parts = [];

        if ($hours > 0) {
            $parts[] = $hours.' jam';
        }

        if ($minutes > 0) {
            $parts[] = $minutes.' menit';
        }

        return implode(' ', $parts) ?: '0 menit';
    }

    /**
     * Turn `HH:MM` or `HH:MM:SS` into a full datetime on the given day.
     */
    /**
     * A `Y-m` month string, falling back to the current month.
     *
     * Both month browsers — the panel's table and the public one — take this
     * value from the address bar, where it can be anything at all. Neither
     * should error on a mangled link that someone pasted into a chat thread;
     * both should show the month the reader is in.
     */
    public static function normaliseMonth(?string $value): string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}$/', $value) !== 1) {
            return self::today()->format('Y-m');
        }

        try {
            return self::toCarbon($value.'-01')->format('Y-m');
        } catch (\Throwable) {
            return self::today()->format('Y-m');
        }
    }

    /**
     * The first day of a `Y-m` month, normalised first.
     */
    public static function startOfMonthFrom(?string $value): CarbonImmutable
    {
        return self::toCarbon(self::normaliseMonth($value).'-01')->startOfMonth();
    }

    public static function combine(CarbonInterface|\DateTimeInterface|string $date, string $time): CarbonImmutable
    {
        [$hour, $minute, $second] = array_pad(array_map('intval', explode(':', $time)), 3, 0);

        return self::toCarbon($date)->startOfDay()->addHours($hour)->addMinutes($minute)->addSeconds($second);
    }

    /**
     * ISO-8601 day-of-week numbers (1 = Monday .. 7 = Sunday) with Indonesian labels.
     *
     * @return array<int, string>
     */
    public static function weekdayOptions(): array
    {
        return [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => "Jum'at",
            6 => 'Sabtu',
            7 => 'Minggu',
        ];
    }

    /**
     * Render a set of ISO weekday numbers as `Senin, Rabu, Jum'at`.
     *
     * @param  array<int, int>  $days
     */
    public static function formatWeekdays(array $days): string
    {
        if ($days === []) {
            return 'Tidak ada';
        }

        sort($days);
        $options = self::weekdayOptions();

        if (count($days) === 7) {
            return 'Setiap hari';
        }

        if ($days === [1, 2, 3, 4, 5]) {
            return "Senin – Jum'at";
        }

        return implode(', ', array_map(
            static fn (int $day): string => $options[$day] ?? '',
            $days,
        ));
    }
}
