<?php

namespace App\Models;

use App\Enums\StagedRecordStatus;
use App\Enums\VoteResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StagedVoteRecord extends Model
{
    protected $fillable = [
        'import_id',
        'question_id',
        'question_number',
        'question_title',
        'deputy_id',
        'deputy_name',
        'original_name',
        'raw_result',
        'recognized_result',
        'raw_payload',
        'status',
        'validation_error',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'recognized_result' => VoteResult::class,
            'status' => StagedRecordStatus::class,
            'raw_payload' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function deputy(): BelongsTo
    {
        return $this->belongsTo(Deputy::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
