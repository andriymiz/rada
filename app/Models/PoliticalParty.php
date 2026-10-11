<?php

namespace App\Models;

use Database\Factories\PoliticalPartyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'edrpou'])]
class PoliticalParty extends Model
{
    /** @use HasFactory<PoliticalPartyFactory> */
    use HasFactory;

    /** @return HasMany<Membership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }
}
