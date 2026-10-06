<?php

namespace Database\Factories;

use App\Models\CouncilOrganization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CouncilOrganization>
 */
class CouncilOrganizationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' міська рада',
            'edrpou' => fake()->unique()->numerify('########'),
            'katoottg' => 'UA'.fake()->unique()->numerify('#################'),
        ];
    }
}
