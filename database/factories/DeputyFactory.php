<?php

namespace Database\Factories;

use App\Enums\DeputyStatus;
use App\Models\Convocation;
use App\Models\Deputy;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deputy>
 */
class DeputyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'convocation_id' => Convocation::factory(),
            'status' => DeputyStatus::Active,
            'biography' => fake()->paragraph(),
        ];
    }
}
