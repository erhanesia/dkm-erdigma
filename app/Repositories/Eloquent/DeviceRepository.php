<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Device;
use App\Repositories\Contracts\DeviceRepositoryInterface;
use App\Support\Helpers\DateHelper;
use App\Support\Helpers\TokenHelper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @extends BaseRepository<Device>
 */
class DeviceRepository extends BaseRepository implements DeviceRepositoryInterface
{
    protected string $defaultOrderColumn = 'name';

    protected string $defaultOrderDirection = 'asc';

    protected function model(): string
    {
        return Device::class;
    }

    public function paginateFiltered(): LengthAwarePaginator
    {
        return QueryBuilder::for(Device::class)
            ->with('zone')
            ->allowedFilters(
                AllowedFilter::callback('search', static function (Builder $query, mixed $value): void {
                    $term = '%'.$value.'%';

                    $query->where(function (Builder $builder) use ($term): void {
                        $builder->where('name', 'like', $term)
                            ->orWhereHas('zone', static fn (Builder $zone) => $zone->where('name', 'like', $term));
                    });
                }),
                AllowedFilter::exact('audio_zone_id'),
                AllowedFilter::exact('is_active'),
                AllowedFilter::exact('status'), )
            ->allowedSorts('name', 'last_seen_at', 'created_at')
            ->defaultSort('name')
            ->paginate((int) config('dkm.per_page'))
            ->withQueryString();
    }

    public function findByToken(string $plainToken): ?Device
    {
        return $this->query()
            ->with('zone')
            ->where('token_hash', TokenHelper::hash($plainToken))
            ->first();
    }

    public function activeInZone(int $zoneId): Collection
    {
        return $this->query()->active()->where('audio_zone_id', $zoneId)->orderBy('name')->get();
    }

    public function allWithZone(): Collection
    {
        return $this->query()->with('zone')->orderBy('name')->get();
    }

    public function stale(): Collection
    {
        return $this->query()->with('zone')->stale()->get();
    }

    public function countOnline(): int
    {
        $threshold = DateHelper::now()->subSeconds((int) config('dkm.device.offline_threshold'));

        return $this->query()
            ->where('is_active', true)
            ->where('is_audio_unlocked', true)
            ->where('last_seen_at', '>=', $threshold)
            ->count();
    }

    public function countActive(): int
    {
        return $this->query()->where('is_active', true)->count();
    }

    public function touchHeartbeat(Device $device, array $attributes): void
    {
        $device->forceFill($attributes)->saveQuietly();
    }
}
