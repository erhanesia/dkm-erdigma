<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Starter accounts so the app can be signed into before the HRIS sync exists.
 *
 * Passwords are intentionally simple and printed to the console — this seeder is
 * for local development, and `DatabaseSeeder` refuses to run it in production.
 */
class DemoUserSeeder extends Seeder
{
    private const DEFAULT_PASSWORD = 'password123';

    public function run(): void
    {
        foreach ($this->accounts() as $account) {
            $role = $account['role'];
            unset($account['role']);

            $user = User::query()->firstOrCreate(
                ['email' => $account['email']],
                [...$account, 'password' => self::DEFAULT_PASSWORD, 'email_verified_at' => now()],
            );

            $roles = [$role->value];

            if ($user->is_mentor && $role !== UserRole::Mentor) {
                $roles[] = UserRole::Mentor->value;
            }

            $user->syncRoles($roles);
        }

        $this->command?->info('Akun demo dibuat. Password semuanya: '.self::DEFAULT_PASSWORD);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function accounts(): array
    {
        return [
            [
                'name' => 'Super Admin',
                'email' => 'admin@erdigma.id',
                'external_employee_id' => 'ERD-0001',
                'position_name' => 'IT Administrator',
                'department_name' => 'IT',
                'is_mentor' => false,
                'role' => UserRole::SuperAdmin,
            ],
            [
                'name' => 'Pengurus DKM',
                'email' => 'dkm@erdigma.id',
                'external_employee_id' => 'ERD-0002',
                'position_name' => 'Staff',
                'department_name' => 'General Affair',
                'is_mentor' => false,
                'role' => UserRole::DkmAdmin,
            ],
            [
                'name' => 'Ustadz Abdullah',
                'email' => 'mentor@erdigma.id',
                'external_employee_id' => 'ERD-0003',
                'position_name' => 'Senior Engineer',
                'department_name' => 'Engineering',
                'is_mentor' => true,
                'role' => UserRole::Mentor,
            ],
            [
                'name' => 'Budi Karyawan',
                'email' => 'karyawan@erdigma.id',
                'external_employee_id' => 'ERD-0004',
                'position_name' => 'Engineer',
                'department_name' => 'Engineering',
                'is_mentor' => false,
                'role' => UserRole::Employee,
            ],
        ];
    }
}
