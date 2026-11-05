<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateLocaleRequest;
use Illuminate\Http\RedirectResponse;

class LocaleController extends Controller
{
    /**
     * Remember the chosen language in the session and, when logged in, in the profile.
     */
    public function update(UpdateLocaleRequest $request): RedirectResponse
    {
        $locale = $request->validated('locale');

        $request->session()->put('locale', $locale);

        $request->user()?->forceFill(['locale' => $locale])->save();

        return back();
    }
}
