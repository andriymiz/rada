<?php

use App\Enums\ContactPointType;
use App\Enums\DatasetRecommendation;
use App\Enums\DatasetResourceFormat;
use App\Enums\DatasetResourceType;
use App\Enums\DatasetUpdateFrequency;
use App\Filament\Resources\Datasets\DatasetResource;
use App\Filament\Resources\Datasets\Pages\EditDataset;
use App\Filament\Resources\Datasets\Pages\ManageDatasets;
use App\Filament\Resources\Datasets\RelationManagers\ResourcesRelationManager;
use App\Models\CouncilOrganization;
use App\Models\Dataset;
use App\Models\DatasetGroup;
use App\Models\DatasetResource as DatasetResourceModel;
use App\Models\Official;
use App\Models\Person;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\QueryException;
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
            'rec_code' => DatasetRecommendation::OrganizationsDirectory->value,
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
        ->and($dataset->rec_code)->toBe(DatasetRecommendation::OrganizationsDirectory)
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

it('creates a custom dataset without a recommendation', function (): void {
    $publisher = CouncilOrganization::factory()->create(['open_data_id' => 'portal-id']);
    $official = Official::factory()->for($publisher, 'organization')->create();

    livewire(ManageDatasets::class)
        ->callAction(TestAction::make('create'), [
            'rec_code' => null,
            'title' => 'Власний набір ради',
            'description' => 'Опис власного набору.',
            'accrual_periodicity' => DatasetUpdateFrequency::Yearly->value,
            'keywords' => ['реєстр'],
            'purpose' => 'Організація роботи ради.',
            'distribution_formats' => [DatasetResourceFormat::Csv->value],
            'publisher_id' => $publisher->id,
            'responsible_official_id' => $official->id,
        ])
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('datasets', [
        'title' => 'Власний набір ради',
        'rec_code' => null,
    ]);
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
            'rec_code' => DatasetRecommendation::LocalDeputies->value,
            'keywords' => ['оновлений реєстр'],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($dataset->refresh()->title)->toBe('Оновлений реєстр')
        ->and($dataset->rec_code)->toBe(DatasetRecommendation::LocalDeputies)
        ->and($dataset->keywords)->toBe(['оновлений реєстр']);
});

it('creates and assigns a group from the dataset form dropdown', function (): void {
    $dataset = Dataset::factory()->create();

    livewire(EditDataset::class, ['record' => $dataset->getKey()])
        ->callFormComponentAction('group_id', 'createOption', [
            'name' => 'Звіти та фінанси',
        ])
        ->assertHasNoFormErrors()
        ->call('save')
        ->assertHasNoFormErrors();

    $group = DatasetGroup::query()->where('name', 'Звіти та фінанси')->firstOrFail();

    expect($dataset->refresh()->group_id)->toBe($group->getKey())
        ->and($dataset->group->name)->toBe('Звіти та фінанси')
        ->and($group->sort)->toBe(1);
});

it('renames a group from the dataset form dropdown', function (): void {
    $group = DatasetGroup::factory()->create(['name' => 'Попередня назва']);
    $dataset = Dataset::factory()->for($group, 'group')->create();

    livewire(EditDataset::class, ['record' => $dataset->getKey()])
        ->callFormComponentAction('group_id', 'editOption', [
            'name' => 'Оновлена назва',
            'sort' => 5,
        ])
        ->assertHasNoFormErrors();

    expect($group->refresh()->name)->toBe('Оновлена назва')
        ->and($group->sort)->toBe(5);
});

it('assigns each new dataset the next order within its group', function (): void {
    $group = DatasetGroup::factory()->create();
    $first = Dataset::factory()->for($group, 'group')->create();
    $second = Dataset::factory()->for($group, 'group')->create();

    expect($first->sort)->toBe(1)
        ->and($second->sort)->toBe(2);
});

it('reorders datasets using the manual order field', function (): void {
    $group = DatasetGroup::factory()->create(['name' => 'Рішення ради']);
    $first = Dataset::factory()->for($group, 'group')->create(['sort' => 1]);
    $second = Dataset::factory()->for($group, 'group')->create(['sort' => 2]);
    $otherGroup = DatasetGroup::factory()->create(['name' => 'Звіти']);
    $third = Dataset::factory()->for($otherGroup, 'group')->create(['sort' => 1]);

    livewire(ManageDatasets::class)
        ->assertCanSeeTableRecords([$first, $second, $third])
        ->assertSee('Рішення ради')
        ->assertSee('Звіти')
        ->call('reorderTable', [$second->getKey(), $first->getKey(), $third->getKey()]);

    expect($second->refresh()->sort)->toBe(1)
        ->and($first->refresh()->sort)->toBe(2)
        ->and($third->refresh()->sort)->toBe(3);
});

