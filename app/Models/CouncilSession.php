<?php

namespace App\Models;

use Database\Factories\CouncilSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['convocation_id', 'name'])]
class CouncilSession extends Model
{
    /** @use HasFactory<CouncilSessionFactory> */
    use HasFactory;

    /** @return BelongsTo<Convocation, $this> */
    public function convocation(): BelongsTo
    {
        return $this->belongsTo(Convocation::class);
    }

    /** @return HasMany<PlenaryMeeting, $this> */
    public function plenaryMeetings(): HasMany
    {
        return $this->hasMany(PlenaryMeeting::class, 'council_session_id');
    }
}
