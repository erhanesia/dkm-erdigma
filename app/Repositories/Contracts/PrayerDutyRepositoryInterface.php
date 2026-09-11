<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\PrayerName;
use App\Models\PrayerDuty;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryInterface<PrayerDuty>
 */
interface PrayerDutyRepositoryInterface extends RepositoryInterface
{
    /**
     * @return Collection<int, PrayerDuty>
     */
    public function between(CarbonImmutable $from, CarbonImmutable $to): Collection;

    /**
     * @return Collection<int, PrayerDuty>
     */
    public function forDate(CarbonImmutable|string $date): Collection;

    public function findFor(CarbonImmutable|string $date, PrayerName $prayer): ?PrayerDuty;

    /**
     * Save a whole week/day grid in one call.
     *
     * @param  array<string, array<string, array<string, mixed>>>  $grid  date => prayer => attributes
     */
    public function saveGrid(array $grid, ?int $createdBy = null): void;

    /**
     * Next duty for a specific person, used by "tugas saya".
     *
     * @return Collection<int, PrayerDuty>
     */
    public function upcomingForUser(int $userId, int $limit = 5): Collection;
}
