<?php

namespace App\Http\Controllers;

use App\Actions\Users\InviteUser;
use App\Enums\UserRole;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\AuditLog;
use App\Models\Profile;
use App\Models\User;
use App\Models\Zone;
use App\Support\Search;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * List every user account with its profile.
     */
    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->toString());
        $profileId = $request->integer('profile') ?: null;
        $state = in_array($request->string('state')->toString(), User::STATES, true) ? $request->string('state')->toString() : null;

        return Inertia::render('users/index', [
            'filters' => ['search' => $search, 'profile' => $profileId, 'state' => $state],
            'profiles' => $this->profiles(),
            'states' => User::STATES,
            'users' => User::query()
                ->with('profile')
                ->filteredBy($search, $profileId, $state)
                ->tap(fn ($query) => Search::orderBy($query, 'name'))
                ->orderBy('id')
                ->get()
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'profile' => $user->effectiveProfile()->label,
                    'is_active' => $user->is_active,
                    'invitation_pending' => $user->password_set_at === null,
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
        ]);
    }

    /**
     * Create a user account without a password the owner knows: the person gets an e-mail to choose their own
     * (playbook SEC-165). Until they do, the account holds a random password nobody has seen. The invitation
     * itself proves the address, so the account is not asked to verify it again.
     */
    public function store(StoreUserRequest $request, InviteUser $invitations): RedirectResponse
    {
        $profile = $this->profileOf($request);

        $user = new User($request->safe()->only(['name', 'email']));
        $user->forceFill([
            'password' => Str::random(64),
            'password_set_at' => null,
            'role' => $profile->role(),
            'profile_id' => $profile->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();
        $user->setRelation('profile', $profile);

        $this->syncZones($user, $request->validated('zone_ids'));

        AuditLog::record('user.created', $user, [], ['email' => $user->email, 'profile' => $profile->label_fr], $request->user());

        $sent = $invitations->sendByMail($user, $request->user());

        Inertia::flash('toast', $sent
            ? ['type' => 'success', 'message' => __('users.created_invited', ['email' => $user->email])]
            : ['type' => 'warning', 'message' => __($invitations->mailIsReal() ? 'users.created_not_sent' : 'users.mail_test_mode')]);

        return $sent ? to_route('users.index') : to_route('users.edit', $user);
    }

    /**
     * Send the user a new e-mail to choose their password (an invitation that expired, or a forgotten password).
     */
    public function invite(Request $request, User $user, InviteUser $invitations): RedirectResponse
    {
        abort_if($user->is($request->user()), 403);
        abort_unless($user->is_active, 422);

        $sent = $invitations->sendByMail($user, $request->user());

        Inertia::flash('toast', $sent
            ? ['type' => 'success', 'message' => __('users.invitation_sent', ['email' => $user->email])]
            : ['type' => 'error', 'message' => __($invitations->mailIsReal() ? 'users.invitation_not_sent' : 'users.mail_test_mode')]);

        return back();
    }

    /**
     * A link to hand over when the e-mail is not available. It replaces the previous one and gives access to the
     * account, so it is shown once, to the owner, and traced.
     */
    public function invitationLink(Request $request, User $user, InviteUser $invitations): RedirectResponse
    {
        abort_if($user->is($request->user()), 403);
        abort_unless($user->is_active, 422);

        Inertia::flash('invitation_link', $invitations->linkToHandOver($user, $request->user()));

        return back();
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
                'invitation_pending' => $user->password_set_at === null,
                'invitation_sent_at' => $user->invitation_sent_at?->toIso8601String(),
            ],
            'profiles' => $this->profiles(),
            'zones' => $this->zones(),
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

        $user->save();

        // A deactivated account keeps no link that could still set its password (invitation or forgotten password).
        if ($before['is_active'] && ! $user->is_active) {
            Password::deleteToken($user);
        }

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
