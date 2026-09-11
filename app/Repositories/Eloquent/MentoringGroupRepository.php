<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\MentoringGroup;
use App\Models\MentoringGroupMember;
use App\Repositories\Contracts\MentoringGroupRepositoryInterface;
use App\Support\Helpers\DateHelper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @extends BaseRepository<MentoringGroup>
 */
class MentoringGroupRepository extends BaseRepository implements MentoringGroupRepositoryInterface
{
    protected string $defaultOrderColumn = 'name';

    protected string $defaultOrderDirection = 'asc';

    protected function model(): string
    {
        return MentoringGroup::class;
    }

    public function paginateFiltered(?int $mentorId = null): LengthAwarePaginator
    {
        return QueryBuilder::for(MentoringGroup::class)
            ->with('mentor')
            ->withCount(['memberships as active_members_count' => static fn (Builder $query) => $query->where('is_active', true)])
            ->when($mentorId !== null, static fn ($query) => $query->where('mentor_id', $mentorId))
            ->allowedFilters(
                AllowedFilter::callback('search', static function (Builder $query, mixed $value): void {
                    $term = '%'.$value.'%';

                    $query->where(function (Builder $builder) use ($term): void {
                        $builder->where('name', 'like', $term)
                            ->orWhere('code', 'like', $term)
                            ->orWhereHas('mentor', static fn (Builder $user) => $user->where('name', 'like', $term));
                    });
                }),
                AllowedFilter::exact('mentor_id'),
                AllowedFilter::exact('is_active'), )
            ->allowedSorts('name', 'code', 'created_at')
            ->defaultSort('name')
            ->paginate((int) config('dkm.per_page'))
            ->withQueryString();
    }

    public function findByCode(string $code): ?MentoringGroup
    {
        return $this->query()->where('code', $code)->first();
    }

    public function activeForMentor(int $mentorId): Collection
    {
        return $this->query()
            ->active()
            ->mentoredBy($mentorId)
            ->withCount(['memberships as active_members_count' => static fn (Builder $query) => $query->where('is_active', true)])
            ->orderBy('name')
            ->get();
    }

    public function options(?int $mentorId = null): array
    {
        return $this->query()
            ->active()
            ->when($mentorId !== null, static fn (Builder $query) => $query->where('mentor_id', $mentorId))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function activeGroupOf(int $userId): ?MentoringGroup
    {
        return $this->query()
            ->with('mentor')
            ->whereHas('memberships', static function (Builder $query) use ($userId): void {
                $query->where('user_id', $userId)->where('is_active', true);
            })
            ->first();
    }

    public function addMember(MentoringGroup $group, int $userId): MentoringGroupMember
    {
        return MentoringGroupMember::query()->updateOrCreate(
            ['mentoring_group_id' => $group->id, 'user_id' => $userId],
            ['joined_at' => DateHelper::today()->toDateString(), 'left_at' => null, 'is_active' => true],
        );
    }

    public function removeMember(MentoringGroup $group, int $userId): void
    {
        MentoringGroupMember::query()
            ->where('mentoring_group_id', $group->id)
            ->where('user_id', $userId)
            ->update(['is_active' => false, 'left_at' => DateHelper::today()->toDateString()]);
    }

    public function syncMembers(MentoringGroup $group, array $userIds): void
    {
        $current = MentoringGroupMember::query()
            ->where('mentoring_group_id', $group->id)
            ->where('is_active', true)
            ->pluck('user_id')
            ->all();

        foreach (array_diff($current, $userIds) as $removedId) {
            $this->removeMember($group, (int) $removedId);
        }

        foreach (array_diff($userIds, $current) as $addedId) {
            $this->addMember($group, (int) $addedId);
        }
    }

    public function nextCode(): string
    {
        $sequence = $this->query()->withTrashed()->count() + 1;

        do {
            $code = sprintf('HLQ-%03d', $sequence);
            $sequence++;
        } while ($this->query()->withTrashed()->where('code', $code)->exists());

        return $code;
    }
}
