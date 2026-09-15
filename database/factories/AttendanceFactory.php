<?php

namespace Database\Factories;

use App\Enums\AttendanceMethod;
use App\Enums\AttendanceStatus;
use App\Models\AfterHoursSession;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * Not recorded yet: the row a new session prepares for every member.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'after_hours_session_id' => AfterHoursSession::factory(),
            'user_id' => User::factory(),
            'status' => AttendanceStatus::Absent,
            'method' => AttendanceMethod::Manual,
        ];
    }

    /**
     * Marked present — which keeps a series from moving the session.
     */
    public function present(): static
    {
        return $this->state([
            'status' => AttendanceStatus::Present,
            'checked_in_at' => now(),
        ]);
    }
}
