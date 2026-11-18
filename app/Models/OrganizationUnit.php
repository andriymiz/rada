<?php

namespace App\Models;

use Database\Factories\OrganizationUnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'uid', 'organization_id', 'head_id', 'parent_unit_id', 'full_name', 'short_name', 'description',
    'constituent_doc', 'homepage', 'contact_point', 'email', 'phone', 'additional_contacts',
    'opening_hours', 'availability_restriction',
])]
class OrganizationUnit extends Model
{
    /** @use HasFactory<OrganizationUnitFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['additional_contacts' => 'array'];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Official, $this> */
    public function head(): BelongsTo
    {
        return $this->belongsTo(Official::class, 'head_id');
    }

    /** @return BelongsTo<OrganizationUnit, $this> */
    public function parentUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'parent_unit_id');
    }

    /** @return HasMany<OrganizationUnit, $this> */
    public function childUnits(): HasMany
    {
        return $this->hasMany(OrganizationUnit::class, 'parent_unit_id');
    }
}
