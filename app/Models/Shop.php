<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Database\Factories\ShopFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A shop the products are bought from.
 *
 * @property int $id
 * @property string $name
 * @property string|null $contact
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $payment_terms
 * @property bool $active
 */
#[Fillable(['name', 'contact', 'phone', 'address', 'payment_terms', 'active'])]
class Shop extends Model
{
    use Audited;

    /** @use HasFactory<ShopFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /**
     * @return HasMany<ShopPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(ShopPayment::class);
    }

    /**
     * What has really been paid to the shop: payments minus what it gave back.
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
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
