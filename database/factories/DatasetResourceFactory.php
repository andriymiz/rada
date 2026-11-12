<?php

namespace Database\Factories;

use App\Enums\DatasetResourceType;
use App\Models\Dataset;
use App\Models\DatasetResource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DatasetResource>
 */
class DatasetResourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dataset_id' => Dataset::factory(),
            'external_id' => fake()->uuid(),
            'type' => DatasetResourceType::Register,
        ];
    }
}
