<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AdminBusinessController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminImpersonateController;
use App\Http\Controllers\Admin\AdminLlmKeyController;
use App\Http\Middleware\RequireSuperAdmin;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Super Admin Routes
|--------------------------------------------------------------------------
|
| All routes here are protected by both 'auth' and RequireSuperAdmin.
| Any authenticated user without role = 'super_admin' receives a 403.
|
*/

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', RequireSuperAdmin::class])
    ->group(function (): void {

        // Dashboard
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Businesses
        Route::prefix('businesses')->name('businesses.')->group(function (): void {
            Route::get('/',                               [AdminBusinessController::class, 'index'])      ->name('index');
            Route::patch('/{business}/toggle',            [AdminBusinessController::class, 'toggle'])     ->name('toggle');
            Route::patch('/{business}/plan',              [AdminBusinessController::class, 'updatePlan']) ->name('update-plan');
            Route::post('/{business}/impersonate',        [AdminImpersonateController::class, 'start'])   ->name('impersonate');
        });

        // Stop impersonation (accessible from tenant dashboard during impersonation)
        Route::post('/impersonate/stop', [AdminImpersonateController::class, 'stop'])
            ->withoutMiddleware(RequireSuperAdmin::class) // Active user is the impersonated tenant
            ->name('impersonate.stop');

        // Platform LLM keys
        Route::prefix('llm-keys')->name('llm-keys.')->group(function (): void {
            Route::get('/',               [AdminLlmKeyController::class, 'index'])   ->name('index');
            Route::post('/',              [AdminLlmKeyController::class, 'store'])   ->name('store');
            Route::patch('/{llmKey}/activate', [AdminLlmKeyController::class, 'activate']) ->name('activate');
            Route::delete('/{llmKey}',    [AdminLlmKeyController::class, 'destroy']) ->name('destroy');
        });

        // Horizon — exposed only for Redis/Horizon deployments
        Route::get('/horizon', function () {
            abort_unless(config('queue.default') === 'redis', 404);

            return redirect('/horizon');
        })->name('horizon');
    });
