<?php

namespace Database\Factories;

use App\Models\Convocation;
use App\Models\CouncilOrganization;
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
            'organization_id' => CouncilOrganization::factory(),
            'name' => Str::upper(fake()->unique()->bothify('?? скликання')),
        ];
    }
}
