<?php

namespace App\Models;

use Database\Factories\VoteEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['motion_id', 'identifier', 'result'])]
class VoteEvent extends Model
{
    /** @use HasFactory<VoteEventFactory> */
    use HasFactory;

    /** @return BelongsTo<Motion, $this> */
    public function motion(): BelongsTo
    {
        return $this->belongsTo(Motion::class);
    }

    /** @return HasMany<Vote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }
}
