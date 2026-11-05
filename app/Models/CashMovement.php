<?php

namespace App\Models;

use Database\Factories\CashMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A movement of the cash box that is not a client payment, a shop payment or a logistics cost: money put
 * in (a contribution, a refund received) or taken out (a withdrawal, a small purchase). It is never edited
 * or deleted: a mistake is cancelled with a reason and stays in the books.
 *
 * @property int $id
 * @property string|null $reference
 * @property int|null $expense_id the logistics cost this movement pays, when it comes from one
 * @property int|null $closing_id the cash closing this movement adjusts, when it is the difference of a count
 * @property string $direction in or out
 * @property int $amount
 * @property Carbon $occurred_on
 * @property string $label
 * @property string|null $counterparty
 * @property string|null $document_reference
 * @property string|null $notes
 * @property string|null $idempotency_key
 * @property int|null $recorded_by
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by
 * @property string|null $cancel_reason
 */
#[Fillable(['expense_id', 'closing_id', 'direction', 'amount', 'occurred_on', 'label', 'counterparty', 'document_reference', 'notes', 'idempotency_key', 'recorded_by'])]
class CashMovement extends Model
{
    /** @use HasFactory<CashMovementFactory> */
    use HasFactory;

    public const IN = 'in';

    public const OUT = 'out';

    /**
     * The only fields that change after the movement is recorded: the ones recording a cancellation, and
     * the reference given right after the creation.
     */
    private const MUTABLE = ['reference', 'cancelled_at', 'cancelled_by', 'cancel_reason', 'updated_at'];

    protected static function booted(): void
    {
        static::updating(fn (self $movement): bool => array_diff(array_keys($movement->getDirty()), self::MUTABLE) === []);
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
            'amount' => 'integer',
            'occurred_on' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return BelongsTo<Expense, $this>
     */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }
}
