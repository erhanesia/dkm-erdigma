<?php

namespace Database\Factories;

use App\Enums\ScheduleSource;
use App\Models\PrayerSchedule;
use App\Support\Helpers\DateHelper;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrayerSchedule>
 */
class PrayerScheduleFactory extends Factory
{
    /**
     * Today, with plausible times. A test that asserts a time should set the
     * one it reads rather than rely on these.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => DateHelper::today()->toDateString(),
            'fajr' => '04:30:00',
            'sunrise' => '05:45:00',
            'dhuhr' => '11:50:00',
            'asr' => '15:10:00',
            'maghrib' => '17:50:00',
            'isha' => '19:00:00',
            'is_manual_override' => false,
            'source' => ScheduleSource::Calculated,
        ];
    }
}
