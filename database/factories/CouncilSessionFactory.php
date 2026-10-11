<?php

namespace Database\Factories;

use App\Models\CouncilSession;
use App\Models\Convocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CouncilSession>
 */
class CouncilSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'convocation_id' => Convocation::factory(),
            'name' => fake()->unique()->numerify('Сесія № ##'),
        ];
    }
}
