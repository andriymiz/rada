<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Organization::query()->firstOrCreate(
            ['edrpou' => 'TEST0001'],
            [
                'full_name' => 'Демонстраційна міська рада',
                'short_name' => 'Демонстраційна рада',
            ],
        );
    }
}
