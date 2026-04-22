<?php

declare(strict_types=1);

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\Auth\TeamInviteAcceptanceController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProviderController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\Settings\AiSettingsController;
use App\Http\Controllers\Settings\BookingRulesController;
use App\Http\Controllers\Settings\BillingSettingsController;
use App\Http\Controllers\Settings\ChannelSettingsController;
use App\Http\Controllers\Settings\DataControlsController;
use App\Http\Controllers\Settings\DiagnosticsController;
use App\Http\Controllers\Settings\IntegrationSettingsController;
use App\Http\Controllers\Settings\ReminderSettingsController;
use App\Http\Controllers\Settings\SubscriptionSettingsController;
use App\Http\Controllers\Settings\TeamManagementController;
use App\Http\Controllers\Settings\VoiceSettingsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    if ($request->user() !== null) {
        return redirect()->route('dashboard');
    }

    return view('marketing.home');
})->name('marketing.home');

Route::view('/features', 'marketing.features')->name('marketing.features');
Route::view('/pricing', 'marketing.pricing')->name('marketing.pricing');

// Public Pages (Required by Meta App Review)
Route::view('/privacy-policy', 'privacy-policy')->name('privacy.policy');
Route::get('/team-invites/{token}', [TeamInviteAcceptanceController::class, 'show'])->middleware('guest')->name('team-invites.show');
Route::post('/team-invites/{token}/accept', [TeamInviteAcceptanceController::class, 'store'])->middleware('guest')->name('team-invites.accept');

