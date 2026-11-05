<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Carbon\CarbonInterface;
use Database\Factories\KitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A type of kit. Its content lives in versions, which are never edited.
 *
 * @property int $id
 * @property string $name_fr
 * @property string $name_en
 * @property bool $active
 * @property-read string $name
 */
#[Fillable(['name_fr', 'name_en', 'active'])]
class Kit extends Model
{
    use Audited;

    /** @use HasFactory<KitFactory> */
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
     * The name in the current language, falling back to the french one.
     *
     * @return Attribute<string, never>
     */
    protected function name(): Attribute
    {
        return Attribute::get(
            fn (): string => (app()->getLocale() === 'en' ? $this->name_en : null) ?: $this->name_fr,
        );
    }

    /**
     * @return HasMany<KitVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(KitVersion::class)->orderByDesc('version');
    }

    /**
     * The version in effect on the given date: the latest one that had started by then.
     */
    public function versionAt(CarbonInterface $date): ?KitVersion
    {
        return $this->versions()
            ->reorder()
            ->whereDate('valid_from', '<=', $date)
            ->orderByDesc('valid_from')
            ->orderByDesc('version')
            ->first();
    }

    /**
     * The version to use for the deliveries of a week: the one in effect on the first day of that week,
     * or, for the current week or a past one, the one in effect today.
     */
    public function versionForWeek(CarbonInterface $week): ?KitVersion
    {
        return $this->versionAt($week->isBefore(today()) ? today() : $week);
    }

    /**
     * The latest version whose date has come.
     *
     * @return HasOne<KitVersion, $this>
     */
    public function currentVersion(): HasOne
    {
        return $this->hasOne(KitVersion::class)->ofMany(
            ['valid_from' => 'max', 'version' => 'max'],
            fn (Builder $query) => $query->whereDate('valid_from', '<=', today()),
        );
    }
}
