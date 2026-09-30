<?php

namespace Database\Factories;

use App\Models\Deputy;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeputyFactory extends Factory
{
    protected $model = Deputy::class;

    public function definition(): array
    {
        return [
            'external_id' => null,
            'name' => 'Test deputy '.$this->faker->unique()->numberBetween(1, 999999),
            'is_active' => true,
        ];
    }
}
