<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\FridaySchedule;
use App\Repositories\Contracts\FridayScheduleRepositoryInterface;
use App\Support\Helpers\DateHelper;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @extends BaseRepository<FridaySchedule>
 */
class FridayScheduleRepository extends BaseRepository implements FridayScheduleRepositoryInterface
{
    /** @var array<int, string> */
    private const ROLE_COLUMNS = ['khatib_id', 'imam_id', 'muadzin_id'];

    protected string $defaultOrderColumn = 'date';

    protected string $defaultOrderDirection = 'desc';

    protected function model(): string
    {
        return FridaySchedule::class;
    }

    public function paginateFiltered(): LengthAwarePaginator
    {
        return QueryBuilder::for(FridaySchedule::class)
            ->with(['khatib', 'imam', 'muadzin'])
            ->allowedFilters(
                AllowedFilter::callback('search', static function (Builder $query, mixed $value): void {
                    $term = '%'.$value.'%';

                    $query->where(function (Builder $builder) use ($term): void {
                        $builder->where('theme', 'like', $term)
                            ->orWhere('external_khatib_name', 'like', $term)
                            ->orWhereHas('khatib', static fn (Builder $user) => $user->where('name', 'like', $term));
                    });
                }),
                AllowedFilter::exact('status'),
                AllowedFilter::callback('month', static function (Builder $query, mixed $value): void {
                    $month = DateHelper::toCarbon($value.'-01');

                    $query->whereBetween('date', [
                        $month->startOfMonth()->toDateString(),
                        $month->endOfMonth()->toDateString(),
                    ]);
                }), )
            ->allowedSorts('date', 'status')
            ->defaultSort('-date')
            ->paginate((int) config('dkm.per_page'))
            ->withQueryString();
    }

    public function nextUpcoming(): ?FridaySchedule
    {
        return $this->query()->with(['khatib', 'imam', 'muadzin'])->upcoming()->first();
    }

    public function upcoming(int $limit = 5): Collection
    {
        return $this->query()->with(['khatib', 'imam', 'muadzin'])->upcoming()->limit($limit)->get();
    }

    public function inMonth(CarbonImmutable $month): Collection
    {
        return $this->query()
            ->with(['khatib', 'imam', 'muadzin'])
            ->inMonth($month)
            ->orderBy('date')
            ->get();
    }

    public function forDate(CarbonImmutable|string $date): ?FridaySchedule
    {
        return $this->query()->whereDate('date', DateHelper::toCarbon($date)->toDateString())->first();
    }

    public function unscheduledFridays(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $scheduled = $this->query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->pluck('date')
            ->map(static fn ($date): string => DateHelper::toCarbon($date)->toDateString())
            ->all();

        $missing = [];

        for ($cursor = $from->startOfDay(); $cursor->lessThanOrEqualTo($to); $cursor = $cursor->addDay()) {
            if (! $cursor->isFriday()) {
                continue;
            }

            if (! in_array($cursor->toDateString(), $scheduled, true)) {
                $missing[] = $cursor->toDateString();
            }
        }

        return $missing;
    }

    public function hasConflict(CarbonImmutable|string $date, int $userId, ?int $exceptId = null): bool
    {
        return $this->query()
            ->whereDate('date', DateHelper::toCarbon($date)->toDateString())
            ->when($exceptId !== null, static fn (Builder $query) => $query->where('id', '!=', $exceptId))
            ->where(function (Builder $query) use ($userId): void {
                foreach (self::ROLE_COLUMNS as $column) {
                    $query->orWhere($column, $userId);
                }
            })
            ->exists();
    }
}
