<?php

namespace App\Models;

use App\Enums\DocumentType;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A PDF that was issued: numbered, archived and attached to its record. It is never edited;
 * a wrong one is cancelled and replaced.
 *
 * @property int $id
 * @property DocumentType $type
 * @property string $number
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string $locale
 * @property string $file_path
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 * @property int|null $issued_by
 * @property Carbon $issued_at
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by
 * @property string|null $cancel_reason
 * @property int|null $replaced_by_id
 */
#[Fillable(['type', 'number', 'subject_type', 'subject_id', 'locale', 'file_path', 'period_start', 'period_end', 'issued_by', 'issued_at'])]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    /**
     * The only fields that change after issue: the ones recording a cancellation.
     */
    private const MUTABLE = ['cancelled_at', 'cancelled_by', 'cancel_reason', 'replaced_by_id', 'updated_at'];

    protected static function booted(): void
    {
        static::updating(fn (self $document): bool => array_diff(array_keys($document->getDirty()), self::MUTABLE) === []);
        static::deleting(fn (): bool => false);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'issued_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }
}
