<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\DutyStatus;
use App\Enums\PrayerName;
use App\Enums\UserRole;
use App\Models\FridaySchedule;
use App\Models\PrayerDuty;
use App\Models\User;
use App\Support\Helpers\DateHelper;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Past and upcoming rosters: Friday sermons and the Dzuhur/Ashar duty grid.
 *
 * DemoScheduleSeeder only looks ahead six Fridays, so the roster pages had
 * nothing to page back through and the duty printout had nothing to print.
 * This fills four months behind and two ahead, and deliberately leaves one
 * upcoming Friday empty so the roster has a visible gap.
 *
 * The gap is seven weeks out, not inside the dashboard's four-week warning:
 * DemoScheduleSeeder already fills the next six Fridays, and making room
 * nearer would mean deleting one of them.
 */
class DemoRosterHistorySeeder extends Seeder
{
    private const FRIDAY_WEEKS_BACK = 16;

    private const FRIDAY_WEEKS_AHEAD = 8;

    /** Left without a roster on purpose — see the class docblock. */
    private const UNSCHEDULED_FRIDAY_WEEK = 7;

    private const DUTY_WEEKS_BACK = 8;

    private const DUTY_WEEKS_AHEAD = 2;

    /** @var array<int, string> */
    private const THEMES = [
        'Menjaga Amanah dalam Pekerjaan',
        'Sabar dan Syukur di Tempat Kerja',
        'Adab Bertetangga dan Bermuamalah',
        'Ikhlas dalam Beramal',
        'Menjaga Lisan di Era Digital',
        'Keutamaan Sholat Berjamaah',
        'Rezeki yang Halal dan Berkah',
        'Birrul Walidain',
        'Istiqamah Setelah Ramadhan',
        'Bahaya Riba dalam Kehidupan Modern',
        'Menjaga Waktu, Menjaga Iman',
        'Keluarga Sakinah',
        'Tawakal dan Ikhtiar',
        'Ukhuwah di Lingkungan Kerja',
        'Muhasabah Diri',
        'Keutamaan Bersedekah',
    ];

