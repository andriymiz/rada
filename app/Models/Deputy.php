<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Deputy extends Model
{
    use HasFactory;

    protected $fillable = ['external_id', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function votes(): HasMany
    {
        return $this->hasMany(RollCallVote::class);
    }
}
