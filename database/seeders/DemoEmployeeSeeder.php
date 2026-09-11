<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\MentoringGroup;
use App\Models\User;
use App\Support\Helpers\DateHelper;
use Illuminate\Database\Seeder;

/**
 * One employee per team, so the app is exercised against the real shape of the
 * company rather than four accounts from the same department.
 *
 * The 22 team names are the real ones, supplied from the HRIS team leaderboard
 * on 2 September 2026. They could not be read from any repository: the HRIS team
 * list lives in production behind authentication (`GET /api/v1/team` answers
 * 401).
 *
 * The **people** are invented. Real colleagues' names appear on that leaderboard,
 * but seeding them here would attribute things the data never said — an email
 * address they do not have, and for some, the role of ustadz. The one exception
 * is the account matching the `/me` response the user shared, which is their own.
 *
 * Two things this seeder is built to demonstrate:
 *
 * 1. A mentor is not a separate kind of person. `is_mentor` is a flag on an
 *    ordinary employee, so the senior people below hold a normal job and mentor
 *    a halaqah on top of it — which is how it actually works at the office.
 * 2. `department_name` is left null, because that is what HRIS really returns
 *    (see planning.md §4.3). Filling it in would hide a live defect behind
 *    convenient fake data.
 */
class DemoEmployeeSeeder extends Seeder
{
    private const DEFAULT_PASSWORD = 'password123';

    private const BUILDING = 'Erdigma Blater';

    /**
     * The 22 HRIS teams, in the order the leaderboard lists them.
     *
     * Each row: team => [name, position, gender, is_mentor].
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: bool}>
     */
    private const PEOPLE = [
        'Data & IT' => ['Fadilla Ma\'sum Haqqoni', 'Back End Engineer', 'male', false],
        'HRGA Team' => ['H. Suryadi Wibowo', 'HR Manager', 'male', true],
        'Erhanesia Creative Space' => ['Anisa Rahmawati', 'Social Media Specialist', 'female', false],
        'PGM Product EDM' => ['Dimas Prakoso', 'Performance Marketing Specialist', 'male', false],
        'Finance Accounting & Tax' => ['H. Bambang Setiawan', 'Finance Manager', 'male', true],
        'You and Milk' => ['Ambar Wijayanti', 'Video Editor Specialist', 'female', false],
        'Erha Idea Cipta Karsa' => ['Rizky Ananda Putra', 'Creative Strategist', 'male', false],
        'Marketplace' => ['Siti Nurhaliza', 'CRM Specialist', 'female', false],
        'Waji & Enrivo' => ['Adi Nugroho', 'Video Editor Specialist', 'male', false],
        'Vitameal & Less Sugar' => ['Wulan Safitri', 'Brand Executive', 'female', false],
        'Bruv & Vitasma' => ['Yusuf Maulana', 'Graphic Design Specialist', 'male', false],
        'Corporate Secretary' => ['Ustadz Nur Hidayat', 'Corporate Secretary Supervisor', 'male', true],
        'Eyebost & Pro' => ['Galih Ramadhan', 'Graphic Design Specialist', 'male', false],
        'Erhaniaga Kulakin Indonesia' => ['Fitri Handayani', 'Affiliate Specialist', 'female', false],
        'Digital Strategic' => ['Bayu Anggara', 'Digital Strategist', 'male', false],
        'Management Trainee' => ['Nadia Puspita', 'Management Trainee', 'female', false],
        'Social Commerce Vitameal & Vitasma' => ['Hendra Gunawan', 'Social Commerce Specialist', 'male', false],
        'Erhanesia Mulia Corpora' => ['Dewi Lestari', 'Finance & Accounting Specialist', 'female', false],
        'PGM Ethos Brand' => ['H. Zainal Abidin', 'Performance Marketing Manager', 'male', true],
        'Social Commerce Eyebost & Waji' => ['Yoga Pratama', 'Social Commerce Specialist', 'male', false],
        'Riset Team' => ['Laila Kusuma', 'Research Analyst', 'female', false],
        'Channel Development' => ['Agus Salim', 'Channel Development Officer', 'male', false],
    ];

