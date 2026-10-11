<?php

namespace App\Models;

use Database\Factories\PlenaryMeetingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $council_session_id
 * @property Carbon $date
 * @property-read CouncilSession $councilSession
 * @property-read int $motions_count
 * @property-read int $imports_awaiting_review_count
 * @property-read int $imports_failed_count
 * @property-read int $imports_processing_count
 */
#[Fillable(['organization_id', 'council_session_id', 'date'])]
class PlenaryMeeting extends Model
{
    /** @use HasFactory<PlenaryMeetingFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date' => 'date:d.m.Y',
        ];
    }

    /** @return BelongsTo<CouncilOrganization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(CouncilOrganization::class, 'organization_id');
    }

    /** @return BelongsTo<CouncilSession, $this> */
    public function councilSession(): BelongsTo
    {
        return $this->belongsTo(CouncilSession::class);
    }

    /** @return HasMany<Motion, $this> */
    public function motions(): HasMany
    {
        return $this->hasMany(Motion::class);
    }

    /** @return HasManyThrough<VoteEvent, Motion, $this> */
    public function voteEvents(): HasManyThrough
    {
        return $this->hasManyThrough(VoteEvent::class, Motion::class);
    }

    /** @return HasMany<RollCallImport, $this> */
    public function rollCallImports(): HasMany
    {
        return $this->hasMany(RollCallImport::class);
    }

    public function displayName(): string
    {
        return "{$this->councilSession->name} сесія {$this->councilSession->convocation->name} скликання";
    }
}
