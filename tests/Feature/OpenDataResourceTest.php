<?php

use App\Filament\Resources\OpenData\OpenDataResource as OpenDataFilamentResource;
use App\Filament\Resources\OpenData\Pages\ListOpenData;
use App\Models\CouncilOrganization;
use App\Models\OpenDataResource;
use App\Models\User;
use App\Services\OpenDataPortal;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('rada');
    $this->actingAs(User::factory()->admin()->create());
});

it('creates an open data resource from a modal with a predefined code', function (string $code): void {
    CouncilOrganization::factory()->create([
        'open_data_id' => '11111111-2222-4333-8444-555555555555',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'data.gov.ua/api/3/action/package_show*' => Http::response([
            'success' => true,
            'result' => [
                'id' => 'dataset-id',
                'name' => 'council-datasets',
                'title' => 'Набори даних ради',
                'resources' => [
                    ['id' => 'resource-id', 'name' => 'Ресурс даних', 'format' => 'CSV'],
                ],
            ],
        ]),
    ]);

    livewire(ListOpenData::class)
        ->callAction(TestAction::make('create'), [
            'package_id' => 'dataset-id',
            'resource_id' => 'resource-id',
            'code' => $code,
        ])
        ->assertHasNoFormErrors();

    $resource = OpenDataResource::query()->where('code', $code)->firstOrFail();

    $this->assertModelExists($resource);
    $this->assertDatabaseHas('open_data_resource', [
        'package_id' => 'dataset-id',
        'resource_id' => 'resource-id',
        'code' => $code,
    ]);
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/package_show'));
})->with([
    'organizations',
    'posts',
    'deputies',
]);

it('hides the create action until an organization portal ID is configured', function (): void {
    livewire(ListOpenData::class)
        ->assertActionHidden(TestAction::make('create'));
});

it('rejects an open data resource code that is not predefined', function (): void {
    CouncilOrganization::factory()->create([
        'open_data_id' => '11111111-2222-4333-8444-555555555555',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'data.gov.ua/api/3/action/package_show*' => Http::response([
            'success' => true,
            'result' => [
                'id' => 'dataset-id',
                'name' => 'council-datasets',
                'title' => 'Набори даних ради',
                'resources' => [
                    ['id' => 'resource-id', 'name' => 'Ресурс даних', 'format' => 'CSV'],
                ],
            ],
        ]),
    ]);

    livewire(ListOpenData::class)
        ->callAction(TestAction::make('create'), [
            'package_id' => 'dataset-id',
            'resource_id' => 'resource-id',
            'code' => 'unknown',
        ])
        ->assertHasFormErrors(['code' => 'in']);

    expect(OpenDataResource::query()->exists())->toBeFalse();
});

it('rejects a resource that does not belong to the selected dataset', function (): void {
    CouncilOrganization::factory()->create([
        'open_data_id' => '11111111-2222-4333-8444-555555555555',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'data.gov.ua/api/3/action/package_show*' => Http::response([
            'success' => true,
            'result' => [
                'id' => 'dataset-id',
                'name' => 'council-datasets',
                'title' => 'Набори даних ради',
                'resources' => [
                    ['id' => 'valid-resource-id', 'name' => 'Наявний ресурс', 'format' => 'CSV'],
                ],
            ],
        ]),
    ]);

    livewire(ListOpenData::class)
        ->callAction(TestAction::make('create'), [
            'package_id' => 'dataset-id',
            'resource_id' => 'resource-from-another-dataset',
            'code' => 'deputies',
        ])
        ->assertHasFormErrors(['resource_id' => 'in']);

    expect(OpenDataResource::query()->exists())->toBeFalse();
});

it('does not offer editing or bulk deletion for open data resources', function (): void {
    $resource = OpenDataResource::factory()->create();

    $component = livewire(ListOpenData::class)
        ->assertTableActionDoesNotExist('edit', record: $resource);

    expect($component->instance()->getTable()->getToolbarActions())->toBeEmpty();
});

