<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AdminAiPolicyController;
use App\Http\Controllers\Admin\AdminAuditLogController;
use App\Http\Controllers\Admin\AdminBusinessController;
use App\Http\Controllers\Admin\AdminBillingController;
use App\Http\Controllers\Admin\AdminBillingPriceController;
use App\Http\Controllers\Admin\AdminComplianceController;
use App\Http\Controllers\Admin\AdminCredentialsController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminIncidentsController;
use App\Http\Controllers\Admin\AdminImpersonateController;
use App\Http\Controllers\Admin\AdminLlmKeyController;
use App\Http\Controllers\Admin\AdminMonitoringController;
use App\Http\Controllers\Admin\AdminOnboardingController;
use App\Http\Controllers\Admin\AdminPlanController;
use App\Http\Controllers\Admin\AdminSupportController;
use App\Http\Controllers\Admin\AdminVoiceController;
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
            Route::patch('/{business}/subscription',      [AdminBusinessController::class, 'updateSubscription'])->name('update-subscription');
            Route::patch('/{business}/owner-password',    [AdminBusinessController::class, 'updateOwnerPassword'])->name('update-owner-password');
            Route::patch('/{business}/messaging/{channel}', [AdminBusinessController::class, 'updateMessagingConnection'])->name('messaging.update');
            Route::post('/{business}/messaging/{channel}/test', [AdminBusinessController::class, 'testMessagingConnection'])->name('messaging.test');
            Route::patch('/{business}/messaging/{channel}/disconnect', [AdminBusinessController::class, 'disconnectMessagingConnection'])->name('messaging.disconnect');
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

        Route::prefix('plans')->name('plans.')->group(function (): void {
            Route::get('/', [AdminPlanController::class, 'index'])->name('index');
            Route::post('/', [AdminPlanController::class, 'store'])->name('store');
            Route::patch('/{plan}', [AdminPlanController::class, 'update'])->name('update');
        });

        Route::prefix('billing')->name('billing.')->group(function (): void {
            Route::get('/', [AdminBillingController::class, 'index'])->name('index');
            Route::get('/prices', [AdminBillingPriceController::class, 'index'])->name('prices.index');
            Route::post('/prices', [AdminBillingPriceController::class, 'store'])->name('prices.store');
            Route::patch('/prices/{billingPrice}', [AdminBillingPriceController::class, 'update'])->name('prices.update');
            Route::get('/{business}', [AdminBillingController::class, 'show'])->name('show');
            Route::patch('/{business}/account', [AdminBillingController::class, 'updateAccount'])->name('account.update');
            Route::patch('/{business}/subscription', [AdminBillingController::class, 'updateSubscription'])->name('subscription.update');
            Route::post('/{business}/documents/run-cycle', [AdminBillingController::class, 'runCycle'])->name('documents.run-cycle');
            Route::post('/{business}/ledger/manual-adjustment', [AdminBillingController::class, 'storeManualAdjustment'])->name('ledger.adjustment.store');
            Route::patch('/{business}/suspend', [AdminBillingController::class, 'suspend'])->name('suspend');
            Route::patch('/{business}/reactivate', [AdminBillingController::class, 'reactivate'])->name('reactivate');
            Route::patch('/documents/{billingDocument}/mark-paid', [AdminBillingController::class, 'markPaid'])->name('documents.mark-paid');
        });

        Route::prefix('voice')->name('voice.')->group(function (): void {
            Route::get('/', [AdminVoiceController::class, 'index'])->name('index');
            Route::patch('/{business}', [AdminVoiceController::class, 'updateBusiness'])->name('update');
            Route::post('/{business}/channels', [AdminVoiceController::class, 'storeChannel'])->name('channels.store');
            Route::patch('/{business}/channels/{voiceChannel}/toggle', [AdminVoiceController::class, 'toggleChannel'])->name('channels.toggle');
            Route::post('/{business}/test/{component}', [AdminVoiceController::class, 'testProvider'])->name('test');
        });

        // ======================================================================
        // PHASE 7 ADMIN CONTROL PLANE
        // ======================================================================

        // Onboarding and Launch Readiness
        // Tracks admin workflow state for onboarding progress
        Route::prefix('onboarding')->name('onboarding.')->group(function (): void {
            Route::get('/', [AdminOnboardingController::class, 'index'])->name('index');
            Route::get('/{business}', [AdminOnboardingController::class, 'show'])->name('show');
            Route::post('/{business}/complete', [AdminOnboardingController::class, 'completeOnboarding'])->name('complete');
            Route::post('/{business}/approve', [AdminOnboardingController::class, 'approveLaunch'])->name('approve');
            Route::post('/{business}/reset', [AdminOnboardingController::class, 'reset'])->name('reset');
            Route::post('/{business}/refresh-readiness', [AdminOnboardingController::class, 'refreshReadiness'])->name('refresh-readiness');
            Route::get('/summary', [AdminOnboardingController::class, 'summary'])->name('summary');
        });

        // Incident Banner Management
        // Platform and business-specific incident notifications
        Route::prefix('incidents')->name('incidents.')->group(function (): void {
            Route::get('/', [AdminIncidentsController::class, 'index'])->name('index');
            Route::get('/create', [AdminIncidentsController::class, 'create'])->name('create');
            Route::post('/', [AdminIncidentsController::class, 'store'])->name('store');
            Route::get('/{incident}/edit', [AdminIncidentsController::class, 'edit'])->name('edit');
            Route::patch('/{incident}', [AdminIncidentsController::class, 'update'])->name('update');
            Route::post('/{incident}/publish', [AdminIncidentsController::class, 'publish'])->name('publish');
            Route::post('/{incident}/archive', [AdminIncidentsController::class, 'archive'])->name('archive');
            Route::post('/{incident}/resolve', [AdminIncidentsController::class, 'resolve'])->name('resolve');
            Route::delete('/{incident}', [AdminIncidentsController::class, 'destroy'])->name('destroy');
            Route::get('/{business}/active', [AdminIncidentsController::class, 'activeForBusiness'])->name('active-for-business');
        });

        // Credential Metadata Management
        // Tracks credential metadata, rotation schedule, verification status
        // NOTE: This is a masked metadata workspace - never exposes raw secrets
        Route::prefix('credentials')->name('credentials.')->group(function (): void {
            Route::get('/', [AdminCredentialsController::class, 'index'])->name('index');
            Route::get('/{sourceType}/{sourceId}', [AdminCredentialsController::class, 'show'])->name('show');
            Route::post('/{sourceType}/{sourceId}/rotation', [AdminCredentialsController::class, 'recordRotation'])->name('record-rotation');
            Route::patch('/{credential}', [AdminCredentialsController::class, 'updateSettings'])->name('update-settings');
            Route::post('/{credential}/verify', [AdminCredentialsController::class, 'recordVerification'])->name('record-verification');
            Route::post('/{credential}/deactivate', [AdminCredentialsController::class, 'deactivate'])->name('deactivate');
            Route::post('/{business}/messaging/{channel}/create', [AdminCredentialsController::class, 'createForMessagingConnection'])->name('create-for-messaging');
            Route::post('/{business}/voice/{voiceChannel}/create', [AdminCredentialsController::class, 'createForVoiceChannel'])->name('create-for-voice');
        });

        // AI Policy Management
        // Views and controls for AI-related policies
        // NOTE: Does not imply centralized runtime - just admin visibility
        Route::prefix('ai-policy')->name('ai-policy.')->group(function (): void {
            Route::get('/', [AdminAiPolicyController::class, 'index'])->name('index');
            Route::get('/{business}', [AdminAiPolicyController::class, 'show'])->name('show');
            Route::patch('/{business}', [AdminAiPolicyController::class, 'update'])->name('update');
            Route::post('/{business}/reset-defaults', [AdminAiPolicyController::class, 'resetToDefaults'])->name('reset-defaults');
        });

        // Support Actions Workspace
        // Auditably controlled support actions
        // NOTE: Non-wildcard routes must come before wildcard routes
        Route::prefix('support')->name('support.')->group(function (): void {
            Route::get('/', [AdminSupportController::class, 'index'])->name('index');
            Route::get('/export-audit', [AdminSupportController::class, 'exportAudit'])->name('export-audit');
            Route::get('/recent-actions', [AdminSupportController::class, 'recentActions'])->name('recent-actions');
            Route::get('/{business}', [AdminSupportController::class, 'show'])->name('show');
            Route::post('/{business}/note', [AdminSupportController::class, 'addNote'])->name('add-note');
            Route::post('/{business}/replay-webhook', [AdminSupportController::class, 'replayLastInbound'])->name('replay-webhook');
            Route::post('/{business}/rerun-billing', [AdminSupportController::class, 'rerunBillingCycle'])->name('rerun-billing');
            Route::post('/{business}/refresh-launch', [AdminSupportController::class, 'refreshLaunchReadiness'])->name('refresh-launch');
            Route::post('/{business}/link-messaging-test', [AdminSupportController::class, 'linkToMessagingTest'])->name('link-messaging-test');
            Route::post('/{business}/link-voice-test', [AdminSupportController::class, 'linkToVoiceTest'])->name('link-voice-test');
        });

        // Audit Log Explorer
        // Comprehensive audit trail for privileged admin actions
        // NOTE: Specific routes must come before wildcard routes to avoid matching as auditLog ID
        Route::prefix('audit-logs')->name('audit-logs.')->group(function (): void {
            Route::get('/', [AdminAuditLogController::class, 'index'])->name('index');
            Route::get('/export', [AdminAuditLogController::class, 'export'])->name('export');
            Route::get('/search/request-id', [AdminAuditLogController::class, 'searchByRequestId'])->name('search-request-id');
            Route::get('/recent', [AdminAuditLogController::class, 'recent'])->name('recent');
            Route::get('/{auditLog}', [AdminAuditLogController::class, 'show'])->name('show');
            Route::get('/business/{business}', [AdminAuditLogController::class, 'forBusiness'])->name('for-business');
            Route::get('/subject/{subjectType}/{subjectId}', [AdminAuditLogController::class, 'forSubject'])->name('for-subject');
        });

        // ======================================================================
        // PHASE 8 MONITORING, COMPLIANCE, DATA CONTROLS
        // ======================================================================
        Route::prefix('monitoring')->name('monitoring.')->group(function (): void {
            Route::get('/', [AdminMonitoringController::class, 'index'])->name('index');
            Route::post('/capture/platform', [AdminMonitoringController::class, 'capturePlatform'])->name('capture-platform');
            Route::post('/capture/tenant/{business}', [AdminMonitoringController::class, 'captureTenant'])->name('capture-tenant');
        });

        Route::prefix('compliance')->name('compliance.')->group(function (): void {
            Route::get('/', [AdminComplianceController::class, 'index'])->name('index');
            Route::get('/requests/{governanceRequest}', [AdminComplianceController::class, 'show'])->name('show');
            Route::post('/requests/{governanceRequest}/approve', [AdminComplianceController::class, 'approve'])->name('approve');
            Route::post('/requests/{governanceRequest}/reject', [AdminComplianceController::class, 'reject'])->name('reject');
            Route::post('/requests/{governanceRequest}/run', [AdminComplianceController::class, 'run'])->name('run');
            Route::get('/requests/{governanceRequest}/artifact', [AdminComplianceController::class, 'downloadArtifact'])->name('artifact');
            Route::patch('/retention/{business}', [AdminComplianceController::class, 'updateRetention'])->name('retention.update');
            Route::post('/retention/run', [AdminComplianceController::class, 'runRetention'])->name('retention.run');
        });

        // Horizon - exposed only for Redis/Horizon deployments
        Route::get('/horizon', function () {
            abort_unless(config('queue.default') === 'redis', 404);

            return redirect('/horizon');
        })->name('horizon');
    });
