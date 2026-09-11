<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\ScheduleSource;
use App\Models\PrayerSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryInterface<PrayerSchedule>
 */
interface PrayerScheduleRepositoryInterface extends RepositoryInterface
{
    public function forDate(CarbonImmutable|string $date): ?PrayerSchedule;

    /**
     * @return Collection<int, PrayerSchedule>
     */
    public function between(CarbonImmutable|string $from, CarbonImmutable|string $to): Collection;

    /**
     * Dates within the range that have no schedule row yet.
     *
     * @return array<int, string>
     */
    public function missingDates(CarbonImmutable $from, CarbonImmutable $to): array;

    /**
     * Insert or refresh a day's timings, never clobbering a manual override.
     *
     * @param  array<string, string>  $timings
     */
    public function storeTimings(
        CarbonImmutable $date,
        array $timings,
        string $method,
        bool $force = false,
        ScheduleSource $source = ScheduleSource::Calculated,
    ): PrayerSchedule;

    public function latestDate(): ?CarbonImmutable;
}
