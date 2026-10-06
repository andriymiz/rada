<?php

namespace App\Models;

use Database\Factories\ParliamentarySessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['convocation_id', 'name'])]
class ParliamentarySession extends Model
{
    /** @use HasFactory<ParliamentarySessionFactory> */
    use HasFactory;

    /** @return BelongsTo<ParliamentaryConvocation, $this> */
    public function convocation(): BelongsTo
    {
        return $this->belongsTo(ParliamentaryConvocation::class, 'convocation_id');
    }

    /** @return HasMany<RollCallImport, $this> */
    public function rollCallImports(): HasMany
    {
        return $this->hasMany(RollCallImport::class, 'session_id');
    }

    /** @return HasMany<PlenaryMeeting, $this> */
    public function plenaryMeetings(): HasMany
    {
        return $this->hasMany(PlenaryMeeting::class, 'parliamentary_session_id');
    }
}
