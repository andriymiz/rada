<?php

namespace App\Models;

use App\Enums\RollCallImportStatus;
use Database\Factories\RollCallImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $session_id
 * @property int $user_id
 * @property string $file_path
 * @property string $original_filename
 * @property RollCallImportStatus $status
 * @property Carbon|null $processed_at
 */
#[Fillable(['session_id', 'user_id', 'file_path', 'original_filename', 'status', 'processed_at'])]
class RollCallImport extends Model
{
    /** @use HasFactory<RollCallImportFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => RollCallImportStatus::class,
            'processed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ParliamentarySession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ParliamentarySession::class, 'session_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Motion, $this> */
    public function motions(): HasMany
    {
        return $this->hasMany(Motion::class);
    }
}
