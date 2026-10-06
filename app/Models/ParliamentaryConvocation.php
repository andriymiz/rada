<?php

namespace App\Models;

use Database\Factories\ParliamentaryConvocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class ParliamentaryConvocation extends Model
{
    /** @use HasFactory<ParliamentaryConvocationFactory> */
    use HasFactory;

    /** @return HasMany<ParliamentarySession, $this> */
    public function sessions(): HasMany
    {
        return $this->hasMany(ParliamentarySession::class, 'convocation_id');
    }
}
