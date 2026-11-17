<?php

namespace App\Models;

use Database\Factories\OfficialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * @property bool $is_head
 * @property array<int, string>|null $social_accounts
 * @property array<int, string>|null $emails
 * @property array<int, string>|null $phones
 * @property-read Reception|null $reception
 */
#[Fillable([
    'person_id',
    'organization_id',
    'position',
    'is_head',
    'image',
    'homepage',
    'social_accounts',
    'emails',
    'phones',
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
            'is_head' => 'boolean',
            'social_accounts' => 'array',
            'emails' => 'array',
            'phones' => 'array',
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

    /** @return MorphOne<Reception, $this> */
    public function reception(): MorphOne
    {
        return $this->morphOne(Reception::class, 'receptionable');
    }
}
