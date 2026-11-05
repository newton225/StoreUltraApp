<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Database\Factories\ZoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A sub-prefecture where clients are located.
 *
 * @property int $id
 * @property string $name
 * @property string|null $code
 * @property bool $active
 */
#[Fillable(['name', 'code', 'active'])]
class Zone extends Model
{
    use Audited;

    /** @use HasFactory<ZoneFactory> */
    use HasFactory;

    /**
     * The field agents assigned to the zone.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'zone_user');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
