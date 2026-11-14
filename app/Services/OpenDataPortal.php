<?php

namespace App\Services;

use App\Enums\DatasetResourceType;
use App\Models\CouncilOrganization;
use App\Models\Dataset;
use App\Models\DatasetMeta;
use App\Models\DatasetResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

class OpenDataPortal
{
    public function getOrganizationUrl(): string
    {
        $baseUrl = rtrim(config('services.open_data.base_url'), '/');
        $organizationSlug = config('services.open_data.organization_slug');

        return filled($organizationSlug)
            ? $baseUrl.'/organization/'.rawurlencode($organizationSlug)
            : $baseUrl;
    }

    /**
     * @return array<string, string>
     */
    public function searchPackages(string $search): array
    {
        $organizationId = CouncilOrganization::query()
            ->whereNotNull('open_data_id')
            ->value('open_data_id');

        return Http::acceptJson()
            ->baseUrl(config('services.open_data.base_url'))
            ->get('/api/3/action/package_search', [
                'q' => filled($search) ? $search : '*',
                'rows' => 10,
                'fq' => 'owner_org:"'.$organizationId.'"',
            ])
            ->throw()
            ->collect('result.results')
            ->mapWithKeys(fn (array $package): array => [
                $package['id'] => $package['title'] ?? $package['name'],
            ])
            ->all();
    }

