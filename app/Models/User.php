<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\PermissionAction;
use App\Enums\UserRole;
use App\Support\Search;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property Carbon|null $password_set_at
 * @property Carbon|null $invitation_sent_at
 * @property UserRole $role
 * @property int|null $profile_id
 * @property bool $is_active
 * @property string|null $locale
 * @property string $appearance
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * The number of profile changes seen when the profile was last loaded (see Profile::changes()).
     */
    private int $profileChanges = -1;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_set_at' => 'datetime',
            'invitation_sent_at' => 'datetime',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * The states an account can be filtered by in the list: working, not yet opened (invitation pending), deactivated.
     */
    public const STATES = ['active', 'pending', 'inactive'];

    /**
     * Accounts whose name or e-mail contains what was typed, accents and case ignored (playbook DATA-78).
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $inner) use ($term): void {
            Search::contains($inner, 'name', $term);
            Search::contains($inner, 'email', $term, or: true);
        });
    }

    /**
     * Accounts holding a profile. An account that kept no profile of its own (created before profiles could be
     * chosen) holds the system profile of its role, so it is found there too.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeWithProfile(Builder $query, Profile $profile): Builder
    {
        return $query->where(function (Builder $inner) use ($profile): void {
            $inner->where('profile_id', $profile->id);

            if ($profile->is_system) {
                $inner->orWhere(fn (Builder $legacy) => $legacy->whereNull('profile_id')->where('role', $profile->role()->value));
            }
        });
    }

    /**
     * The filters of the users list, shared by the screen and its export.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeFilteredBy(Builder $query, string $search, ?int $profileId, ?string $state): Builder
    {
        $profile = $profileId === null ? null : Profile::query()->find($profileId);

        return $query
            ->when($search !== '', fn (Builder $q) => $q->search($search))
            ->when($profile !== null, fn (Builder $q) => $q->withProfile($profile))
            ->when($state !== null, fn (Builder $q) => $q->inState($state));
    }

    /**
     * Accounts in one state (see STATES).
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeInState(Builder $query, string $state): Builder
    {
        return match ($state) {
            'active' => $query->where('is_active', true)->whereNotNull('password_set_at'),
            'pending' => $query->where('is_active', true)->whereNull('password_set_at'),
            'inactive' => $query->where('is_active', false),
            default => $query,
        };
    }

    /**
     * The zones a field agent is assigned to.
     *
     * @return BelongsToMany<Zone, $this>
     */
    public function zones(): BelongsToMany
    {
        return $this->belongsToMany(Zone::class, 'zone_user');
    }

    /**
     * The profile that says what the user may do.
     *
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * The profile in force: the one assigned, or the one of the role for an account that has none yet.
     * It is read from the database each time (once per user object), so a change of profile or of its
     * permissions takes effect on the next request.
     */
    public function effectiveProfile(): Profile
    {
        if ($this->relationLoaded('profile') && $this->profile !== null && $this->profileChanges === Profile::changes()) {
            return $this->profile;
        }

        $profile = $this->profile_id !== null ? Profile::query()->find($this->profile_id) : null;
        $profile ??= Profile::forRole($this->role);

        $this->setRelation('profile', $profile);
        $this->profileChanges = Profile::changes();

        return $profile;
    }

    /**
     * Whether the user has a permission: the owner has them all, the others have what their profile grants.
     */
    public function hasPermission(Permission $permission): bool
    {
        return $this->isOwner() || $this->effectiveProfile()->grants($permission);
    }

    /**
     * Whether the user may do an action: the owner always, the others when their profile grants the menu
     * and the action.
     */
    public function canDo(PermissionAction $action): bool
    {
        return $this->isOwner() || $this->effectiveProfile()->grantsAction($action);
    }

    /**
     * The actions the user may do, as the values of the catalogue.
     *
     * @return list<string>
     */
    public function grantedActions(): array
    {
        return array_values(array_filter(
            PermissionAction::values(),
            fn (string $value): bool => $this->canDo(PermissionAction::from($value)),
        ));
    }

    /**
     * The permissions the user holds, as the values of the catalogue.
     *
     * @return list<string>
     */
    public function grantedPermissions(): array
    {
        return array_values(array_filter(
            Permission::values(),
            fn (string $value): bool => $this->hasPermission(Permission::from($value)),
        ));
    }

    /**
     * Whether the user only sees the data of the sub-prefectures they are assigned to.
     */
    public function isRestrictedToZones(): bool
    {
        return ! $this->isOwner() && $this->effectiveProfile()->restricted_to_zones;
    }

    /**
     * Whether the client is within the user's reach: everyone for the office, only the assigned zones for a
     * restricted profile. The clients menu is not involved: orders are visible on the same rule.
     */
    public function reachesClient(Client $client): bool
    {
        if (! $this->isRestrictedToZones()) {
            return true;
        }

        return $this->zones()->whereKey($client->zone_id)->exists();
    }

    /**
     * Whether the user is the business owner.
     */
    public function isOwner(): bool
    {
        return $this->role === UserRole::Owner;
    }
}
