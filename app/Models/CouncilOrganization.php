<?php

namespace App\Models;

use Database\Factories\CouncilOrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'edrpou', 'katoottg'])]
class CouncilOrganization extends Model
{
    /** @use HasFactory<CouncilOrganizationFactory> */
    use HasFactory;

    /** @return HasMany<Membership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'organization_id');
    }

    /** @return HasMany<PlenaryMeeting, $this> */
    public function plenaryMeetings(): HasMany
    {
        return $this->hasMany(PlenaryMeeting::class, 'organization_id');
    }
}
