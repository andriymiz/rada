<?php

namespace App\Models;

use Database\Factories\DatasetGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property int $sort
 */
#[Fillable(['name', 'sort'])]
class DatasetGroup extends Model
{
    /** @use HasFactory<DatasetGroupFactory> */
    use HasFactory;

    /** @return HasMany<Dataset, $this> */
    public function datasets(): HasMany
    {
        return $this->hasMany(Dataset::class, 'group_id');
    }
}
