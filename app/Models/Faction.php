<?php

namespace App\Models;

use Database\Factories\FactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['convocation_id', 'name'])]
class Faction extends Model
{
    /** @use HasFactory<FactionFactory> */
    use HasFactory;

    /** @return BelongsTo<Convocation, $this> */
    public function convocation(): BelongsTo
    {
        return $this->belongsTo(Convocation::class);
    }

    /** @return HasMany<Deputy, $this> */
    public function deputies(): HasMany
    {
        return $this->hasMany(Deputy::class);
    }
}
