<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\MurottalSchedule;
use App\Repositories\Contracts\MurottalScheduleRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @extends BaseRepository<MurottalSchedule>
 */
class MurottalScheduleRepository extends BaseRepository implements MurottalScheduleRepositoryInterface
{
    protected string $defaultOrderColumn = 'start_time';

    protected string $defaultOrderDirection = 'asc';

    protected function model(): string
    {
        return MurottalSchedule::class;
    }

    public function paginateFiltered(): LengthAwarePaginator
    {
        return QueryBuilder::for(MurottalSchedule::class)
            ->with(['zone', 'track'])
            ->allowedFilters(
                AllowedFilter::partial('name'),
                AllowedFilter::exact('audio_zone_id'),
                AllowedFilter::exact('is_active'), )
            ->allowedSorts('name', 'start_time', 'created_at')
            ->defaultSort('start_time')
            ->paginate((int) config('dkm.per_page'))
            ->withQueryString();
    }

    public function activeForZone(int $zoneId): Collection
    {
        return $this->query()
            ->with('track')
            ->active()
            ->where('audio_zone_id', $zoneId)
            ->orderBy('start_time')
            ->get();
    }

    public function hasOverlap(int $zoneId, string $startTime, string $endTime, array $daysOfWeek, ?int $exceptId = null): bool
    {
        $candidates = $this->query()
            ->where('audio_zone_id', $zoneId)
            ->where('is_active', true)
            ->when($exceptId !== null, static fn (Builder $query) => $query->where('id', '!=', $exceptId))
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->get();

        foreach ($candidates as $candidate) {
            if (array_intersect($candidate->days_of_week ?? [], $daysOfWeek) !== []) {
                return true;
            }
        }

        return false;
    }
}
