<?php

use App\Enums\ContactPointType;
use App\Enums\DatasetResourceFormat;
use App\Enums\DatasetResourceType;
use App\Enums\DatasetUpdateFrequency;
use App\Filament\Resources\Datasets\DatasetResource;
use App\Filament\Resources\Datasets\Pages\EditDataset;
use App\Filament\Resources\Datasets\Pages\ManageDatasets;
use App\Filament\Resources\Datasets\RelationManagers\ResourcesRelationManager;
use App\Models\CouncilOrganization;
use App\Models\Dataset;
use App\Models\DatasetResource as DatasetResourceModel;
use App\Models\Official;
use App\Models\Person;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel('rada');
    $this->actingAs(User::factory()->admin()->create());
});

it('creates a dataset with an organization and responsible official', function () {
    $title = 'Довідник підприємств, установ, організацій, що належать до сфери управління розпорядника інформації, та територіальних органів розпорядника інформації, зокрема їх ідентифікаційних кодів юридичної особи в Єдиному державному реєстрі підприємств і організацій України, офіційних веб-сайтів, адрес електронної пошти, номерів телефонів, адрес місцезнаходження';
    $publisher = CouncilOrganization::factory()->create([
        'open_data_id' => '11111111-2222-4333-8444-555555555555',
    ]);
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

it('hides dataset creation until an organization portal ID is configured', function (): void {
    livewire(ManageDatasets::class)
        ->assertActionHidden(TestAction::make('create'));
});

it('does not allow non-administrators to create datasets when the portal is configured', function (): void {
    CouncilOrganization::factory()->create([
        'open_data_id' => '11111111-2222-4333-8444-555555555555',
    ]);
    $this->actingAs(User::factory()->create());

    livewire(ManageDatasets::class)
        ->assertActionHidden(TestAction::make('create'));
});

it('edits dataset details on its edit page', function () {
    $publisher = CouncilOrganization::factory()->create();
    $contact = Official::factory()->for($publisher, 'organization')->create();
    $dataset = Dataset::factory()
        ->for($publisher, 'publisher')
        ->for($contact, 'responsibleOfficial')
        ->create();

    livewire(EditDataset::class, ['record' => $dataset->getKey()])
        ->fillForm([
            'title' => 'Оновлений реєстр',
            'keywords' => ['оновлений реєстр'],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($dataset->refresh()->title)->toBe('Оновлений реєстр')
        ->and($dataset->keywords)->toBe(['оновлений реєстр']);
});

it('opens the dataset edit page from the table action', function (): void {
    $dataset = Dataset::factory()->create();

    livewire(ManageDatasets::class)
        ->assertTableActionHasUrl(
            'edit',
            DatasetResource::getUrl('edit', ['record' => $dataset]),
            $dataset,
        );
});

it('shows portal resource management on the dataset edit page', function (): void {
    $dataset = Dataset::factory()->create();

    $this->get(DatasetResource::getUrl('edit', ['record' => $dataset]))
        ->assertOk()
        ->assertSeeLivewire(ResourcesRelationManager::class);
});

it('hides portal resource management from non-administrators', function (): void {
    $dataset = Dataset::factory()->create();
    $this->actingAs(User::factory()->create());

    $this->get(DatasetResource::getUrl('edit', ['record' => $dataset]))
        ->assertOk()
        ->assertDontSeeLivewire(ResourcesRelationManager::class);
});

it('creates a portal resource from the selected dataset in its relation manager', function (): void {
    $dataset = Dataset::factory()->create([
        'external_id' => 'dataset-package-id',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'data.gov.ua/api/3/action/package_show*' => Http::response([
            'success' => true,
            'result' => [
                'resources' => [
                    ['id' => 'register-resource-id', 'name' => 'Реєстр наборів', 'format' => 'CSV'],
                ],
            ],
        ]),
    ]);

    livewire(ResourcesRelationManager::class, [
        'ownerRecord' => $dataset,
        'pageClass' => EditDataset::class,
    ])
        ->callTableAction('create', null, [
            'type' => DatasetResourceType::Register->value,
            'external_id' => 'register-resource-id',
        ])
        ->assertHasNoFormErrors();

    $resource = $dataset->resources()->firstOrFail();

    expect($resource->type)->toBe(DatasetResourceType::Register)
        ->and($resource->external_id)->toBe('register-resource-id');

    $this->assertDatabaseHas('dataset_resources', [
        'dataset_id' => $dataset->getKey(),
        'external_id' => 'register-resource-id',
        'type' => DatasetResourceType::Register->value,
    ]);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/package_show')
        && $request->data()['id'] === 'dataset-package-id');
});

it('rejects a portal resource that does not belong to the selected dataset', function (): void {
    $dataset = Dataset::factory()->create([
        'external_id' => 'dataset-package-id',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'data.gov.ua/api/3/action/package_show*' => Http::response([
            'success' => true,
            'result' => [
                'resources' => [
                    ['id' => 'valid-resource-id', 'name' => 'Реєстр наборів', 'format' => 'CSV'],
                ],
            ],
        ]),
    ]);

    livewire(ResourcesRelationManager::class, [
        'ownerRecord' => $dataset,
        'pageClass' => EditDataset::class,
    ])
        ->callTableAction('create', null, [
            'type' => DatasetResourceType::Register->value,
            'external_id' => 'resource-from-another-dataset',
        ])
        ->assertHasFormErrors(['external_id' => 'in']);

    expect($dataset->resources()->exists())->toBeFalse();
});

it('rejects a resource type that is not supported', function (): void {
    $dataset = Dataset::factory()->create([
        'external_id' => 'dataset-package-id',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'data.gov.ua/api/3/action/package_show*' => Http::response([
            'success' => true,
            'result' => [
                'resources' => [
                    ['id' => 'valid-resource-id', 'name' => 'Реєстр наборів', 'format' => 'CSV'],
                ],
            ],
        ]),
    ]);

    livewire(ResourcesRelationManager::class, [
        'ownerRecord' => $dataset,
        'pageClass' => EditDataset::class,
    ])
        ->callTableAction('create', null, [
            'type' => 'unsupported',
            'external_id' => 'valid-resource-id',
        ])
        ->assertHasFormErrors(['type']);

    expect($dataset->resources()->exists())->toBeFalse();
});

it('updates a portal resource from the related dataset page', function (): void {
    $dataset = Dataset::factory()->create([
        'external_id' => 'dataset-package-id',
    ]);
    $resource = DatasetResourceModel::factory()
        ->for($dataset)
        ->create([
            'external_id' => 'old-resource-id',
            'type' => DatasetResourceType::Register,
        ]);

    Http::preventStrayRequests();
    Http::fake([
        'data.gov.ua/api/3/action/package_show*' => Http::response([
            'success' => true,
            'result' => [
                'resources' => [
                    ['id' => 'old-resource-id', 'name' => 'Старий файл', 'format' => 'CSV'],
                    ['id' => 'new-resource-id', 'name' => 'Оновлений файл', 'format' => 'CSV'],
                ],
            ],
        ]),
    ]);

    livewire(ResourcesRelationManager::class, [
        'ownerRecord' => $dataset,
        'pageClass' => EditDataset::class,
    ])
        ->callTableAction('edit', $resource, [
            'type' => DatasetResourceType::Register->value,
            'external_id' => 'new-resource-id',
        ])
        ->assertHasNoFormErrors();

    expect($resource->refresh()->external_id)->toBe('new-resource-id');
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
