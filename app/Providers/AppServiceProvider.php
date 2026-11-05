<?php

namespace App\Providers;

use App\Enums\DefaultRole;
use App\Models\Person;
use App\Models\User;
use App\Support\Brand;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->configureSuperAdmin();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureMorphMap();
        Brand::applyToConfig();
    }

    /**
     * Deactivated accounts pass nothing; super admins pass every permission check. This runs while registering so
     * it comes before the permission package's own check, which is added when it boots and would otherwise grant
     * a deactivated account its role permissions.
     */
    protected function configureSuperAdmin(): void
    {
        Gate::before(function (User $user): ?bool {
            if ($user->hasRole(DefaultRole::Inactive->value)) {
                return false;
            }

            return $user->hasRole(DefaultRole::SuperAdmin->value) ? true : null;
        });
    }

    /**
     * Store short aliases instead of class names in polymorphic columns.
     */
    protected function configureMorphMap(): void
    {
        Relation::enforceMorphMap([
            'person' => Person::class,
            'user' => User::class,
            'role' => Role::class,
        ]);
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
