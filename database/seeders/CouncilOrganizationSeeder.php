<?php

namespace Database\Seeders;

use App\Models\CouncilOrganization;
use Illuminate\Database\Seeder;

class CouncilOrganizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CouncilOrganization::query()->firstOrCreate(
            ['edrpou' => 'TEST0001'],
            [
                'name' => 'Демонстраційна міська рада',
                'katoottg' => 'UA00000000000000000',
            ],
        );
    }
}
