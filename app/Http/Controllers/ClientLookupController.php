<?php

namespace App\Http\Controllers;

use App\Enums\ClientStatus;
use App\Models\Client;
use App\Support\Search;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientLookupController extends Controller
{
    /** How many clients a search returns: a list of choices, never the whole table. */
    public const LIMIT = 20;

    /**
     * Look clients up by what the user types, for a list of choices that cannot hold every client (the order form).
     * Accents and case are ignored (playbook DATA-13). The same clients as the form offers: active ones, and for a
     * profile limited to zones only those of its zones. Empty search: the first ones by name.
     */
    public function index(Request $request): JsonResponse
    {
        $term = trim($request->string('q')->toString());

        $clients = Client::query()
            ->visibleTo($request->user())
            ->where('status', ClientStatus::Active->value)
            ->when($term !== '', fn ($query) => $query->search($term))
            ->tap(fn ($query) => Search::orderBy($query, 'name'))
            ->orderBy('id')
            ->limit(self::LIMIT)
            ->get(['id', 'name', 'reference']);

        return response()->json([
            'data' => $clients->map(fn (Client $client): array => ['id' => $client->id, 'name' => $client->name, 'reference' => $client->reference])->values(),
        ]);
    }
}
