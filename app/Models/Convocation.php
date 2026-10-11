<?php

namespace App\Models;

use Database\Factories\ConvocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property int|null $organization_id
 */
#[Fillable(['organization_id', 'name'])]
class Convocation extends Model
{
    /** @use HasFactory<ConvocationFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Convocation $convocation): void {
            if ($convocation->organization_id !== null) {
                return;
            }

            throw ValidationException::withMessages([
                'organization_id' => 'Скликання має належати до організації ради.',
            ]);
        });
    }

    /** @return BelongsTo<CouncilOrganization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(CouncilOrganization::class);
    }

    /** @return HasMany<CouncilSession, $this> */
    public function sessions(): HasMany
    {
        return $this->hasMany(CouncilSession::class, 'convocation_id');
    }

    /** @return HasMany<PermanentCommission, $this> */
    public function permanentCommissions(): HasMany
    {
        return $this->hasMany(PermanentCommission::class);
    }

    /** @return HasMany<Membership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /** @return HasManyThrough<PlenaryMeeting, CouncilSession, $this> */
    public function plenaryMeetings(): HasManyThrough
    {
        return $this->hasManyThrough(PlenaryMeeting::class, CouncilSession::class);
    }
}
