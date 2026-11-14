<?php

use App\Enums\DatasetRecommendation;
use App\Filament\Resources\Datasets\DatasetResource;
use App\Filament\Resources\Datasets\Pages\EditDataset;
use App\Filament\Resources\Datasets\Pages\ManageDatasets;
use App\Filament\Resources\Datasets\RelationManagers\ResourcesRelationManager;
use App\Models\CouncilOrganization;
use App\Models\Dataset;
use App\Models\DatasetGroup;
use App\Models\DatasetResource as DatasetResourceModel;
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

it('sets the portal identifier from the search action without fetching metadata', function (): void {
    $packageId = 'c65f219b-4f4e-4e51-8dc6-7a1df3001258';
    $dataset = Dataset::factory()->create([
        'external_id' => null,
    ]);

    Http::preventStrayRequests();

    livewire(EditDataset::class, ['record' => $dataset->getKey()])
        ->callAction(
            TestAction::make('searchPortalDataset')->schemaComponent('external_id'),
            ['package_id' => $packageId],
        )
        ->assertSet('data.external_id', $packageId)
        ->assertSchemaComponentHidden('portalMetadata');

    Http::assertNothingSent();

    $this->assertDatabaseMissing('dataset_metas', [
        'dataset_id' => $dataset->getKey(),
    ]);
    $this->assertDatabaseHas('datasets', [
        'id' => $dataset->getKey(),
        'external_id' => null,
    ]);
});

it('hides dataset creation until an organization portal ID is configured', function (): void {
    livewire(ManageDatasets::class)
        ->assertActionHidden(TestAction::make('create'));
});

it('hides the portal metadata section for datasets without a saved portal identifier', function (): void {
    $dataset = Dataset::factory()->create([
        'external_id' => null,
    ]);

    livewire(EditDataset::class, ['record' => $dataset->getKey()])
        ->assertSchemaComponentHidden('portalMetadata');
});

