<?php

namespace App\Models;

use Database\Factories\DatasetResourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $name
 * @property string|null $format
 */
#[Fillable(['dataset_id', 'external_id', 'name', 'format'])]
class DatasetResource extends Model
{
    /** @use HasFactory<DatasetResourceFactory> */
    use HasFactory;

    /** @return BelongsTo<Dataset, $this> */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }
}
