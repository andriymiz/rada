<?php

use App\Enums\TerritorialUnitCategory;
use App\Filament\Imports\DeputyImporter;
use App\Filament\Resources\Convocations\Pages\EditConvocation;
use App\Filament\Resources\Convocations\RelationManagers\DeputiesRelationManager;
use App\Models\Convocation;
use App\Models\Deputy;
use App\Models\Person;
use App\Models\PoliticalParty;
use App\Models\TerritorialUnit;
use App\Models\User;
use Filament\Actions\Exports\ContentGenerators\CsvExportContentGenerator;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('rada');
    $this->actingAs(User::factory()->admin()->create());
});

function createDeputyExporterTerritorialUnitHierarchy(): void
{
    $region = TerritorialUnit::query()->create([
        'system_number' => 13043,
        'katottg_code' => 'UA01000000000013043',
        'category' => TerritorialUnitCategory::FirstLevel,
        'name' => 'Тестова область',
    ]);
    $district = TerritorialUnit::query()->create([
        'system_number' => 22387,
        'parent_number' => $region->getKey(),
        'katottg_code' => 'UA01020000000022387',
        'category' => TerritorialUnitCategory::SecondLevel,
        'name' => 'Тестовий район',
    ]);
    $community = TerritorialUnit::query()->create([
        'system_number' => 48857,
        'parent_number' => $district->getKey(),
        'katottg_code' => 'UA01020010000048857',
        'category' => TerritorialUnitCategory::ThirdLevel,
        'name' => 'Тестова громада',
    ]);
    TerritorialUnit::query()->create([
        'system_number' => 75540,
        'parent_number' => $community->getKey(),
        'katottg_code' => 'UA01020010010075540',
        'category' => TerritorialUnitCategory::Village,
        'name' => 'Тестове село',
    ]);
}

it('exports contact details only for deputies in the selected convocation', function (): void {
    config(['queue.default' => 'sync']);
    Storage::fake('local');
    createDeputyExporterTerritorialUnitHierarchy();
    $convocation = Convocation::factory()->create();
    $otherConvocation = Convocation::factory()->create();
    $person = Person::factory()->create(['last_name' => 'Коваль', 'first_name' => 'Олена']);
    $party = PoliticalParty::factory()->create();
    Deputy::factory()->for($person)->for($convocation, 'convocation')->for($party, 'politicalParty')->create([
        'number' => 1,
        'territorial_unit_id' => 75540,
        'email' => 'deputy@example.gov.ua',
        'phone' => '+12025550123',
        'social_accounts' => ['https://facebook.com/koval', 'https://t.me/koval'],
        'opening_hours' => 'Пн 09:00-12:00',
        'availability_restriction' => 'За записом',
    ]);
    Deputy::factory()->for($otherConvocation, 'convocation')->create();

    livewire(DeputiesRelationManager::class, [
        'ownerRecord' => $convocation,
        'pageClass' => EditConvocation::class,
    ])
        ->callAction(TestAction::make('export')->table())
        ->assertNotified();

    $export = Export::query()->sole();
    expect($export->total_rows)->toBe(1)
        ->and($export->successful_rows)->toBe(1)
        ->and($export->completed_at)->not->toBeNull();

    $contents = implode('', iterator_to_array(app(CsvExportContentGenerator::class)($export)));
    $csv = Reader::fromString($contents);
    $csv->setHeaderOffset(0);
    $rows = array_values(iterator_to_array($csv->getRecords()));

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['familyName'])->toBe('Коваль')
        ->and($rows[0]['email'])->toBe('deputy@example.gov.ua')
        ->and($rows[0]['telephone'])->toBe('+12025550123')
        ->and($rows[0]['CATUTTC'])->toBe('UA01020010010075540')
        ->and($rows[0]['addressAdminUnitL1'])->toBe('Тестова область')
        ->and($rows[0]['addressAdminUnitL2'])->toBe('Тестовий район')
        ->and($rows[0]['addressAdminUnitL3'])->toBe('Тестова громада')
        ->and($rows[0]['addressAdminUnitL4'])->toBe('Тестове село')
        ->and($rows[0]['account'])->toBe('https://facebook.com/koval, https://t.me/koval')
        ->and($rows[0]['openingHours'])->toBe('Пн 09:00-12:00')
        ->and($rows[0]['availabilityRestriction'])->toBe('За записом');

    $importConvocation = Convocation::factory()->create();
    DeputyImporter::test(
        columnMap: [
            'number' => 'id',
            'last_name' => 'familyName',
            'first_name' => 'name',
            'middle_name' => 'additionalName',
            'gender' => 'gender',
            'date_of_birth' => 'birthDate',
            'biography' => 'biography',
            'partyName' => 'partyName',
            'email' => 'email',
            'phone' => 'telephone',
            'account' => 'account',
            'openingHours' => 'openingHours',
            'availabilityRestriction' => 'availabilityRestriction',
            'territorialUnit' => 'CATUTTC',
        ],
        options: ['convocation' => $importConvocation],
    )->import($rows[0])->assertImported();

    $imported = $importConvocation->deputies()->sole();
    expect($imported->email)->toBe('deputy@example.gov.ua')
        ->and($imported->phone)->toBe('+12025550123')
        ->and($imported->territorial_unit_id)->toBe(75540)
        ->and($imported->social_accounts)->toBe(['https://facebook.com/koval', 'https://t.me/koval'])
        ->and($imported->opening_hours)->toBe('Пн 09:00-12:00')
        ->and($imported->availability_restriction)->toBe('За записом');
});