    public function getPackageLabel(string $packageId): string
    {
        $package = $this->getPackage($packageId);
        $label = $package['title'] ?? $package['name'] ?? null;

        if (! is_string($label) || blank($label)) {
            throw new UnexpectedValueException("The open data package [{$packageId}] has no label.");
        }

        return $label;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPackageMetadata(string $packageId): array
    {
        $package = $this->getPackage($packageId);

        return [
            'description' => $package['notes'] ?? '',
            'accrual_periodicity' => $package['update_frequency'],
            'keywords' => collect(is_array($package['tags'] ?? null) ? $package['tags'] : [])
                ->map(fn (mixed $tag): mixed => is_array($tag) ? ($tag['name'] ?? null) : $tag)
                ->filter(fn (mixed $keyword): bool => is_string($keyword) && filled($keyword))
                ->values()
                ->all(),
            'purpose' => $package['purpose_of_collecting_information'],
            'contact_point_name' => $package['maintainer'],
            'contact_point_email' => $package['maintainer_email'],
        ];
    }

    public function syncDatasetMetadata(Dataset $dataset): DatasetMeta
    {
        $packageId = $dataset->external_id;

        if (! is_string($packageId) || blank($packageId)) {
            throw new UnexpectedValueException('The dataset must be linked to the open data portal.');
        }

        $metadata = $this->getPackageMetadata($packageId);

        $savedMetadata = $dataset->meta()->updateOrCreate([], $metadata);
        $dataset->setRelation('meta', $savedMetadata);

        return $savedMetadata;
    }

    /**
     * @param  array<int, string>  $resourceIds
     * @return array<int, string>
     */
    public function getPackageResourceFormats(string $packageId, array $resourceIds): array
    {
        if ($resourceIds === []) {
            return [];
        }

        return $this->getPackageResources($packageId)
            ->filter(fn (mixed $resource): bool => is_array($resource)
                && is_string($resource['id'] ?? null)
                && in_array($resource['id'], $resourceIds, true))
            ->map(fn (array $resource): mixed => $resource['format'] ?? null)
            ->filter(fn (mixed $format): bool => is_string($format) && filled($format))
            ->unique()
            ->values()
            ->all();
    }

    public function syncDatasetResources(Dataset $dataset): int
    {
        $packageId = $dataset->external_id;

        if (! is_string($packageId) || blank($packageId)) {
            throw new UnexpectedValueException('The dataset must be linked to the open data portal.');
        }

        $package = $this->getPackage($packageId);
        $portalResources = $package['resources'] ?? null;

        if (! is_array($portalResources)) {
            throw new UnexpectedValueException("The open data package [{$packageId}] has no resource list.");
        }

        $resourcesById = [];

        foreach ($portalResources as $resource) {
            if (
                ! is_array($resource)
                || ! is_string($resource['id'] ?? null)
                || blank($resource['id'])
                || ! is_string($resource['name'] ?? null)
                || blank($resource['name'])
            ) {
                throw new UnexpectedValueException("The open data package [{$packageId}] contains an invalid resource.");
            }

            $resourceId = $resource['id'];
            $format = $resource['format'] ?? null;

            if (array_key_exists($resourceId, $resourcesById)) {
                throw new UnexpectedValueException("The open data package [{$packageId}] contains a duplicate resource ID.");
            }

            if ($format !== null && ! is_string($format)) {
                throw new UnexpectedValueException("The open data resource [{$resourceId}] has an invalid format.");
            }

            $resourcesById[$resourceId] = [
                'name' => $resource['name'],
                'format' => is_string($format) && filled($format) ? $format : null,
            ];
        }

        DB::transaction(function () use ($dataset, $resourcesById): void {
            foreach ($resourcesById as $resourceId => $data) {
                $dataset->resources()->updateOrCreate(
                    ['external_id' => $resourceId],
                    $data,
                );
            }

            if ($resourcesById === []) {
                $dataset->resources()->delete();

                return;
            }

            $dataset->resources()
                ->whereNotIn('external_id', array_keys($resourcesById))
                ->delete();
        });

        return count($resourcesById);
    }

    /**
     * @return array<string, string>
     */
    public function searchResources(string $packageId, string $search): array
    {
        $search = mb_strtolower($search);

        return $this->getPackageResources($packageId)
            ->filter(function (mixed $resource) use ($search): bool {
                if (! is_array($resource) || ! is_string($resource['id'] ?? null)) {
                    return false;
                }

                if ($search === '') {
                    return true;
                }

                $searchableValues = array_filter([
                    $resource['id'],
                    $resource['name'] ?? null,
                    $resource['description'] ?? null,
                    $resource['format'] ?? null,
                ], fn (mixed $value): bool => is_string($value));
                $searchableText = mb_strtolower(implode(' ', $searchableValues));

                return str_contains($searchableText, $search);
            })
            ->mapWithKeys(fn (array $resource): array => [
                $resource['id'] => $this->getResourceName($resource),
            ])
            ->all();
    }

    public function getResourceLabel(string $packageId, string $resourceId): string
    {
        return $this->searchResources($packageId, '')[$resourceId] ?? $resourceId;
    }

    public function getResourcePageUrl(string $packageId, string $resourceId): string
    {
        return rtrim(config('services.open_data.base_url'), '/')
            .'/dataset/'.rawurlencode($packageId)
            .'/resource/'.rawurlencode($resourceId);
    }

    public function getResourceDownloadUrl(DatasetResourceType $type): ?string
    {
        $configuredResources = DatasetResource::query()
            ->with('dataset')
            ->where('name', $type)
            ->get();

        if ($configuredResources->isEmpty()) {
            return null;
        }

        if ($configuredResources->count() > 1) {
            throw new UnexpectedValueException(
                "Multiple portal resources are configured for [{$type}].",
            );
        }

        $configuredResource = $configuredResources->sole();
        $packageId = $configuredResource->dataset->external_id;

        if (! is_string($packageId) || blank($packageId)) {
            return null;
        }

        $resource = $this->getPackageResources($packageId)
            ->first(fn (mixed $resource): bool => is_array($resource)
                && ($resource['id'] ?? null) === $configuredResource->external_id);
        $url = is_array($resource) ? ($resource['url'] ?? null) : null;

        if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)
            ? $url
            : null;
    }

    /**
     * @return Collection<int, mixed>
     */
    private function getPackageResources(string $packageId): Collection
    {
        $resources = $this->getPackage($packageId)['resources'] ?? [];

        return collect(is_array($resources) ? $resources : []);
    }

    /**
     * @return array<string, mixed>
     */
    private function getPackage(string $packageId): array
    {
        return Http::acceptJson()
            ->connectTimeout(3)
            ->timeout(10)
            ->baseUrl(config('services.open_data.base_url'))
            ->get('/api/3/action/package_show', ['id' => $packageId])
            ->throw()
            ->json('result');
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    private function getResourceName(array $resource): string
    {
        $name = $resource['name'] ?? null;
        $format = $resource['format'] ?? null;

        if (is_string($name) && filled($name)) {
            return is_string($format) && filled($format)
                ? "{$name} ({$format})"
                : $name;
        }

        return $resource['id'];
    }
}
