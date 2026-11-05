<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Database\Factories\ClientMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person attached to a client that is a small business (TPE): its manager or one of its assistants.
 *
 * @property int $id
 * @property int $client_id
 * @property string $name
 * @property string $role manager or assistant
 * @property string|null $trade
 * @property string|null $phone
 * @property int $position
 */
#[Fillable(['client_id', 'name', 'role', 'trade', 'phone', 'position'])]
class ClientMember extends Model
{
    use Audited;

    /** @use HasFactory<ClientMemberFactory> */
    use HasFactory;

    public const ROLE_MANAGER = 'manager';

    public const ROLE_ASSISTANT = 'assistant';

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
