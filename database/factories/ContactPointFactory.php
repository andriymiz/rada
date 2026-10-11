<?php

namespace Database\Factories;

use App\Enums\ContactPointType;
use App\Models\ContactPoint;
use App\Models\Deputy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactPoint>
 */
class ContactPointFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contactable_type' => (new Deputy)->getMorphClass(),
            'contactable_id' => Deputy::factory(),
            'type' => ContactPointType::Email,
            'value' => fake()->safeEmail(),
            'is_public' => false,
            'note' => null,
        ];
    }
}
