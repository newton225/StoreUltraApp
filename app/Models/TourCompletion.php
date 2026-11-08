<?php

namespace App\Models;

use Database\Factories\TourCompletionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A guided tour a member has seen (or skipped): one line per member and tour. Replaying a tour does not
 * change it. It is a display preference, not business data, so it is not traced in the audit journal.
 *
 * @property int $id
 * @property int $user_id
 * @property string $tour
 * @property Carbon $completed_at
 */
#[Fillable(['user_id', 'tour', 'completed_at'])]
class TourCompletion extends Model
{
    /** @use HasFactory<TourCompletionFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * The screens that have a tour. The key is also the one the screen sends, so an unknown one is refused.
     */
    public const TOURS = ['dashboard', 'orders', 'order-create', 'order-show', 'cash', 'profits', 'users'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
