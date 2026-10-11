<?php

namespace Database\Factories;

use App\Models\Convocation;
use App\Models\Faction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Faction>
 */
class FactionFactory extends Factory
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
            'name' => fake()->unique()->company(),
        ];
    }
}
