<?php

namespace App\Models;

use Database\Factories\OfficialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

#[Fillable([
    'person_id',
    'organization_id',
    'position',
    'image',
    'homepage',
])]
class Official extends Model
{
    /** @use HasFactory<OfficialFactory> */
    use HasFactory;

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /** @return BelongsTo<CouncilOrganization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(CouncilOrganization::class);
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
