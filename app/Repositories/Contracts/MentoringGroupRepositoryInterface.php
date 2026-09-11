<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\MentoringGroup;
use App\Models\MentoringGroupMember;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryInterface<MentoringGroup>
 */
interface MentoringGroupRepositoryInterface extends RepositoryInterface
{
    /**
     * @return LengthAwarePaginator<int, MentoringGroup>
     */
    public function paginateFiltered(?int $mentorId = null): LengthAwarePaginator;

    public function findByCode(string $code): ?MentoringGroup;

    /**
     * @return Collection<int, MentoringGroup>
     */
    public function activeForMentor(int $mentorId): Collection;

    /**
     * @return array<int, string>
     */
    public function options(?int $mentorId = null): array;

    /**
     * The active halaqah an employee currently belongs to, if any.
     */
    public function activeGroupOf(int $userId): ?MentoringGroup;

    public function addMember(MentoringGroup $group, int $userId): MentoringGroupMember;

    public function removeMember(MentoringGroup $group, int $userId): void;

    /**
     * Replace the membership list in one transaction-safe call.
     *
     * @param  array<int, int>  $userIds
     */
    public function syncMembers(MentoringGroup $group, array $userIds): void;

    public function nextCode(): string;
}
