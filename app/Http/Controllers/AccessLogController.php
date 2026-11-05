<?php

namespace App\Http\Controllers;

use App\Models\AccessLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccessLogController extends Controller
{
    private const PER_PAGE = 50;

    /**
     * Read the access journal, newest first, filtered by user, address, kind and period.
     */
    public function index(Request $request): Response
    {
        $userId = $request->integer('user') ?: null;
        $ip = trim($request->string('ip')->toString());
        $kind = in_array($request->string('kind')->toString(), [AccessLog::PAGE, AccessLog::LOGIN_FAILED, AccessLog::LOCKOUT], true)
            ? $request->string('kind')->toString()
            : null;
        $from = $request->date('from');
        $to = $request->date('to');

        $entries = AccessLog::query()
            ->with('user:id,name')
            ->when($userId !== null, fn (Builder $query) => $query->where('user_id', $userId))
            ->when($ip !== '', fn (Builder $query) => $query->where('ip_address', $ip))
            ->when($kind !== null, fn (Builder $query) => $query->where('kind', $kind))
            ->when($from !== null, fn (Builder $query) => $query->whereDate('created_at', '>=', $from))
            ->when($to !== null, fn (Builder $query) => $query->whereDate('created_at', '<=', $to))
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (AccessLog $entry): array => [
                'id' => $entry->id,
                'at' => $entry->created_at->toIso8601String(),
                'kind' => $entry->kind,
                'user' => $entry->user?->name,
                'email' => $entry->email,
                'method' => $entry->method,
                'route' => $entry->route,
                'path' => $entry->path.($entry->query === null ? '' : '?'.$entry->query),
                'status' => $entry->status,
                'ip' => $entry->ip_address,
                'user_agent' => $entry->user_agent,
            ]);

        return Inertia::render('access-log/index', [
            'entries' => $entries,
            'filters' => ['user' => $userId, 'ip' => $ip, 'kind' => $kind, 'from' => $from?->toDateString(), 'to' => $to?->toDateString()],
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'retention_days' => (int) config('security.access_log_retention_days'),
        ]);
    }
}
