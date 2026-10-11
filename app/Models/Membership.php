<?php

namespace App\Models;

use Database\Factories\MembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable([
    'deputy_id',
    'organization_id',
    'convocation_id',
    'voting_identifier',
    'political_party_id',
    'permanent_commission_id',
    'faction_name',
    'faction_position',
    'commission_position',
    'area_id',
    'role',
    'start_date',
    'end_date',
])]
class Membership extends Model
{
    /** @use HasFactory<MembershipFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Membership $membership): void {
            if ($membership->permanent_commission_id === null || $membership->convocation_id === null) {
                return;
            }

            $commissionMatchesConvocation = $membership->permanentCommission()
                ->where('convocation_id', $membership->convocation_id)
                ->exists();

            if (! $commissionMatchesConvocation) {
                throw ValidationException::withMessages([
                    'permanent_commission_id' => 'Постійна комісія має належати до вибраного скликання.',
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

    /** @return BelongsTo<Deputy, $this> */
    public function deputy(): BelongsTo
    {
        return $this->belongsTo(Deputy::class);
    }

    /** @return BelongsTo<CouncilOrganization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(CouncilOrganization::class, 'organization_id');
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

    /** @return BelongsTo<PermanentCommission, $this> */
    public function permanentCommission(): BelongsTo
    {
        return $this->belongsTo(PermanentCommission::class);
    }
}
