<?php

namespace Database\Factories;

use App\Models\Motion;
use App\Models\VoteEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VoteEvent>
 */
class VoteEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'motion_id' => Motion::factory(),
            'identifier' => 'vote-'.Str::lower(Str::random(10)),
            'result' => 'Прийнято',
            'start_date' => now(),
            'end_date' => now()->addMinute(),
        ];
    }
}
