<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\DutyStatus;
use App\Enums\SessionStatus;
use App\Models\AfterHoursSession;
use App\Models\FridaySchedule;
use App\Models\MentoringGroup;
use App\Models\User;
use App\Support\Helpers\DateHelper;
use App\Support\Helpers\TokenHelper;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Friday rosters and kajian sessions for development.
 *
 * Without these the public pages render nothing but empty states, which makes it
 * impossible to tell "the query is wrong" apart from "there is no data yet".
 *
 * Dates are computed from today rather than hard-coded, so the seed still
 * produces upcoming entries whenever it is run.
 */
class DemoScheduleSeeder extends Seeder
{
    private const FRIDAY_WEEKS = 6;

    public function run(): void
    {
        $mentor = User::query()->where('is_mentor', true)->first();
        $admin = User::query()->where('email', 'dkm@erdigma.id')->first()
            ?? User::query()->first();

        if ($mentor === null || $admin === null) {
            $this->command?->warn('Lewati jadwal demo: belum ada akun pengguna.');

            return;
        }

        $this->seedFridays($mentor, $admin);
        $this->seedSessions($mentor, $admin);
    }

    private function seedFridays(User $mentor, User $admin): void
    {
        $themes = [
            'Menjaga Amanah dalam Pekerjaan',
            'Sabar dan Syukur di Tempat Kerja',
            'Adab Bertetangga dan Bermuamalah',
            'Ikhlas dalam Beramal',
            'Menjaga Lisan di Era Digital',
            'Keutamaan Sholat Berjamaah',
        ];

        $externalKhatibs = [
            ['Ust. Ahmad Fauzi, Lc.', 'Pondok Pesantren Al-Hikmah'],
            ['Ust. Muhammad Ridwan, S.Ag.', 'MUI Kabupaten Purbalingga'],
            ['Ust. Hasan Basri', 'Masjid Agung Darussalam'],
        ];

        $friday = $this->nextFriday();

        for ($week = 0; $week < self::FRIDAY_WEEKS; $week++) {
            $date = $friday->addWeeks($week);

            // Alternating in-house and visiting khatib, which is how a roster
            // usually looks and exercises both name paths in the view.
            $isExternal = $week % 2 === 1;
            $external = $externalKhatibs[intdiv($week, 2) % count($externalKhatibs)];

            FridaySchedule::query()->firstOrCreate(
                ['date' => $date->toDateString()],
                [
                    // The khatib normally leads the prayer too, so the same
                    // person fills both roles unless a guest is speaking.
                    'khatib_id' => $isExternal ? null : $mentor->id,
                    'imam_id' => $mentor->id,
                    'muadzin_id' => $admin->id,
                    'external_khatib_name' => $isExternal ? $external[0] : null,
                    'external_khatib_origin' => $isExternal ? $external[1] : null,
                    'theme' => $themes[$week % count($themes)],
                    'location' => 'Musholla Erdigma',
                    'start_time' => '11:45',
                    'status' => DutyStatus::Confirmed->value,
                    'created_by' => $admin->id,
                ],
            );
        }

        $this->command?->info(self::FRIDAY_WEEKS.' jadwal Jumat demo dibuat.');
    }

    private function seedSessions(User $mentor, User $admin): void
    {
        // Prefer a halaqah that already has members: sessions attached to an
        // empty group produce empty attendance lists, which reads as a bug
        // rather than as "nobody has joined yet".
        $group = MentoringGroup::query()->has('members')->orderBy('id')->first()
            ?? MentoringGroup::query()->firstOrCreate(
                ['code' => 'KEL-01'],
                [
                    'name' => 'Kelompok Binaan 1',
                    'mentor_id' => $mentor->id,
                    'capacity' => 10,
                    'description' => 'Kelompok pembinaan karyawan angkatan pertama.',
                    'default_location' => 'Musholla Erdigma',
                    'is_active' => true,
                ],
            );

        $mentor = $group->mentor ?? $mentor;

        // Topic is the label now — there is no separate title, because every
        // one of these is an after hours session and saying so twice told a
        // reader nothing.
        $topics = [
            ['Kajian Tafsir Surat Al-Kahfi', 'Membahas ayat 1–10 beserta keutamaan membacanya di hari Jumat, dan bagaimana kisah Ashabul Kahfi berbicara tentang keteguhan memegang prinsip di lingkungan yang tidak mendukung.'],
            ['Fiqih Muamalah Sehari-hari', 'Akad jual beli dan riba dalam transaksi modern — termasuk pembahasan praktis soal cicilan, kartu kredit, dan jual beli daring.'],
            ['Sirah Nabawiyah: Periode Makkah', 'Keteguhan Rasulullah menghadapi tekanan pada tahun-tahun awal dakwah, dan pelajarannya bagi kita yang bekerja di bawah tekanan target.'],
            ['Tahsin Al-Quran', 'Makharijul huruf dan hukum nun sukun, dengan praktik langsung bergantian.'],
        ];

        $membershipCount = 0;

        foreach ($topics as $index => [$topic, $description]) {
            // Spread across the coming weeks, on weekday evenings.
            $start = DateHelper::today()
                ->addDays(($index * 5) + 2)
                ->setTime(16, 30);

            $session = AfterHoursSession::query()->firstOrCreate(
                [
                    'mentoring_group_id' => $group->id,
                    'starts_at' => $start->toDateTimeString(),
                ],
                [
                    'mentor_id' => $mentor->id,
                    'topic' => $topic,
                    'description' => $description,
                    'ends_at' => $start->addMinutes(90)->toDateTimeString(),
                    'location' => 'Musholla Erdigma',
                    'status' => SessionStatus::Scheduled->value,
                    'qr_token' => TokenHelper::generateSessionToken(),
                    'is_qr_enabled' => true,
                    'is_public' => true,
                    'created_by' => $admin->id,
                ],
            );

            $membershipCount += $session->wasRecentlyCreated ? 1 : 0;
        }

        $this->command?->info($membershipCount.' kajian demo dibuat.');
    }

    /**
     * The coming Friday, or today when today already is one.
     */
    private function nextFriday(): CarbonImmutable
    {
        $today = DateHelper::today();

        return $today->isFriday() ? $today : $today->next(CarbonImmutable::FRIDAY);
    }
}
