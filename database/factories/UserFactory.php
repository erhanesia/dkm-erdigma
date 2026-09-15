<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            // Stated rather than left to the column defaults. A model built here
            // does not carry them until it is read back from the table, and the
            // app reads these as booleans on every panel request — the sidebar
            // calls `isMentor()`, and `EnsureUserIsActive` signs out anyone whose
            // flag is not true.
            'is_active' => true,
            'is_mentor' => false,
            'is_supervisor' => false,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * A mentor (ustadz): the domain flag the halaqah rules check, and the role
     * the panel routes check.
     */
    public function mentor(): static
    {
        return $this->state(['is_mentor' => true])->withRole(UserRole::Mentor);
    }

    /**
     * Give the user one of the application's roles.
     *
     * Roles live in the permission tables rather than on the user row, and a
     * fresh test database has none, so the role is created on first use.
     */
    public function withRole(UserRole $role): static
    {
        return $this->afterCreating(function (User $user) use ($role): void {
            $user->assignRole(Role::findOrCreate($role->value, 'web'));
        });
    }
}
