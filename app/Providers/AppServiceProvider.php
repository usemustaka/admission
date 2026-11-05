<?php

namespace App\Providers;

use App\Enums\DefaultRole;
use App\Models\Person;
use App\Models\User;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureMorphMap();
        $this->configureSuperAdmin();
    }

    /**
     * Super admins pass every permission check.
     */
    protected function configureSuperAdmin(): void
    {
        Gate::before(fn (User $user): ?bool => $user->hasRole(DefaultRole::SuperAdmin->value) ? true : null);
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
