<?php

namespace App\Models;

use Database\Factories\CouncilOrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['name', 'edrpou', 'katoottg', 'open_data_id'])]
class CouncilOrganization extends Model
{
    /** @use HasFactory<CouncilOrganizationFactory> */
    use HasFactory;

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

    /** @return HasMany<Convocation, $this> */
    public function convocations(): HasMany
    {
        return $this->hasMany(Convocation::class, 'organization_id');
    }
}
