<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Models\Deputy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deputy>
 */
class DeputyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $familyName = fake('uk_UA')->lastName();
        $firstName = fake('uk_UA')->firstName();

        return [
            'family_name' => $familyName,
            'first_name' => $firstName,
            'additional_name' => 'Батькович',
            'gender' => fake()->randomElement([Gender::Male, Gender::Female]),
            'birth_date' => fake()->dateTimeBetween('-80 years', '-25 years')->format('Y-m-d'),
            'image' => null,
            'homepage' => null,
            'biography' => fake()->paragraph(),
        ];
    }
}
