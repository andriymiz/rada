<?php

namespace App\Models;

use Database\Factories\ReceptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
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
class Reception extends Model
{
    /** @use HasFactory<ReceptionFactory> */
    use HasFactory;

    /** @return MorphTo<Model, $this> */
    public function receptionable(): MorphTo
    {
        return $this->morphTo();
    }
}