it('creates a custom dataset without a recommendation', function (): void {
    $publisher = CouncilOrganization::factory()->create(['open_data_id' => 'portal-id']);

    livewire(ManageDatasets::class)
        ->callAction(TestAction::make('create'), [
            'rec_code' => null,
            'title' => 'Власний набір ради',
            'publisher_id' => $publisher->id,
        ])
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('datasets', [
        'title' => 'Власний набір ради',
        'rec_code' => null,
        'external_id' => null,
    ]);
    $this->assertDatabaseMissing('dataset_metas', [
        'dataset_id' => Dataset::query()->where('title', 'Власний набір ради')->value('id'),
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
    $dataset = Dataset::factory()
        ->for($publisher, 'publisher')
        ->create([
            'external_id' => 'c65f219b-4f4e-4e51-8dc6-7a1df3001259',
        ]);
    $dataset->meta()->create([
        'description' => 'Опис набору.',
        'accrual_periodicity' => 'yearly',
        'keywords' => ['оновлений реєстр'],
        'purpose' => 'Підстава.',
        'contact_point_name' => 'Контакт',
        'contact_point_email' => 'contact@example.gov.ua',
    ]);

    Http::preventStrayRequests();

    livewire(EditDataset::class, ['record' => $dataset->getKey()])
        ->fillForm([
            'title' => 'Оновлений реєстр',
            'rec_code' => DatasetRecommendation::LocalDeputies->value,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($dataset->refresh()->title)->toBe('Оновлений реєстр')
        ->and($dataset->rec_code)->toBe(DatasetRecommendation::LocalDeputies)
        ->and($dataset->meta->keywords)->toBe(['оновлений реєстр']);

    Http::assertNothingSent();
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
    $dataset = Dataset::factory()->create([
        'external_id' => 'dataset-package-id',
    ]);

    $this->get(DatasetResource::getUrl('edit', ['record' => $dataset]))
        ->assertOk()
        ->assertSeeLivewire(ResourcesRelationManager::class);
});

it('hides portal resource management when the dataset is not linked to the portal', function (): void {
    $dataset = Dataset::factory()->create();

    $this->get(DatasetResource::getUrl('edit', ['record' => $dataset]))
        ->assertOk()
        ->assertDontSeeLivewire(ResourcesRelationManager::class);
});

it('hides portal resource management from non-administrators', function (): void {
    $dataset = Dataset::factory()->create([
        'external_id' => 'dataset-package-id',
    ]);
    $this->actingAs(User::factory()->create());

    $this->get(DatasetResource::getUrl('edit', ['record' => $dataset]))
        ->assertOk()
        ->assertDontSeeLivewire(ResourcesRelationManager::class);
});

it('synchronizes portal resources and removes local resources that no longer exist', function (): void {
    $dataset = Dataset::factory()->create([
        'external_id' => 'dataset-package-id',
    ]);
    $obsoleteResource = $dataset->resources()->create([
        'external_id' => 'obsolete-resource-id',
        'name' => 'obsolete.csv',
        'format' => 'CSV',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'data.gov.ua/api/3/action/package_show*' => Http::response([
            'success' => true,
            'result' => [
                'id' => 'dataset-package-id',
                'resources' => [
                    ['id' => 'reports-archive-id', 'name' => 'reports.zip', 'format' => 'ZIP'],
                    ['id' => 'reports-list-id', 'name' => 'listOfReports.csv', 'format' => 'CSV'],
                ],
            ],
        ]),
    ]);

    livewire(ResourcesRelationManager::class, [
        'ownerRecord' => $dataset,
        'pageClass' => EditDataset::class,
    ])
        ->callTableAction('sync')
        ->assertNotified();

    $this->assertDatabaseHas('dataset_resources', [
        'dataset_id' => $dataset->getKey(),
        'external_id' => 'reports-archive-id',
        'name' => 'reports.zip',
        'format' => 'ZIP',
    ]);
    $this->assertDatabaseHas('dataset_resources', [
        'dataset_id' => $dataset->getKey(),
        'external_id' => 'reports-list-id',
        'name' => 'listOfReports.csv',
        'format' => 'CSV',
    ]);
    $this->assertDatabaseMissing('dataset_resources', [
        'id' => $obsoleteResource->getKey(),
    ]);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/package_show')
        && $request->data()['id'] === 'dataset-package-id');
});

it('updates an existing resource using the portal resource with the same external ID', function (): void {
    $dataset = Dataset::factory()->create([
        'external_id' => 'dataset-package-id',
    ]);
    $resource = $dataset->resources()->create([
        'external_id' => 'existing-resource-id',
        'name' => 'old-name.csv',
        'format' => 'XML',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'data.gov.ua/api/3/action/package_show*' => Http::response([
            'success' => true,
            'result' => [
                'id' => 'dataset-package-id',
                'resources' => [
                    ['id' => 'existing-resource-id', 'name' => 'posts.csv', 'format' => 'JSON'],
                ],
            ],
        ]),
    ]);

    livewire(ResourcesRelationManager::class, [
        'ownerRecord' => $dataset,
        'pageClass' => EditDataset::class,
    ])
        ->callTableAction('sync')
        ->assertNotified();

    expect($resource->refresh()->external_id)->toBe('existing-resource-id')
        ->and($resource->name)->toBe('posts.csv')
        ->and($resource->format)->toBe('JSON');

    expect($dataset->resources()->count())->toBe(1);
});

it('links each resource row to its portal resource page', function (): void {
    config(['services.open_data.base_url' => 'https://portal.example.test/']);
    $dataset = Dataset::factory()->create([
        'external_id' => 'dataset-package-id',
    ]);
    $resource = DatasetResourceModel::factory()
        ->for($dataset)
        ->create([
            'external_id' => 'portal-resource-id',
            'name' => 'organizations.csv',
        ]);

    $url = 'https://portal.example.test/dataset/dataset-package-id/resource/portal-resource-id';

    livewire(ResourcesRelationManager::class, [
        'ownerRecord' => $dataset,
        'pageClass' => EditDataset::class,
    ])
        ->assertTableActionExists('openPortalResource', record: $resource)
        ->assertTableActionHasUrl('openPortalResource', $url, $resource);
});

it('generates the dataset page link from the configured open data portal URL', function () {
    config(['services.open_data.base_url' => 'https://portal.example.test/']);

    $dataset = Dataset::factory()->create([
        'external_id' => 'c65f219b-4f4e-4e51-8dc6-7a1df3001252',
    ]);

    expect($dataset->url)->toBe('https://portal.example.test/dataset/c65f219b-4f4e-4e51-8dc6-7a1df3001252');

    expect(Schema::hasColumn('datasets', 'url'))->toBeFalse();
});

it('renders the dataset portal link as an icon action', function () {
    config(['services.open_data.base_url' => 'https://portal.example.test']);

    $dataset = Dataset::factory()->create([
        'external_id' => 'c65f219b-4f4e-4e51-8dc6-7a1df3001252',
    ]);

    livewire(ManageDatasets::class)
        ->assertTableActionExists('portalLink', record: $dataset)
        ->assertTableActionHasIcon('portalLink', Heroicon::OutlinedArrowTopRightOnSquare, $dataset)
        ->assertTableActionHasUrl('portalLink', $dataset->url, $dataset)
        ->assertTableActionShouldOpenUrlInNewTab('portalLink', $dataset);
});

it('does not render a portal link for datasets without an external identifier', function (): void {
    $dataset = Dataset::factory()->create();

    expect($dataset->url)->toBeNull();

    livewire(ManageDatasets::class)
        ->assertTableActionHidden('portalLink', $dataset);
});

it('stores dataset metadata in its own related table', function (): void {
    $nullableMetadataColumns = collect(Schema::getColumns('dataset_metas'))
        ->whereIn('name', [
            'description',
            'accrual_periodicity',
            'keywords',
            'purpose',
            'contact_point_name',
            'contact_point_email',
        ])
        ->where('nullable', true)
        ->pluck('name')
        ->all();

    expect(Schema::hasTable('dataset_metas'))->toBeTrue()
        ->and(Schema::hasColumn('datasets', 'responsible_official_id'))->toBeFalse()
        ->and(Schema::hasColumn('dataset_metas', 'official_title'))->toBeFalse()
        ->and(Schema::hasColumn('dataset_metas', 'description'))->toBeTrue()
        ->and(Schema::hasColumn('dataset_metas', 'accrual_periodicity'))->toBeTrue()
        ->and(Schema::hasColumn('dataset_metas', 'keywords'))->toBeTrue()
        ->and(Schema::hasColumn('dataset_metas', 'purpose'))->toBeTrue()
        ->and(Schema::hasColumn('dataset_metas', 'distribution_format'))->toBeFalse()
        ->and(Schema::hasColumn('dataset_metas', 'contact_point_name'))->toBeTrue()
        ->and(Schema::hasColumn('dataset_metas', 'contact_point_email'))->toBeTrue()
        ->and(Schema::hasColumn('datasets', 'description'))->toBeFalse()
        ->and(Schema::hasColumn('datasets', 'accrual_periodicity'))->toBeFalse()
        ->and(Schema::hasColumn('datasets', 'keywords'))->toBeFalse()
        ->and(Schema::hasColumn('datasets', 'purpose'))->toBeFalse()
        ->and(Schema::hasColumn('datasets', 'distribution_formats'))->toBeFalse()
        ->and($nullableMetadataColumns)->toBe([]);
});

it('does not display update frequency in the datasets table', function (): void {
    $dataset = Dataset::factory()->create();
    $dataset->meta()->create([
        'description' => 'Опис набору.',
        'accrual_periodicity' => 'yearly',
        'keywords' => ['реєстр'],
        'purpose' => 'Підстава.',
        'contact_point_name' => 'Контакт',
        'contact_point_email' => 'contact@example.gov.ua',
    ]);

    livewire(ManageDatasets::class)
        ->assertCanSeeTableRecords([$dataset])
        ->assertDontSee('Щороку');
});

it('does not display resource formats in dataset metadata', function () {
    $dataset = Dataset::factory()->create([
        'external_id' => 'c65f219b-4f4e-4e51-8dc6-7a1df3001252',
    ]);
    $dataset->meta()->create([
        'description' => 'Опис набору.',
        'accrual_periodicity' => 'yearly',
        'keywords' => ['реєстр'],
        'purpose' => 'Підстава.',
        'contact_point_name' => 'Контакт',
        'contact_point_email' => 'contact@example.gov.ua',
    ]);

    livewire(EditDataset::class, ['record' => $dataset->getKey()])
        ->assertDontSee('Формати ресурсів');
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

it('renders the datasets page with its order column', function () {
    $publisher = CouncilOrganization::factory()->create();
    $dataset = Dataset::factory()
        ->for($publisher, 'publisher')
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
