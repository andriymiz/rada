<?php

namespace Database\Seeders;

use App\Models\Convocation;
use App\Models\CouncilOrganization;
use Illuminate\Database\Seeder;

class ConvocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(CouncilOrganizationSeeder::class);

        $organization = CouncilOrganization::query()
            ->where('edrpou', 'TEST0001')
            ->firstOrFail();

        foreach ([
            'Перше скликання',
            'Друге скликання',
            'Третє скликання',
            'Четверте скликання',
            'П’яте скликання',
            'Шосте скликання',
            'Сьоме скликання',
        ] as $label) {
            Convocation::query()->firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'label' => $label,
                ],
            );
        }
    }
}
