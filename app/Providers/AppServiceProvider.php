<?php

namespace App\Providers;

use App\Enums\Rank;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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
        if (app()->environment('production') || str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        $this->registerRankGates();
    }

    /**
     * Register platform-wide abilities that are not tied to a single model.
     *
     * The admin panel stays Admin I only until Phase 7 builds the geolocation-free
     * Admin II view; opening it earlier would expose geolocation and IPs (decision Q9).
     */
    protected function registerRankGates(): void
    {
        Gate::define('accessAdminPanel', fn (User $user): bool => $user->hasRank(Rank::AdminOne));
        Gate::define('viewGeolocation', fn (User $user): bool => $user->hasRank(Rank::AdminOne));
        Gate::define('voteOnBans', fn (User $user): bool => $user->displaysAsAdmin() && ! $user->isBanned());
        Gate::define('banAdminTwo', fn (User $user): bool => $user->hasRank(Rank::AdminOne));
    }
}
