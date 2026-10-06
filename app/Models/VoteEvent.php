<?php

namespace App\Models;

use Database\Factories\VoteEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['motion_id', 'identifier', 'result', 'start_date', 'end_date'])]
class VoteEvent extends Model
{
    /** @use HasFactory<VoteEventFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
        ];
    }

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
