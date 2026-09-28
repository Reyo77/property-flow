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
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;
use Knuckles\Scribe\Scribe;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\CleanupHasFailed;
use Spatie\Backup\Events\UnhealthyBackupWasFound;
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
        $this->configureApiDocs();
        $this->configureReliability();
    }

    /**
     * Failed jobs and backup problems don't otherwise stand out in the logs; a `critical`-level
     * entry is what a log alerting rule (e.g. in the hosting platform or a log shipper) would
     * watch for. No email provider is wired up yet (Phase 12), so this replaces laravel-backup's
     * own mail notifications rather than leaving them silently unconfigured.
     */
    protected function configureReliability(): void
    {
        Queue::failing(function (JobFailed $event): void {
            Log::critical('Queued job failed', [
                'connection' => $event->connectionName,
                'job' => $event->job->resolveName(),
                'exception' => $event->exception->getMessage(),
            ]);
        });

        Event::listen(function (BackupHasFailed $event): void {
            Log::critical('Backup failed', ['exception' => $event->exception->getMessage()]);
        });

        Event::listen(function (CleanupHasFailed $event): void {
            Log::critical('Backup cleanup failed', ['exception' => $event->exception->getMessage()]);
        });

        Event::listen(function (UnhealthyBackupWasFound $event): void {
            Log::critical('Backup is unhealthy', [
                'disk' => $event->diskName,
                'reason' => $event->failureMessages->pluck('message')->implode(' '),
            ]);
        });
    }

    /**
     * Document URLs exactly as they are routed (`{community}`, `{serviceRequest}`) rather than
     * Scribe's `{community_id}` style. Scribe is a development tool, so it's absent in production.
     */
    protected function configureApiDocs(): void
    {
        if (class_exists(Scribe::class)) {
            Scribe::normalizeEndpointUrlUsing(fn (string $url): string => $url);
        }
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
