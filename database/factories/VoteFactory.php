<?php

namespace Database\Factories;

use App\Enums\VoteOption;
use App\Models\Deputy;
use App\Models\Vote;
use App\Models\VoteEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vote>
 */
class VoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vote_event_id' => VoteEvent::factory(),
            'deputy_id' => Deputy::factory(),
            'voter_identifier' => null,
            'voter_name' => null,
            'option' => fake()->randomElement(VoteOption::cases()),
        ];
    }

    public function forDeputy(Deputy $deputy): static
    {
        return $this->state([
            'deputy_id' => $deputy->getKey(),
            'voter_name' => $deputy->full_name,
        ]);
    }
}
