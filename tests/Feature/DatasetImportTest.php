<?php

use App\Enums\DatasetRecommendation;
use App\Enums\DatasetStatus;
use App\Enums\DatasetUpdateFrequency;
use App\Filament\Imports\DatasetImporter;
use App\Filament\Resources\Datasets\Pages\ManageDatasets;
use App\Models\CouncilOrganization;
use App\Models\Dataset;
use App\Models\User;
use Filament\Actions\Imports\Jobs\ImportCsv;
use Filament\Actions\Imports\Models\Import;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
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

it('imports and updates a dataset from a register row', function (): void {
    Http::fake([
        rtrim(config('services.open_data.base_url'), '/').'/api/3/action/package_show*' => Http::sequence()
            ->push(['result' => ['resources' => [
                ['id' => 'resource-1', 'name' => 'Початкова назва', 'format' => 'CSV'],
                ['id' => 'resource-2', 'name' => 'Старий ресурс', 'format' => 'CSV'],
            ]]])
            ->push(['result' => ['resources' => [
                ['id' => 'resource-1', 'name' => 'Оновлена назва', 'format' => 'JSON'],
                ['id' => 'resource-3', 'name' => 'Новий ресурс', 'format' => 'XML'],
            ]]]),
    ]);

    $title = 'Довідник підприємств, установ, організацій, що належать до сфери управління розпорядника інформації, та територіальних органів розпорядника інформації, зокрема їх ідентифікаційних кодів юридичної особи в Єдиному державному реєстрі підприємств і організацій України, офіційних веб-сайтів, адрес електронної пошти, номерів телефонів, адрес місцезнаходження';
    $organization = CouncilOrganization::factory()->create([
        'edrpou' => '04058410',
        'name' => 'ЗБОРІВСЬКА МІСЬКА РАДА',
    ]);
    $externalId = 'c65f219b-4f4e-4e51-8dc6-7a1df3001252';
    $row = [
        'external_id' => $externalId,
        'title' => $title,
        'description' => 'Опис першої версії.',
        'accrual_periodicity' => 'Щороку',
        'keywords' => 'реєстр,набори даних',
        'purpose' => 'Підстава для збору даних.',
        'source_url' => 'https://data.gov.ua/dataset/register-dataset-1',
        'publisher_id' => ' null ',
        'publisher' => 'ЗБОРІВСЬКА МІСЬКА РАДА',
        'contact_point_name' => 'Симоненко Олена Петрівна',
        'contact_point_email' => 'not-an-email',
    ];

    DatasetImporter::test()
        ->import($row)
        ->assertImported();

    $dataset = Dataset::query()->sole();
    $externalId = $dataset->external_id;

    expect($dataset->title)->toBe($title)
        ->and($dataset->status)->toBe(DatasetStatus::Published)
        ->and($dataset->resources()->count())->toBe(2)
        ->and($dataset->resources()->where('external_id', 'resource-1')->sole()->name)->toBe('Початкова назва')
        ->and($dataset->meta->toArray())->toMatchArray([
            'description' => 'Опис першої версії.',
            'accrual_periodicity' => DatasetUpdateFrequency::Yearly->value,
            'keywords' => ['реєстр', 'набори даних'],
            'purpose' => 'Підстава для збору даних.',
            'contact_point_name' => 'Симоненко Олена Петрівна',
            'contact_point_email' => 'not-an-email',
        ])
        ->and($dataset->publisher->is($organization))->toBeTrue()
        ->and($dataset->meta->contact_point_name)->toBe('Симоненко Олена Петрівна');

    $dataset->update(['status' => DatasetStatus::NotPublished, 'status_note' => 'Особистий коментар']);
    $row['title'] = 'Оновлена офіційна назва.';
    $row['description'] = 'Оновлений опис.';
    $row['publisher_id'] = ' null ';

    DatasetImporter::test()
        ->import($row)
        ->assertImported();

    expect(Dataset::query()->count())->toBe(1)
        ->and($dataset->refresh()->title)->toBe($title)
        ->and($dataset->refresh()->meta->description)->toBe('Оновлений опис.')
        ->and($dataset->external_id)->toBe($externalId)
        ->and($dataset->status)->toBe(DatasetStatus::Published)
        ->and($dataset->status_note)->toBe('Особистий коментар')
        ->and($dataset->resources()->count())->toBe(2)
        ->and($dataset->resources()->where('external_id', 'resource-1')->sole()->name)->toBe('Оновлена назва')
        ->and($dataset->resources()->where('external_id', 'resource-1')->sole()->format)->toBe('JSON')
        ->and($dataset->resources()->where('external_id', 'resource-2')->exists())->toBeFalse()
        ->and($dataset->resources()->where('external_id', 'resource-3')->sole()->name)->toBe('Новий ресурс');

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request['id'] === $externalId);
});

