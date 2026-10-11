<?php

namespace App\Models;

use Database\Factories\ConvocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class Convocation extends Model
{
    /** @use HasFactory<ConvocationFactory> */
    use HasFactory;

    /** @return HasMany<CouncilSession, $this> */
    public function sessions(): HasMany
    {
        return $this->hasMany(CouncilSession::class, 'convocation_id');
    }

    /** @return HasMany<PermanentCommission, $this> */
    public function permanentCommissions(): HasMany
    {
        return $this->hasMany(PermanentCommission::class);
    }

    /** @return HasMany<Membership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }
}
