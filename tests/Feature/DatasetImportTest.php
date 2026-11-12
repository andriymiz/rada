<?php

use App\Enums\ContactPointType;
use App\Enums\DatasetResourceFormat;
use App\Enums\DatasetUpdateFrequency;
use App\Filament\Imports\DatasetImporter;
use App\Filament\Resources\Datasets\Pages\ManageDatasets;
use App\Models\ContactPoint;
use App\Models\CouncilOrganization;
use App\Models\Dataset;
use App\Models\Official;
use App\Models\Person;
use App\Models\User;
use Filament\Actions\Imports\Models\Import;
use Filament\Facades\Filament;
use Illuminate\Validation\Rules\Enum as EnumRule;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('rada');
    $this->actingAs(User::factory()->admin()->create());
});

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
    $title = 'Довідник підприємств, установ, організацій, що належать до сфери управління розпорядника інформації, та територіальних органів розпорядника інформації, зокрема їх ідентифікаційних кодів юридичної особи в Єдиному державному реєстрі підприємств і організацій України, офіційних веб-сайтів, адрес електронної пошти, номерів телефонів, адрес місцезнаходження';
    $organization = CouncilOrganization::factory()->create([
        'edrpou' => '04058410',
        'name' => 'ЗБОРІВСЬКА МІСЬКА РАДА',
    ]);
    $person = Person::factory()->create([
        'last_name' => 'Симоненко',
        'first_name' => 'Олена',
        'middle_name' => 'Петрівна',
    ]);
    $official = Official::factory()
        ->for($person)
        ->for($organization, 'organization')
        ->create();
    $row = [
        'external_id' => 'register-dataset-1',
        'title' => $title,
        'description' => 'Опис першої версії.',
        'accrual_periodicity' => 'Щороку',
        'keywords' => 'реєстр,набори даних',
        'purpose' => 'Підстава для збору даних.',
        'source_url' => 'https://data.gov.ua/dataset/register-dataset-1',
        'distribution_formats' => 'CSV,JSON',
        'publisher_id' => '04058410',
        'publisher_name' => 'ЗБОРІВСЬКА МІСЬКА РАДА',
        'contact_point_name' => 'Симоненко Олена Петрівна',
        'contact_point_email' => 'not-an-email',
    ];

    DatasetImporter::test()
        ->import($row)
        ->assertImported();

    $dataset = Dataset::query()->sole();
    $externalId = $dataset->external_id;

    expect($dataset->title)->toBe($title)
        ->and($dataset->description)->toBe('Опис першої версії.')
        ->and($dataset->accrual_periodicity)->toBe(DatasetUpdateFrequency::Yearly)
        ->and($dataset->keywords)->toBe(['реєстр', 'набори даних'])
        ->and($dataset->distribution_formats)->toBe([
            DatasetResourceFormat::Csv->value,
            DatasetResourceFormat::Json->value,
        ])
        ->and($dataset->publisher->is($organization))->toBeTrue()
        ->and($dataset->responsibleOfficial->is($official))->toBeTrue();

    $this->assertDatabaseHas(Dataset::class, [
        'id' => $dataset->id,
        'accrual_periodicity' => 'yearly',
    ]);

    $row['description'] = 'Оновлений опис.';
    $row['publisher_id'] = ' null ';

    DatasetImporter::test()
        ->import($row)
        ->assertImported();

    expect(Dataset::query()->count())->toBe(1)
        ->and($dataset->refresh()->description)->toBe('Оновлений опис.')
        ->and($dataset->external_id)->toBe($externalId);

    $this->assertDatabaseMissing(ContactPoint::class, [
        'contactable_type' => $official->getMorphClass(),
        'contactable_id' => $official->id,
        'type' => ContactPointType::Email->value,
        'value' => 'not-an-email',
    ]);
});

it('rejects a dataset row when its publisher is unknown', function (): void {
    DatasetImporter::test()
        ->import([
            'external_id' => 'unknown-dataset',
            'title' => 'Невідомий набір',
            'description' => 'Опис',
            'accrual_periodicity' => 'Щороку',
            'keywords' => 'відкриті дані',
            'purpose' => 'Підстава',
            'source_url' => 'https://data.gov.ua/dataset/unknown-dataset',
            'distribution_formats' => 'CSV',
            'publisher_id' => '04058410',
            'publisher_name' => 'Невідома рада',
            'contact_point_name' => 'Невідома Особа',
        ])
        ->assertHasRowFailure();

    expect(Dataset::query()->exists())->toBeFalse();
});

