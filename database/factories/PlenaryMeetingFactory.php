<?php

namespace Database\Factories;

use App\Models\CouncilOrganization;
use App\Models\ParliamentarySession;
use App\Models\PlenaryMeeting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlenaryMeeting>
 */
class PlenaryMeetingFactory extends Factory
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
            'parliamentary_session_id' => ParliamentarySession::factory(),
            'date' => fake()->dateTimeBetween('-4 years', 'now')->format('Y-m-d'),
        ];
    }
}
