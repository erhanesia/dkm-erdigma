<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\AudioZone;
use App\Repositories\Contracts\AudioZoneRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @extends BaseRepository<AudioZone>
 */
class AudioZoneRepository extends BaseRepository implements AudioZoneRepositoryInterface
{
    protected string $defaultOrderColumn = 'sort_order';

    protected string $defaultOrderDirection = 'asc';

    protected function model(): string
    {
        return AudioZone::class;
    }

    public function paginateFiltered(): LengthAwarePaginator
    {
        return QueryBuilder::for(AudioZone::class)
            ->withCount(['devices', 'murottalSchedules'])
            ->allowedFilters(
                AllowedFilter::callback('search', static function (Builder $query, mixed $value): void {
                    $term = '%'.$value.'%';

                    $query->where(function (Builder $builder) use ($term): void {
                        $builder->where('name', 'like', $term)
                            ->orWhere('code', 'like', $term)
                            ->orWhere('floor', 'like', $term);
                    });
                }),
                AllowedFilter::exact('is_active'),
                AllowedFilter::exact('is_adhan_enabled'),
                AllowedFilter::exact('is_murottal_enabled'), )
            ->allowedSorts('name', 'code', 'sort_order', 'created_at')
            ->defaultSort('sort_order', 'name')
            ->paginate((int) config('dkm.per_page'))
            ->withQueryString();
    }

    public function activeWithSchedules(): Collection
    {
        return $this->query()
            ->active()
            ->with([
                'prayerSettings.adhanTrack',
                'prayerSettings.tarhimTrack',
                'prayerSettings.iqamahTrack',
                'murottalSchedules' => static fn ($query) => $query->where('is_active', true),
                'murottalSchedules.track',
            ])
            ->ordered()
            ->get();
    }

    public function orderedActive(): Collection
    {
        return $this->query()->active()->ordered()->get();
    }

    public function findByCode(string $code): ?AudioZone
    {
        return $this->query()->where('code', $code)->first();
    }

    public function options(): array
    {
        return $this->query()->active()->ordered()->pluck('name', 'id')->all();
    }

    public function withDeviceCounts(): Collection
    {
        return $this->query()
            ->withCount([
                'devices',
                'devices as online_devices_count' => static function (Builder $query): void {
                    $threshold = now()->subSeconds((int) config('dkm.device.offline_threshold'));

                    $query->where('is_active', true)->where('last_seen_at', '>=', $threshold);
                },
            ])
            ->ordered()
            ->get();
    }
}
