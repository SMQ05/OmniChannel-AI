<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\RequireSuperAdmin;
use App\Models\BusinessDataRetentionSetting;
use App\Models\DataGovernanceRequest;
use App\Models\TeamInvite;
use App\Models\User;
use App\Policies\BusinessDataRetentionSettingPolicy;
use App\Policies\DataGovernanceRequestPolicy;
use App\Policies\TeamInvitePolicy;
use App\Policies\UserPolicy;
use App\Services\Audit\AuditLogger;
use App\Services\Voice\VoiceProviderResolver;
use App\Voice\Contracts\LlmStreamInterface;
use App\Voice\Contracts\SpeechToTextInterface;
use App\Voice\Contracts\TelephonyTransportInterface;
use App\Voice\Contracts\TextToSpeechInterface;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

/**
 * Application Service Provider.
 *
 * Responsible for:
 *  - Routing named job classes to their designated Redis queues so
 *    Horizon can process each queue tier independently.
 *  - Any application-level bindings that do not belong in a
 *    domain-specific provider.
 *
 * Queue topology:
 *  webhooks     — inbound Meta webhook payloads (latency-sensitive)
 *  ai           — AI agent invocations (reserved for Phase 3 direct dispatch)
 *  reminders    — outbound reminder messages
 *  integrations — Google Calendar / Sheets sync
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TelephonyTransportInterface::class, fn ($app) => $app->make(VoiceProviderResolver::class)->transport());
        $this->app->singleton(SpeechToTextInterface::class, fn ($app) => $app->make(VoiceProviderResolver::class)->stt());
        $this->app->singleton(LlmStreamInterface::class, fn ($app) => $app->make(VoiceProviderResolver::class)->llm());
        $this->app->singleton(TextToSpeechInterface::class, fn ($app) => $app->make(VoiceProviderResolver::class)->tts());
        $this->app->singleton('audit.logger', fn ($app) => new AuditLogger());
    }

    /**
     * Bootstrap any application services.
     *
     * Queue routing uses Queue::route() introduced in Laravel 11+ to
     * declaratively bind job classes to specific connection/queue pairs
     * without touching each job's $queue property.
     */
    public function boot(): void
    {
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        $this->registerMiddlewareAliases();
        $this->registerPolicies();
        $this->registerHorizonGate();
    }

    /**
     * Register middleware aliases for use in route files.
     */
    private function registerMiddlewareAliases(): void
    {
        Route::aliasMiddleware('super_admin', RequireSuperAdmin::class);
    }

    private function registerPolicies(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(TeamInvite::class, TeamInvitePolicy::class);
        Gate::policy(DataGovernanceRequest::class, DataGovernanceRequestPolicy::class);
        Gate::policy(BusinessDataRetentionSetting::class, BusinessDataRetentionSettingPolicy::class);
    }

    /**
     * Allow Horizon access to authenticated super_admin users when Redis mode is enabled.
     */
    private function registerHorizonGate(): void
    {
        if (!class_exists(\Laravel\Horizon\Horizon::class)) {
            return;
        }

        Gate::define('viewHorizon', fn (?object $user): bool => $user instanceof \App\Models\User && $user->isSuperAdmin());
    }

}
