<?php

namespace Database\Factories;

use App\Models\DatasetGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DatasetGroup>
 */
class DatasetGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
        ];
    }
}
