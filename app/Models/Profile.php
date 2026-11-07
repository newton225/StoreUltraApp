<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\PermissionAction;
use App\Enums\UserRole;
use Database\Factories\ProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * What a kind of user may do: a set of permissions from the closed catalogue, and whether the user only
 * sees the data of the sub-prefectures they are assigned to. The three profiles that exist from the start
 * (owner, employee, field agent) are system profiles: they cannot be changed or removed, and a different
 * set of permissions is a new, custom profile.
 *
 * @property int $id
 * @property string|null $code
 * @property string $label_fr
 * @property string $label_en
 * @property bool $restricted_to_zones
 * @property bool $is_system
 * @property list<string> $permissions
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $label
 */
#[Fillable(['label_fr', 'label_en', 'restricted_to_zones', 'permissions'])]
class Profile extends Model
{
    /** @use HasFactory<ProfileFactory> */
    use HasFactory;

    /**
     * How many times a profile was saved or deleted in this process. A user object keeps the profile it
     * loaded only while this number is the same, so a change takes effect on the next check.
     */
    private static int $changeCount = 0;

    public static function changes(): int
    {
        return self::$changeCount;
    }

    protected static function booted(): void
    {
        static::saved(function (): void {
            self::$changeCount++;
        });
        static::deleted(function (): void {
            self::$changeCount++;
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'restricted_to_zones' => 'boolean',
            'is_system' => 'boolean',
            'permissions' => 'array',
        ];
    }

    /**
     * The users who have this profile.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * The name in the current language, falling back to the french one.
     */
    public function getLabelAttribute(): string
    {
        return (app()->getLocale() === 'en' ? $this->label_en : null) ?: $this->label_fr;
    }

    /**
     * Whether this profile grants a permission.
     */
    public function grants(Permission $permission): bool
    {
        return in_array($permission->value, $this->permissions, true);
    }

    /**
     * Whether this profile grants an action. The action alone decides: the menu opens the screens, and a
     * field agent does a few things (payments, deliveries, receipts) without having the orders menu.
     */
    public function grantsAction(PermissionAction $action): bool
    {
        return in_array($action->value, $this->permissions, true);
    }

    /**
     * Whether this is the profile of the owner, who always has every permission.
     */
    public function isOwnerProfile(): bool
    {
        return $this->code === UserRole::Owner->value;
    }

    /**
     * The profile that goes with a role: the one every user had before profiles could be changed.
     */
    public static function forRole(UserRole $role): self
    {
        return self::query()->where('code', $role->value)->firstOrFail();
    }

    /**
     * The role a user with this profile is recorded under, for what still reads the role: the owner
     * for the owner's profile, a field agent for a profile tied to sub-prefectures, an employee otherwise.
     */
    public function role(): UserRole
    {
        return match (true) {
            $this->isOwnerProfile() => UserRole::Owner,
            $this->restricted_to_zones => UserRole::Agent,
            default => UserRole::Employee,
        };
    }
}