it('records a failed portal response and continues importing the next row', function (): void {
    Http::fake([
        rtrim(config('services.open_data.base_url'), '/').'/api/3/action/package_show*' => Http::sequence()
            ->push(['result' => ['resources' => [['id' => 'invalid']]]], 400)
            ->push(['result' => ['resources' => []]]),
    ]);
    Exceptions::fake([RequestException::class]);
    $organization = CouncilOrganization::factory()->create(['edrpou' => '04058410']);
    $import = Import::query()->create([
        'file_name' => 'datasets.csv',
        'file_path' => 'datasets.csv',
        'importer' => DatasetImporter::class,
        'total_rows' => 2,
        'user_id' => auth()->id(),
    ]);
    $row = [
        'external_id' => 'c65f219b-4f4e-4e51-8dc6-7a1df3001252',
        'title' => 'Перший набір',
        'description' => 'Опис',
        'accrual_periodicity' => 'Щороку',
        'keywords' => 'реєстр',
        'purpose' => 'Мета',
        'publisher_id' => '04058410',
        'publisher' => $organization->name,
        'contact_point_name' => 'Контактна особа',
        'contact_point_email' => 'contact@example.gov.ua',
    ];
    $nextRow = array_replace($row, [
        'external_id' => 'c65f219b-4f4e-4e51-8dc6-7a1df3001253',
        'title' => 'Другий набір',
    ]);
    $columnMap = array_combine(array_keys($row), array_keys($row));

    (new ImportCsv($import, [$row, $nextRow], $columnMap))->handle();

    expect($import->refresh()->processed_rows)->toBe(2)
        ->and($import->successful_rows)->toBe(2)
        ->and($import->failedRows()->count())->toBe(0)
        ->and(Dataset::query()->where('external_id', $row['external_id'])->sole()->status)->toBe(DatasetStatus::Published)
        ->and(Dataset::query()->where('external_id', $nextRow['external_id'])->sole()->status)->toBe(DatasetStatus::Published);
    Http::assertSentCount(2);
});

it('rejects a dataset row when its publisher is unknown', function (): void {
    DatasetImporter::test()
        ->import([
            'external_id' => 'c65f219b-4f4e-4e51-8dc6-7a1df3001253',
            'title' => 'Невідомий набір',
            'description' => 'Опис',
            'accrual_periodicity' => 'Щороку',
            'keywords' => 'відкриті дані',
            'purpose' => 'Підстава',
            'source_url' => 'https://data.gov.ua/dataset/unknown-dataset',
            'distribution_formats' => 'CSV',
            'publisher_id' => '04058410',
            'publisher' => 'Невідома рада',
            'contact_point_name' => 'Невідома Особа',
            'contact_point_email' => 'contact@example.gov.ua',
        ])
        ->assertHasErrors(['publisher']);

    expect(Dataset::query()->exists())->toBeFalse();
});

