<?php

namespace App\Http\Controllers;

use App\Actions\Kits\CreateKitVersion;
use App\Http\Requests\StoreKitVersionRequest;
use App\Models\Kit;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class KitVersionController extends Controller
{
    /**
     * Add a version to a kit. Orders already made keep the version they used.
     */
    public function store(StoreKitVersionRequest $request, Kit $kit, CreateKitVersion $createVersion): RedirectResponse
    {
        $createVersion->handle($kit, $request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('kits.version_created')]);

        return to_route('kits.show', $kit);
    }
}
