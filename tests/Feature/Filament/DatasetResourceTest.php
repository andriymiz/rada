<?php

use App\Enums\ContactPointType;
use App\Enums\DatasetResourceFormat;
use App\Enums\DatasetUpdateFrequency;
use App\Filament\Resources\Datasets\DatasetResource;
use App\Filament\Resources\Datasets\Pages\ManageDatasets;
use App\Models\CouncilOrganization;
use App\Models\Dataset;
use App\Models\Official;
use App\Models\Person;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Schema;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel('rada');
    $this->actingAs(User::factory()->admin()->create());
});

it('creates a dataset with an organization and responsible official', function () {
    $title = 'Довідник підприємств, установ, організацій, що належать до сфери управління розпорядника інформації, та територіальних органів розпорядника інформації, зокрема їх ідентифікаційних кодів юридичної особи в Єдиному державному реєстрі підприємств і організацій України, офіційних веб-сайтів, адрес електронної пошти, номерів телефонів, адрес місцезнаходження';
    $publisher = CouncilOrganization::factory()->create();
    $official = Official::factory()
        ->for($publisher, 'organization')
        ->create();
    $official->contactPoints()->create([
        'type' => ContactPointType::Email,
        'value' => 'contact@example.gov.ua',
        'is_public' => true,
    ]);

    livewire(ManageDatasets::class)
        ->callAction(TestAction::make('create'), [
            'title' => $title,
            'description' => 'Перелік наборів відкритих даних.',
            'accrual_periodicity' => DatasetUpdateFrequency::Yearly->value,
            'keywords' => ['реєстр', 'набори даних'],
            'purpose' => 'Закон України «Про доступ до публічної інформації».',
            'distribution_formats' => [
                DatasetResourceFormat::Csv->value,
                DatasetResourceFormat::Json->value,
            ],
            'publisher_id' => $publisher->id,
            'responsible_official_id' => $official->id,
        ])
        ->assertHasNoFormErrors();

    $dataset = Dataset::query()->where('title', $title)->firstOrFail();

    expect($dataset->external_id)->toBeUuid()
        ->and($dataset->accrual_periodicity)->toBe(DatasetUpdateFrequency::Yearly)
        ->and($dataset->keywords)->toBe(['реєстр', 'набори даних'])
        ->and($dataset->distribution_formats)->toBe([
            DatasetResourceFormat::Csv->value,
            DatasetResourceFormat::Json->value,
        ])
        ->and($dataset->publisher->is($publisher))->toBeTrue()
        ->and($dataset->responsibleOfficial->is($official))->toBeTrue()
        ->and($dataset->responsibleOfficial->person->full_name)->toBe($official->person->full_name)
        ->and($dataset->responsibleOfficial->contactPoints->first()->value)->toBe('contact@example.gov.ua');

    $this->assertModelExists($dataset);
});

it('edits dataset details through a table modal', function () {
    $publisher = CouncilOrganization::factory()->create();
    $contact = Official::factory()->for($publisher, 'organization')->create();
    $dataset = Dataset::factory()
        ->for($publisher, 'publisher')
        ->for($contact, 'responsibleOfficial')
        ->create();

    livewire(ManageDatasets::class)
        ->callAction(TestAction::make('edit')->table($dataset), [
            'title' => 'Оновлений реєстр',
            'keywords' => ['оновлений реєстр'],
        ])
        ->assertHasNoFormErrors();

    expect($dataset->refresh()->title)->toBe('Оновлений реєстр')
        ->and($dataset->keywords)->toBe(['оновлений реєстр']);
});

it('generates the dataset page link from the configured open data portal URL', function () {
    config(['services.open_data.base_url' => 'https://portal.example.test/']);

    $dataset = Dataset::factory()->create();

    expect($dataset->url)->toBe('https://portal.example.test/dataset/'.$dataset->external_id);

    expect(Schema::hasColumn('datasets', 'url'))->toBeFalse();
});

it('renders the dataset portal link as an icon action', function () {
    config(['services.open_data.base_url' => 'https://portal.example.test']);

    $dataset = Dataset::factory()->create();

    livewire(ManageDatasets::class)
        ->assertTableActionExists('portalLink', record: $dataset)
        ->assertTableActionHasIcon('portalLink', Heroicon::OutlinedArrowTopRightOnSquare, $dataset)
        ->assertTableActionHasUrl('portalLink', $dataset->url, $dataset)
        ->assertTableActionShouldOpenUrlInNewTab('portalLink', $dataset);
});

it('renders the datasets page with the responsible person and email', function () {
    $person = Person::factory()->create([
        'last_name' => 'Петренко',
        'first_name' => 'Олена',
        'middle_name' => 'Іванівна',
    ]);
    $publisher = CouncilOrganization::factory()->create();
    $official = Official::factory()
        ->for($person)
        ->for($publisher, 'organization')
        ->create();
    $official->contactPoints()->create([
        'type' => ContactPointType::Email,
        'value' => 'olena@example.gov.ua',
        'is_public' => true,
    ]);
    $dataset = Dataset::factory()
        ->for($publisher, 'publisher')
        ->for($official, 'responsibleOfficial')
        ->create(['title' => 'Набір для перевірки']);

    livewire(ManageDatasets::class)
        ->assertCanSeeTableRecords([$dataset])
        ->assertSee('Набори даних')
        ->assertTableColumnStateSet('external_id', $dataset->external_id, $dataset);

    $this->get(DatasetResource::getUrl())
        ->assertOk()
        ->assertSee('Набори даних');
});
