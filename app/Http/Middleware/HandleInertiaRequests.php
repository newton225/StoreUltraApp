<?php

namespace App\Http\Middleware;

use App\Models\TourCompletion;
use App\Support\DashboardReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'canViewProfit' => $request->user()?->can('view-profit') ?? false,
                'canManageSettings' => $request->user()?->can('manage-settings') ?? false,
                'permissions' => $request->user()?->grantedPermissions() ?? [],
                'restrictedToZones' => $request->user()?->isRestrictedToZones() ?? false,
            ],
            'locale' => app()->getLocale(),
            'supportedLocales' => config('app.supported_locales'),
            'translations' => $this->translations(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'alerts' => fn (): ?array => $this->alerts($request),
            'toursDone' => fn (): array => $request->user() === null
                ? []
                : TourCompletion::query()->where('user_id', $request->user()->id)->pluck('tour')->all(),
        ];
    }

    /**
     * What needs attention, for the notification bell of every screen. It is worked out at most once
     * a minute for each user, since it is asked for on every page.
     *
     * @return array<string, mixed>|null
     */
    protected function alerts(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        return Cache::remember(
            "alerts.user.{$user->id}",
            60,
            fn (): array => app(DashboardReport::class)->alertsFor($user),
        );
    }

    /**
     * The translation groups sent to the browser, in the current language over the fallback one.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function translations(): array
    {
        $loader = app('translator')->getLoader();
        $locale = app()->getLocale();
        $fallback = config('app.fallback_locale');

        $translations = [];

        foreach (config()->array('app.client_translation_groups') as $group) {
            $translations[$group] = array_replace_recursive(
                $loader->load($fallback, $group),
                $loader->load($locale, $group),
            );
        }

        return $translations;
    }
}
