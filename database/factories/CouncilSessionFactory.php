<?php

namespace Database\Factories;

use App\Models\CouncilSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class CouncilSessionFactory extends Factory
{
    protected $model = CouncilSession::class;

    public function definition(): array
    {
        $number = $this->faker->unique()->numberBetween(1, 999999);

        return [
            'title' => 'Тестова сесія '.$number,
            'session_number' => (string) $number,
            'held_at' => $this->faker->date(),
            'status' => 'scheduled',
        ];
    }
}
