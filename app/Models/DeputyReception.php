<?php

namespace App\Models;

use Database\Factories\DeputyReceptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'deputy_id',
    'katoottg',
    'address_post_code',
    'address_admin_unit_l1',
    'address_admin_unit_l2',
    'address_admin_unit_l3',
    'address_admin_unit_l4',
    'address_post_name',
    'address_thoroughfare',
    'address_locator_designator',
    'address_locator_building',
    'address_locator_name',
    'opening_hours',
    'availability_restriction',
])]
class DeputyReception extends Model
{
    /** @use HasFactory<DeputyReceptionFactory> */
    use HasFactory;

    /** @return BelongsTo<Deputy, $this> */
    public function deputy(): BelongsTo
    {
        return $this->belongsTo(Deputy::class);
    }
}
