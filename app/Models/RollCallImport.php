<?php

namespace App\Models;

use App\Enums\RollCallImportStatus;
use Database\Factories\RollCallImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $session_id
 * @property int $user_id
 * @property string $file_path
 * @property string $original_filename
 * @property RollCallImportStatus $status
 * @property string|null $error_message
 * @property array<string, mixed>|null $parsed_result
 * @property Carbon|null $processed_at
 */
#[Fillable(['session_id', 'user_id', 'file_path', 'original_filename', 'status', 'error_message', 'parsed_result', 'processed_at'])]
class RollCallImport extends Model
{
    /** @use HasFactory<RollCallImportFactory> */
    use HasFactory;

    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => RollCallImportStatus::class,
            'parsed_result' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function sessionLabel(): string
    {
        $session = $this->session;

        if ($session !== null) {
            $convocation = $session->convocation;

            return $convocation === null
                ? $session->name
                : "{$session->name} ({$convocation->name})";
        }

        $parsedSession = $this->parsed_result['session'] ?? null;

        if (is_string($parsedSession) && filled($parsedSession)) {
            if (preg_match('/^(.+сесія)\s+(.+скликання)$/u', $parsedSession, $matches) === 1) {
                return "{$matches[1]} ({$matches[2]})";
            }

            return $parsedSession;
        }

        return '-';
    }

    /**
     * @param  Builder<RollCallImport>  $query
     * @return Builder<RollCallImport>
     */
    #[Scope]
    protected function pendingOrProcessing(Builder $query): Builder
    {
        return $query->whereIn('status', [
            RollCallImportStatus::Queued->value,
            RollCallImportStatus::Processing->value,
        ]);
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
