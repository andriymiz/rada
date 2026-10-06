<?php

namespace Database\Factories;

use App\Enums\VoteOption;
use App\Models\Person;
use App\Models\Vote;
use App\Models\VoteEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vote>
 */
class VoteFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterMaking(function (Vote $vote): void {
            $person = $vote->person;

            $vote->voter_identifier ??= $person->voting_identifier;
            $vote->voter_name ??= $person->name;
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vote_event_id' => VoteEvent::factory(),
            'person_id' => Person::factory(),
            'voter_identifier' => null,
            'voter_name' => null,
            'option' => fake()->randomElement(VoteOption::cases()),
        ];
    }

    public function forPerson(Person $person): static
    {
        return $this->state([
            'person_id' => $person->getKey(),
            'voter_identifier' => $person->voting_identifier,
            'voter_name' => $person->name,
        ]);
    }
}
