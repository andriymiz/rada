<?php

namespace App\Models;

use App\Enums\VoteResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RollCallVote extends Model
{
    protected $fillable = [
        'question_id',
        'deputy_id',
        'original_name',
        'result',
        'notes',
        'confirmed_by',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'result' => VoteResult::class,
            'confirmed_at' => 'datetime',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function deputy(): BelongsTo
    {
        return $this->belongsTo(Deputy::class);
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
