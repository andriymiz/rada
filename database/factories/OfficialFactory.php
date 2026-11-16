<?php

namespace Database\Factories;

use App\Models\Official;
use App\Models\Organization;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Official>
 */
class OfficialFactory extends Factory
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
            'is_head' => false,
            'image' => fake()->optional()->imageUrl(),
            'homepage' => fake()->optional()->url(),
        ];
    }
}
