<?php

use App\Enums\ContactPointType;
use App\Enums\DatasetResourceFormat;
use App\Enums\DatasetUpdateFrequency;
use App\Filament\Exports\DatasetExporter;
use App\Filament\Resources\Datasets\Pages\ManageDatasets;
use App\Models\CouncilOrganization;
use App\Models\Dataset;
use App\Models\Official;
use App\Models\Person;
use App\Models\User;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\Models\Export;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('rada');
    $this->actingAs(User::factory()->admin()->create());
});

it('exports dataset rows using the open data register schema', function (): void {
    config(['services.open_data.base_url' => 'https://data.gov.ua']);

    $publisher = CouncilOrganization::factory()->create([
        'name' => 'ЗБОРІВСЬКА МІСЬКА РАДА',
        'edrpou' => '04058410',
    ]);
    $person = Person::factory()->create([
        'last_name' => 'Симоненко',
        'first_name' => 'Олена',
        'middle_name' => 'Петрівна',
    ]);
    $official = Official::factory()
        ->for($person)
        ->for($publisher, 'organization')
        ->create();
    $official->contactPoints()->create([
        'type' => ContactPointType::Email,
        'value' => 'contact@example.gov.ua',
        'is_public' => true,
    ]);
    $dataset = Dataset::factory()
        ->for($publisher, 'publisher')
        ->for($official, 'responsibleOfficial')
        ->create([
            'external_id' => 'c65f219b-4f4e-4e51-8dc6-7a1df3001252',
            'title' => 'Реєстр наборів даних',
            'description' => 'Перелік наборів даних розпорядника.',
            'accrual_periodicity' => DatasetUpdateFrequency::Yearly,
            'keywords' => ['реєстр', 'набори даних'],
            'purpose' => 'Закон України «Про доступ до публічної інформації».',
            'distribution_formats' => [
                DatasetResourceFormat::Csv->value,
                DatasetResourceFormat::Json->value,
            ],
        ])
        ->load(['publisher', 'responsibleOfficial.person', 'responsibleOfficial.contactPoints']);

    expect(DatasetExporter::test()->export($dataset))->toBe([
        'c65f219b-4f4e-4e51-8dc6-7a1df3001252',
        'Реєстр наборів даних',
        'Перелік наборів даних розпорядника.',
        'Щороку',
        'реєстр, набори даних',
        'Закон України «Про доступ до публічної інформації».',
        'https://data.gov.ua/dataset/c65f219b-4f4e-4e51-8dc6-7a1df3001252',
        'CSV, JSON',
        'ЗБОРІВСЬКА МІСЬКА РАДА',
        '04058410',
        'Симоненко Олена Петрівна',
        'contact@example.gov.ua',
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

it('exports null text for an absent publisher identifier and responsible email', function (): void {
    $publisher = CouncilOrganization::factory()->create([
        'edrpou' => 'null',
    ]);
    $official = Official::factory()->for($publisher, 'organization')->create();
    $dataset = Dataset::factory()
        ->for($publisher, 'publisher')
        ->for($official, 'responsibleOfficial')
        ->create()
        ->load(['publisher', 'responsibleOfficial.person', 'responsibleOfficial.contactPoints']);

    expect(array_slice(DatasetExporter::test()->export($dataset), 9))
        ->toBe([
            'null',
            $official->person->full_name,
            'null',
        ]);
});

it('prefixes formula-like text with an apostrophe in exported CSV cells', function (): void {
    $dataset = Dataset::factory()->create([
        'title' => '=HYPERLINK("https://example.test", "open")',
    ])->load(['publisher', 'responsibleOfficial.person', 'responsibleOfficial.contactPoints']);

    expect(DatasetExporter::test()->export($dataset)[1])
        ->toBe("'=HYPERLINK(\"https://example.test\", \"open\")");
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
