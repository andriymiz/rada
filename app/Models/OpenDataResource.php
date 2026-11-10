<?php

namespace App\Models;

use Database\Factories\OpenDataResourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['package_id', 'resource_id', 'code'])]
class OpenDataResource extends Model
{
    protected $table = 'open_data_resource';

    /** @use HasFactory<OpenDataResourceFactory> */
    use HasFactory;
}
