<?php

namespace App\Models;

use App\Enums\ImportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Import extends Model
{
    protected $fillable = [
        'source_document_id',
        'session_id',
        'uploaded_by',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return ['status' => ImportStatus::class];
    }

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(SourceDocument::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CouncilSession::class, 'session_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function stagedRecords(): HasMany
    {
        return $this->hasMany(StagedVoteRecord::class);
    }
}
