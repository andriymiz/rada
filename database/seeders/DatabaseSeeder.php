<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(CouncilOrganizationSeeder::class);
        $this->call(DatasetGroupSeeder::class);
        $this->call(DatasetSeeder::class);
        $this->call(ConvocationSeeder::class);
    }
}
