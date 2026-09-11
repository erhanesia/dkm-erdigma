<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\FridaySchedule;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryInterface<FridaySchedule>
 */
interface FridayScheduleRepositoryInterface extends RepositoryInterface
{
    /**
     * @return LengthAwarePaginator<int, FridaySchedule>
     */
    public function paginateFiltered(): LengthAwarePaginator;

    public function nextUpcoming(): ?FridaySchedule;

    /**
     * @return Collection<int, FridaySchedule>
     */
    public function upcoming(int $limit = 5): Collection;

    /**
     * @return Collection<int, FridaySchedule>
     */
    public function inMonth(CarbonImmutable $month): Collection;

    public function forDate(CarbonImmutable|string $date): ?FridaySchedule;

    /**
     * Fridays in the range that have no schedule yet — the "belum dijadwalkan"
     * warning on the dashboard.
     *
     * @return array<int, string>
     */
    public function unscheduledFridays(CarbonImmutable $from, CarbonImmutable $to): array;

    /**
     * Whether a person is already booked for another role on that date.
     */
    public function hasConflict(CarbonImmutable|string $date, int $userId, ?int $exceptId = null): bool;
}
