<?php

use App\Enums\DatasetRecommendation;
use App\Enums\DatasetResourceType;
use App\Enums\DatasetUpdateFrequency;
use App\Filament\Exports\DatasetExporter;
use App\Filament\Resources\Datasets\Pages\ManageDatasets;
use App\Models\CouncilOrganization;
use App\Models\Dataset;
use App\Models\User;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\Models\Export;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('rada');
    $this->actingAs(User::factory()->admin()->create());
});

function exporterMetadata(array $overrides = []): array
{
    return array_replace([
        'description' => 'Опис набору.',
        'accrual_periodicity' => DatasetUpdateFrequency::Yearly->value,
        'keywords' => ['реєстр'],
        'purpose' => 'Підстава.',
        'contact_point_name' => 'Контакт порталу',
        'contact_point_email' => 'portal@example.gov.ua',
    ], $overrides);
}

it('exports dataset rows using the open data register schema', function (): void {
    config(['services.open_data.base_url' => 'https://data.gov.ua']);

    $publisher = CouncilOrganization::factory()->create([
        'name' => 'ЗБОРІВСЬКА МІСЬКА РАДА',
        'edrpou' => '04058410',
    ]);
    $dataset = Dataset::factory()
        ->for($publisher, 'publisher')
        ->create([
            'external_id' => 'c65f219b-4f4e-4e51-8dc6-7a1df3001252',
            'title' => 'Реєстр',
            'rec_code' => DatasetRecommendation::LocalDeputies,
        ]);
    $dataset->resources()->createMany([
        [
            'external_id' => 'csv-resource',
            'name' => DatasetResourceType::Deputies,
            'format' => 'CSV',
        ],
        [
            'external_id' => 'json-resource',
            'name' => DatasetResourceType::Posts,
            'format' => 'JSON',
        ],
        [
            'external_id' => 'csv-copy',
            'name' => DatasetResourceType::Organizations,
            'format' => 'CSV',
        ],
    ]);
    Http::preventStrayRequests();
    $dataset->meta()->create(exporterMetadata([
        'description' => 'Перелік наборів даних розпорядника.',
        'accrual_periodicity' => DatasetUpdateFrequency::Yearly->value,
        'keywords' => ['реєстр', 'набори даних'],
        'purpose' => 'Закон України «Про доступ до публічної інформації».',
        'contact_point_name' => 'Контакт порталу',
        'contact_point_email' => 'portal@example.gov.ua',
    ]));
    $dataset->load(['meta', 'publisher']);

    expect(DatasetExporter::test()->export($dataset))->toBe([
        'c65f219b-4f4e-4e51-8dc6-7a1df3001252',
        'Дані про депутатів місцевих рад, зокрема контактна інформація та графік прийому',
        'Перелік наборів даних розпорядника.',
        'Щороку',
        'реєстр, набори даних',
        'Закон України «Про доступ до публічної інформації».',
        'https://data.gov.ua/dataset/c65f219b-4f4e-4e51-8dc6-7a1df3001252',
        'CSV, JSON',
        'ЗБОРІВСЬКА МІСЬКА РАДА',
        '04058410',
        'Контакт порталу',
        'portal@example.gov.ua',
    ]);

    expect(collect(DatasetExporter::getColumns())->map->getLabel()->all())->toBe([
        'uid',
        'title',
        'description',
        'accrualPeriodicity',
        'keywords',
        'purpose',
        'url',
        'distributionFormat',
        'publisherName',
        'publisherId',
        'contactPointName',
        'contactPointEmail',
    ]);
});

it('exports metadata contact values without using the responsible official', function (): void {
    $publisher = CouncilOrganization::factory()->create([
        'edrpou' => 'null',
    ]);
    $dataset = Dataset::factory()
        ->for($publisher, 'publisher')
        ->create([
            'external_id' => 'c65f219b-4f4e-4e51-8dc6-7a1df3001253',
        ]);
    $dataset->meta()->create(exporterMetadata([
        'contact_point_name' => 'Контакт із метаданих',
        'contact_point_email' => 'metadata@example.gov.ua',
    ]));
    $dataset->load(['meta', 'publisher']);

    expect(array_slice(DatasetExporter::test()->export($dataset), 9))
        ->toBe([
            'null',
            'Контакт із метаданих',
            'metadata@example.gov.ua',
        ]);
});

it('exports dataset metadata stored in its related record', function (): void {
    $dataset = Dataset::factory()->create([
        'external_id' => 'c65f219b-4f4e-4e51-8dc6-7a1df3001252',
        'title' => 'Коротка назва',
    ]);
    $dataset->meta()->create(exporterMetadata([
        'description' => 'Опис із порталу',
        'accrual_periodicity' => 'yearly',
        'keywords' => ['реєстр', 'дані'],
        'purpose' => 'Підстава.',
    ]));
    $dataset->load(['meta', 'publisher']);

    expect(array_slice(DatasetExporter::test()->export($dataset), 1, 7))->toBe([
        'Коротка назва',
        'Опис із порталу',
        'Щороку',
        'реєстр, дані',
        'Підстава.',
        'https://data.gov.ua/dataset/c65f219b-4f4e-4e51-8dc6-7a1df3001252',
        null,
    ]);
});

it('exports contact information from the related metadata when present', function (): void {
    $dataset = Dataset::factory()->create([
        'external_id' => 'c65f219b-4f4e-4e51-8dc6-7a1df3001254',
    ]);
    $dataset->meta()->create(exporterMetadata([
        'contact_point_name' => 'Контакт порталу',
        'contact_point_email' => 'portal-contact@example.gov.ua',
    ]));
    $dataset->load(['meta', 'publisher']);

    expect(array_slice(DatasetExporter::test()->export($dataset), 10))
        ->toBe(['Контакт порталу', 'portal-contact@example.gov.ua']);
});

it('exports only the complete register schema as a CSV resource', function (): void {
    livewire(ManageDatasets::class)
        ->assertActionExists('export', fn (ExportAction $action): bool => $action->getExporter() === DatasetExporter::class
            && $action->getFileName(new Export) === 'register'
            && $action->getFormats() === [ExportFormat::Csv]
            && ! $action->hasColumnMapping());
});

it('shows a concise completion message with exported and total row counts', function (): void {
    $export = new Export([
        'exporter' => DatasetExporter::class,
        'successful_rows' => 8,
        'total_rows' => 10,
    ]);

    expect(DatasetExporter::getCompletedNotificationBody($export))
        ->toBe('DatasetExporter - Успішно експортовано 8 із 10 записів.');
});
