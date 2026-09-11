<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\UserRole;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Support\Helpers\DateHelper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @extends BaseRepository<User>
 */
class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    protected string $defaultOrderColumn = 'name';

    protected string $defaultOrderDirection = 'asc';

    protected function model(): string
    {
        return User::class;
    }

    public function paginateFiltered(): LengthAwarePaginator
    {
        return QueryBuilder::for(User::class)
            ->with('roles')
            ->allowedFilters(
                AllowedFilter::callback('search', static function (Builder $query, mixed $value): void {
                    $term = '%'.$value.'%';

                    $query->where(function (Builder $builder) use ($term): void {
                        $builder->where('name', 'like', $term)
                            ->orWhere('email', 'like', $term)
                            ->orWhere('external_employee_id', 'like', $term)
                            ->orWhere('position_name', 'like', $term);
                    });
                }),
                AllowedFilter::exact('department_name'),
                AllowedFilter::exact('is_active'),
                AllowedFilter::exact('is_mentor'),
                AllowedFilter::callback('role', static function (Builder $query, mixed $value): void {
                    $query->role($value);
                }),
                AllowedFilter::callback('source', static function (Builder $query, mixed $value): void {
                    $value === 'hris' ? $query->fromHris() : $query->localOnly();
                }), )
            ->allowedSorts('name', 'email', 'department_name', 'created_at', 'last_login_at')
            ->defaultSort('name')
            ->paginate((int) config('dkm.per_page'))
            ->withQueryString();
    }

    public function activeWithRole(UserRole $role): Collection
    {
        return $this->query()
            ->active()
            ->role($role->value)
            ->orderBy('name')
            ->get();
    }

    public function optionsForRole(UserRole $role): array
    {
        return $this->activeWithRole($role)->pluck('name', 'id')->all();
    }

    public function activeOptions(): array
    {
        return $this->query()->active()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function mentorOptions(): array
    {
        return $this->query()->active()->mentors()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function employeesWithoutGroup(?int $exceptGroupId = null): Collection
    {
        return $this->query()
            ->active()
            ->whereDoesntHave('groupMemberships', function (Builder $query) use ($exceptGroupId): void {
                $query->where('is_active', true);

                if ($exceptGroupId !== null) {
                    $query->where('mentoring_group_id', '!=', $exceptGroupId);
                }
            })
            ->orderBy('name')
            ->get();
    }

    public function departments(): array
    {
        return $this->query()
            ->whereNotNull('department_name')
            ->distinct()
            ->orderBy('department_name')
            ->pluck('department_name')
            ->all();
    }

    public function findByHrisEmployeeId(string $hrisEmployeeId): ?User
    {
        return $this->query()->where('hris_employee_id', $hrisEmployeeId)->first();
    }

    public function findByEmail(string $email): ?User
    {
        return $this->query()->where('email', $email)->first();
    }

    public function markLoggedIn(User $user): void
    {
        $user->forceFill(['last_login_at' => DateHelper::now()])->saveQuietly();
    }
}
