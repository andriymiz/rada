<?php

namespace App\Models;

use App\Enums\VoteOption;
use Database\Factories\VoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['vote_event_id', 'deputy_id', 'voter_identifier', 'voter_name', 'option'])]
class Vote extends Model
{
    /** @use HasFactory<VoteFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Vote $vote): void {
            if ((! $vote->isDirty('deputy_id') && ! $vote->isDirty('vote_event_id')) || $vote->deputy_id === null) {
                return;
            }

            $deputy = $vote->deputy()->firstOrFail();
            $voteEvent = $vote->voteEvent()->with('motion.plenaryMeeting.councilSession')->first();
            $convocationId = $voteEvent?->motion?->plenaryMeeting?->councilSession?->convocation_id;

            $vote->voter_identifier = $convocationId === null
                ? null
                : $deputy->memberships()
                    ->where('convocation_id', $convocationId)
                    ->value('voting_identifier');
            $vote->voter_name = $deputy->full_name;
        });
    }

    protected function casts(): array
    {
        return [
            'option' => VoteOption::class,
        ];
    }

    /** @return BelongsTo<VoteEvent, $this> */
    public function voteEvent(): BelongsTo
    {
        return $this->belongsTo(VoteEvent::class);
    }

    /** @return BelongsTo<Deputy, $this> */
    public function deputy(): BelongsTo
    {
        return $this->belongsTo(Deputy::class);
    }
}
