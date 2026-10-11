<?php

namespace Database\Factories;

use App\Models\Convocation;
use App\Models\PermanentCommission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PermanentCommission>
 */
class PermanentCommissionFactory extends Factory
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
            'name' => fake()->unique()->words(3, true),
        ];
    }
}
