<?php

namespace Database\Factories;

use App\Models\MentoringGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MentoringGroup>
 */
class MentoringGroupFactory extends Factory
{
    /**
     * An active halaqah with room for ten, led by a mentor made for it.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Halaqah '.fake()->unique()->lastName(),
            'code' => 'HLQ-'.fake()->unique()->numerify('#####'),
            'mentor_id' => User::factory()->mentor(),
            'capacity' => 10,
            'is_active' => true,
        ];
    }
}
