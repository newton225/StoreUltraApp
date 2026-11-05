<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\AuditLog;
use App\Models\Profile;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * List every user account with its profile.
     */
    public function index(): Response
    {
        return Inertia::render('users/index', [
            'users' => User::query()
                ->with('profile')
                ->orderBy('name')
                ->get()
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'profile' => $user->effectiveProfile()->label,
                    'is_active' => $user->is_active,
                ])
                ->values(),
        ]);
    }

    /**
     * Show the form creating a user account.
     */
    public function create(): Response
    {
        return Inertia::render('users/create', [
            'profiles' => $this->profiles(),
            'zones' => $this->zones(),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    /**
     * Create a user account. The owner vouches for the email address, so it starts verified.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $profile = $this->profileOf($request);

        $user = new User($request->safe()->only(['name', 'email', 'password']));
        $user->forceFill([
            'role' => $profile->role(),
            'profile_id' => $profile->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();
        $user->setRelation('profile', $profile);

        $this->syncZones($user, $request->validated('zone_ids'));

        AuditLog::record('user.created', $user, [], ['email' => $user->email, 'profile' => $profile->label_fr], $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('users.created')]);

        return to_route('users.index');
    }

    /**
     * Show the form editing a user account.
     */
    public function edit(User $user): Response
    {
        return Inertia::render('users/edit', [
            'editedUser' => [
                ...$user->only(['id', 'name', 'email', 'is_active']),
                'profile_id' => $user->effectiveProfile()->id,
                'zone_ids' => $user->zones()->pluck('zones.id')->all(),
            ],
            'profiles' => $this->profiles(),
            'zones' => $this->zones(),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    /**
     * Update a user account.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $profile = $this->profileOf($request);
        $before = ['profile' => $user->effectiveProfile()->label_fr, 'is_active' => $user->is_active];

        $user->fill($request->safe()->only(['name', 'email']));
        $user->forceFill([
            'role' => $profile->role(),
            'profile_id' => $profile->id,
            'is_active' => $request->boolean('is_active'),
        ]);
        $user->setRelation('profile', $profile);

        if ($request->filled('password')) {
            $user->password = $request->validated('password');
        }

        $user->save();

        $this->syncZones($user, $request->validated('zone_ids'));

        AuditLog::record('user.updated', $user, $before, ['profile' => $profile->label_fr, 'is_active' => $user->is_active], $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('users.updated')]);

        return to_route('users.index');
    }

    /**
     * The profile chosen in the form. A request that still names only a role (the three profiles that
     * existed before profiles could be changed) gets the profile of that role.
     */
    private function profileOf(StoreUserRequest|UpdateUserRequest $request): Profile
    {
        return $request->filled('profile_id')
            ? Profile::query()->findOrFail($request->integer('profile_id'))
            : Profile::forRole(UserRole::from((string) $request->validated('role')));
    }

    /**
     * Only a user whose profile is tied to sub-prefectures keeps them: any other loses them.
     *
     * @param  array<int, int>|null  $zoneIds
     */
    private function syncZones(User $user, ?array $zoneIds): void
    {
        $user->zones()->sync($user->isRestrictedToZones() ? ($zoneIds ?? []) : []);
    }

    /**
     * @return Collection<int, Zone>
     */
    private function zones(): Collection
    {
        return Zone::query()->orderBy('name')->get(['id', 'name', 'active']);
    }

    /**
     * The profiles a user can be given.
     *
     * @return list<array{id: int, name: string, restricted_to_zones: bool}>
     */
    private function profiles(): array
    {
        return array_values(Profile::query()
            ->orderByDesc('is_system')
            ->orderBy('label_fr')
            ->get()
            ->map(fn (Profile $profile): array => [
                'id' => $profile->id,
                'name' => $profile->label,
                'restricted_to_zones' => $profile->restricted_to_zones,
            ])
            ->all());
    }
}
