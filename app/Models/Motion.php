<?php

namespace App\Models;

use App\Enums\MotionResult;
use Database\Factories\MotionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'uid',
    'plenary_meeting_id',
    'number',
    'title',
    'project_number',
    'result',
    'text_url',
    'text',
])]
class Motion extends Model
{
    /** @use HasFactory<MotionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'result' => MotionResult::class,
        ];
    }

    /** @return BelongsTo<PlenaryMeeting, $this> */
    public function plenaryMeeting(): BelongsTo
    {
        return $this->belongsTo(PlenaryMeeting::class);
    }

    /** @return HasMany<VoteEvent, $this> */
    public function voteEvents(): HasMany
    {
        return $this->hasMany(VoteEvent::class);
    }
}