it('preserves a UUID supplied as the register identifier', function (): void {
    $organization = CouncilOrganization::factory()->create([
        'edrpou' => '04058410',
        'name' => 'ЗБОРІВСЬКА МІСЬКА РАДА',
    ]);
    $official = Official::factory()->for($organization, 'organization')->create();
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
            'publisher_name' => 'ЗБОРІВСЬКА МІСЬКА РАДА',
            'contact_point_name' => $official->person->full_name,
        ])
        ->assertImported();

    expect(Dataset::query()->sole()->external_id)->toBe($externalId);
});

it('rejects a dataset row when its responsible official is not found in its organization', function (): void {
    CouncilOrganization::factory()->create([
        'edrpou' => '04058410',
        'name' => 'ЗБОРІВСЬКА МІСЬКА РАДА',
    ]);
    $otherOrganization = CouncilOrganization::factory()->create([
        'edrpou' => '04058411',
        'name' => 'ІНША МІСЬКА РАДА',
    ]);
    $person = Person::factory()->create([
        'last_name' => 'Симоненко',
        'first_name' => 'Олена',
        'middle_name' => 'Петрівна',
    ]);
    Official::factory()
        ->for($person)
        ->for($otherOrganization, 'organization')
        ->create();

    DatasetImporter::test()
        ->import([
            'external_id' => 'unknown-contact-dataset',
            'title' => 'Набір без відповідальної особи',
            'description' => 'Опис',
            'accrual_periodicity' => 'Щороку',
            'keywords' => 'відкриті дані',
            'purpose' => 'Підстава',
            'source_url' => 'https://data.gov.ua/dataset/unknown-contact-dataset',
            'distribution_formats' => 'CSV',
            'publisher_id' => '04058410',
            'publisher_name' => 'ЗБОРІВСЬКА МІСЬКА РАДА',
            'contact_point_name' => 'Симоненко Олена Петрівна',
        ])
        ->assertHasRowFailure();

    expect(Dataset::query()->exists())->toBeFalse();
});

it('rejects dataset update frequencies that are not labels', function (string $frequency): void {
    $organization = CouncilOrganization::factory()->create([
        'edrpou' => '04058410',
        'name' => 'ЗБОРІВСЬКА МІСЬКА РАДА',
    ]);
    $official = Official::factory()->for($organization, 'organization')->create();

    DatasetImporter::test()
        ->import([
            'external_id' => 'invalid-periodicity',
            'title' => 'Набір із невідомою частотою',
            'description' => 'Опис',
            'accrual_periodicity' => $frequency,
            'keywords' => 'відкриті дані',
            'purpose' => 'Підстава',
            'source_url' => 'https://data.gov.ua/dataset/invalid-periodicity',
            'distribution_formats' => 'CSV',
            'publisher_id' => '04058410',
            'publisher_name' => 'ЗБОРІВСЬКА МІСЬКА РАДА',
            'contact_point_name' => $official->person->full_name,
            'contact_point_email' => 'contact@example.gov.ua',
        ])
        ->assertHasErrors(['accrual_periodicity' => 'in']);

    expect(Dataset::query()->exists())->toBeFalse();
})->with([
    'unknown label' => 'За потреби',
    'enum code' => 'yearly',
]);

it('rejects unsupported resource formats', function (): void {
    $organization = CouncilOrganization::factory()->create([
        'edrpou' => '04058410',
        'name' => 'ЗБОРІВСЬКА МІСЬКА РАДА',
    ]);
    $official = Official::factory()->for($organization, 'organization')->create();

    DatasetImporter::test()
        ->import([
            'external_id' => 'invalid-format',
            'title' => 'Набір із невідомим форматом',
            'description' => 'Опис',
            'accrual_periodicity' => 'Щороку',
            'keywords' => 'відкриті дані',
            'purpose' => 'Підстава',
            'source_url' => 'https://data.gov.ua/dataset/invalid-format',
            'distribution_formats' => 'CSV,UNSUPPORTED',
            'publisher_id' => '04058410',
            'publisher_name' => 'ЗБОРІВСЬКА МІСЬКА РАДА',
            'contact_point_name' => $official->person->full_name,
            'contact_point_email' => 'contact@example.gov.ua',
        ])
        ->assertHasErrors(['distribution_formats.1' => EnumRule::class]);

    expect(Dataset::query()->exists())->toBeFalse();
});

it('renders the dataset csv import action', function (): void {
    livewire(ManageDatasets::class)
        ->assertActionExists('import');
});
