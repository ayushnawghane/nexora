<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\RolePolicy;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Lazy loading, silently discarded attributes and missing attributes throw instead of failing quietly.
        Model::shouldBeStrict();

        // Destructive commands (migrate:fresh, db:wipe, ...) are blocked outside local/testing.
        DB::prohibitDestructiveCommands(! $this->app->environment('local', 'testing'));

        Gate::policy(Role::class, RolePolicy::class);

        // The super-admin role passes every permission check.
        Gate::before(fn (User $user) => $user->hasRole(Permissions::superAdminRole()) ? true : null);

        Password::defaults(function () {
            $rule = Password::min(10)->letters()->mixedCase()->numbers()->symbols();

            // The breach check calls an external API, so it only runs in production.
            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });
    }
}
