<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\Prayer\PrayerScheduleService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeds everything the app needs to be usable.
     *
     * Roles, settings, and zones are safe to run anywhere — each uses
     * `firstOrCreate`. Demo accounts are skipped in production so a real
     * deployment never ends up with a known password.
     */
    public function run(PrayerScheduleService $prayerSchedules): void
    {
        $this->call([
            RoleSeeder::class,
            SettingSeeder::class,
            AudioZoneSeeder::class,
        ]);

        if (! app()->isProduction()) {
            $this->call([
                DemoUserSeeder::class,
                DemoEmployeeSeeder::class,
                DemoScheduleSeeder::class,
            ]);
        }

        $days = $prayerSchedules->ensureHorizon();

        $this->command?->info('Jadwal sholat dibuat untuk '.$days.' hari ke depan.');
    }
}
