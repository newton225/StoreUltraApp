<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AppearanceUpdateRequest;
use Illuminate\Http\RedirectResponse;

class AppearanceController extends Controller
{
    /**
     * Save the display mode (light, dark or system) in the user's profile.
     */
    public function update(AppearanceUpdateRequest $request): RedirectResponse
    {
        $request->user()->forceFill($request->validated())->save();

        return back();
    }
}
