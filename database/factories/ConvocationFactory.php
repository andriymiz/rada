<?php

namespace Database\Factories;

use App\Models\Convocation;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Convocation>
 */
class ConvocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'label' => Str::upper(fake()->unique()->bothify('?? скликання')),
        ];
    }
}
