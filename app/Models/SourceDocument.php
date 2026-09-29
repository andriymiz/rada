<?php

namespace App\Models;

use App\Enums\SourceDocumentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SourceDocument extends Model
{
    protected $fillable = [
        'uploaded_by',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'sha256',
        'status',
    ];

    protected function casts(): array
    {
        return ['status' => SourceDocumentStatus::class];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function imports(): HasMany
    {
        return $this->hasMany(Import::class);
    }
}
