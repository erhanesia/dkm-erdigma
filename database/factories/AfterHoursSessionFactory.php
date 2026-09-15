<?php

namespace Database\Factories;

use App\Enums\SessionStatus;
use App\Models\AfterHoursSession;
use App\Models\MentoringGroup;
use App\Support\Helpers\DateHelper;
use App\Support\Helpers\TokenHelper;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AfterHoursSession>
 */
class AfterHoursSessionFactory extends Factory
{
    /**
     * Scheduled for a week from today, 16:30 to 18:00, shown publicly.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = DateHelper::today()->addWeek()->setTime(16, 30);

        return [
            'mentoring_group_id' => MentoringGroup::factory(),
            'topic' => fake()->sentence(4),
            'starts_at' => $startsAt->toDateTimeString(),
            'ends_at' => $startsAt->addMinutes(90)->toDateTimeString(),
            'status' => SessionStatus::Scheduled,
            'qr_token' => TokenHelper::generateSessionToken(),
            'is_qr_enabled' => true,
            'is_public' => true,
        ];
    }

    public function completed(): static
    {
        return $this->state(['status' => SessionStatus::Completed]);
    }
}
