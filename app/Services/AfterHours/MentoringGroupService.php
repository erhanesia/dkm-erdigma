<?php

declare(strict_types=1);

namespace App\Services\AfterHours;

use App\Exceptions\BusinessRuleException;
use App\Models\MentoringGroup;
use App\Models\User;
use App\Repositories\Contracts\MentoringGroupRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Halaqah management: which ustadz guides which employees.
 *
 * The rules enforced here are the ones the brief calls out — a mentor holds
 * roughly ten people, and an employee belongs to exactly one active group.
 */
class MentoringGroupService
{
    public function __construct(
        private readonly MentoringGroupRepositoryInterface $groups,
        private readonly UserRepositoryInterface $users,
        private readonly LocationService $locations,
    ) {}

    /**
     * @return LengthAwarePaginator<int, MentoringGroup>
     */
    public function paginate(?User $mentor = null): LengthAwarePaginator
    {
        return $this->groups->paginateFiltered($mentor?->id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, int>  $memberIds
     */
    public function create(array $attributes, array $memberIds = []): MentoringGroup
    {
        $this->guardMentorRole((int) $attributes['mentor_id']);

        return DB::transaction(function () use ($attributes, $memberIds): MentoringGroup {
            $group = $this->groups->create([
                ...$attributes,
                ...$this->defaultPlace($attributes),
                'code' => $attributes['code'] ?? $this->groups->nextCode(),
            ]);

            if ($memberIds !== []) {
                $this->assignMembers($group, $memberIds);
            }

            return $group;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, int>|null  $memberIds
     */
    public function update(MentoringGroup $group, array $attributes, ?array $memberIds = null): MentoringGroup
    {
        if (isset($attributes['mentor_id'])) {
            $this->guardMentorRole((int) $attributes['mentor_id']);
        }

        return DB::transaction(function () use ($group, $attributes, $memberIds): MentoringGroup {
            $updated = $this->groups->update($group, [...$attributes, ...$this->defaultPlace($attributes)]);

            if ($memberIds !== null) {
                $this->assignMembers($updated, $memberIds);
            }

            return $updated;
        });
    }

    public function delete(MentoringGroup $group): void
    {
        if ($group->sessions()->exists()) {
            throw new BusinessRuleException(
                'Halaqah ini sudah memiliki riwayat kegiatan. Nonaktifkan saja agar datanya tetap tersimpan.',
            );
        }

        $this->groups->delete($group);
    }

    /**
     * Replace the member list, checking capacity and single-membership first.
     *
     * @param  array<int, int>  $memberIds
     */
    public function assignMembers(MentoringGroup $group, array $memberIds): void
    {
        $memberIds = array_values(array_unique(array_map('intval', $memberIds)));

        if (count($memberIds) > $group->capacity) {
            throw new BusinessRuleException(sprintf(
                'Kapasitas halaqah %s adalah %d orang, sedangkan yang dipilih %d orang.',
                $group->name,
                $group->capacity,
                count($memberIds),
            ));
        }

        foreach ($memberIds as $memberId) {
            $existing = $this->groups->activeGroupOf($memberId);

            if ($existing !== null && $existing->id !== $group->id) {
                $user = $this->users->find($memberId);

                throw new BusinessRuleException(sprintf(
                    '%s sudah terdaftar di halaqah %s. Keluarkan dari halaqah tersebut terlebih dahulu.',
                    $user?->name ?? 'Karyawan tersebut',
                    $existing->name,
                ));
            }
        }

        $this->groups->syncMembers($group, $memberIds);
    }

    public function addMember(MentoringGroup $group, int $userId): void
    {
        if ($group->isFull()) {
            throw new BusinessRuleException('Halaqah ini sudah penuh.');
        }

        $existing = $this->groups->activeGroupOf($userId);

        if ($existing !== null && $existing->id !== $group->id) {
            throw new BusinessRuleException('Karyawan ini sudah terdaftar di halaqah '.$existing->name.'.');
        }

        $this->groups->addMember($group, $userId);
    }

    public function removeMember(MentoringGroup $group, int $userId): void
    {
        $this->groups->removeMember($group, $userId);
    }

    /**
     * @return Collection<int, MentoringGroup>
     */
    public function forMentor(User $mentor): Collection
    {
        return $this->groups->activeForMentor($mentor->id);
    }

    public function activeGroupOf(User $user): ?MentoringGroup
    {
        return $this->groups->activeGroupOf($user->id);
    }

    /**
     * Employees still available to be assigned, plus the current members.
     *
     * @return Collection<int, User>
     */
    public function assignableEmployees(?MentoringGroup $group = null): Collection
    {
        $available = $this->users->employeesWithoutGroup($group?->id);

        if ($group === null) {
            return $available;
        }

        return $available->merge($group->members)->unique('id')->sortBy('name')->values();
    }

    /**
     * @return array<int, string>
     */
    public function options(?User $mentor = null): array
    {
        return $this->groups->options($mentor?->id);
    }

    /**
     * The list row and the name to store for a halaqah's "Lokasi Rutin", when
     * the form sent one.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{default_location?: string|null, default_location_id?: int|null}
     */
    private function defaultPlace(array $attributes): array
    {
        if (! array_key_exists('default_location', $attributes)) {
            return [];
        }

        $place = $this->locations->resolve($attributes['default_location']);

        return ['default_location' => $place['name'], 'default_location_id' => $place['id']];
    }

    /**
     * `is_mentor` is the domain flag, so an administrator who has not been
     * marked as a mentor cannot be handed a halaqah by accident.
     */
    private function guardMentorRole(int $mentorId): void
    {
        $mentor = $this->users->find($mentorId);

        if ($mentor === null || ! $mentor->is_mentor) {
            throw new BusinessRuleException(
                'Pengguna yang dipilih belum ditandai sebagai mentor (ustadz). '
                .'Aktifkan dulu status mentornya di halaman Pengguna.',
            );
        }

        if (! $mentor->is_active) {
            throw new BusinessRuleException('Mentor yang dipilih berstatus nonaktif.');
        }
    }
}
