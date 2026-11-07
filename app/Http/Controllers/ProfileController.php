<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\PermissionAction;
use App\Http\Requests\ProfileRequest;
use App\Http\Requests\ToggleProfileRightRequest;
use App\Models\AuditLog;
use App\Models\Profile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * List the profiles: the system ones first, then the custom ones, with what each grants.
     */
    public function index(): Response
    {
        return Inertia::render('profiles/index', [
            'profiles' => Profile::query()
                ->withCount('users')
                ->orderByDesc('is_system')
                ->orderBy('id')
                ->get()
                ->map(fn (Profile $profile): array => $this->listed($profile))
                ->values(),
            'permissions' => Permission::values(),
            'actions' => PermissionAction::byMenu(),
        ]);
    }

    /**
     * Show the form creating a custom profile, empty or starting from the rights of an existing one.
     */
    public function create(Request $request): Response
    {
        $template = $request->filled('from') ? Profile::query()->find($request->integer('from')) : null;

        return Inertia::render('profiles/create', [
            'permissions' => Permission::values(),
            'actions' => PermissionAction::byMenu(),
            'template' => $template === null ? null : [
                'name' => $template->label,
                'restricted_to_zones' => $template->restricted_to_zones,
                'permissions' => $template->permissions,
            ],
        ]);
    }

    /**
     * Create a custom profile.
     */
    public function store(ProfileRequest $request): RedirectResponse
    {
        $profile = new Profile($request->safe()->only(['label_fr', 'label_en']));
        $profile->forceFill([
            'restricted_to_zones' => $request->boolean('restricted_to_zones'),
            'permissions' => $this->permissionsOf($request),
        ])->save();

        AuditLog::record('profile.created', $profile, [], $this->traceOf($profile), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('profiles.created')]);

        return to_route('profiles.index');
    }

    /**
     * Show a profile: editable when it is a custom one, read only when it is a system profile.
     */
    public function edit(Profile $profile): Response
    {
        return Inertia::render('profiles/edit', [
            'profile' => [
                ...$profile->only(['id', 'label_fr', 'label_en', 'restricted_to_zones', 'is_system', 'permissions']),
                'name' => $profile->label,
                'users_count' => $profile->users()->count(),
            ],
            'permissions' => Permission::values(),
            'actions' => PermissionAction::byMenu(),
        ]);
    }

    /**
     * Update a custom profile. The role recorded for its users, and the sub-prefectures of those who are
     * no longer tied to any, follow the change right away.
     */
    public function update(ProfileRequest $request, Profile $profile): RedirectResponse
    {
        $before = $this->traceOf($profile);

        DB::transaction(function () use ($request, $profile): void {
            $profile->fill($request->safe()->only(['label_fr', 'label_en']));
            $profile->forceFill([
                'restricted_to_zones' => $request->boolean('restricted_to_zones'),
                'permissions' => $this->permissionsOf($request),
            ])->save();

            $profile->users()->update(['role' => $profile->role()->value]);

            if (! $profile->restricted_to_zones) {
                DB::table('zone_user')->whereIn('user_id', $profile->users()->select('id'))->delete();
            }
        });

        AuditLog::record('profile.updated', $profile, $before, $this->traceOf($profile), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('profiles.updated')]);

        return to_route('profiles.index');
    }

    /**
     * Tick or untick one right of a custom profile straight from the table of rights. Same effects as a
     * full update: the role of its users follows, and their sub-prefectures go when it is no longer restricted.
     */
    public function toggleRight(ToggleProfileRightRequest $request, Profile $profile): RedirectResponse
    {
        $before = $this->traceOf($profile);
        $right = (string) $request->validated('right');
        $granted = $request->boolean('granted');

        DB::transaction(function () use ($profile, $right, $granted): void {
            if ($right === 'restricted_to_zones') {
                $profile->forceFill(['restricted_to_zones' => $granted]);
            } else {
                // A menu brings its actions with it, or takes them away; an action goes alone.
                $affected = [$right, ...array_map(
                    fn (PermissionAction $action): string => $action->value,
                    Permission::tryFrom($right) === null ? [] : PermissionAction::ofMenu(Permission::from($right)),
                )];
                $permissions = array_values(array_unique([
                    ...array_filter($profile->permissions, fn (string $held): bool => ! in_array($held, $affected, true)),
                    ...($granted ? $affected : []),
                ]));
                $profile->forceFill(['permissions' => $permissions]);
            }

            $profile->save();
            $profile->users()->update(['role' => $profile->role()->value]);

            if (! $profile->restricted_to_zones) {
                DB::table('zone_user')->whereIn('user_id', $profile->users()->select('id'))->delete();
            }
        });

        AuditLog::record('profile.updated', $profile, $before, $this->traceOf($profile), $request->user());

        return back();
    }

    /**
     * Delete a custom profile that nobody has any more. A system profile, or one that is still given to
     * somebody, stays.
     */
    public function destroy(Request $request, Profile $profile): RedirectResponse
    {
        abort_if($profile->is_system, 403);

        if ($profile->users()->exists()) {
            return back()->withErrors(['profile' => __('profiles.errors.in_use')]);
        }

        $before = $this->traceOf($profile);
        $profile->delete();

        AuditLog::record('profile.deleted', $profile, $before, [], $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('profiles.deleted')]);

        return to_route('profiles.index');
    }

    /**
     * The permissions ticked in the form, from the closed catalogue only (the request checks it).
     *
     * @return list<string>
     */
    private function permissionsOf(ProfileRequest $request): array
    {
        return array_values((array) ($request->validated('permissions') ?? []));
    }

    /**
     * What the journal keeps of a profile.
     *
     * @return array<string, mixed>
     */
    private function traceOf(Profile $profile): array
    {
        return [
            'label_fr' => $profile->label_fr,
            'label_en' => $profile->label_en,
            'restricted_to_zones' => $profile->restricted_to_zones,
            'permissions' => $profile->permissions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function listed(Profile $profile): array
    {
        return [
            'id' => $profile->id,
            'name' => $profile->label,
            'is_system' => $profile->is_system,
            'restricted_to_zones' => $profile->restricted_to_zones,
            'permissions' => $profile->permissions,
            'users_count' => (int) $profile->getAttribute('users_count'),
        ];
    }
}