it('preserves a UUID supplied as the register identifier', function (): void {
    fakeEmptyDatasetResources();
    $organization = CouncilOrganization::factory()->create([
        'edrpou' => '04058410',
        'name' => 'ЗБОРІВСЬКА МІСЬКА РАДА',
    ]);
    $externalId = 'c65f219b-4f4e-4e51-8dc6-7a1df3001252';

    DatasetImporter::test()
        ->import([
            'external_id' => $externalId,
            'title' => 'Набір із UUID',
            'description' => 'Опис',
            'accrual_periodicity' => 'Щороку',
            'keywords' => 'відкриті дані',
            'purpose' => 'Підстава',
            'source_url' => 'https://data.gov.ua/dataset/'.$externalId,
            'distribution_formats' => 'CSV',
            'publisher_id' => '04058410',
            'publisher' => 'ЗБОРІВСЬКА МІСЬКА РАДА',
            'contact_point_name' => 'Контактна особа',
            'contact_point_email' => 'contact@example.gov.ua',
        ])
        ->assertImported();

    expect(Dataset::query()->sole()->external_id)->toBe($externalId)
        ->and(Dataset::query()->sole()->publisher_id)->toBe($organization->getKey());
    Http::assertSentCount(1);
});

it('matches imported datasets by recommendation when the external identifier is new', function (): void {
    fakeEmptyDatasetResources();
    $organization = CouncilOrganization::factory()->create(['edrpou' => '04058410']);
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
            'title' => DatasetRecommendation::OrganizationsDirectory->getLabel(),
            'description' => 'Оновлений опис',
            'accrual_periodicity' => 'Щороку',
            'keywords' => 'реєстр',
            'purpose' => 'Мета',
            'distribution_formats' => 'CSV',
            'publisher_id' => '04058410',
            'publisher' => $organization->name,
            'contact_point_name' => 'Контактна особа',
            'contact_point_email' => 'contact@example.gov.ua',
        ])
        ->assertImported();

    expect(Dataset::query()->count())->toBe(1)
        ->and($dataset->refresh()->title)->toBe('Коротка назва')
        ->and($dataset->external_id)->toBe('c65f219b-4f4e-4e51-8dc6-7a1df3001257')
        ->and($dataset->refresh()->meta->description)->toBe('Оновлений опис');
    Http::assertSentCount(1);
});

it('fills an empty title on an existing dataset', function (): void {
    fakeEmptyDatasetResources();
    $organization = CouncilOrganization::factory()->create(['edrpou' => '04058410']);
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
            'title' => $title,
            'description' => 'Опис',
            'accrual_periodicity' => 'Щороку',
            'keywords' => 'реєстр',
            'purpose' => 'Мета',
            'distribution_formats' => 'CSV',
            'publisher_id' => '04058410',
            'publisher' => $organization->name,
            'contact_point_name' => 'Контактна особа',
            'contact_point_email' => 'contact@example.gov.ua',
        ])
        ->assertImported();

    expect($dataset->refresh()->title)->toBe($title);
    Http::assertSentCount(1);
});

it('falls back to dataset title when no recommendation matches the imported title', function (): void {
    fakeEmptyDatasetResources();
    $organization = CouncilOrganization::factory()->create(['edrpou' => '04058410']);
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
            'title' => 'Набір за назвою',
            'description' => 'Оновлений опис',
            'accrual_periodicity' => 'Щороку',
            'keywords' => 'реєстр',
            'purpose' => 'Мета',
            'distribution_formats' => 'CSV',
            'publisher_id' => '04058410',
            'publisher' => $organization->name,
            'contact_point_name' => 'Контактна особа',
            'contact_point_email' => 'contact@example.gov.ua',
        ])
        ->assertImported();

    expect(Dataset::query()->count())->toBe(1)
        ->and($dataset->refresh()->title)->toBe('Набір за назвою')
        ->and($dataset->refresh()->external_id)->toBe('c65f219b-4f4e-4e51-8dc6-7a1df3001258')
        ->and($dataset->refresh()->meta->description)->toBe('Оновлений опис');
    Http::assertSentCount(1);
});

