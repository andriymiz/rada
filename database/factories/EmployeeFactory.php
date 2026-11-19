<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Organization;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'organization_id' => Organization::factory(),
            'position' => fake('uk_UA')->jobTitle(),
            'is_post' => false,
            'image' => fake()->optional()->imageUrl(),
            'homepage' => fake()->optional()->url(),
        ];
    }
}
