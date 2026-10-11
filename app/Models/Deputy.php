<?php

namespace App\Models;

use Database\Factories\DeputyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Validation\ValidationException;

/**
 * @property-read Person $person
 * @property-read string $full_name
 * @property-read string $short_name
 */
#[Fillable([
    'person_id',
    'convocation_id',
    'political_party_id',
    'permanent_commission_id',
    'faction_id',
    'voting_identifier',
    'role',
    'start_date',
    'end_date',
    'faction_position',
    'commission_position',
    'area_id',
    'homepage',
    'biography',
])]
class Deputy extends Model
{
    /** @use HasFactory<DeputyFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Deputy $deputy): void {
            if (
                $deputy->permanent_commission_id !== null
                && ! $deputy->permanentCommission()
                    ->where('convocation_id', $deputy->convocation_id)
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'permanent_commission_id' => 'Постійна комісія має належати до вибраного скликання.',
                ]);
            }

            if (
                $deputy->faction_id !== null
                && ! $deputy->faction()
                    ->where('convocation_id', $deputy->convocation_id)
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'faction_id' => 'Фракція має належати до вибраного скликання.',
                ]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /** @return Attribute<string, never> */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => $this->person->full_name);
    }

    /** @return Attribute<string, never> */
    protected function shortName(): Attribute
    {
        return Attribute::get(fn (): string => $this->person->short_name);
    }

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /** @return BelongsTo<Convocation, $this> */
    public function convocation(): BelongsTo
    {
        return $this->belongsTo(Convocation::class);
    }

    /** @return BelongsTo<PoliticalParty, $this> */
    public function politicalParty(): BelongsTo
    {
        return $this->belongsTo(PoliticalParty::class);
    }

    /** @return BelongsTo<Faction, $this> */
    public function faction(): BelongsTo
    {
        return $this->belongsTo(Faction::class);
    }

    /** @return BelongsTo<PermanentCommission, $this> */
    public function permanentCommission(): BelongsTo
    {
        return $this->belongsTo(PermanentCommission::class);
    }

    /** @return HasMany<Vote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    /** @return MorphMany<ContactPoint, $this> */
    public function contactPoints(): MorphMany
    {
        return $this->morphMany(ContactPoint::class, 'contactable');
    }

    /** @return HasOne<DeputyReception, $this> */
    public function reception(): HasOne
    {
        return $this->hasOne(DeputyReception::class);
    }
}
