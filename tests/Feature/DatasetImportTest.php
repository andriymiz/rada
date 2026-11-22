<?php

use App\Enums\DatasetRecommendation;
use App\Enums\DatasetStatus;
use App\Enums\DatasetUpdateFrequency;
use App\Filament\Imports\DatasetImporter;
use App\Filament\Resources\Datasets\Pages\ManageDatasets;
use App\Models\Dataset;
use App\Models\Organization;
use App\Models\User;
use Filament\Actions\ImportAction;
use Filament\Actions\Imports\Jobs\ImportCsv;
use Filament\Actions\Imports\Models\Import;
use Filament\Facades\Filament;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('rada');
    $this->actingAs(User::factory()->admin()->create());
    Http::preventStrayRequests();
});

function fakeEmptyDatasetResources(): void
{
    Http::fake([
        rtrim(config('services.open_data.base_url'), '/').'/api/3/action/package_show*' => Http::response([
            'success' => true,
            'result' => ['resources' => []],
        ]),
    ]);
}

it('uses only the importer class name in the completed notification', function (): void {
    $import = new Import;
    $import->importer = DatasetImporter::class;
    $import->total_rows = 1;
    $import->successful_rows = 1;

    expect(DatasetImporter::getCompletedNotificationBody($import))
        ->toBe('DatasetImporter - Успішно імпортовано 1 із 1 запис.');

    $import->total_rows = 3;
    $import->successful_rows = 1;

    expect(DatasetImporter::getCompletedNotificationBody($import))
        ->toBe('DatasetImporter - Успішно імпортовано 1 із 3 записи.');
});

it('imports optional dataset fields when they are present in the register', function (): void {
    fakeEmptyDatasetResources();
    $organization = Organization::factory()->create([
        'edrpou' => '04058410',
        'full_name' => 'ЗБОРІВСЬКА МІСЬКА РАДА',
    ]);

    DatasetImporter::test(columnMap: datasetImporterColumnMap())
        ->import([
            'external_id' => 'c65f219b-4f4e-4e51-8dc6-7a1df3001260',
            'external_title' => 'Офіційна назва',
            'description' => 'Опис',
            'accrual_periodicity' => 'Щороку',
            'keywords' => 'відкриті дані',
            'purpose' => 'Підстава',
            'publisher' => '04058410',
            'contact_point_name' => 'Контактна особа',
            'contact_point_email' => 'contact@example.gov.ua',
            'internalTitle' => 'Внутрішня назва',
            'status' => DatasetStatus::NotPublished->value,
            'statusNote' => 'Не підлягає оприлюдненню',
            'infoOwner' => 'Відділ цифрового розвитку',
        ])
        ->assertImported();

    $dataset = Dataset::query()->sole();

    expect($dataset->title)->toBe('Внутрішня назва')
        ->and($dataset->status)->toBe(DatasetStatus::NotPublished)
        ->and($dataset->status_note)->toBe('Не підлягає оприлюдненню')
        ->and($dataset->info_owner)->toBe('Відділ цифрового розвитку');
});

it('imports a register row when optional dataset columns are absent', function (): void {
    fakeEmptyDatasetResources();
    $organization = Organization::factory()->create([
        'edrpou' => '04058410',
        'full_name' => 'ЗБОРІВСЬКА МІСЬКА РАДА',
    ]);

    DatasetImporter::test(columnMap: datasetImporterColumnMap(includeOptional: false))
        ->import([
            'external_id' => 'c65f219b-4f4e-4e51-8dc6-7a1df3001261',
            'external_title' => 'Набір без додаткових колонок',
            'description' => 'Опис',
            'accrual_periodicity' => 'Щороку',
            'keywords' => 'відкриті дані',
            'purpose' => 'Підстава',
            'publisher' => '04058410',
            'contact_point_name' => 'Контактна особа',
            'contact_point_email' => 'contact@example.gov.ua',
        ])
        ->assertImported();

    $dataset = Dataset::query()->sole();

    expect($dataset->status)->toBe(DatasetStatus::Published)
        ->and($dataset->status_note)->toBeNull()
        ->and($dataset->info_owner)->toBeNull();
});

