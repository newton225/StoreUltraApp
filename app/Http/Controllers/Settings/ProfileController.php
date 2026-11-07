<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\AuditLog;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $changes = $request->user()->getDirty();
        $before = array_intersect_key($request->user()->getOriginal(), $changes);

        $request->user()->save();

        if (array_diff_key($changes, ['email_verified_at' => true]) !== []) {
            AuditLog::record('auth.profile_updated', $request->user(), $before, array_diff_key($changes, ['email_verified_at' => true]));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('account.profile.updated')]);

        return to_route('profile.edit');
    }
}
