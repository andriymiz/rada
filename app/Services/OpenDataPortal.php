<?php

namespace App\Services;

use App\Enums\DatasetResourceType;
use App\Models\CouncilOrganization;
use App\Models\DatasetResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

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
        $organizationId = CouncilOrganization::query()->value('open_data_id');

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
        $result = Http::acceptJson()
            ->baseUrl(config('services.open_data.base_url'))
            ->get('/api/3/action/package_show', ['id' => $packageId])
            ->throw()
            ->collect('result');

        return $result->get('title') ?? $result->get('name');
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

    public function getResourceDownloadUrl(DatasetResourceType $type): ?string
    {
        $configuredResource = DatasetResource::query()
            ->with('dataset')
            ->where('type', $type->value)
            ->first();

        if ($configuredResource === null) {
            return null;
        }

        $resource = $this->getPackageResources($configuredResource->dataset->external_id)
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
        $payload = Http::acceptJson()
            ->baseUrl(config('services.open_data.base_url'))
            ->get('/api/3/action/package_show', ['id' => $packageId])
            ->throw()
            ->collect();

        if ($payload->get('success') !== true) {
            return collect();
        }

        $result = $payload->get('result');
        $resources = is_array($result) ? ($result['resources'] ?? []) : [];

        return is_array($resources) ? collect($resources) : collect();
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
