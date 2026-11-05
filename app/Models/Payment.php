<?php

namespace App\Models;

use App\Enums\PaymentKind;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Money received from a client or given back. Never edited or deleted: a mistake is
 * corrected by a refund that points to the payment it cancels.
 *
 * @property int $id
 * @property string|null $receipt_number
 * @property int $order_id
 * @property PaymentKind $kind
 * @property int $amount
 * @property int $payment_method_id
 * @property string|null $reference
 * @property string|null $payer_name
 * @property string|null $payer_phone
 * @property Carbon $paid_on
 * @property int|null $collected_by
 * @property int|null $recorded_by
 * @property int|null $reverses_payment_id
 * @property string|null $notes
 * @property string|null $idempotency_key
 */
#[Fillable(['receipt_number', 'order_id', 'kind', 'amount', 'payment_method_id', 'reference', 'payer_name', 'payer_phone', 'paid_on', 'collected_by', 'recorded_by', 'reverses_payment_id', 'notes', 'idempotency_key'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * A payment is never modified or removed once recorded.
     */
    protected static function booted(): void
    {
        static::updating(fn (): bool => false);
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
            'kind' => PaymentKind::class,
            'amount' => 'integer',
            'paid_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<PaymentMethod, $this>
     */
    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    /**
     * The logged-in user who entered the payment in the app.
     *
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * The columns a list of payments can be sorted by (the keys of the column headers).
     */
    public const SORTABLE = ['receipt', 'date', 'client', 'kind', 'method', 'amount'];

    /**
     * Sorts a list of payments by one of the SORTABLE keys, newest first when none is given.
     *
     * @param  Builder<Payment>  $query
     * @param  'asc'|'desc'  $direction
     * @return Builder<Payment>
     */
    public function scopeSortedBy(Builder $query, ?string $sort, string $direction = 'asc'): Builder
    {
        return (match ($sort) {
            'receipt' => $query->orderBy('receipt_number', $direction),
            'date' => $query->orderBy('paid_on', $direction),
            'client' => $query->orderBy(Client::query()->select('clients.name')
                ->join('orders', 'orders.client_id', '=', 'clients.id')
                ->whereColumn('orders.id', 'payments.order_id'), $direction),
            'kind' => $query->orderBy('kind', $direction),
            'method' => $query->orderBy(PaymentMethod::query()->select('label_fr')->whereColumn('payment_methods.id', 'payments.payment_method_id'), $direction),
            'amount' => $query->orderBy('amount', $direction),
            default => $query->orderByDesc('paid_on'),
        })->orderBy('id', $sort === null ? 'desc' : $direction);
    }
}
