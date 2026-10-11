<?php

namespace App\Models;

use App\Enums\VoteOption;
use Database\Factories\VoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['vote_event_id', 'person_id', 'voter_identifier', 'voter_name', 'option'])]
class Vote extends Model
{
    /** @use HasFactory<VoteFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Vote $vote): void {
            if (! $vote->isDirty('person_id') || $vote->person_id === null) {
                return;
            }

            $person = $vote->person()->firstOrFail();
            $vote->voter_identifier = $person->voting_identifier;
            $vote->voter_name = $person->name;
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

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
