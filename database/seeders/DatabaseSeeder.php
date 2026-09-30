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
            ['name' => 'Demonstration department'],
        );

        foreach (['Demonstration deputy 1', 'Demonstration deputy 2'] as $name) {
            Deputy::firstOrCreate(['name' => $name], ['is_active' => true]);
        }

        $session = CouncilSession::firstOrCreate(
            ['session_number' => 'demo-01'],
            [
                'title' => 'Demonstration session',
                'held_at' => null,
                'status' => 'scheduled',
            ],
        );

        Question::firstOrCreate(
            ['session_id' => $session->id, 'question_number' => 'demo-01'],
            ['title' => 'Demonstration question (no voting results)'],
        );
    }
}
