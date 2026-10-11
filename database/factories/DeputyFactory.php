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
        $lastName = fake('uk_UA')->lastName();
        $firstName = fake('uk_UA')->firstName();

        return [
            'last_name' => $lastName,
            'first_name' => $firstName,
            'middle_name' => 'Батькович',
            'gender' => fake()->randomElement([Gender::Male, Gender::Female]),
            'date_of_birth' => fake()->dateTimeBetween('-80 years', '-25 years')->format('Y-m-d'),
            'image' => null,
            'homepage' => null,
            'biography' => fake()->paragraph(),
        ];
    }
}
