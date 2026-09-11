<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Enums\UserRole;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Account management and role assignment.
 *
 * `is_mentor` is the domain flag ("this person guides a halaqah") while the
 * spatie `mentor` role is what the authorization layer checks. Keeping the two
 * in step happens here, in one place, so nothing else has to remember to do it.
 */
class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(): LengthAwarePaginator
    {
        return $this->users->paginateFiltered();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, UserRole $role): User
    {
        return DB::transaction(function () use ($attributes, $role): User {
            $user = $this->users->create([
                ...$attributes,
                'email_verified_at' => now(),
            ]);

            $this->applyRole($user, $role);

            return $user->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $user, array $attributes, ?UserRole $role = null): User
    {
        return DB::transaction(function () use ($user, $attributes, $role): User {
            if (blank($attributes['password'] ?? null)) {
                unset($attributes['password']);
            }

            $updated = $this->users->update($user, $attributes);

            if ($role !== null) {
                $this->guardLastSuperAdmin($updated, $role);
            }

            $this->applyRole($updated, $role ?? $updated->primaryRole() ?? UserRole::Employee);

            return $updated->refresh();
        });
    }

    public function delete(User $user, User $actor): void
    {
        if ($user->id === $actor->id) {
            throw new BusinessRuleException('Anda tidak dapat menghapus akun Anda sendiri.');
        }

        if ($user->mentoredGroups()->exists()) {
            throw new BusinessRuleException(
                'Pengguna ini masih menjadi mentor pada halaqah aktif. Pindahkan halaqahnya ke mentor lain terlebih dahulu.',
            );
        }

        $this->guardLastSuperAdmin($user, null);

        $this->users->delete($user);
    }

    public function toggleActive(User $user, bool $active, User $actor): User
    {
        if ($user->id === $actor->id && ! $active) {
            throw new BusinessRuleException('Anda tidak dapat menonaktifkan akun Anda sendiri.');
        }

        return $this->users->update($user, ['is_active' => $active]);
    }

    /**
     * Flip the mentor flag and keep the `mentor` role in step with it.
     */
    public function setMentorFlag(User $user, bool $isMentor): User
    {
        if (! $isMentor && $user->mentoredGroups()->where('is_active', true)->exists()) {
            throw new BusinessRuleException(
                'Pengguna ini masih membina halaqah aktif, jadi status mentornya belum bisa dicabut.',
            );
        }

        return DB::transaction(function () use ($user, $isMentor): User {
            $updated = $this->users->update($user, ['is_mentor' => $isMentor]);

            $this->applyRole($updated, $updated->primaryRole() ?? UserRole::Employee);

            return $updated->refresh();
        });
    }

    public function resetPassword(User $user, string $password): User
    {
        return $this->users->update($user, ['password' => $password]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateOwnProfile(User $user, array $attributes): User
    {
        return $this->users->update($user, $attributes);
    }

    public function changeOwnPassword(User $user, string $currentPassword, string $newPassword): User
    {
        if ($user->hasPassword() && ! Hash::check($currentPassword, (string) $user->password)) {
            throw new BusinessRuleException('Password saat ini tidak sesuai.');
        }

        return $this->users->update($user, ['password' => $newPassword]);
    }

    // -----------------------------------------------------------------
    // Option lists
    // -----------------------------------------------------------------

    /**
     * @return array<int, string>
     */
    public function optionsForRole(UserRole $role): array
    {
        return $this->users->optionsForRole($role);
    }

    /**
     * Mentors available to lead a halaqah.
     *
     * @return array<int, string>
     */
    public function mentorOptions(): array
    {
        return $this->users->mentorOptions();
    }

    /**
     * People eligible to lead a Friday prayer or call the adhan — anyone active,
     * since a khatib is not necessarily a halaqah mentor.
     *
     * @return array<int, string>
     */
    public function dutyCandidateOptions(): array
    {
        return $this->users->activeOptions();
    }

    /**
     * @return array<int, string>
     */
    public function departments(): array
    {
        return $this->users->departments();
    }

    public function markLoggedIn(User $user): void
    {
        $this->users->markLoggedIn($user);
    }

    // -----------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------

    /**
     * A user holds exactly one primary role, plus `mentor` whenever the domain
     * flag says so. Administrators keep mentor duties too if they have any.
     */
    private function applyRole(User $user, UserRole $role): void
    {
        $roles = [$role->value];

        if ($user->is_mentor && $role !== UserRole::Mentor) {
            $roles[] = UserRole::Mentor->value;
        }

        $user->syncRoles(array_unique($roles));
    }

    /**
     * Prevent locking everyone out of the system.
     */
    private function guardLastSuperAdmin(User $user, ?UserRole $newRole): void
    {
        if (! $user->hasRole(UserRole::SuperAdmin->value)) {
            return;
        }

        if ($newRole === UserRole::SuperAdmin) {
            return;
        }

        $remaining = User::query()
            ->role(UserRole::SuperAdmin->value)
            ->where('id', '!=', $user->id)
            ->count();

        if ($remaining === 0) {
            throw new BusinessRuleException('Minimal harus ada satu Super Admin yang aktif.');
        }
    }
}
