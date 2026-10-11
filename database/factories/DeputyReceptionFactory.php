<?php

namespace Database\Factories;

use App\Models\Deputy;
use App\Models\DeputyReception;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeputyReception>
 */
class DeputyReceptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'deputy_id' => Deputy::factory(),
            'katoottg' => 'UA12345678901234567',
            'address_post_code' => '01001',
            'address_admin_unit_l1' => 'Україна',
            'address_admin_unit_l2' => 'Київська область',
            'address_admin_unit_l3' => null,
            'address_admin_unit_l4' => null,
            'address_post_name' => 'Тестове',
            'address_thoroughfare' => 'Тестова',
            'address_locator_designator' => '1',
            'address_locator_building' => null,
            'address_locator_name' => null,
            'opening_hours' => 'Пн 09:00–18:00',
            'availability_restriction' => null,
        ];
    }
}
