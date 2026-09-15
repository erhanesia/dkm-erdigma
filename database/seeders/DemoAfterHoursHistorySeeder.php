<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AttendanceMethod;
use App\Enums\AttendanceStatus;
use App\Enums\SessionStatus;
use App\Enums\UserRole;
use App\Models\AfterHoursSession;
use App\Models\Attendance;
use App\Models\MentoringGroup;
use App\Models\MentoringGroupMember;
use App\Models\User;
use App\Support\Helpers\DateHelper;
use App\Support\Helpers\TokenHelper;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Six months of halaqah life: more employees, halaqah filled to capacity, a
 * weekly session for each one, and who came to it.
 *
 * DemoEmployeeSeeder and DemoScheduleSeeder give the app its shape — one person
 * per team, a handful of upcoming kajian. That is enough to open every page but
 * not enough to read one: the attendance report, the six-month trend and all
 * three dashboards summarise the past, and there was no past.
 *
 * Attendance is drawn from weighted odds that improve month by month, so the
 * trend chart has a direction to show rather than flat noise. Faker is seeded,
 * so a fresh database comes out the same every time, and every section skips
 * what is already there, so running it twice adds nothing.
 */
class DemoAfterHoursHistorySeeder extends Seeder
{
    private const DEFAULT_PASSWORD = 'password123';

    private const EXTRA_EMPLOYEES = 40;

    private const MONTHS_OF_HISTORY = 6;

    private const UPCOMING_WEEKS = 3;

    /** @var array<int, string> */
    private const POSITIONS = [
        'Content Creator',
        'Customer Service Specialist',
        'Admin Marketplace',
        'Graphic Design Specialist',
        'Video Editor Specialist',
        'Finance Staff',
        'Warehouse Staff',
        'Data Analyst',
        'Frontend Engineer',
        'Brand Executive',
        'Host Live Streaming',
        'HR Staff',
    ];

    /**
     * Topic, and what it covered.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const TOPICS = [
        ['Tafsir Surat Al-Asr', 'Tiga syarat selamat dari kerugian: iman, amal saleh, dan saling menasihati dalam kebenaran dan kesabaran.'],
        ['Adab Menuntut Ilmu', 'Niat, kesabaran, dan menghormati guru, dengan contoh dari kitab Ta\'limul Muta\'allim.'],
        ['Fiqih Thaharah', 'Tata cara wudhu dan tayamum, termasuk hal-hal yang membatalkannya.'],
        ['Hadits Arbain: Niat', 'Hadits pertama Arbain Nawawi, dan bagaimana niat mengubah nilai pekerjaan sehari-hari.'],
        ['Sirah: Hijrah ke Madinah', 'Perencanaan hijrah Rasulullah dan pelajaran mengelola risiko di dalamnya.'],
        ['Mengelola Keuangan Keluarga', 'Prioritas nafkah, sedekah, dan menghindari utang konsumtif.'],
        ['Tahsin: Hukum Mim Sukun', 'Ikhfa syafawi, idgham mimi, dan izhar syafawi, dengan praktik bergantian.'],
        ['Akhlak kepada Rekan Kerja', 'Menjaga lisan, menjauhi ghibah, dan menunaikan amanah tim.'],
        ['Dzikir Pagi dan Petang', 'Keutamaan Al-Ma\'tsurat dan cara menjaganya di tengah kesibukan kantor.'],
        ['Fiqih Sholat Jamak dan Qashar', 'Ketentuan jamak dan qashar bagi yang sering dinas ke luar kota.'],
        ['Kisah Nabi Yusuf', 'Kesabaran menghadapi fitnah dan kejujuran ketika memegang jabatan.'],
        ['Muhasabah Akhir Bulan', 'Evaluasi target ibadah pribadi dan saling berbagi kendala.'],
    ];

    /**
     * Last readings, written into `summary`: where the halaqah stopped, with
     * the occasional note a mentor adds.
     *
     * @var array<int, string>
     */
    private const READINGS = [
        'Al-Baqarah ayat 24',
        'Ali Imran ayat 18, ulang bacaan mad thabi\'i',
        'An-Nisa ayat 11',
        'Al-Kahfi ayat 10, lanjut tadabbur ayat 11–16',
        'Yasin ayat 40',
        'Al-Mulk ayat 15, perhatikan dengung ikhfa',
    ];

    /** @var array<int, string> */
    private const EXCUSED_NOTES = ['Dinas luar kota.', 'Ada keperluan keluarga.', 'Lembur deadline campaign.'];

    /** @var array<int, string> */
    private const SICK_NOTES = ['Demam.', 'Sakit kepala, izin pulang lebih awal.', 'Kontrol ke dokter.'];

    public function run(): void
    {
        fake()->seed(20260914);

        $admin = User::query()->where('email', 'dkm@erdigma.id')->first();

        if ($admin === null) {
            $this->command?->warn('Lewati riwayat after hours: akun pengurus DKM belum ada.');

            return;
        }

        $this->seedEmployees();
        $this->fillHalaqah();

        $sessions = 0;
        $attendances = 0;

        foreach (MentoringGroup::query()->whereNotNull('mentor_id')->orderBy('id')->get()->values() as $index => $group) {
            [$groupSessions, $groupAttendances] = $this->seedSessions($group, $index, $admin);

            $sessions += $groupSessions;
            $attendances += $groupAttendances;
        }

        $this->command?->info($sessions.' kegiatan after hours dan '.$attendances.' presensi demo dibuat.');
    }

