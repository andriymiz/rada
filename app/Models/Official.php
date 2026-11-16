<?php

namespace App\Models;

use App\Contracts\ContactPointable;
use Database\Factories\OfficialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * @property bool $is_head
 * @property-read Reception|null $reception
 */
#[Fillable([
    'person_id',
    'organization_id',
    'position',
    'is_head',
    'image',
    'homepage',
])]
class Official extends Model implements ContactPointable
{
    /** @use HasFactory<OfficialFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_head' => 'boolean',
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

    /** @return MorphMany<ContactPoint, $this> */
    public function contactPoints(): MorphMany
    {
        return $this->morphMany(ContactPoint::class, 'contactable');
    }

    /** @return MorphOne<Reception, $this> */
    public function reception(): MorphOne
    {
        return $this->morphOne(Reception::class, 'receptionable');
    }
}
