<?php

use App\Enums\TerritorialUnitCategory;
use App\Filament\Imports\TerritorialUnitImporter;
use App\Filament\Resources\TerritorialUnits\Pages\ListTerritorialUnits;
use App\Filament\Resources\TerritorialUnits\TerritorialUnitResource;
use App\Models\TerritorialUnit;
use App\Models\User;
use Filament\Actions\ImportAction;
use Filament\Actions\Imports\Models\Import;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('rada');
    $this->actingAs(User::factory()->admin()->create());
});

it('imports each object with its own code and immediate parent', function (): void {
    $rows = [
        territorialUnitImportRow([
            'Категорія об’єкта' => 'O',
            'Назва об’єкта' => 'Автономна Республіка Крим',
        ]),
        territorialUnitImportRow([
            'Другий рівень' => 'UA01020000000022387',
            'Категорія об’єкта' => 'P',
            'Назва об’єкта' => 'Бахчисарайський',
        ]),
        territorialUnitImportRow([
            'Другий рівень' => 'UA01020000000022387',
            'Третій рівень' => 'UA01020010000048857',
            'Категорія об’єкта' => 'H',
            'Назва об’єкта' => 'Андріївська',
        ]),
        territorialUnitImportRow([
            'Другий рівень' => 'UA01020000000022387',
            'Третій рівень' => 'UA01020010000048857',
            'Четвертий рівень' => 'UA01020010020030666',
            'Категорія об’єкта' => 'C',
            'Назва об’єкта' => 'Андріївка',
        ]),
    ];

    foreach ($rows as $row) {
        TerritorialUnitImporter::test(columnMap: territorialUnitImportMap())
            ->import($row)
            ->assertImported();
    }

    $unit = TerritorialUnit::query()->findOrFail(30666);

    expect($unit->system_number)->toBe(30666)
        ->and($unit->parent_number)->toBe(48857)
        ->and($unit->katottg_code)->toBe('UA01020010020030666')
        ->and($unit->category)->toBe(TerritorialUnitCategory::Village)
        ->and($unit->parent()->firstOrFail()->system_number)->toBe(48857)
        ->and($unit->parent()->firstOrFail()->parent()->firstOrFail()->system_number)->toBe(22387);

    TerritorialUnitImporter::test(columnMap: territorialUnitImportMap())
        ->import(territorialUnitImportRow([
            'Другий рівень' => 'UA01020000000022387',
            'Третій рівень' => 'UA01020010000048857',
            'Четвертий рівень' => 'UA01020010020030666',
            'Категорія об’єкта' => 'C',
            'Назва об’єкта' => 'Оновлена Андріївка',
        ]))
        ->assertImported();

    expect(TerritorialUnit::query()->count())->toBe(4)
        ->and(TerritorialUnit::query()->findOrFail(30666)->name)->toBe('Оновлена Андріївка');
});

it('selects the official code column based on the object category', function (): void {
    $categories = [
        'O' => 1,
        'K' => 1,
        'P' => 2,
        'H' => 3,
        'M' => 4,
        'X' => 4,
        'C' => 4,
        'B' => 5,
    ];

    $caseNumber = 0;

    foreach ($categories as $category => $level) {
        $caseNumber++;
        $codes = [];

        for ($levelNumber = 1; $levelNumber <= 5; $levelNumber++) {
            $codes[] = sprintf('UA%011d%06d', $caseNumber, 300000 + ($caseNumber * 10) + $levelNumber);
        }

        $row = array_combine(array_slice(array_values(territorialUnitImportMap()), 0, 5), $codes);
        $row['Категорія об’єкта'] = $category;
        $row['Назва об’єкта'] = "Об’єкт {$category}";

        TerritorialUnitImporter::test(columnMap: territorialUnitImportMap())
            ->import($row)
            ->assertImported();

        $unit = TerritorialUnit::query()
            ->where('katottg_code', $codes[$level - 1])
            ->sole();

        expect($unit->system_number)->toBe(300000 + ($caseNumber * 10) + $level)
            ->and($unit->parent_number)->toBe($level > 1 ? 300000 + ($caseNumber * 10) + $level - 1 : null);
    }

    expect(TerritorialUnit::query()->count())->toBe(count($categories));
});

it('rejects categories outside the catalog classification', function (): void {
    TerritorialUnitImporter::test(columnMap: territorialUnitImportMap())
        ->import(territorialUnitImportRow([
            'Категорія об’єкта' => 'Z',
        ]))
        ->assertHasErrors(['category']);

    expect(TerritorialUnit::query()->count())->toBe(0);
});

