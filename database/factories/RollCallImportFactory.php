<?php

namespace Database\Factories;

use App\Enums\RollCallImportStatus;
use App\Models\ParliamentarySession;
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
            'session_id' => ParliamentarySession::factory(),
            'user_id' => User::factory(),
            'file_path' => 'roll-call-imports/example.pdf',
            'original_filename' => 'example.pdf',
            'status' => RollCallImportStatus::Queued,
            'processed_at' => null,
        ];
    }
}
