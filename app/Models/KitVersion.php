<?php

namespace App\Models;

use Database\Factories\KitVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The content of a kit from a given date. Orders keep the version they were made with.
 *
 * @property int $id
 * @property int $kit_id
 * @property int $version
 * @property string $sale_price_mode lines (sum of the line prices) or fixed
 * @property int|null $fixed_sale_price
 * @property Carbon $valid_from
 * @property int|null $created_by
 */
#[Fillable(['kit_id', 'version', 'sale_price_mode', 'fixed_sale_price', 'valid_from', 'created_by'])]
class KitVersion extends Model
{
    /** @use HasFactory<KitVersionFactory> */
    use HasFactory;

    public const MODE_LINES = 'lines';

    public const MODE_FIXED = 'fixed';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'fixed_sale_price' => 'integer',
            'valid_from' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Kit, $this>
     */
    public function kit(): BelongsTo
    {
        return $this->belongsTo(Kit::class);
    }

    /**
     * @return HasMany<KitVersionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(KitVersionItem::class)->orderBy('id');
    }
}
