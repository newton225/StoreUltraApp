<?php

namespace App\Models;

use App\Enums\ClientStatus;
use App\Models\Concerns\Audited;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A client of the business. Clients never log in: the staff registers them.
 *
 * @property int $id
 * @property string|null $reference
 * @property string|null $commercial_number
 * @property string $name
 * @property string $phone
 * @property int $zone_id
 * @property string|null $locality
 * @property string|null $delivery_address
 * @property string|null $contact_person
 * @property ClientStatus $status
 * @property string|null $notes
 * @property int|null $created_by
 */
#[Fillable(['commercial_number', 'name', 'phone', 'zone_id', 'locality', 'delivery_address', 'contact_person', 'notes'])]
class Client extends Model
{
    use Audited;

    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => ClientStatus::class];
    }

    /**
     * @return BelongsTo<Zone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The people of a small business (TPE) client: its manager first, then its assistants.
     *
     * @return HasMany<ClientMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(ClientMember::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<SupplyPlan, $this>
     */
    public function supplyPlans(): HasMany
    {
        return $this->hasMany(SupplyPlan::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * The clients the user may see: every client, or for a field agent the ones of their zones.
     *
     * @param  Builder<Client>  $query
     * @return Builder<Client>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isRestrictedToZones()
            ? $query->whereIn('zone_id', $user->zones()->select('zones.id'))
            : $query;
    }

    /**
     * The columns a list of clients can be sorted by (the keys of the column headers).
     */
    public const SORTABLE = ['reference', 'name', 'zone', 'status'];

    /**
     * Sorts a list of clients by one of the SORTABLE keys, by name when none is given.
     *
     * @param  Builder<Client>  $query
     * @param  'asc'|'desc'  $direction
     * @return Builder<Client>
     */
    public function scopeSortedBy(Builder $query, ?string $sort, string $direction = 'asc'): Builder
    {
        return (match ($sort) {
            'reference' => $query->orderBy('reference', $direction),
            'zone' => $query->orderBy(Zone::query()->select('name')->whereColumn('zones.id', 'clients.zone_id'), $direction),
            'status' => $query->orderBy('status', $direction),
            default => $query->orderBy('name', $sort === 'name' ? $direction : 'asc'),
        })->orderBy('id');
    }

    /**
     * Match a term against the name, the phone, the reference and the commercial number.
     *
     * @param  Builder<Client>  $query
     * @return Builder<Client>
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $like = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(fn (Builder $inner) => $inner
            ->whereLike('name', $like)
            ->orWhereLike('phone', $like)
            ->orWhereLike('reference', $like)
            ->orWhereLike('commercial_number', $like));
    }

    /**
     * The clients that take a place under the client cap.
     *
     * @param  Builder<Client>  $query
     * @return Builder<Client>
     */
    public function scopeCountingTowardsCap(Builder $query): Builder
    {
        return $query->where('status', '!=', ClientStatus::Ended->value);
    }
}