    public function run(): void
    {
        $created = 0;
        $index = 0;

        foreach (self::PEOPLE as $team => [$name, $position, $gender, $isMentor]) {
            $index++;

            $user = User::query()->firstOrCreate(
                ['email' => $this->emailFor($name)],
                [
                    'name' => $name,
                    'external_employee_id' => sprintf('ERD-%04d', 2000 + $index),
                    'position_name' => $position,
                    'job_level' => $isMentor ? 'Manager' : 'Staff',
                    'team_name' => $team,
                    // Deliberately null — see the class docblock.
                    'department_name' => null,
                    'building_name' => self::BUILDING,
                    'gender' => $gender,
                    // Mentors are the senior ones: older, and here far longer.
                    'birth_date' => $isMentor ? '1972-04-18' : '1996-08-12',
                    'join_date' => $isMentor ? '2018-07-16' : '2023-03-13',
                    'is_supervisor' => $isMentor,
                    'is_mentor' => $isMentor,
                    'password' => self::DEFAULT_PASSWORD,
                    'email_verified_at' => now(),
                    'is_active' => true,
                ],
            );

            $created += $user->wasRecentlyCreated ? 1 : 0;

            // UserService keeps is_mentor and the `mentor` role in step; this
            // seeder writes the model directly, so the role is set here to match.
            $user->syncRoles([$isMentor ? UserRole::Mentor->value : UserRole::Employee->value]);
        }

        $this->command?->info($created.' karyawan demo dibuat di '.count(self::PEOPLE).' team.');

        // Every mentor, including the accounts DemoUserSeeder made — a mentor
        // with an empty halaqah makes the attendance pages look broken.
        $this->assignHalaqah(
            User::query()->where('is_mentor', true)->orderBy('id')->get()->all(),
        );
    }

    /**
     * `H. Suryadi Wibowo` → `suryadi.wibowo@erdigma.id`
     *
     * Honorifics and punctuation are dropped so the address stays valid; two
     * name parts are enough to keep them distinct at this size.
     */
    private function emailFor(string $name): string
    {
        $parts = collect(explode(' ', $name))
            ->map(static fn (string $part): string => preg_replace('/[^a-z]/', '', mb_strtolower($part)) ?? '')
            ->filter(static fn (string $part): bool => mb_strlen($part) > 1
                && ! in_array($part, ['ustadz', 'ustadzah'], true));

        return $parts->take(2)->implode('.').'@erdigma.id';
    }

    /**
     * One halaqah per mentor, filled with employees from other teams.
     *
     * Members are dealt round-robin rather than by team: a halaqah that happened
     * to hold one whole team would read as a department meeting, and would not
     * show that membership is independent of the org chart.
     *
     * @param  array<int, User>  $mentors
     */
    private function assignHalaqah(array $mentors): void
    {
        if ($mentors === []) {
            return;
        }

        $members = User::query()
            ->where('is_mentor', false)
            ->whereDoesntHave('roles', static fn ($query) => $query->whereIn('name', UserRole::administrative()))
            ->orderBy('id')
            ->get()
            ->values();

        $assigned = 0;

        foreach ($mentors as $index => $mentor) {
            $group = MentoringGroup::query()->firstOrCreate(
                ['code' => sprintf('HLQ-%02d', $index + 1)],
                [
                    'name' => 'Halaqah '.$mentor->name,
                    'mentor_id' => $mentor->id,
                    'capacity' => 10,
                    'description' => 'Kelompok pembinaan yang diampu '.$mentor->name.'.',
                    'default_location' => 'Musholla Erdigma',
                    'is_active' => true,
                ],
            );

            $share = $members->filter(
                static fn (User $member, int $position): bool => $position % count($mentors) === $index,
            );

            foreach ($share as $member) {
                if ($group->members()->where('user_id', $member->id)->exists()) {
                    continue;
                }

                $group->members()->attach($member->id, [
                    'joined_at' => DateHelper::today()->subMonths(3)->toDateString(),
                    'is_active' => true,
                ]);

                $assigned++;
            }
        }

        $this->command?->info($assigned.' anggota dimasukkan ke '.count($mentors).' halaqah.');
    }
}
