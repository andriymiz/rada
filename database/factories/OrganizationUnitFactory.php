<?php

namespace Database\Factories;

use App\Models\Official;
use App\Models\Organization;
use App\Models\OrganizationUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationUnit>
 */
class OrganizationUnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uid' => fake()->unique()->uuid(),
            'organization_id' => Organization::factory(),
            'head_id' => function (array $attributes): int {
                return Official::factory()->create(['organization_id' => $attributes['organization_id']])->getKey();
            },
            'full_name' => fake()->words(3, true),
            'short_name' => fake()->words(1, true),
            'description' => fake()->sentence(),
            'contact_point' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => '+380501234567',
        ];
    }
}
