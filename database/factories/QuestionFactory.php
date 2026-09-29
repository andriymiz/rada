<?php

namespace Database\Factories;

use App\Models\CouncilSession;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuestionFactory extends Factory
{
    protected $model = Question::class;

    public function definition(): array
    {
        return [
            'session_id' => CouncilSession::factory(),
            'question_number' => (string) $this->faker->unique()->numberBetween(1, 999999),
            'title' => 'Тестове питання',
        ];
    }
}
