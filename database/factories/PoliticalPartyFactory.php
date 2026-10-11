<?php

namespace Database\Factories;

use App\Models\PoliticalParty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PoliticalParty>
 */
class PoliticalPartyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'edrpou' => fake()->unique()->numerify('########'),
        ];
    }
}
