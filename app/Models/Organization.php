<?php

namespace App\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property string $full_name
 * @property string|null $short_name
 * @property string $edrpou
 * @property int|null $territorial_unit_id
 * @property string|null $description
 * @property string|null $constituent_doc
 * @property string|null $homepage
 * @property array<int, string>|null $social_accounts
 * @property string|null $email
 * @property string|null $phone
 * @property array<int, array{name: string, value: string}>|null $additional_contacts
 * @property string|null $logo
 * @property int|null $parent_org_id
 * @property int|null $head_id
 * @property int|null $group_id
 * @property-read Employee|null $head
 * @property-read TerritorialUnit|null $territorialUnit
 * @property-read OrganizationGroup|null $group
 * @property-read Organization|null $parentOrganization
 */
#[Fillable([
    'full_name',
    'short_name',
    'edrpou',
    'territorial_unit_id',
    'description',
    'constituent_doc',
    'homepage',
    'social_accounts',
    'contact_point',
    'email',
    'phone',
    'additional_contacts',
    'opening_hours',
    'availability_restriction',
    'logo',
    'parent_org_id',
    'head_id',
    'group_id',
])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'social_accounts' => 'array',
            'additional_contacts' => 'array',
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

    /** @return HasMany<Employee, $this> */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /** @return HasMany<OrganizationUnit, $this> */
    public function organizationUnits(): HasMany
    {
        return $this->hasMany(OrganizationUnit::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function head(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'head_id');
    }

    /** @return BelongsTo<TerritorialUnit, $this> */
    public function territorialUnit(): BelongsTo
    {
        return $this->belongsTo(TerritorialUnit::class, 'territorial_unit_id', 'system_number');
    }

    /** @return BelongsTo<Organization, $this> */
    public function parentOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'parent_org_id');
    }

    /** @return BelongsTo<OrganizationGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(OrganizationGroup::class);
    }

    /** @return HasMany<Organization, $this> */
    public function childOrganizations(): HasMany
    {
        return $this->hasMany(Organization::class, 'parent_org_id');
    }

    /** @return HasMany<Convocation, $this> */
    public function convocations(): HasMany
    {
        return $this->hasMany(Convocation::class, 'organization_id');
    }
}
