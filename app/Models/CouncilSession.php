<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CouncilSession extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'session_number', 'held_at', 'status'];

    protected function casts(): array
    {
        return ['held_at' => 'date:Y-m-d'];
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'session_id');
    }

    public function imports(): HasMany
    {
        return $this->hasMany(Import::class, 'session_id');
    }
}
