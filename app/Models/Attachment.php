<?php

namespace App\Models;

use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An administrative document of the company, a shop or a client. The kind of owner comes from its
 * type; `owner_id` is empty for the company.
 *
 * @property int $id
 * @property int $attachment_type_id
 * @property int|null $owner_id
 * @property string $number
 * @property Carbon|null $issued_on
 * @property Carbon|null $expires_on
 * @property string|null $file_path
 * @property string|null $notes
 * @property int|null $created_by
 */
#[Fillable(['attachment_type_id', 'owner_id', 'number', 'issued_on', 'expires_on', 'file_path', 'notes', 'created_by'])]
class Attachment extends Model
{
    /** @use HasFactory<AttachmentFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['issued_on' => 'date', 'expires_on' => 'date'];
    }

    /**
     * @return BelongsTo<AttachmentType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(AttachmentType::class, 'attachment_type_id');
    }
}
