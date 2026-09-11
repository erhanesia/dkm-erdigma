<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\MurottalSchedule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryInterface<MurottalSchedule>
 */
interface MurottalScheduleRepositoryInterface extends RepositoryInterface
{
    /**
     * @return LengthAwarePaginator<int, MurottalSchedule>
     */
    public function paginateFiltered(): LengthAwarePaginator;

    /**
     * @return Collection<int, MurottalSchedule>
     */
    public function activeForZone(int $zoneId): Collection;

    /**
     * Detect an overlapping window in the same zone before saving.
     */
    public function hasOverlap(int $zoneId, string $startTime, string $endTime, array $daysOfWeek, ?int $exceptId = null): bool;
}
