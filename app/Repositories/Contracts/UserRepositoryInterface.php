<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryInterface<User>
 */
interface UserRepositoryInterface extends RepositoryInterface
{
    /**
     * Paginated, filterable, sortable listing driven by request query strings.
     *
     * @return LengthAwarePaginator<int, User>
     */
    public function paginateFiltered(): LengthAwarePaginator;

    /**
     * @return Collection<int, User>
     */
    public function activeWithRole(UserRole $role): Collection;

    /**
     * id => name, for assignment dropdowns.
     *
     * @return array<int, string>
     */
    public function optionsForRole(UserRole $role): array;

    /**
     * @return array<int, string>
     */
    public function activeOptions(): array;

    /**
     * Everyone flagged as a mentor (ustadz), regardless of assigned role.
     *
     * @return array<int, string>
     */
    public function mentorOptions(): array;

    /**
     * People who are not yet in any active halaqah.
     *
     * @return Collection<int, User>
     */
    public function employeesWithoutGroup(?int $exceptGroupId = null): Collection;

    /**
     * Distinct department names, for the filter dropdown.
     *
     * @return array<int, string>
     */
    public function departments(): array;

    /**
     * Look-up used by the HRIS sync to decide between insert and update.
     */
    public function findByHrisEmployeeId(string $hrisEmployeeId): ?User;

    public function findByEmail(string $email): ?User;

    public function markLoggedIn(User $user): void;
}
