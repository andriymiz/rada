<?php

namespace App\Models;

use App\Enums\DatasetResourceType;
use Database\Factories\DatasetResourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property DatasetResourceType $type */
#[Fillable(['dataset_id', 'external_id', 'type'])]
class DatasetResource extends Model
{
    /** @use HasFactory<DatasetResourceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => DatasetResourceType::class,
        ];
    }

    /** @return BelongsTo<Dataset, $this> */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }
}
