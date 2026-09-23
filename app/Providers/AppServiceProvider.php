<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\RolePolicy;
use App\Support\Tenancy\CurrentCommunity;
use App\Support\Tenancy\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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
        $this->app->scoped(CurrentCompany::class);
        $this->app->scoped(CurrentCommunity::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureTenancy();
    }

    /**
     * Scope role and permission checks to the signed-in user's company.
     */
    protected function configureTenancy(): void
    {
        Gate::policy(Role::class, RolePolicy::class);

        Event::listen(function (Authenticated $event): void {
            if ($event->user instanceof User) {
                setPermissionsTeamId($event->user->company_id);
                $event->user->unsetRelation('roles')->unsetRelation('permissions');
            }
        });
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

        Model::shouldBeStrict(! app()->isProduction());

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
