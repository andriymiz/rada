<?php

use App\Enums\DatasetResourceType;
use App\Models\Dataset;
use App\Models\DatasetResource;
use App\Services\OpenDataPortal;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

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

it('gets the resource download URL from its related dataset and type', function (): void {
    $dataset = Dataset::factory()->create([
        'external_id' => 'deputies-dataset-id',
    ]);
    DatasetResource::factory()
        ->for($dataset)
        ->create([
            'external_id' => 'deputies-resource-id',
            'type' => DatasetResourceType::Deputies,
        ]);

    Http::preventStrayRequests();
    Http::fake([
        'data.gov.ua/api/3/action/package_show*' => Http::response([
            'success' => true,
            'result' => [
                'resources' => [
                    [
                        'id' => 'deputies-resource-id',
                        'url' => 'https://data.gov.ua/dataset/deputies/resource/deputies.csv',
                    ],
                ],
            ],
        ]),
    ]);

    expect(app(OpenDataPortal::class)->getResourceDownloadUrl(DatasetResourceType::Deputies))
        ->toBe('https://data.gov.ua/dataset/deputies/resource/deputies.csv');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/package_show')
        && $request->data()['id'] === 'deputies-dataset-id');
});

it('returns no download URL when no resource is configured for the requested type', function (): void {
    Http::preventStrayRequests();

    expect(app(OpenDataPortal::class)->getResourceDownloadUrl(DatasetResourceType::Register))
        ->toBeNull();
});
