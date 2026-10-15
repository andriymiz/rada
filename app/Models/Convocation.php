<?php

namespace App\Models;

use Database\Factories\ConvocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property string $label
 * @property Carbon|null $date_from
 * @property Carbon|null $date_until
 */
#[Fillable(['organization_id', 'label', 'date_from', 'date_until'])]
class Convocation extends Model
{
    /** @use HasFactory<ConvocationFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Convocation $convocation): void {
            if (! $convocation->exists && $convocation->organization_id === null) {
                $organization = CouncilOrganization::query()->orderBy('id')->first();

                if ($organization !== null) {
                    $convocation->organization()->associate($organization);
                }
            }

            if ($convocation->organization_id !== null) {
                return;
            }

            throw ValidationException::withMessages([
                'organization_id' => 'Скликання має належати до організації ради.',
            ]);
        });
    }

    protected function casts(): array
    {
        return [
            'date_from' => 'date:d.m.Y',
            'date_until' => 'date:d.m.Y',
        ];
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

    /** @return HasMany<Faction, $this> */
    public function factions(): HasMany
    {
        return $this->hasMany(Faction::class);
    }

    /** @return HasMany<Deputy, $this> */
    public function deputies(): HasMany
    {
        return $this->hasMany(Deputy::class);
    }

    /** @return HasManyThrough<PlenaryMeeting, CouncilSession, $this> */
    public function plenaryMeetings(): HasManyThrough
    {
        return $this->hasManyThrough(PlenaryMeeting::class, CouncilSession::class);
    }
}
