<?php

namespace App\Models;

use Database\Factories\DeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Kits handed over to a client, with the name of the person who received them and their signature.
 *
 * @property int $id
 * @property string|null $reference
 * @property int $order_id
 * @property int $kits_delivered
 * @property Carbon $delivered_on
 * @property int|null $agent_id
 * @property string $received_by
 * @property string|null $signature_path
 * @property Carbon|null $signed_at
 * @property string|null $notes
 * @property string|null $idempotency_key
 * @property int|null $created_by
 */
#[Fillable(['reference', 'order_id', 'kits_delivered', 'delivered_on', 'agent_id', 'received_by', 'signature_path', 'signed_at', 'notes', 'idempotency_key', 'created_by'])]
class Delivery extends Model
{
    /** @use HasFactory<DeliveryFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kits_delivered' => 'integer',
            'delivered_on' => 'date',
            'signed_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