it('dispatches imports when the CSV omits optional dataset columns', function (): void {
    $imports = ImportAction::fake();
    $csv = implode("\n", [
        'external_id,title,description,accrual_periodicity,keywords,purpose,publisher_id,publisher,contact_point_name,contact_point_email',
        'c65f219b-4f4e-4e51-8dc6-7a1df3001262,Набір,Опис,Щороку,дані,Підстава,04058410,Рада,Контактна особа,contact@example.gov.ua',
    ]);

    livewire(ManageDatasets::class)
        ->mountAction('import')
        ->fillForm([
            'file' => UploadedFile::fake()->createWithContent('datasets.csv', $csv),
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $imports->assertDispatched(DatasetImporter::class);
});

it('records a failed portal response and continues importing the next row', function (): void {
    Http::fake([
        rtrim(config('services.open_data.base_url'), '/').'/api/3/action/package_show*' => Http::sequence()
            ->push(['result' => ['resources' => [['id' => 'invalid']]]], 400)
            ->push(['result' => ['resources' => []]]),
    ]);
    Exceptions::fake([RequestException::class]);
    $organization = Organization::factory()->create(['edrpou' => '04058410']);
    $import = Import::query()->create([
        'file_name' => 'datasets.csv',
        'file_path' => 'datasets.csv',
        'importer' => DatasetImporter::class,
        'total_rows' => 2,
        'user_id' => auth()->id(),
    ]);
    $row = [
        'external_id' => 'c65f219b-4f4e-4e51-8dc6-7a1df3001252',
        'external_title' => 'Перший набір',
        'description' => 'Опис',
        'accrual_periodicity' => 'Щороку',
        'keywords' => 'реєстр',
        'purpose' => 'Мета',
        'publisher' => '04058410',
        'contact_point_name' => 'Контактна особа',
        'contact_point_email' => 'contact@example.gov.ua',
    ];
    $nextRow = array_replace($row, [
        'external_id' => 'c65f219b-4f4e-4e51-8dc6-7a1df3001253',
        'external_title' => 'Другий набір',
    ]);
    $columnMap = array_combine(array_keys($row), array_keys($row));

    (new ImportCsv($import, [$row, $nextRow], $columnMap))->handle();

    expect($import->refresh()->processed_rows)->toBe(2)
        ->and($import->successful_rows)->toBe(2)
        ->and($import->failedRows()->count())->toBe(0)
        ->and(Dataset::query()->where('external_id', $row['external_id'])->sole()->status)->toBe(DatasetStatus::Published)
        ->and(Dataset::query()->where('external_id', $nextRow['external_id'])->sole()->status)->toBe(DatasetStatus::Published);
});

it('rejects a dataset row when its publisher is unknown', function (): void {
    DatasetImporter::test()
        ->import([
            'external_id' => 'c65f219b-4f4e-4e51-8dc6-7a1df3001253',
            'external_title' => 'Невідомий набір',
            'description' => 'Опис',
            'accrual_periodicity' => 'Щороку',
            'keywords' => 'відкриті дані',
            'purpose' => 'Підстава',
            'source_url' => 'https://data.gov.ua/dataset/unknown-dataset',
            'distribution_formats' => 'CSV',
            'publisher' => '04058410',
            'contact_point_name' => 'Невідома Особа',
            'contact_point_email' => 'contact@example.gov.ua',
        ])
        ->assertHasErrors(['publisher']);

    expect(Dataset::query()->exists())->toBeFalse();
});

it('preserves a UUID supplied as the register identifier', function (): void {
    fakeEmptyDatasetResources();
    $organization = Organization::factory()->create([
        'edrpou' => '04058410',
        'full_name' => 'ЗБОРІВСЬКА МІСЬКА РАДА',
    ]);
    $externalId = 'c65f219b-4f4e-4e51-8dc6-7a1df3001252';

    DatasetImporter::test()
        ->import([
            'external_id' => $externalId,
            'external_title' => 'Набір із UUID',
            'description' => 'Опис',
            'accrual_periodicity' => 'Щороку',
            'keywords' => 'відкриті дані',
            'purpose' => 'Підстава',
            'source_url' => 'https://data.gov.ua/dataset/'.$externalId,
            'distribution_formats' => 'CSV',
            'publisher' => '04058410',
            'contact_point_name' => 'Контактна особа',
            'contact_point_email' => 'contact@example.gov.ua',
        ])
        ->assertImported();

    expect(Dataset::query()->sole()->external_id)->toBe($externalId)
        ->and(Dataset::query()->sole()->publisher_id)->toBe($organization->getKey());
});

it('matches imported datasets by recommendation when the external identifier is new', function (): void {
    fakeEmptyDatasetResources();
    $organization = Organization::factory()->create(['edrpou' => '04058410']);
    $dataset = Dataset::factory()
        ->for($organization, 'publisher')
        ->create([
            'title' => 'Коротка назва',
            'rec_code' => DatasetRecommendation::OrganizationsDirectory,
        ]);
    $dataset->meta()->create([
        'description' => 'Старий опис',
        'accrual_periodicity' => DatasetUpdateFrequency::Yearly->value,
        'keywords' => ['реєстр'],
        'purpose' => 'Стара підстава',
        'contact_point_name' => 'Контактна особа',
        'contact_point_email' => 'contact@example.gov.ua',
    ]);

    DatasetImporter::test()
        ->import([
            'external_id' => 'c65f219b-4f4e-4e51-8dc6-7a1df3001257',
            'external_title' => DatasetRecommendation::OrganizationsDirectory->getLabel(),
            'description' => 'Оновлений опис',
            'accrual_periodicity' => 'Щороку',
            'keywords' => 'реєстр',
            'purpose' => 'Мета',
            'distribution_formats' => 'CSV',
            'publisher' => '04058410',
            'contact_point_name' => 'Контактна особа',
            'contact_point_email' => 'contact@example.gov.ua',
        ])
        ->assertImported();

    expect(Dataset::query()->count())->toBe(1)
        ->and($dataset->refresh()->title)->toBe('Коротка назва')
        ->and($dataset->external_id)->toBe('c65f219b-4f4e-4e51-8dc6-7a1df3001257')
        ->and($dataset->refresh()->meta->description)->toBe('Оновлений опис');
});

it('fills an empty title on an existing dataset', function (): void {
    fakeEmptyDatasetResources();
    $organization = Organization::factory()->create(['edrpou' => '04058410']);
    $dataset = Dataset::factory()
        ->for($organization, 'publisher')
        ->create([
            'title' => '',
            'rec_code' => DatasetRecommendation::OrganizationsDirectory,
        ]);
    $title = DatasetRecommendation::OrganizationsDirectory->getLabel();

    DatasetImporter::test()
        ->import([
            'external_id' => 'c65f219b-4f4e-4e51-8dc6-7a1df3001259',
            'external_title' => $title,
            'description' => 'Опис',
            'accrual_periodicity' => 'Щороку',
            'keywords' => 'реєстр',
            'purpose' => 'Мета',
            'distribution_formats' => 'CSV',
            'publisher' => '04058410',
            'contact_point_name' => 'Контактна особа',
            'contact_point_email' => 'contact@example.gov.ua',
        ])
        ->assertImported();

    expect($dataset->refresh()->title)->toBe($title);
});

it('falls back to dataset title when no recommendation matches the imported title', function (): void {
    fakeEmptyDatasetResources();
    $organization = Organization::factory()->create(['edrpou' => '04058410']);
    $dataset = Dataset::factory()
        ->for($organization, 'publisher')
        ->create(['title' => 'Набір за назвою']);
    $dataset->meta()->create([
        'description' => 'Старий опис',
        'accrual_periodicity' => DatasetUpdateFrequency::Yearly->value,
        'keywords' => ['реєстр'],
        'purpose' => 'Стара підстава',
        'contact_point_name' => 'Контактна особа',
        'contact_point_email' => 'contact@example.gov.ua',
    ]);

    DatasetImporter::test()
        ->import([
            'external_id' => 'c65f219b-4f4e-4e51-8dc6-7a1df3001258',
            'external_title' => 'Набір за назвою',
            'description' => 'Оновлений опис',
            'accrual_periodicity' => 'Щороку',
            'keywords' => 'реєстр',
            'purpose' => 'Мета',
            'distribution_formats' => 'CSV',
            'publisher' => '04058410',
            'contact_point_name' => 'Контактна особа',
            'contact_point_email' => 'contact@example.gov.ua',
        ])
        ->assertImported();

    expect(Dataset::query()->count())->toBe(1)
        ->and($dataset->refresh()->title)->toBe('Набір за назвою')
        ->and($dataset->refresh()->external_id)->toBe('c65f219b-4f4e-4e51-8dc6-7a1df3001258')
        ->and($dataset->refresh()->meta->description)->toBe('Оновлений опис');
});

it('imports a dataset row without an external identifier', function (): void {
    $organization = Organization::factory()->create(['edrpou' => '04058410']);
    $columnMap = datasetImporterColumnMap();
    unset($columnMap['external_id']);

    DatasetImporter::test(columnMap: $columnMap)
        ->import([
            'external_title' => 'Набір без зовнішнього ID',
            'description' => '',
            'accrual_periodicity' => '',
            'keywords' => '',
            'purpose' => '',
            'distribution_formats' => 'CSV',
            'publisher' => '04058410',
            'contact_point_name' => '',
            'contact_point_email' => '',
        ])
        ->assertImported();

    $dataset = Dataset::query()->sole();

    expect($dataset->external_id)->toBeNull()
        ->and($dataset->meta()->exists())->toBeFalse();
});

it('does not update existing metadata when no external identifier is supplied', function (): void {
    $organization = Organization::factory()->create(['edrpou' => '04058410']);
    $dataset = Dataset::factory()->for($organization, 'publisher')->create([
        'external_id' => null,
        'title' => 'Набір без UID',
    ]);
    $dataset->meta()->create([
        'description' => 'Старий опис',
        'accrual_periodicity' => DatasetUpdateFrequency::Yearly->value,
        'keywords' => ['старі дані'],
        'purpose' => 'Стара підстава',
        'contact_point_name' => 'Стара контактна особа',
        'contact_point_email' => 'old@example.gov.ua',
    ]);

    DatasetImporter::test()
        ->import([
            'external_id' => 'null',
            'external_title' => 'Набір без UID',
            'description' => 'Новий опис',
            'accrual_periodicity' => 'Щомісяця',
            'keywords' => 'нові дані',
            'purpose' => 'Нова підстава',
            'publisher' => '04058410',
            'contact_point_name' => 'Нова контактна особа',
            'contact_point_email' => 'new@example.gov.ua',
        ])
        ->assertImported();

    expect($dataset->refresh()->meta->description)->toBe('Старий опис')
        ->and($dataset->meta->accrual_periodicity)->toBe(DatasetUpdateFrequency::Yearly)
        ->and($dataset->meta->keywords)->toBe(['старі дані'])
        ->and($dataset->meta->purpose)->toBe('Стара підстава')
        ->and($dataset->meta->contact_point_name)->toBe('Стара контактна особа')
        ->and($dataset->meta->contact_point_email)->toBe('old@example.gov.ua');
});

it('requires mapped metadata values when an external identifier is supplied', function (): void {
    $organization = Organization::factory()->create(['edrpou' => '04058410']);

    DatasetImporter::test()
        ->import([
            'external_id' => 'c65f219b-4f4e-4e51-8dc6-7a1df3001264',
            'external_title' => 'Набір з UID без метаданих',
            'description' => '',
            'accrual_periodicity' => '',
            'keywords' => '',
            'purpose' => '',
            'publisher_id' => '04058410',
            'publisher' => $organization->full_name,
            'contact_point_name' => '',
            'contact_point_email' => '',
        ])
        ->assertHasErrors([
            'description' => 'required_with',
            'accrual_periodicity' => 'required_with',
            'keywords' => 'required_with',
            'purpose' => 'required_with',
            'contact_point_name' => 'required_with',
            'contact_point_email' => 'required_with',
        ]);

    expect(Dataset::query()->exists())->toBeFalse();
});

it('imports contact metadata without resolving an official', function (): void {
    fakeEmptyDatasetResources();
    Organization::factory()->create([
        'edrpou' => '04058410',
        'full_name' => 'ЗБОРІВСЬКА МІСЬКА РАДА',
    ]);

    DatasetImporter::test()
        ->import([
            'external_id' => 'c65f219b-4f4e-4e51-8dc6-7a1df3001254',
            'external_title' => 'Набір без відповідальної особи',
            'description' => 'Опис',
            'accrual_periodicity' => 'Щороку',
            'keywords' => 'відкриті дані',
            'purpose' => 'Підстава',
            'source_url' => 'https://data.gov.ua/dataset/unknown-contact-dataset',
            'distribution_formats' => 'CSV',
            'publisher' => '04058410',
            'contact_point_name' => 'Симоненко Олена Петрівна',
            'contact_point_email' => 'contact@example.gov.ua',
        ])
        ->assertImported();

    expect(Dataset::query()->sole()->meta->contact_point_name)
        ->toBe('Симоненко Олена Петрівна');
});

it('rejects dataset update frequencies that are not labels', function (string $frequency): void {
    $organization = Organization::factory()->create([
        'edrpou' => '04058410',
        'full_name' => 'ЗБОРІВСЬКА МІСЬКА РАДА',
    ]);
    DatasetImporter::test()
        ->import([
            'external_id' => 'c65f219b-4f4e-4e51-8dc6-7a1df3001255',
            'external_title' => 'Набір із невідомою частотою',
            'description' => 'Опис',
            'accrual_periodicity' => $frequency,
            'keywords' => 'відкриті дані',
            'purpose' => 'Підстава',
            'source_url' => 'https://data.gov.ua/dataset/invalid-periodicity',
            'distribution_formats' => 'CSV',
            'publisher_id' => '04058410',
            'publisher' => 'ЗБОРІВСЬКА МІСЬКА РАДА',
            'contact_point_name' => 'Контактна особа',
            'contact_point_email' => 'contact@example.gov.ua',
        ])
        ->assertHasErrors(['accrual_periodicity' => 'in']);

    expect(Dataset::query()->exists())->toBeFalse();
})->with([
    'unknown label' => 'За потреби',
    'enum code' => 'yearly',
]);

it('ignores resource formats during dataset import', function (): void {
    fakeEmptyDatasetResources();
    $organization = Organization::factory()->create([
        'edrpou' => '04058410',
        'full_name' => 'ЗБОРІВСЬКА МІСЬКА РАДА',
    ]);
    DatasetImporter::test()
        ->import([
            'external_id' => 'c65f219b-4f4e-4e51-8dc6-7a1df3001256',
            'external_title' => 'Набір із невідомим форматом',
            'description' => 'Опис',
            'accrual_periodicity' => 'Щороку',
            'keywords' => 'відкриті дані',
            'purpose' => 'Підстава',
            'source_url' => 'https://data.gov.ua/dataset/invalid-format',
            'distribution_formats' => 'CSV,UNSUPPORTED',
            'publisher' => '04058410',
            'contact_point_name' => 'Контактна особа',
            'contact_point_email' => 'contact@example.gov.ua',
        ])
        ->assertImported();

    expect(Dataset::query()->exists())->toBeTrue()
        ->and(Dataset::query()->sole()->resources()->exists())->toBeFalse();
});

it('renders the dataset csv import action', function (): void {
    livewire(ManageDatasets::class)
        ->assertActionExists('import');
});

/**
 * @return array<string, string>
 */
function datasetImporterColumnMap(bool $includeOptional = true): array
{
    $columnMap = [
        'external_id' => 'external_id',
        'external_title' => 'external_title',
        'description' => 'description',
        'accrual_periodicity' => 'accrual_periodicity',
        'keywords' => 'keywords',
        'purpose' => 'purpose',
        'publisher_id' => 'publisher_id',
        'publisher' => 'publisher',
        'contact_point_name' => 'contact_point_name',
        'contact_point_email' => 'contact_point_email',
    ];

    if ($includeOptional) {
        $columnMap = [
            ...$columnMap,
            'internal_title' => 'internalTitle',
            'status' => 'status',
            'status_note' => 'statusNote',
            'info_owner' => 'infoOwner',
        ];
    }

    return $columnMap;
}
