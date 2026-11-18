<?php

namespace Database\Factories;

use App\Models\OrganizationGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationGroup>
 */
class OrganizationGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
        ];
    }
}
