<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Database\Factories\AttachmentTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A kind of administrative document (trade register, tax account...) the owner keeps for the company,
 * a shop or a client. Only the number is needed; the file is optional.
 *
 * @property int $id
 * @property string $code
 * @property string $label_fr
 * @property string $label_en
 * @property string $applies_to company, shop or client
 * @property bool $required
 * @property bool $has_expiry
 * @property bool $on_documents printed in the header of the documents we issue (company only)
 * @property bool $active
 * @property-read string $name
 */
#[Fillable(['code', 'label_fr', 'label_en', 'applies_to', 'required', 'has_expiry', 'on_documents', 'active'])]
class AttachmentType extends Model
{
    use Audited;

    /** @use HasFactory<AttachmentTypeFactory> */
    use HasFactory;

    public const KINDS = ['company', 'shop', 'client'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['required' => 'boolean', 'has_expiry' => 'boolean', 'on_documents' => 'boolean', 'active' => 'boolean'];
    }

    /**
     * @return HasMany<Attachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
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
