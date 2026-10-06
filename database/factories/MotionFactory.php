<?php

namespace Database\Factories;

use App\Enums\MotionResult;
use App\Models\Motion;
use App\Models\PlenaryMeeting;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Motion>
 */
class MotionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uid' => (string) Str::uuid(),
            'plenary_meeting_id' => PlenaryMeeting::factory(),
            'roll_call_import_id' => null,
            'number' => fake()->unique()->numberBetween(1, 500),
            'title' => 'Про '.fake()->sentence(4),
            'project_number' => fake()->optional()->numerify('###/##'),
            'result' => MotionResult::Passed,
            'text_url' => fake()->optional()->url(),
            'text' => fake()->bothify('decision-###.html'),
        ];
    }
}
