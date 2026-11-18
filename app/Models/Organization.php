<?php

namespace App\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * @property string $name
 * @property string|null $short_name
 * @property string $edrpou
 * @property string|null $katoottg
 * @property string|null $description
 * @property string|null $constituent_doc
 * @property string|null $homepage
 * @property array<int, string>|null $social_accounts
 * @property array<int, string>|null $emails
 * @property array<int, string>|null $phones
 * @property string|null $logo
 * @property int|null $parent_org_id
 * @property-read Official|null $head
 * @property-read Organization|null $parentOrganization
 */
#[Fillable([
    'name',
    'short_name',
    'edrpou',
    'katoottg',
    'description',
    'constituent_doc',
    'homepage',
    'social_accounts',
    'contact_point',
    'emails',
    'phones',
    'opening_hours',
    'availability_restriction',
    'logo',
    'parent_org_id',
])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'social_accounts' => 'array',
            'emails' => 'array',
            'phones' => 'array',
        ];
    }

    /** @return HasManyThrough<Deputy, Convocation, $this> */
    public function deputies(): HasManyThrough
    {
        return $this->hasManyThrough(
            Deputy::class,
            Convocation::class,
            'organization_id',
            'convocation_id',
            'id',
            'id',
        );
    }

    /** @return HasMany<Official, $this> */
    public function officials(): HasMany
    {
        return $this->hasMany(Official::class);
    }

    /** @return HasMany<OrganizationUnit, $this> */
    public function organizationUnits(): HasMany
    {
        return $this->hasMany(OrganizationUnit::class);
    }

    /** @return HasOne<Official, $this> */
    public function head(): HasOne
    {
        return $this->hasOne(Official::class)->where('is_head', true);
    }

    /** @return BelongsTo<Organization, $this> */
    public function parentOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'parent_org_id');
    }

    /** @return HasMany<Organization, $this> */
    public function childOrganizations(): HasMany
    {
        return $this->hasMany(Organization::class, 'parent_org_id');
    }

    public function setHead(Official $official): void
    {
        if (! $this->exists || ! $official->exists || ! $this->officials()->whereKey($official->getKey())->exists()) {
            throw new LogicException('Керівником можна призначити лише посадовця цієї організації.');
        }

        DB::transaction(function () use ($official): void {
            $this->newQuery()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->officials()
                ->where('is_head', true)
                ->whereKeyNot($official->getKey())
                ->update(['is_head' => false]);

            $official->is_head = true;
            $official->save();
        });
    }

    /** @return HasMany<Convocation, $this> */
    public function convocations(): HasMany
    {
        return $this->hasMany(Convocation::class, 'organization_id');
    }
}
