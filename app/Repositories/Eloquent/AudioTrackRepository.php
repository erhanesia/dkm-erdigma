<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\AudioTrackType;
use App\Models\AudioTrack;
use App\Repositories\Contracts\AudioTrackRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @extends BaseRepository<AudioTrack>
 */
class AudioTrackRepository extends BaseRepository implements AudioTrackRepositoryInterface
{
    protected string $defaultOrderColumn = 'title';

    protected string $defaultOrderDirection = 'asc';

    protected function model(): string
    {
        return AudioTrack::class;
    }

    public function paginateFiltered(): LengthAwarePaginator
    {
        return QueryBuilder::for(AudioTrack::class)
            ->with('uploader')
            ->allowedFilters(
                AllowedFilter::callback('search', static function (Builder $query, mixed $value): void {
                    $term = '%'.$value.'%';

                    $query->where(function (Builder $builder) use ($term): void {
                        $builder->where('title', 'like', $term)->orWhere('reciter', 'like', $term);
                    });
                }),
                AllowedFilter::exact('type'),
                AllowedFilter::exact('is_active'), )
            ->allowedSorts('title', 'type', 'duration_seconds', 'created_at')
            ->defaultSort('title')
            ->paginate((int) config('dkm.per_page'))
            ->withQueryString();
    }

    public function activeOfType(AudioTrackType $type): Collection
    {
        return $this->query()->active()->ofType($type)->orderBy('title')->get();
    }

    public function defaultForType(AudioTrackType $type): ?AudioTrack
    {
        return $this->query()
            ->active()
            ->ofType($type)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    public function groupedOptions(): array
    {
        $grouped = [];

        foreach ($this->query()->active()->orderBy('title')->get() as $track) {
            $grouped[$track->type->label()][$track->id] = $track->title;
        }

        return $grouped;
    }

    public function optionsForTypes(AudioTrackType ...$types): array
    {
        return $this->query()
            ->active()
            ->whereIn('type', array_map(static fn (AudioTrackType $type): string => $type->value, $types))
            ->orderBy('title')
            ->pluck('title', 'id')
            ->all();
    }

    public function clearDefaultFlag(AudioTrackType $type, ?int $exceptId = null): void
    {
        $this->query()
            ->ofType($type)
            ->when($exceptId !== null, static fn (Builder $query) => $query->where('id', '!=', $exceptId))
            ->update(['is_default' => false]);
    }
}
