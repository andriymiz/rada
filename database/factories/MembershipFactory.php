<?php

namespace Database\Factories;

use App\Models\CouncilOrganization;
use App\Models\Membership;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'organization_id' => CouncilOrganization::factory(),
            'role' => 'Депутат міської ради',
            'start_date' => fake()->dateTimeBetween('-4 years', 'now')->format('Y-m-d'),
            'end_date' => null,
        ];
    }
}
