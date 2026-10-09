<?php

namespace App\Models;

use Database\Factories\PlenaryMeetingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'parliamentary_session_id', 'date'])]
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

    /** @return BelongsTo<ParliamentarySession, $this> */
    public function parliamentarySession(): BelongsTo
    {
        return $this->belongsTo(ParliamentarySession::class, 'parliamentary_session_id');
    }

    /** @return HasMany<Motion, $this> */
    public function motions(): HasMany
    {
        return $this->hasMany(Motion::class);
    }

    /** @return HasMany<RollCallImport, $this> */
    public function rollCallImports(): HasMany
    {
        return $this->hasMany(RollCallImport::class);
    }

    public function displayName(): string
    {
        return "{$this->parliamentarySession->name} сесія {$this->parliamentarySession->convocation->name} скликання ({$this->date->format('d.m.Y')})";
    }
}
