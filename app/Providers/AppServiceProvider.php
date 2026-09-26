<?php

namespace App\Providers;

use App\Http\Webhooks\ApiResourcePayloads;
use App\Models\User;
use App\Policies\RolePolicy;
use App\Support\Payments\LocalPaymentGateway;
use App\Support\Payments\PaymentGateway;
use App\Support\Tenancy\CurrentCommunity;
use App\Support\Tenancy\CurrentCompany;
use App\Support\Webhooks\WebhookPayloads;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(WebhookPayloads::class, ApiResourcePayloads::class);
        $this->app->scoped(CurrentCompany::class);
        $this->app->scoped(CurrentCommunity::class);
        $this->app->singleton(PaymentGateway::class, fn ($app) => match (config('services.payments.gateway')) {
            'local' => $app->make(LocalPaymentGateway::class),
            default => throw new InvalidArgumentException('Unknown payment gateway ['.json_encode(config('services.payments.gateway')).'].'),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureTenancy();
        $this->configureRateLimiting();
    }

    /**
     * API limits: each user (or, before sign-in, each address) gets a steady allowance, and
     * token sign-in is slowed per email and address to blunt password guessing.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('api-token', fn (Request $request) => Limit::perMinute(5)->by(Str::lower($request->string('email')->toString()).'|'.$request->ip()));
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