it('allows a dataset recommendation to remain unchanged or be cleared', function (): void {
    $dataset = Dataset::factory()->create(['rec_code' => DatasetRecommendation::LocalDeputies]);

    livewire(EditDataset::class, ['record' => $dataset->getKey()])
        ->call('save')
        ->assertHasNoFormErrors()
        ->fillForm(['rec_code' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($dataset->refresh()->rec_code)->toBeNull();
});

it('rejects a recommendation already assigned to another dataset', function (): void {
    Dataset::factory()->create(['rec_code' => DatasetRecommendation::LocalDeputies]);
    $dataset = Dataset::factory()->create();

    livewire(EditDataset::class, ['record' => $dataset->getKey()])
        ->fillForm(['rec_code' => DatasetRecommendation::LocalDeputies->value])
        ->call('save')
        ->assertHasFormErrors(['rec_code' => 'unique']);

    expect($dataset->refresh()->rec_code)->toBeNull();
});

it('rejects an unknown dataset recommendation', function (): void {
    $dataset = Dataset::factory()->create();

    livewire(EditDataset::class, ['record' => $dataset->getKey()])
        ->fillForm(['rec_code' => 'unknown_recommendation'])
        ->call('save')
        ->assertHasFormErrors(['rec_code']);

    expect($dataset->refresh()->rec_code)->toBeNull();
});

it('allows multiple custom datasets without a recommendation', function (): void {
    $datasets = Dataset::factory()->count(2)->create();

    expect($datasets->first()->rec_code)->toBeNull()
        ->and($datasets->last()->rec_code)->toBeNull();
});

it('enforces recommendation uniqueness in the database', function (): void {
    Dataset::factory()->create(['rec_code' => DatasetRecommendation::LocalDeputies]);

    expect(fn () => Dataset::factory()->create(['rec_code' => DatasetRecommendation::LocalDeputies]))
        ->toThrow(QueryException::class);
});

it('provides official labels and documentation links for the recommendations', function (): void {
    expect(DatasetRecommendation::cases())->toHaveCount(75)
        ->and(DatasetRecommendation::LocalDeputies->getLabel())
        ->toBe('Дані про депутатів місцевих рад, зокрема контактна інформація та графік прийому')
        ->and(DatasetRecommendation::LocalDeputies->getExternalLink())
        ->toBe('https://data.gov.ua/pages/835-recm-local-government-deputies')
        ->and(DatasetRecommendation::Playgrounds->getExternalLink())
        ->toBe('https://data.gov.ua/pages/835-recm-budget-budget-programs')
        ->and(DatasetRecommendation::PreschoolCatchmentAreas->getExternalLink())
        ->toBe('https://data.gov.ua/pages/terytorii-obsluhovuvannia-komunalnykh-zakladiv-doshkilnoi-osvity')
        ->and(DatasetRecommendation::BurialRegisters->getLabel())
        ->toBe('Перелік книг обліку реєстрації поховань');
});

it('opens the dataset edit page from the table action', function (): void {
    $dataset = Dataset::factory()->create();
    $editUrl = DatasetResource::getUrl('edit', ['record' => $dataset]);

    livewire(ManageDatasets::class)
        ->assertTableActionHasUrl(
            'edit',
            $editUrl,
            $dataset,
        )
        ->assertSee('href="'.$editUrl.'"', false);
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

it('renders a documentation action that opens the recommendation in a new tab', function (): void {
    $dataset = Dataset::factory()->create([
        'rec_code' => DatasetRecommendation::LocalDeputies,
    ]);

    livewire(ManageDatasets::class)
        ->assertCanSeeTableRecords([$dataset])
        ->assertTableActionVisible('documentationLink', $dataset)
        ->assertTableActionHasIcon('documentationLink', Heroicon::OutlinedInformationCircle, $dataset)
        ->assertTableActionHasUrl(
            'documentationLink',
            'https://data.gov.ua/pages/835-recm-local-government-deputies',
            $dataset,
        )
        ->assertTableActionShouldOpenUrlInNewTab('documentationLink', $dataset);
});

it('hides the documentation action for custom datasets', function (): void {
    $dataset = Dataset::factory()->create(['rec_code' => null]);

    livewire(ManageDatasets::class)
        ->assertCanSeeTableRecords([$dataset])
        ->assertTableActionHidden('documentationLink', $dataset);
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
        ->assertSee('№')
        ->assertTableColumnStateSet('sort', $dataset->sort, $dataset);

    $this->get(DatasetResource::getUrl())
        ->assertOk()
        ->assertSee('Набори даних');
});
