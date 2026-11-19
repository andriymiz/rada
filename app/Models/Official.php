<?php

namespace App\Models;

use Database\Factories\OfficialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * @property bool $is_post
 * @property array<int, string>|null $social_accounts
 * @property string|null $email
 * @property string|null $phone
 * @property array<int, array{name: string, value: string}>|null $additional_contacts
 * @property-read Reception|null $reception
 */
#[Fillable([
    'person_id',
    'organization_id',
    'position',
    'is_post',
    'image',
    'homepage',
    'social_accounts',
    'email',
    'phone',
    'additional_contacts',
    'opening_hours',
    'availability_restriction',
])]
class Official extends Model
{
    /** @use HasFactory<OfficialFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_post' => 'boolean',
            'social_accounts' => 'array',
            'additional_contacts' => 'array',
        ];
    }

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasMany<OrganizationUnit, $this> */
    public function headedUnits(): HasMany
    {
        return $this->hasMany(OrganizationUnit::class, 'head_id');
    }

    /** @return MorphOne<Reception, $this> */
    public function reception(): MorphOne
    {
        return $this->morphOne(Reception::class, 'receptionable');
    }
}
