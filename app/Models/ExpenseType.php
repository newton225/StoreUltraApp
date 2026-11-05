<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Database\Factories\ExpenseTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A kind of logistics cost (transport, handling, contingency...), set by the owner.
 *
 * @property int $id
 * @property string $code
 * @property string $label_fr
 * @property string $label_en
 * @property bool $active
 * @property-read string $name
 */
#[Fillable(['code', 'label_fr', 'label_en', 'active'])]
class ExpenseType extends Model
{
    use Audited;

    /** @use HasFactory<ExpenseTypeFactory> */
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
     * The label in the current language, falling back to the french one.
     *
     * @return Attribute<string, never>
     */
    protected function name(): Attribute
    {
        return Attribute::get(
            fn (): string => (app()->getLocale() === 'en' ? $this->label_en : null) ?: $this->label_fr,
        );
    }
}