    /**
     * Employees beyond the one-per-team accounts, spread over the teams that
     * already exist so the user list reads like a company rather than a sample.
     */
    private function seedEmployees(): void
    {
        $teams = User::query()->whereNotNull('team_name')->distinct()->orderBy('team_name')->pluck('team_name')->all();

        if ($teams === []) {
            $this->command?->warn('Lewati karyawan tambahan: belum ada team dari DemoEmployeeSeeder.');

            return;
        }

        $created = 0;

        for ($number = 1; $number <= self::EXTRA_EMPLOYEES; $number++) {
            $isMale = fake()->boolean(60);
            $name = $isMale
                ? fake()->firstNameMale().' '.fake()->lastNameMale()
                : fake()->firstNameFemale().' '.fake()->lastNameFemale();
            $employeeId = 3000 + $number;

            $user = User::query()->firstOrCreate(
                ['external_employee_id' => sprintf('ERD-%04d', $employeeId)],
                [
                    'name' => $name,
                    // The number keeps two people who share a name apart.
                    'email' => sprintf('%s.%d@erdigma.id', Str::slug($name, '.'), $employeeId),
                    'phone' => fake()->numerify('08##########'),
                    'position_name' => fake()->randomElement(self::POSITIONS),
                    'job_level' => 'Staff',
                    'team_name' => fake()->randomElement($teams),
                    // Null, as HRIS really returns it — see DemoEmployeeSeeder.
                    'department_name' => null,
                    'building_name' => 'Erdigma Blater',
                    'gender' => $isMale ? 'male' : 'female',
                    'birth_date' => fake()->dateTimeBetween('-40 years', '-21 years')->format('Y-m-d'),
                    'join_date' => fake()->dateTimeBetween('-6 years', '-2 months')->format('Y-m-d'),
                    'is_supervisor' => false,
                    'is_mentor' => false,
                    // A couple of former staff, so the inactive filter has something to find.
                    'is_active' => $number % 20 !== 0,
                    'last_login_at' => fake()->dateTimeBetween('-14 days', 'now')->format('Y-m-d H:i:s'),
                    'password' => self::DEFAULT_PASSWORD,
                    'email_verified_at' => now(),
                ],
            );

            if ($user->wasRecentlyCreated) {
                $user->syncRoles([UserRole::Employee->value]);
                $created++;
            }
        }

        $this->command?->info($created.' karyawan tambahan dibuat.');
    }

    /**
     * Tops every halaqah up to its capacity with employees who are in none yet.
     *
     * Join dates are spread over the history, so the trend grows as people
     * arrive instead of every halaqah appearing fully formed six months ago.
     * Whoever is left over stays unassigned, which is also how it really is.
     */
    private function fillHalaqah(): void
    {
        $candidates = User::query()
            ->where('is_active', true)
            ->where('is_mentor', false)
            ->whereDoesntHave('roles', static fn ($query) => $query->whereIn('name', UserRole::administrative()))
            ->whereNotIn('id', MentoringGroupMember::query()->select('user_id'))
            ->orderBy('id')
            ->get();

        $assigned = 0;

        foreach (MentoringGroup::query()->withCount('members')->orderBy('id')->get() as $group) {
            $room = max(0, $group->capacity - $group->members_count);

            foreach ($candidates->splice(0, $room) as $member) {
                $group->members()->attach($member->id, [
                    'joined_at' => DateHelper::today()
                        ->subDays(fake()->numberBetween(20, self::MONTHS_OF_HISTORY * 30))
                        ->toDateString(),
                    'is_active' => true,
                ]);

                $assigned++;
            }
        }

        $this->command?->info($assigned.' anggota ditambahkan ke halaqah.');
    }

