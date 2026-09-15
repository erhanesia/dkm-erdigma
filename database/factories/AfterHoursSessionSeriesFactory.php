<?php

namespace Database\Factories;

use App\Models\AfterHoursSessionSeries;
use App\Models\MentoringGroup;
use App\Support\Helpers\DateHelper;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AfterHoursSessionSeries>
 */
class AfterHoursSessionSeriesFactory extends Factory
{
    /**
     * Every two weeks from today for three months, 16:30 to 18:00.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsOn = DateHelper::today();

        return [
            'mentoring_group_id' => MentoringGroup::factory(),
            'topic' => fake()->sentence(4),
            'weekday' => $startsOn->dayOfWeekIso,
            'interval_weeks' => 2,
            'start_time' => '16:30:00',
            'end_time' => '18:00:00',
            'starts_on' => $startsOn->toDateString(),
            'ends_on' => $startsOn->addMonths(3)->toDateString(),
        ];
    }
}
