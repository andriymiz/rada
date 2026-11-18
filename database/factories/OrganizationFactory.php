<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'full_name' => fake()->company().' міська рада',
            'short_name' => fake()->company(),
            'edrpou' => fake()->unique()->numerify('########'),
            'katoottg' => 'UA'.fake()->unique()->numerify('#################'),
        ];
    }
}