    /**
     * One session a week for the group, from the start of the history to a few
     * weeks ahead. Each halaqah meets on its own weekday evening.
     *
     * @return array{0: int, 1: int} Sessions and attendance rows created.
     */
    private function seedSessions(MentoringGroup $group, int $index, User $admin): array
    {
        $weekdays = [CarbonImmutable::MONDAY, CarbonImmutable::TUESDAY, CarbonImmutable::WEDNESDAY, CarbonImmutable::THURSDAY];
        $today = DateHelper::today();
        $historyStart = $today->subMonths(self::MONTHS_OF_HISTORY)->startOfMonth();
        $lastDate = $today->addWeeks(self::UPCOMING_WEEKS);

        $members = MentoringGroupMember::query()
            ->where('mentoring_group_id', $group->id)
            ->where('is_active', true)
            ->get();

        $sessions = 0;
        $attendances = 0;
        $week = 0;

        for ($date = $historyStart->next($weekdays[$index % count($weekdays)]); $date->lessThanOrEqualTo($lastDate); $date = $date->addWeek()) {
            $week++;

            // DemoScheduleSeeder may already have put a session on this evening.
            if (AfterHoursSession::query()->where('mentoring_group_id', $group->id)->whereDate('starts_at', $date->toDateString())->exists()) {
                continue;
            }

            $startsAt = $date->setTime(16, 30);
            $isPast = $startsAt->lessThan(DateHelper::now());
            $isCancelled = $isPast && fake()->boolean(5);
            [$topic, $description] = self::TOPICS[($week + $index * 3) % count(self::TOPICS)];

            $status = match (true) {
                $isCancelled => SessionStatus::Cancelled,
                $isPast => SessionStatus::Completed,
                default => SessionStatus::Scheduled,
            };

            $session = AfterHoursSession::query()->create([
                'mentoring_group_id' => $group->id,
                'mentor_id' => $group->mentor_id,
                'topic' => $topic,
                'description' => $description,
                'starts_at' => $startsAt->toDateTimeString(),
                'ends_at' => $startsAt->addMinutes(90)->toDateTimeString(),
                'location' => $group->default_location ?? 'Musholla Erdigma',
                'status' => $status->value,
                'qr_token' => TokenHelper::generateSessionToken(),
                'is_qr_enabled' => ! $isPast,
                'is_public' => fake()->boolean(70),
                'summary' => $status === SessionStatus::Completed ? fake()->randomElement(self::READINGS) : null,
                'created_by' => $admin->id,
                'created_at' => $startsAt->subDays(7)->toDateTimeString(),
                'updated_at' => ($isPast ? $startsAt->addHours(2) : $startsAt->subDays(7))->toDateTimeString(),
            ]);

            $sessions++;

            if ($status === SessionStatus::Completed) {
                $attendances += $this->seedAttendance($session, $members, $historyStart);
            }
        }

        return [$sessions, $attendances];
    }

    /**
     * Who came, for every member who had already joined by that evening.
     *
     * @param  Collection<int, MentoringGroupMember>  $members
     */
    private function seedAttendance(AfterHoursSession $session, Collection $members, CarbonImmutable $historyStart): int
    {
        $startsAt = DateHelper::toCarbon($session->starts_at);

        // 0 at the start of the history, 1 today: attendance improves along it.
        $progress = min(1.0, max(0.0, $historyStart->diffInDays($startsAt) / max(1.0, $historyStart->diffInDays(DateHelper::today()))));

        $rows = [];

        foreach ($members as $member) {
            if ($member->joined_at !== null && $member->joined_at->greaterThan($startsAt)) {
                continue;
            }

            // Some people are simply more regular than others, and stay that way.
            $diligence = ($member->user_id * 7) % 21 - 10;

            $status = AttendanceStatus::from($this->weighted([
                AttendanceStatus::Present->value => max(5, (int) round(55 + 25 * $progress) + $diligence),
                AttendanceStatus::Late->value => 10,
                AttendanceStatus::Excused->value => 7,
                AttendanceStatus::Sick->value => 5,
                AttendanceStatus::Absent->value => max(2, (int) round(23 - 17 * $progress) - $diligence),
            ]));

            $method = match ($status) {
                AttendanceStatus::Present, AttendanceStatus::Late => fake()->boolean(85) ? AttendanceMethod::QrCode : AttendanceMethod::Manual,
                AttendanceStatus::Absent => AttendanceMethod::Manual,
                default => fake()->boolean(60) ? AttendanceMethod::SelfReport : AttendanceMethod::Manual,
            };

            $checkedInAt = match ($status) {
                AttendanceStatus::Present => $startsAt->addMinutes(fake()->numberBetween(-10, 8)),
                AttendanceStatus::Late => $startsAt->addMinutes(fake()->numberBetween(15, 40)),
                default => null,
            };

            $recordedAt = ($checkedInAt ?? $startsAt->addHours(2))->toDateTimeString();

            $rows[] = [
                'after_hours_session_id' => $session->id,
                'user_id' => $member->user_id,
                'status' => $status->value,
                'method' => $method->value,
                'checked_in_at' => $checkedInAt?->toDateTimeString(),
                'note' => match ($status) {
                    AttendanceStatus::Excused => fake()->randomElement(self::EXCUSED_NOTES),
                    AttendanceStatus::Sick => fake()->randomElement(self::SICK_NOTES),
                    default => null,
                },
                'recorded_by' => $method === AttendanceMethod::Manual ? $session->mentor_id : null,
                'created_at' => $recordedAt,
                'updated_at' => $recordedAt,
            ];
        }

        if ($rows !== []) {
            Attendance::query()->insert($rows);
        }

        return count($rows);
    }

    /**
     * One key from `value => weight`, chosen in proportion to its weight.
     *
     * @param  array<string, int>  $weights
     */
    private function weighted(array $weights): string
    {
        $roll = fake()->numberBetween(1, array_sum($weights));

        foreach ($weights as $value => $weight) {
            $roll -= $weight;

            if ($roll <= 0) {
                return (string) $value;
            }
        }

        return (string) array_key_last($weights);
    }
}