it('rejects a dataset row without an external identifier', function (): void {
    $organization = CouncilOrganization::factory()->create(['edrpou' => '04058410']);

    DatasetImporter::test()
        ->import([
            'external_id' => 'null',
            'title' => 'Набір без зовнішнього ID',
            'description' => 'Опис',
            'accrual_periodicity' => 'Щороку',
            'keywords' => 'реєстр',
            'purpose' => 'Мета',
            'distribution_formats' => 'CSV',
            'publisher_id' => '04058410',
            'publisher' => $organization->name,
            'contact_point_name' => 'Контактна особа',
            'contact_point_email' => 'contact@example.gov.ua',
        ])
        ->assertHasErrors(['external_id' => 'uuid']);

    expect(Dataset::query()->exists())->toBeFalse();
});

it('imports contact metadata without resolving an official', function (): void {
    fakeEmptyDatasetResources();
    CouncilOrganization::factory()->create([
        'edrpou' => '04058410',
        'name' => 'ЗБОРІВСЬКА МІСЬКА РАДА',
    ]);

    DatasetImporter::test()
        ->import([
            'external_id' => 'c65f219b-4f4e-4e51-8dc6-7a1df3001254',
            'title' => 'Набір без відповідальної особи',
            'description' => 'Опис',
            'accrual_periodicity' => 'Щороку',
            'keywords' => 'відкриті дані',
            'purpose' => 'Підстава',
            'source_url' => 'https://data.gov.ua/dataset/unknown-contact-dataset',
            'distribution_formats' => 'CSV',
            'publisher_id' => '04058410',
            'publisher' => 'ЗБОРІВСЬКА МІСЬКА РАДА',
            'contact_point_name' => 'Симоненко Олена Петрівна',
            'contact_point_email' => 'contact@example.gov.ua',
        ])
        ->assertImported();

    expect(Dataset::query()->sole()->meta->contact_point_name)
        ->toBe('Симоненко Олена Петрівна');
    Http::assertSentCount(1);
});

it('rejects dataset update frequencies that are not labels', function (string $frequency): void {
    $organization = CouncilOrganization::factory()->create([
        'edrpou' => '04058410',
        'name' => 'ЗБОРІВСЬКА МІСЬКА РАДА',
    ]);
    DatasetImporter::test()
        ->import([
            'external_id' => 'c65f219b-4f4e-4e51-8dc6-7a1df3001255',
            'title' => 'Набір із невідомою частотою',
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
    $organization = CouncilOrganization::factory()->create([
        'edrpou' => '04058410',
        'name' => 'ЗБОРІВСЬКА МІСЬКА РАДА',
    ]);
    DatasetImporter::test()
        ->import([
            'external_id' => 'c65f219b-4f4e-4e51-8dc6-7a1df3001256',
            'title' => 'Набір із невідомим форматом',
            'description' => 'Опис',
            'accrual_periodicity' => 'Щороку',
            'keywords' => 'відкриті дані',
            'purpose' => 'Підстава',
            'source_url' => 'https://data.gov.ua/dataset/invalid-format',
            'distribution_formats' => 'CSV,UNSUPPORTED',
            'publisher_id' => '04058410',
            'publisher' => 'ЗБОРІВСЬКА МІСЬКА РАДА',
            'contact_point_name' => 'Контактна особа',
            'contact_point_email' => 'contact@example.gov.ua',
        ])
        ->assertImported();

    expect(Dataset::query()->exists())->toBeTrue()
        ->and(Dataset::query()->sole()->resources()->exists())->toBeFalse();
    Http::assertSentCount(1);
});

it('renders the dataset csv import action', function (): void {
    livewire(ManageDatasets::class)
        ->assertActionExists('import');
});
