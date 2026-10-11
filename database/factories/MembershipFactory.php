<?php

namespace Database\Factories;

use App\Models\Convocation;
use App\Models\Deputy;
use App\Models\Membership;
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
            'deputy_id' => Deputy::factory(),
            'convocation_id' => Convocation::factory(),
            'organization_id' => function (array $attributes): int {
                $convocation = Convocation::query()
                    ->whereKey($attributes['convocation_id'])
                    ->firstOrFail();

                return $convocation->organization_id
                    ?? throw new \LogicException('Factory convocations must belong to a council organization.');
            },
            'role' => 'Депутат міської ради',
            'start_date' => fake()->dateTimeBetween('-4 years', 'now')->format('Y-m-d'),
            'end_date' => null,
        ];
    }
}
