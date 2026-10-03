<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\RolePolicy;
use App\Services\CompanyLookup\CachedCompanyLookup;
use App\Services\CompanyLookup\CodiumCompanyLookup;
use App\Services\CompanyLookup\CompanyLookup;
use App\Services\CompanyLookup\FakeCompanyLookup;
use App\Services\Fees\FeeScheduleService;
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
        $this->app->bind(FeeScheduleService::class, fn () => FeeScheduleService::fromConfig());

        $this->app->singleton(CompanyLookup::class, function () {
            $config = config('services.company_lookup');
            $driver = match ($config['driver']) {
                'codium' => new CodiumCompanyLookup($config['codium']),
                'fake' => new FakeCompanyLookup,
                default => throw new \InvalidArgumentException("Unknown COMPANY_LOOKUP_DRIVER [{$config['driver']}]."),
            };

            return new CachedCompanyLookup($driver, (int) $config['cache_hours']);
        });
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
        // Only for declared permission names: policy abilities ("update" a transaction, …) also encode
        // business rules (e.g. only drafts are editable) that must hold for super-admins too. Policies
        // call $user->can('<permission>') internally, so super-admins still pass the permission part.
        Gate::before(fn (User $user, string $ability) => in_array($ability, Permissions::all(), true)
            && $user->hasRole(Permissions::superAdminRole()) ? true : null);

        Password::defaults(function () {
            $rule = Password::min(10)->letters()->mixedCase()->numbers()->symbols();

            // The breach check calls an external API, so it only runs in production.
            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });
    }
}