    /**
     * Name, and where they come from.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const EXTERNAL_KHATIBS = [
        ['Ust. Ahmad Fauzi, Lc.', 'Pondok Pesantren Al-Hikmah'],
        ['Ust. Muhammad Ridwan, S.Ag.', 'MUI Kabupaten Purbalingga'],
        ['Ust. Hasan Basri', 'Masjid Agung Darussalam'],
        ['Dr. Khoirul Anam, M.Pd.I.', 'UIN Saizu Purwokerto'],
        ['Ust. Syamsul Arifin', 'PD Muhammadiyah Purbalingga'],
    ];

    public function run(): void
    {
        fake()->seed(20260915);

        $admin = User::query()->where('email', 'dkm@erdigma.id')->first();

        $mentors = User::query()
            ->where('is_active', true)
            ->where('is_mentor', true)
            ->orderBy('id')
            ->get()
            ->values();

        // Men who are not mentors call the adhan; mentors lead.
        $muadzins = User::query()
            ->where('is_active', true)
            ->where('is_mentor', false)
            ->where('gender', 'male')
            ->whereDoesntHave('roles', static fn ($query) => $query->whereIn('name', UserRole::administrative()))
            ->orderBy('id')
            ->get()
            ->values();

        if ($admin === null || $mentors->isEmpty() || $muadzins->isEmpty()) {
            $this->command?->warn('Lewati riwayat roster: akun pengurus, mentor, atau karyawan laki-laki belum ada.');

            return;
        }

        $this->seedFridays($admin, $mentors, $muadzins);
        $this->seedPrayerDuties($admin, $mentors, $muadzins);
    }

    /**
     * @param  Collection<int, User>  $mentors
     * @param  Collection<int, User>  $muadzins
     */
    private function seedFridays(User $admin, Collection $mentors, Collection $muadzins): void
    {
        $today = DateHelper::today();
        $thisFriday = $today->isFriday() ? $today : $today->next(CarbonImmutable::FRIDAY);
        $created = 0;

        for ($week = -self::FRIDAY_WEEKS_BACK; $week <= self::FRIDAY_WEEKS_AHEAD; $week++) {
            if ($week === self::UNSCHEDULED_FRIDAY_WEEK) {
                continue;
            }

            $date = $thisFriday->addWeeks($week);
            $slot = $week + self::FRIDAY_WEEKS_BACK;

            // Every third Friday a visiting khatib, the rest in-house.
            $isExternal = $slot % 3 === 2;
            [$externalName, $externalOrigin] = self::EXTERNAL_KHATIBS[intdiv($slot, 3) % count(self::EXTERNAL_KHATIBS)];
            $mentor = $mentors[$slot % $mentors->count()];

            $status = match (true) {
                $date->lessThan($today) => DutyStatus::Done,
                $week >= 5 => DutyStatus::Assigned,
                default => DutyStatus::Confirmed,
            };

            $friday = FridaySchedule::query()->firstOrCreate(
                ['date' => $date->toDateString()],
                [
                    'khatib_id' => $isExternal ? null : $mentor->id,
                    'imam_id' => $mentor->id,
                    'muadzin_id' => $muadzins[$slot % $muadzins->count()]->id,
                    'external_khatib_name' => $isExternal ? $externalName : null,
                    'external_khatib_origin' => $isExternal ? $externalOrigin : null,
                    'theme' => self::THEMES[$slot % count(self::THEMES)],
                    'notes' => $isExternal ? 'Konfirmasi kehadiran khatib H-3.' : null,
                    'location' => 'Musholla Erdigma',
                    'start_time' => '11:45',
                    'status' => $status->value,
                    'created_by' => $admin->id,
                    'created_at' => $date->subDays(14)->setTime(9, 0)->toDateTimeString(),
                    'updated_at' => $date->subDays(14)->setTime(9, 0)->toDateTimeString(),
                ],
            );

            $created += $friday->wasRecentlyCreated ? 1 : 0;
        }

        $this->command?->info($created.' jadwal Jumat tambahan dibuat.');
    }

    /**
     * @param  Collection<int, User>  $mentors
     * @param  Collection<int, User>  $muadzins
     */
    private function seedPrayerDuties(User $admin, Collection $mentors, Collection $muadzins): void
    {
        $today = DateHelper::today();
        $thisMonday = $today->startOfWeek(CarbonImmutable::MONDAY);
        $lastDay = $thisMonday->addWeeks(self::DUTY_WEEKS_AHEAD)->addDays(4);
        $created = 0;
        $slot = 0;

        for ($date = $thisMonday->subWeeks(self::DUTY_WEEKS_BACK); $date->lessThanOrEqualTo($lastDay); $date = $date->addDay()) {
            if (! $date->isWeekday()) {
                continue;
            }

            foreach ([PrayerName::Dhuhr, PrayerName::Asr] as $prayer) {
                $slot++;
                $plannedAt = $date->startOfWeek(CarbonImmutable::MONDAY)->subDays(3)->setTime(9, 0)->toDateTimeString();

                $duty = PrayerDuty::query()->firstOrCreate(
                    ['date' => $date->toDateString(), 'prayer' => $prayer->value],
                    [
                        'imam_id' => $mentors[$slot % $mentors->count()]->id,
                        'muadzin_id' => $muadzins[($slot * 3) % $muadzins->count()]->id,
                        'status' => ($date->lessThan($today) ? DutyStatus::Done : DutyStatus::Assigned)->value,
                        'created_by' => $admin->id,
                        'created_at' => $plannedAt,
                        'updated_at' => $plannedAt,
                    ],
                );

                $created += $duty->wasRecentlyCreated ? 1 : 0;
            }
        }

        $this->command?->info($created.' petugas sholat demo dibuat.');
    }
}
