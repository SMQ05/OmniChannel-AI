<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        then: function () {
            \Illuminate\Support\Facades\Route::middleware('web')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\TrustProxies::class);

        $middleware->alias([
            'super_admin'      => \App\Http\Middleware\RequireSuperAdmin::class,
            'require_business' => \App\Http\Middleware\RequireBusiness::class,
            'permission'       => \App\Http\Middleware\EnsurePermission::class,
            'voice_gateway'    => \App\Http\Middleware\VerifyVoiceGatewayRequest::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
