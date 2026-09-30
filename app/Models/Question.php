<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'question_number',
        'title',
        'project_number',
        'voting_result',
        'decision_document_url',
        'decision_document_name',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(CouncilSession::class, 'session_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(RollCallVote::class);
    }
}
