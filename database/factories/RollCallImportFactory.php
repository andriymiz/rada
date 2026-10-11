<?php

namespace Database\Factories;

use App\Enums\RollCallImportStatus;
use App\Models\PlenaryMeeting;
use App\Models\RollCallImport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RollCallImport>
 */
class RollCallImportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plenary_meeting_id' => PlenaryMeeting::factory(),
            'user_id' => User::factory(),
            'file_path' => 'roll-call-imports/example.pdf',
            'original_filename' => 'example.pdf',
            'status' => RollCallImportStatus::Queued,
            'processed_at' => null,
        ];
    }
}
