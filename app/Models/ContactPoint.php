<?php

namespace App\Models;

use App\Enums\ContactPointType;
use Database\Factories\ContactPointFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['type', 'value', 'is_public', 'note'])]
class ContactPoint extends Model
{
    /** @use HasFactory<ContactPointFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => ContactPointType::class,
            'is_public' => 'boolean',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function contactable(): MorphTo
    {
        return $this->morphTo();
    }
}
