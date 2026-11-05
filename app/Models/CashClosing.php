<?php

namespace App\Models;

use Database\Factories\CashClosingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * The count of the cash box on a date: what the books said it should hold, what was counted, and the
 * difference. It is never edited or deleted. Once a date is closed, no movement dated on or before it can be
 * recorded or cancelled any more: a difference is written as one adjustment movement dated the closing day, so
 * that the books agree with the count.
 *
 * @property int $id
 * @property string|null $reference
 * @property Carbon $closed_on
 * @property int $expected_amount
 * @property int $counted_amount
 * @property int $difference counted minus expected: negative is a shortage, positive a surplus
 * @property string|null $notes
 * @property int|null $closed_by
 */
#[Fillable(['closed_on', 'expected_amount', 'counted_amount', 'difference', 'notes', 'closed_by'])]
class CashClosing extends Model
{
    /** @use HasFactory<CashClosingFactory> */
    use HasFactory;

    /**
     * Only the reference, given right after the creation, may be written once more.
     */
    private const MUTABLE = ['reference', 'updated_at'];

    protected static function booted(): void
    {
        static::updating(fn (self $closing): bool => array_diff(array_keys($closing->getDirty()), self::MUTABLE) === []);
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
            'closed_on' => 'date',
            'expected_amount' => 'integer',
            'counted_amount' => 'integer',
            'difference' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * @return HasOne<CashMovement, $this>
     */
    public function adjustment(): HasOne
    {
        return $this->hasOne(CashMovement::class, 'closing_id');
    }

    /**
     * Whether a date falls in a closed period: nothing dated on or before the last closing can change.
     */
    public static function isClosed(\DateTimeInterface $date): bool
    {
        $last = static::lastClosedOn();

        return $last !== null && Carbon::instance($date)->startOfDay()->lessThanOrEqualTo($last);
    }

    /**
     * The date up to which the cash box is closed, or null when it never was.
     */
    public static function lastClosedOn(): ?Carbon
    {
        $date = static::query()->max('closed_on');

        return $date === null ? null : Carbon::parse($date)->startOfDay();
    }
}
