<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An order of kits for one client and one delivery week. Prices are frozen when it is created.
 *
 * @property int $id
 * @property string|null $reference
 * @property int $client_id
 * @property int|null $supply_plan_id
 * @property int $kit_version_id
 * @property int $kits_count
 * @property Carbon $delivery_week
 * @property OrderStatus $status
 * @property int $advance_due
 * @property int $total_sale
 * @property int $total_purchase_expected
 * @property string|null $notes
 * @property int|null $created_by
 */
#[Fillable(['reference', 'client_id', 'supply_plan_id', 'kit_version_id', 'kits_count', 'delivery_week', 'status', 'advance_due', 'total_sale', 'total_purchase_expected', 'notes', 'created_by'])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kits_count' => 'integer',
            'delivery_week' => 'date',
            'status' => OrderStatus::class,
            'advance_due' => 'integer',
            'total_sale' => 'integer',
            'total_purchase_expected' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<SupplyPlan, $this>
     */
    public function supplyPlan(): BelongsTo
    {
        return $this->belongsTo(SupplyPlan::class);
    }

    /**
     * @return BelongsTo<KitVersion, $this>
     */
    public function kitVersion(): BelongsTo
    {
        return $this->belongsTo(KitVersion::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<Delivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class)->orderBy('id');
    }

    /**
     * How many kits are still to be handed over.
     */
    public function remainingKits(): int
    {
        return max(0, $this->kits_count - (int) $this->deliveries()->getQuery()->toBase()->sum('kits_delivered'));
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * What the client has really paid: payments received minus what was given back.
     */
    public function netPaid(): int
    {
        return (int) $this->payments()
            ->getQuery()
            ->toBase()
            ->selectRaw("coalesce(sum(case when kind = 'refund' then -amount else amount end), 0) as total")
            ->value('total');
    }

    /**
     * @return HasMany<OrderStatusChange, $this>
     */
    public function statusChanges(): HasMany
    {
        return $this->hasMany(OrderStatusChange::class)->orderBy('id');
    }

    /**
     * The orders of the clients the user may see.
     *
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('client', fn (Builder $clients) => $clients->visibleTo($user));
    }

    /**
     * The columns a list of orders can be sorted by (the keys of the column headers).
     */
    public const SORTABLE = ['reference', 'client', 'week', 'kits', 'total', 'status'];

    /**
     * Sorts a list of orders by one of the SORTABLE keys, newest week first when none is given. The id breaks
     * the ties so that the pages never overlap.
     *
     * @param  Builder<Order>  $query
     * @param  'asc'|'desc'  $direction
     * @return Builder<Order>
     */
    public function scopeSortedBy(Builder $query, ?string $sort, string $direction = 'asc'): Builder
    {
        return (match ($sort) {
            'reference' => $query->orderBy('reference', $direction),
            'client' => $query->orderBy(Client::query()->select('name')->whereColumn('clients.id', 'orders.client_id'), $direction),
            'week' => $query->orderBy('delivery_week', $direction),
            'kits' => $query->orderBy('kits_count', $direction),
            'total' => $query->orderBy('total_sale', $direction),
            'status' => $query->orderBy('status', $direction),
            default => $query->orderByDesc('delivery_week'),
        })->orderBy('id', $sort === null ? 'desc' : $direction);
    }
}
