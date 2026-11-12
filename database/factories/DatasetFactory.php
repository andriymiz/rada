<?php

namespace Database\Factories;

use App\Enums\DatasetResourceFormat;
use App\Enums\DatasetUpdateFrequency;
use App\Models\CouncilOrganization;
use App\Models\Dataset;
use App\Models\Official;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dataset>
 */
class DatasetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'accrual_periodicity' => DatasetUpdateFrequency::Yearly,
            'keywords' => ['відкриті дані', 'реєстр'],
            'purpose' => fake()->sentence(),
            'distribution_formats' => [DatasetResourceFormat::Csv->value],
            'publisher_id' => CouncilOrganization::factory(),
            'responsible_official_id' => Official::factory(),
        ];
    }
}