Route::middleware(['auth'])->group(function (): void {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/stats', [DashboardController::class, 'stats'])->name('dashboard.stats');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'require_business'])->group(function (): void {

    // Appointments
    Route::get('/appointments', [AppointmentController::class, 'index'])->middleware('permission:business.appointments.view')->name('appointments.index');
    Route::get('/appointments/feed', [AppointmentController::class, 'feed'])->middleware('permission:business.appointments.view')->name('appointments.feed');
    Route::get('/appointments/create', [AppointmentController::class, 'create'])->middleware('permission:business.appointments.manage')->name('appointments.create');
    Route::post('/appointments', [AppointmentController::class, 'store'])->middleware('permission:business.appointments.manage')->name('appointments.store');
    Route::get('/appointments/{appointment}', [AppointmentController::class, 'show'])->middleware('permission:business.appointments.view')->name('appointments.show');
    Route::get('/appointments/{appointment}/edit', [AppointmentController::class, 'edit'])->middleware('permission:business.appointments.manage')->name('appointments.edit');
    Route::patch('/appointments/{appointment}', [AppointmentController::class, 'update'])->middleware('permission:business.appointments.manage')->name('appointments.update');
    Route::patch('/appointments/{appointment}/status', [AppointmentController::class, 'updateStatus'])->middleware('permission:business.appointments.manage')->name('appointments.update-status');

    // Conversations
    Route::get('/conversations', [ConversationController::class, 'index'])->middleware('permission:business.conversations.view')->name('conversations.index');
    Route::get('/conversations/{conversationLog}', [ConversationController::class, 'show'])->middleware('permission:business.conversations.view')->name('conversations.show');
    Route::post('/conversations/{conversationLog}/takeover', [ConversationController::class, 'takeover'])->middleware('permission:business.conversations.takeover')->name('conversations.takeover');
    Route::post('/conversations/{conversationLog}/notes', [ConversationController::class, 'storeNote'])->middleware('permission:business.conversations.manage')->name('conversations.notes.store');

    // Providers
    Route::get('/providers', [ProviderController::class, 'index'])->middleware('permission:business.providers.view')->name('providers.index');
    Route::get('/providers/create', [ProviderController::class, 'create'])->middleware('permission:business.providers.manage')->name('providers.create');
    Route::post('/providers', [ProviderController::class, 'store'])->middleware('permission:business.providers.manage')->name('providers.store');
    Route::get('/providers/{provider}', [ProviderController::class, 'show'])->middleware('permission:business.providers.view')->name('providers.show');
    Route::patch('/providers/{provider}', [ProviderController::class, 'update'])->middleware('permission:business.providers.manage')->name('providers.update');
    Route::patch('/providers/{provider}/toggle', [ProviderController::class, 'toggle'])->middleware('permission:business.providers.manage')->name('providers.toggle');
    Route::post('/providers/{provider}/blocked-dates', [ProviderController::class, 'addBlockedDate'])->middleware('permission:business.providers.manage')->name('providers.blocked-dates.store');
    Route::delete('/providers/{provider}/blocked-dates/{blockedDate}', [ProviderController::class, 'removeBlockedDate'])->middleware('permission:business.providers.manage')->name('providers.blocked-dates.destroy');

    // Services
    Route::get('/services', [ServiceController::class, 'index'])->middleware('permission:business.services.view')->name('services.index');
    Route::post('/services', [ServiceController::class, 'store'])->middleware('permission:business.services.manage')->name('services.store');
    Route::get('/services/{service}', [ServiceController::class, 'show'])->middleware('permission:business.services.view')->name('services.show');
    Route::patch('/services/{service}', [ServiceController::class, 'update'])->middleware('permission:business.services.manage')->name('services.update');
    Route::patch('/services/{service}/toggle', [ServiceController::class, 'toggle'])->middleware('permission:business.services.manage')->name('services.toggle');
    Route::patch('/services/{service}/providers', [ServiceController::class, 'syncProviders'])->middleware('permission:business.services.manage')->name('services.providers.sync');

    // Patients
    Route::get('/patients', [PatientController::class, 'index'])->middleware('permission:business.patients.view')->name('patients.index');
    Route::get('/patients/create', [PatientController::class, 'create'])->middleware('permission:business.patients.manage')->name('patients.create');
    Route::post('/patients', [PatientController::class, 'store'])->middleware('permission:business.patients.manage')->name('patients.store');
    Route::get('/patients/{patient}', [PatientController::class, 'show'])->middleware('permission:business.patients.view')->name('patients.show');
    Route::get('/patients/{patient}/edit', [PatientController::class, 'edit'])->middleware('permission:business.patients.manage')->name('patients.edit');
    Route::patch('/patients/{patient}', [PatientController::class, 'update'])->middleware('permission:business.patients.manage')->name('patients.update');

    // Settings
    Route::prefix('settings')->name('settings.')->group(function (): void {
        Route::get('/ai', [AiSettingsController::class, 'edit'])->middleware('permission:business.ai_content.view')->name('ai');
        Route::post('/ai', [AiSettingsController::class, 'update'])->middleware('permission:business.ai_content.manage')->name('ai.update');
        Route::get('/ai/preview', [AiSettingsController::class, 'preview'])->middleware('permission:business.ai_content.view')->name('ai.preview');
        Route::post('/ai/preview', [AiSettingsController::class, 'preview'])->middleware('permission:business.ai_content.view');

        Route::get('/channels', [ChannelSettingsController::class, 'edit'])->middleware('permission:business.channels.view')->name('channels');
        Route::post('/channels', [ChannelSettingsController::class, 'update'])->middleware('permission:business.channels.approve')->name('channels.update');

        Route::get('/integrations', [IntegrationSettingsController::class, 'edit'])->middleware('permission:business.integrations.view')->name('integrations');
        Route::post('/integrations', [IntegrationSettingsController::class, 'update'])->middleware('permission:business.integrations.manage')->name('integrations.update');
        Route::get('/integrations/google/oauth/{service}', [IntegrationSettingsController::class, 'oauthRedirect'])->middleware('permission:business.integrations.manage')->name('integrations.oauth.redirect');
        Route::get('/integrations/google/callback', [IntegrationSettingsController::class, 'oauthCallback'])->middleware('permission:business.integrations.manage')->name('integrations.oauth.callback');
        Route::post('/integrations/{service}/test', [IntegrationSettingsController::class, 'test'])->middleware('permission:business.integrations.manage')->name('integrations.test');

        Route::get('/reminders', [ReminderSettingsController::class, 'edit'])->middleware('permission:business.reminders.view')->name('reminders');
        Route::post('/reminders', [ReminderSettingsController::class, 'update'])->middleware('permission:business.reminders.manage')->name('reminders.update');

        Route::get('/voice', [VoiceSettingsController::class, 'edit'])->middleware('permission:business.voice_preferences.view')->name('voice');
        Route::post('/voice', [VoiceSettingsController::class, 'update'])->middleware('permission:business.voice_preferences.manage')->name('voice.update');
        Route::post('/voice/routing', [VoiceSettingsController::class, 'updateRouting'])->middleware('permission:business.voice_preferences.manage')->name('voice.routing.update');
        Route::post('/voice/channels', [VoiceSettingsController::class, 'storeChannel'])->middleware('permission:business.voice_preferences.manage')->name('voice.channels.store');
        Route::patch('/voice/channels/{voiceChannel}/toggle', [VoiceSettingsController::class, 'toggleChannel'])->middleware('permission:business.voice_preferences.manage')->name('voice.channels.toggle');
        Route::post('/voice/test/{component}', [VoiceSettingsController::class, 'testProvider'])->middleware('permission:business.voice_preferences.manage')->name('voice.test');

        Route::get('/subscription', [SubscriptionSettingsController::class, 'index'])->middleware('permission:business.usage.view')->name('subscription');
        Route::get('/billing', [BillingSettingsController::class, 'index'])->middleware('permission:business.billing.view')->name('billing');
        Route::post('/billing/portal', [BillingSettingsController::class, 'portal'])->middleware('permission:business.billing.portal')->name('billing.portal');
        Route::get('/booking-rules', [BookingRulesController::class, 'edit'])->middleware('permission:business.services.manage')->name('booking-rules');
        Route::post('/booking-rules', [BookingRulesController::class, 'update'])->middleware('permission:business.services.manage')->name('booking-rules.update');

        Route::get('/diagnostics', [DiagnosticsController::class, 'index'])->middleware('permission:business.diagnostics.view')->name('diagnostics');
        Route::post('/diagnostics/channels/{channel}/test-send', [DiagnosticsController::class, 'sendChannelTest'])->middleware('permission:business.diagnostics.run')->name('diagnostics.channels.test-send');
        Route::post('/diagnostics/integrations/{service}/test', [DiagnosticsController::class, 'testGoogle'])->middleware('permission:business.diagnostics.run')->name('diagnostics.integrations.test');
        Route::post('/diagnostics/failed-jobs/retry', [DiagnosticsController::class, 'retryFailedJobs'])->middleware('permission:business.diagnostics.run')->name('diagnostics.failed-jobs.retry');
        Route::post('/diagnostics/inbound/replay', [DiagnosticsController::class, 'replayLastInboundWebhook'])->middleware('permission:business.diagnostics.run')->name('diagnostics.inbound.replay');

        Route::get('/data-controls', [DataControlsController::class, 'index'])->middleware('permission:business.data_controls.view')->name('data-controls');
        Route::post('/data-controls/export', [DataControlsController::class, 'requestExport'])->middleware('permission:business.data_export.request')->name('data-controls.export');
        Route::post('/data-controls/delete', [DataControlsController::class, 'requestDeletion'])->middleware('permission:business.data_delete.request')->name('data-controls.delete');
        Route::get('/data-controls/requests/{governanceRequest}/artifact', [DataControlsController::class, 'downloadArtifact'])->middleware('permission:business.data_controls.view')->name('data-controls.artifact');

        Route::get('/team', [TeamManagementController::class, 'index'])->middleware('permission:business.team.view')->name('team');
        Route::post('/team/invites', [TeamManagementController::class, 'storeInvite'])->middleware('permission:business.team.manage')->name('team.invites.store');
        Route::patch('/team/invites/{teamInvite}/resend', [TeamManagementController::class, 'resendInvite'])->middleware('permission:business.team.manage')->name('team.invites.resend');
        Route::delete('/team/invites/{teamInvite}', [TeamManagementController::class, 'destroyInvite'])->middleware('permission:business.team.manage')->name('team.invites.destroy');
        Route::patch('/team/members/{user}/role', [TeamManagementController::class, 'updateMemberRole'])->middleware('permission:business.roles.manage')->name('team.members.role');
        Route::delete('/team/members/{user}', [TeamManagementController::class, 'destroyMember'])->middleware('permission:business.team.manage')->name('team.members.destroy');
    });
});

require __DIR__.'/auth.php';
