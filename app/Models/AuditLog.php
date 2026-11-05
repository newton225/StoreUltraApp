<?php

namespace App\Models;

use App\Support\AuditChain;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One line of the journal: who did what to which record, before and after, from which address and
 * browser. It can only be added to: the model refuses to change or remove an entry, the database
 * refuses it too, and each entry carries the hash that chains it to the one before.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $action
 * @property string $subject_type
 * @property int $subject_id
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $hash
 * @property Carbon|null $created_at
 */
#[Fillable(['user_id', 'action', 'subject_type', 'subject_id', 'before', 'after', 'ip_address', 'user_agent', 'hash'])]
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * An entry is never modified or removed once written.
     */
    protected static function booted(): void
    {
        static::updating(fn (): bool => false);
        static::deleting(fn (): bool => false);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Write an entry for an action on a record, by the given user or else the logged-in one, with the
     * address and browser of the request. The entry is chained to the one before under a lock on the
     * head of the chain, so two entries written at once cannot both follow the same one.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public static function record(string $action, Model $subject, array $before = [], array $after = [], ?User $user = null): self
    {
        $userId = ($user ?? Auth::user())?->getAuthIdentifier();
        $fromRequest = ! app()->runningInConsole() || app()->runningUnitTests();
        $userAgent = $fromRequest ? Str::limit((string) request()->userAgent(), 255, '') : '';

        return DB::transaction(function () use ($action, $subject, $before, $after, $userId, $fromRequest, $userAgent): self {
            $head = DB::table('audit_chain')->where('id', 1)->lockForUpdate()->first();

            if ($head === null) {
                DB::table('audit_chain')->insertOrIgnore(['id' => 1, 'last_hash' => null]);
                $head = DB::table('audit_chain')->where('id', 1)->lockForUpdate()->first();
            }

            $createdAt = now()->startOf('second');
            $before = $before === [] ? null : $before;
            $after = $after === [] ? null : $after;

            $entry = new self([
                'user_id' => $userId === null ? null : (int) $userId,
                'action' => $action,
                'subject_type' => $subject::class,
                'subject_id' => $subject->getKey(),
                'before' => $before,
                'after' => $after,
                'ip_address' => $fromRequest ? request()->ip() : null,
                'user_agent' => $userAgent === '' ? null : $userAgent,
            ]);

            $entry->hash = AuditChain::hash(
                $head?->last_hash,
                $entry->user_id,
                $action,
                $entry->subject_type,
                (int) $entry->subject_id,
                $before,
                $after,
                $createdAt->format('Y-m-d H:i:s'),
            );
            $entry->forceFill(['created_at' => $createdAt])->save();

            DB::table('audit_chain')->where('id', 1)->update(['last_hash' => $entry->hash]);

            return $entry;
        });
    }
}
