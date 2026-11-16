<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::define('place-orders', fn (User $user): bool => $user->hasRole(Role::Admin, Role::Balcao));
        Gate::define('manage-orders', fn (User $user): bool => $user->hasRole(Role::Admin, Role::Balcao));
        Gate::define('mark-ready', fn (User $user): bool => $user->hasRole(Role::Admin, Role::Balcao, Role::Cozinha));

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(Str::lower((string) $request->input('username')).'|'.$request->ip()));
    }
}
