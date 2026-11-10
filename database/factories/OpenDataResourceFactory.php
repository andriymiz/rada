<?php

namespace Database\Factories;

use App\Models\OpenDataResource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpenDataResource>
 */
class OpenDataResourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'package_id' => fake()->uuid(),
            'resource_id' => fake()->uuid(),
            'code' => fake()->unique()->slug(),
        ];
    }
}
