<?php

namespace App\Models;

use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An invoice for a delivered order, with the state of its normalized electronic invoice (FNE).
 *
 * @property int $id
 * @property string $number
 * @property int $order_id
 * @property int $client_id
 * @property Carbon $issued_on
 * @property int $total
 * @property int $paid_amount
 * @property string $fne_status not_required, to_issue or issued
 * @property string|null $fne_number
 * @property Carbon|null $cancelled_at
 * @property int|null $created_by
 */
#[Fillable(['number', 'order_id', 'client_id', 'issued_on', 'total', 'paid_amount', 'fne_status', 'fne_number', 'created_by'])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'total' => 'integer',
            'paid_amount' => 'integer',
            'cancelled_at' => 'datetime',
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
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
