<?php

namespace Database\Seeders;

use App\Models\CouncilSession;
use App\Models\Department;
use App\Models\Deputy;
use App\Models\Question;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Department::firstOrCreate(
            ['slug' => 'demo-department'],
            ['name' => 'Демонстраційний підрозділ'],
        );

        foreach (['Демонстраційний депутат 1', 'Демонстраційний депутат 2'] as $name) {
            Deputy::firstOrCreate(['name' => $name], ['is_active' => true]);
        }

        $session = CouncilSession::firstOrCreate(
            ['session_number' => 'demo-01'],
            [
                'title' => 'Демонстраційна сесія',
                'held_at' => null,
                'status' => 'scheduled',
            ],
        );

        Question::firstOrCreate(
            ['session_id' => $session->id, 'question_number' => 'demo-01'],
            ['title' => 'Демонстраційне питання (без результатів голосування)'],
        );
    }
}
