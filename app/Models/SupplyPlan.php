<?php

namespace App\Models;

use App\Enums\Frequency;
use App\Enums\SupplyPlanStatus;
use App\Support\WeekCalendar;
use Carbon\CarbonImmutable;
use Database\Factories\SupplyPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * What a client receives at each cycle: a kit, a number of kits and an advance.
 *
 * @property int $id
 * @property int $client_id
 * @property int $kit_id
 * @property int $kits_per_cycle
 * @property int $advance_amount
 * @property Frequency $frequency
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property int|null $cycles_count
 * @property SupplyPlanStatus $status
 * @property int|null $created_by
 */
#[Fillable(['client_id', 'kit_id', 'kits_per_cycle', 'advance_amount', 'frequency', 'starts_on', 'ends_on', 'cycles_count'])]
class SupplyPlan extends Model
{
    /** @use HasFactory<SupplyPlanFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kits_per_cycle' => 'integer',
            'advance_amount' => 'integer',
            'frequency' => Frequency::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'cycles_count' => 'integer',
            'status' => SupplyPlanStatus::class,
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
     * @return BelongsTo<Kit, $this>
     */
    public function kit(): BelongsTo
    {
        return $this->belongsTo(Kit::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * The first day of the delivery week of the given cycle (0 is the first one).
     */
    public function cycleWeek(int $cycle, WeekCalendar $calendar): CarbonImmutable
    {
        $start = CarbonImmutable::instance($this->starts_on)->startOfDay();

        return $calendar->startOf($this->frequency->dateOfCycle($start, $cycle));
    }
}
