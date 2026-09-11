<?php

declare(strict_types=1);

namespace App\Support\Helpers;

use App\Enums\PrayerName;
use App\Models\PrayerSchedule;

/**
 * Shapes prayer schedules for display.
 *
 * The front page and the monthly table both walk a schedule row prayer by
 * prayer. Doing that inline meant the same `substr(..., 0, 5)` and the same
 * ordering assumption in two places, so it lives here instead.
 */
final class PublicSchedule
{
    /**
     * One day's timings, in the order they occur.
     *
     * Sunrise is included even though no adhan is called for it: it closes the
     * Fajr window, which is exactly why people look it up.
     *
     * @return array<int, array{prayer: PrayerName, time: string}>
     */
    public static function timings(PrayerSchedule $schedule): array
    {
        return array_map(
            static fn (PrayerName $prayer): array => [
                'prayer' => $prayer,
                'time' => self::time($schedule, $prayer),
            ],
            PrayerName::cases(),
        );
    }

    /**
     * A single timing as `HH:MM`.
     *
     * The column is a TIME, which MySQL hands back as `HH:MM:SS`. Seconds are
     * noise on a prayer schedule — the underlying calculation is rounded to the
     * minute anyway — so they are trimmed rather than displayed.
     */
    public static function time(PrayerSchedule $schedule, PrayerName $prayer): string
    {
        return substr((string) $schedule->{$prayer->value}, 0, 5);
    }
}
