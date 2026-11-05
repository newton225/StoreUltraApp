<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Database\Factories\PaymentMethodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A way a client can pay (cash, mobile money, transfer...), set by the owner.
 *
 * @property int $id
 * @property string $code
 * @property string $label_fr
 * @property string $label_en
 * @property bool $requires_reference
 * @property bool $active
 * @property-read string $name
 */
#[Fillable(['code', 'label_fr', 'label_en', 'requires_reference', 'active'])]
class PaymentMethod extends Model
{
    use Audited;

    /** @use HasFactory<PaymentMethodFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['requires_reference' => 'boolean', 'active' => 'boolean'];
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
