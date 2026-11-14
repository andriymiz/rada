<?php

namespace Database\Seeders;

use App\Models\DatasetGroup;
use Illuminate\Database\Seeder;

class DatasetGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DatasetGroup::query()->upsert(
            [
                ['name' => 'Усі розпорядники інформації', 'sort' => 1],
                ['name' => 'Архітектура та містобудування', 'sort' => 2],
                ['name' => 'Бюджет та фінанси', 'sort' => 3],
                ['name' => 'Інфраструктура', 'sort' => 4],
                ['name' => 'Комунальна власність', 'sort' => 5],
                ['name' => 'Місцеві ради', 'sort' => 6],
                ['name' => 'Навколишнє середовище', 'sort' => 7],
                ['name' => 'Освіта', 'sort' => 8],
                ['name' => "Охорона здоров'я", 'sort' => 9],
                ['name' => 'Торгівля', 'sort' => 10],
                ['name' => 'Громадський транспорт', 'sort' => 11],
                ['name' => 'Участь громадськості та інше', 'sort' => 12],
            ],
            uniqueBy: ['name'],
            update: ['updated_at'],
        );
    }
}
