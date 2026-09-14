<?php

namespace Database\Factories;

use App\Enums\DutyStatus;
use App\Enums\PrayerName;
use App\Models\PrayerDuty;
use App\Models\User;
use App\Support\Helpers\DateHelper;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrayerDuty>
 */
class PrayerDutyFactory extends Factory
{
    /**
     * Today's Dzuhur, with both roles filled.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => DateHelper::today()->toDateString(),
            'prayer' => PrayerName::Dhuhr,
            'imam_id' => User::factory(),
            'muadzin_id' => User::factory(),
            'status' => DutyStatus::Assigned,
        ];
    }
}
