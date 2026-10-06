<?php

namespace Database\Factories;

use App\Models\ParliamentaryConvocation;
use App\Models\ParliamentarySession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParliamentarySession>
 */
class ParliamentarySessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'convocation_id' => ParliamentaryConvocation::factory(),
            'name' => fake()->unique()->numerify('Сесія № ##'),
        ];
    }
}
