<?php

namespace App\Models;

use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A logistics cost (transport, handling, contingency) shared between the orders it concerns.
 * It is never edited or deleted: a mistake is cancelled with a reason.
 *
 * @property int $id
 * @property int $expense_type_id
 * @property int $amount
 * @property bool $paid_from_cash paid out of the cash box: a linked cash movement exists
 * @property Carbon $incurred_on
 * @property string $scope order, delivery, zone, shop or week
 * @property int|null $order_id
 * @property int|null $delivery_id
 * @property int|null $zone_id
 * @property int|null $shop_id
 * @property Carbon|null $week
 * @property string|null $notes
 * @property string|null $idempotency_key
 * @property string|null $proof_path
 * @property int|null $created_by
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by
 * @property string|null $cancel_reason
 */
#[Fillable(['expense_type_id', 'amount', 'paid_from_cash', 'incurred_on', 'scope', 'order_id', 'delivery_id', 'zone_id', 'shop_id', 'week', 'notes', 'idempotency_key', 'proof_path', 'created_by'])]
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory;

    /**
     * The only fields that change after the expense is recorded: the ones recording a cancellation, and the
     * mark "paid from the cash box" that an older cost receives, once and never removed, when it is attached to the
     * cash box (LinkExpensesToCash).
     */
    private const MUTABLE = ['cancelled_at', 'cancelled_by', 'cancel_reason', 'paid_from_cash', 'updated_at'];

    protected static function booted(): void
    {
        static::updating(fn (self $expense): bool => array_diff(array_keys($expense->getDirty()), self::MUTABLE) === []
            && ! ($expense->isDirty('paid_from_cash') && $expense->getOriginal('paid_from_cash')));
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
            'paid_from_cash' => 'boolean',
            'incurred_on' => 'date',
            'week' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ExpenseType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(ExpenseType::class, 'expense_type_id');
    }

    /**
     * The cash movement that records the payment of this expense out of the cash box, if it was paid so.
     *
     * @return HasOne<CashMovement, $this>
     */
    public function cashMovement(): HasOne
    {
        return $this->hasOne(CashMovement::class);
    }

    /**
     * @return HasMany<ExpenseAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(ExpenseAllocation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }
}
