<?php

namespace Database\Factories;

use App\Models\ParliamentaryConvocation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ParliamentaryConvocation>
 */
class ParliamentaryConvocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => Str::upper(fake()->unique()->bothify('?? скликання')),
        ];
    }
}
