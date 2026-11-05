<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Listeners\RecordAuthenticationEvents;
use App\Models\User;
use App\Support\ProductionCheck;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureTrustedProxies();
        $this->configureRateLimiters();
        $this->refuseUnsafeProduction();
        Event::subscribe(RecordAuthenticationEvents::class);
    }

    /**
     * Sending a file is costly (it is read, decoded and written again): a user, or an address when
     * nobody is signed in, may send twenty a minute.
     */
    protected function configureRateLimiters(): void
    {
        RateLimiter::for('uploads', fn (Request $request): Limit => Limit::perMinute(20)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }

    /**
     * Only the proxies listed in TRUSTED_PROXIES may tell us the address and the scheme of a visitor.
     */
    protected function configureTrustedProxies(): void
    {
        $proxies = (array) config('security.trusted_proxies');

        if ($proxies !== []) {
            TrustProxies::at($proxies);
        }
    }

    /**
     * A production server does not answer a web request with a dangerous configuration (debug on,
     * no HTTPS, no second factor for the owner...). The commands stay available to fix it.
     */
    protected function refuseUnsafeProduction(): void
    {
        if ($this->app->isProduction() && ! $this->app->runningInConsole()) {
            $this->app->make(ProductionCheck::class)->abortIfUnsafe();
        }
    }

    /**
     * Define the abilities. Managing users, profiles, the settings, the sub-prefectures and the audit log
     * stay with the owner; everything else is a permission of the profile of the user (the owner has them all).
     */
    protected function configureAuthorization(): void
    {
        Gate::define('manage-users', fn (User $user): bool => $user->isOwner());
        Gate::define('manage-settings', fn (User $user): bool => $user->isOwner());
        Gate::define('manage-catalog', fn (User $user): bool => $user->hasPermission(Permission::ManageCatalog));
        Gate::define('manage-orders', fn (User $user): bool => $user->hasPermission(Permission::ManageOrders));
        Gate::define('manage-purchases', fn (User $user): bool => $user->hasPermission(Permission::ManagePurchases));
        Gate::define('manage-expenses', fn (User $user): bool => $user->hasPermission(Permission::ManageExpenses));
        Gate::define('manage-cash', fn (User $user): bool => $user->hasPermission(Permission::ManageCash));
        Gate::define('view-profit', fn (User $user): bool => $user->hasPermission(Permission::ViewProfit));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
