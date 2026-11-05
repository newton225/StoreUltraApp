<?php

namespace App\Http\Controllers;

use App\Http\Requests\ZoneRequest;
use App\Models\Zone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ZoneController extends Controller
{
    /**
     * List the sub-prefectures.
     */
    public function index(): Response
    {
        return Inertia::render('zones/index', [
            'zones' => Zone::query()->orderBy('name')->get(['id', 'name', 'code', 'active']),
        ]);
    }

    /**
     * Show the form creating a sub-prefecture.
     */
    public function create(): Response
    {
        return Inertia::render('zones/create');
    }

    /**
     * Create a sub-prefecture. A form that adds one without leaving its page (the client form)
     * sends a JSON request and gets the new sub-prefecture back to select it.
     */
    public function store(ZoneRequest $request): RedirectResponse|JsonResponse
    {
        $zone = Zone::create($request->validated());

        if ($request->wantsJson()) {
            return response()->json(['id' => $zone->id, 'name' => $zone->name], 201);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('catalog.zones.created')]);

        return to_route('zones.index');
    }

    /**
     * Show the form editing a sub-prefecture.
     */
    public function edit(Zone $zone): Response
    {
        return Inertia::render('zones/edit', [
            'zone' => $zone->only(['id', 'name', 'code', 'active']),
        ]);
    }

    /**
     * Update a sub-prefecture.
     */
    public function update(ZoneRequest $request, Zone $zone): RedirectResponse
    {
        $zone->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('catalog.zones.updated')]);

        return to_route('zones.index');
    }
}
