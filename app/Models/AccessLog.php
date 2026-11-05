<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * One line of the access journal: a page a signed-in user opened, a failed sign-in or a lock-out, with the
 * address and browser. It is kept apart from the audit journal (which holds what people changed and is
 * chained): it is far larger, never edited, and purged after a bounded time (playbook SEC-162).
 *
 * @property int $id
 * @property string $kind page, login_failed or lockout
 * @property int|null $user_id
 * @property string|null $email the address typed in a failed sign-in
 * @property string|null $method
 * @property string|null $route
 * @property string|null $path
 * @property string|null $query
 * @property int|null $status
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $locale
 * @property Carbon $created_at
 */
#[Fillable(['kind', 'user_id', 'email', 'method', 'route', 'path', 'query', 'status', 'ip_address', 'user_agent', 'locale'])]
class AccessLog extends Model
{
    public const PAGE = 'page';

    public const LOGIN_FAILED = 'login_failed';

    public const LOCKOUT = 'lockout';

    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'status' => 'integer'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Write a line for the current request. Never throws: the journal must not break what it watches.
     *
     * @param  array<string, mixed>  $extra
     */
    public static function record(string $kind, Request $request, array $extra = []): void
    {
        try {
            self::query()->create([
                'kind' => $kind,
                'user_id' => $request->user()?->getAuthIdentifier(),
                'method' => $request->method(),
                'route' => $request->route()?->getName(),
                'path' => mb_substr('/'.ltrim($request->path(), '/'), 0, 500),
                'query' => self::maskedQuery($request),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255) ?: null,
                'locale' => app()->getLocale(),
                ...$extra,
            ]);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /**
     * The query string with every value that could be a secret replaced, so it is safe to keep.
     */
    public static function maskedQuery(Request $request): ?string
    {
        $query = $request->query();

        if ($query === []) {
            return null;
        }

        $masked = [];

        foreach ($query as $key => $value) {
            $masked[$key] = preg_match('/pass|token|secret|code|key|signature|otp/i', (string) $key) === 1 ? '***' : $value;
        }

        return mb_substr(http_build_query($masked), 0, 1000);
    }
}
