<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Organization;
use App\Models\OrganizationUnit;
use App\Models\Person;
use Illuminate\Database\Seeder;

class OrganizationUnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(OrganizationSeeder::class);
        $organization = Organization::query()->where('edrpou', 'TEST0001')->firstOrFail();
        $person = Person::query()->firstOrCreate([
            'last_name' => 'Демонстраційний',
            'first_name' => 'Керівник',
            'middle_name' => null,
        ]);
        $head = Employee::query()->firstOrCreate([
            'organization_id' => $organization->getKey(),
            'person_id' => $person->getKey(),
        ], ['position' => 'Начальник відділу']);

        OrganizationUnit::query()->firstOrCreate(['uid' => 'demo-unit-1'], [
            'organization_id' => $organization->getKey(),
            'head_id' => $head->getKey(),
            'full_name' => 'Демонстраційний відділ',
            'short_name' => 'Відділ',
            'description' => 'Приклад структурного підрозділу.',
            'contact_point' => 'Приймальня',
            'email' => 'office@example.com',
            'phone' => '+380501234567',
        ]);
    }
}