it('dispatches the catalog CSV through Filament importer with semicolon columns mapped', function (): void {
    $imports = ImportAction::fake();
    $csvPath = createTerritorialUnitTestCsv([
        territorialUnitImportRow(['Категорія об’єкта' => 'O']),
    ]);

    try {
        $csvContents = file_get_contents($csvPath);

        if (! is_string($csvContents)) {
            throw new RuntimeException('Unable to read the test CSV.');
        }

        livewire(ListTerritorialUnits::class)
            ->mountAction('import')
            ->fillForm([
                'file' => UploadedFile::fake()->createWithContent('territories.csv', $csvContents),
            ])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $imports->assertDispatched(
            TerritorialUnitImporter::class,
            static fn (Import $import, array $columnMap, array $options): bool => $columnMap === territorialUnitImportMap(),
        );
    } finally {
        unlink($csvPath);
    }
});

it('rejects non-CSV uploads in the Filament importer', function (): void {
    $imports = ImportAction::fake();
    $csvPath = createTerritorialUnitTestCsv([
        territorialUnitImportRow(['Категорія об’єкта' => 'O']),
    ]);

    try {
        $csvContents = file_get_contents($csvPath);

        if (! is_string($csvContents)) {
            throw new RuntimeException('Unable to read the test CSV.');
        }

        livewire(ListTerritorialUnits::class)
            ->mountAction('import')
            ->fillForm([
                'file' => UploadedFile::fake()->createWithContent('territories.xlsx', $csvContents),
            ])
            ->callMountedAction()
            ->assertHasFormErrors(['file']);

        $imports->assertNothingDispatched();
    } finally {
        unlink($csvPath);
    }
});

it('lists territories with import but without create edit or delete actions', function (): void {
    $unit = TerritorialUnit::query()->create([
        'system_number' => 13043,
        'parent_number' => null,
        'katottg_code' => 'UA01000000000013043',
        'category' => TerritorialUnitCategory::FirstLevel,
        'name' => 'Автономна Республіка Крим',
    ]);

    livewire(ListTerritorialUnits::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$unit])
        ->assertActionExists('import')
        ->assertActionDoesNotExist('create')
        ->assertTableActionDoesNotExist('edit', record: $unit)
        ->assertTableActionDoesNotExist('delete', record: $unit)
        ->assertTableColumnStateSet('category', TerritorialUnitCategory::FirstLevel, $unit)
        ->assertTableColumnStateSet('katottg_code', 'UA01000000000013043', $unit);
});

it('restricts the territorial catalog to administrators', function (): void {
    $this->actingAs(User::factory()->create());

    expect(TerritorialUnitResource::canAccess())->toBeFalse();
});

/**
 * @return array<string, string>
 */
function territorialUnitImportMap(): array
{
    return [
        '1' => 'Перший рівень',
        '2' => 'Другий рівень',
        '3' => 'Третій рівень',
        '4' => 'Четвертий рівень',
        '5' => 'Додатковий рівень',
        'category' => 'Категорія об’єкта',
        'name' => 'Назва об’єкта',
    ];
}

/**
 * @param  array<string, string|null>  $overrides
 * @return array<string, string|null>
 */
function territorialUnitImportRow(array $overrides = []): array
{
    return array_replace([
        'Перший рівень' => 'UA01000000000013043',
        'Другий рівень' => null,
        'Третій рівень' => null,
        'Четвертий рівень' => null,
        'Додатковий рівень' => null,
        'Категорія об’єкта' => 'O',
        'Назва об’єкта' => 'Автономна Республіка Крим',
    ], $overrides);
}

/**
 * @param  array<int, array<string, string|null>>  $rows
 */
function createTerritorialUnitTestCsv(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'territorial-units-');

    if ($path === false) {
        throw new RuntimeException('Unable to create a temporary CSV.');
    }

    $file = fopen($path, 'wb');

    if ($file === false) {
        throw new RuntimeException('Unable to open the temporary CSV.');
    }

    fwrite($file, "\xEF\xBB\xBF");
    fputcsv($file, array_values(territorialUnitImportMap()), ';', '"', '');

    foreach ($rows as $row) {
        fputcsv($file, array_values($row), ';', '"', '');
    }

    fclose($file);

    return $path;
}