it('allows deleting an individual open data resource', function (): void {
    $resource = OpenDataResource::factory()->create();

    livewire(ListOpenData::class)
        ->callAction(TestAction::make('delete')->table($resource))
        ->assertNotified();

    $this->assertModelMissing($resource);
});

it('searches CKAN datasets within the configured organization', function (): void {
    CouncilOrganization::factory()->create([
        'open_data_id' => '11111111-2222-4333-8444-555555555555',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'data.gov.ua/api/3/action/package_search*' => Http::response([
            'success' => true,
            'result' => [
                'results' => [
                    [
                        'id' => 'dataset-id',
                        'name' => 'council-deputies',
                        'title' => 'Дані про депутатів',
                    ],
                ],
            ],
        ]),
    ]);

    $packages = app(OpenDataPortal::class)->searchPackages('депутати');

    expect($packages)->toBe(['dataset-id' => 'Дані про депутатів']);

    Http::assertSent(function (Request $request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return str_contains($request->url(), '/package_search')
            && ($query['q'] ?? null) === 'депутати'
            && ($query['rows'] ?? null) === '10'
            && ($query['fq'] ?? null) === 'owner_org:"11111111-2222-4333-8444-555555555555"';
    });
});

it('returns no package options when the portal search is unsuccessful', function (): void {
    CouncilOrganization::factory()->create([
        'open_data_id' => '123e4567-e89b-42d3-a456-426614174000',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'data.gov.ua/api/3/action/package_search*' => Http::response([
            'success' => false,
            'error' => ['message' => 'Portal unavailable'],
        ]),
    ]);

    expect(app(OpenDataPortal::class)->searchPackages('deputies'))->toBe([]);
});

it('searches resources only within the selected dataset', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'data.gov.ua/api/3/action/package_show*' => Http::response([
            'success' => true,
            'result' => [
                'resources' => [
                    [
                        'id' => 'deputies-resource-id',
                        'name' => 'Депутати',
                        'description' => 'Список депутатів',
                        'format' => 'CSV',
                    ],
                    [
                        'id' => 'posts-resource-id',
                        'name' => 'Посади',
                        'description' => 'Список посад',
                        'format' => 'CSV',
                    ],
                ],
            ],
        ]),
    ]);

    $resources = app(OpenDataPortal::class)->searchResources('deputies-dataset-id', 'депутат');

    expect($resources)->toBe(['deputies-resource-id' => 'Депутати (CSV)']);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/package_show')
        && $request->data()['id'] === 'deputies-dataset-id');
});

it('returns no resource options when CKAN reports an unsuccessful response', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'data.gov.ua/api/3/action/package_show*' => Http::response([
            'success' => false,
            'error' => ['message' => 'Dataset unavailable'],
        ]),
    ]);

    expect(app(OpenDataPortal::class)->searchResources('dataset-id', 'resource'))->toBe([]);
});

it('restricts open data resource settings to administrators', function (): void {
    $this->actingAs(User::factory()->create());

    livewire(ListOpenData::class)->assertForbidden();
    $this->get(OpenDataFilamentResource::getUrl())->assertForbidden();
});

it('lists configured open data resources', function (): void {
    $resource = OpenDataResource::factory()->create([
        'package_id' => 'dataset-id',
        'resource_id' => 'resource-id',
        'code' => 'deputies',
    ]);

    livewire(ListOpenData::class)
        ->assertCanSeeTableRecords([$resource])
        ->assertSee('Відкриті Дані')
        ->assertSee('deputies')
        ->assertSee('dataset-id')
        ->assertSee('resource-id');
});

it('provides only the list page for open data resources', function (): void {
    expect(array_keys(OpenDataFilamentResource::getPages()))->toBe(['index']);
});

it('uses the requested singular table and stores only portal identifiers and code', function (): void {
    $resource = OpenDataResource::factory()->create([
        'package_id' => 'dataset-id',
        'resource_id' => 'resource-id',
        'code' => 'deputies',
    ]);

    expect($resource->getTable())->toBe('open_data_resource')
        ->and(array_keys($resource->getAttributes()))->toContain(
            'package_id',
            'resource_id',
            'code',
            'created_at',
            'updated_at',
        );
});
