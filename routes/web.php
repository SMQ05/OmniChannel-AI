<?php

declare(strict_types=1);

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProviderController;
use App\Http\Controllers\Settings\AiSettingsController;
use App\Http\Controllers\Settings\ChannelSettingsController;
use App\Http\Controllers\Settings\DiagnosticsController;
use App\Http\Controllers\Settings\IntegrationSettingsController;
use App\Http\Controllers\Settings\ReminderSettingsController;
use App\Http\Controllers\Settings\SubscriptionSettingsController;
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
    Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');
    Route::get('/appointments/feed', [AppointmentController::class, 'feed'])->name('appointments.feed');
    Route::get('/appointments/create', [AppointmentController::class, 'create'])->name('appointments.create');
    Route::post('/appointments', [AppointmentController::class, 'store'])->name('appointments.store');
    Route::get('/appointments/{appointment}', [AppointmentController::class, 'show'])->name('appointments.show');
    Route::get('/appointments/{appointment}/edit', [AppointmentController::class, 'edit'])->name('appointments.edit');
    Route::patch('/appointments/{appointment}', [AppointmentController::class, 'update'])->name('appointments.update');
    Route::patch('/appointments/{appointment}/status', [AppointmentController::class, 'updateStatus'])->name('appointments.update-status');

    // Conversations
    Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::get('/conversations/{conversationLog}', [ConversationController::class, 'show'])->name('conversations.show');
    Route::post('/conversations/{conversationLog}/takeover', [ConversationController::class, 'takeover'])->name('conversations.takeover');
    Route::post('/conversations/{conversationLog}/notes', [ConversationController::class, 'storeNote'])->name('conversations.notes.store');

    // Providers
    Route::get('/providers', [ProviderController::class, 'index'])->name('providers.index');
    Route::get('/providers/create', [ProviderController::class, 'create'])->name('providers.create');
    Route::post('/providers', [ProviderController::class, 'store'])->name('providers.store');
    Route::get('/providers/{provider}', [ProviderController::class, 'show'])->name('providers.show');
    Route::patch('/providers/{provider}', [ProviderController::class, 'update'])->name('providers.update');
    Route::patch('/providers/{provider}/toggle', [ProviderController::class, 'toggle'])->name('providers.toggle');
    Route::post('/providers/{provider}/blocked-dates', [ProviderController::class, 'addBlockedDate'])->name('providers.blocked-dates.store');
    Route::delete('/providers/{provider}/blocked-dates/{blockedDate}', [ProviderController::class, 'removeBlockedDate'])->name('providers.blocked-dates.destroy');

    // Patients
    Route::get('/patients', [PatientController::class, 'index'])->name('patients.index');
    Route::get('/patients/create', [PatientController::class, 'create'])->name('patients.create');
    Route::post('/patients', [PatientController::class, 'store'])->name('patients.store');
    Route::get('/patients/{patient}', [PatientController::class, 'show'])->name('patients.show');
    Route::get('/patients/{patient}/edit', [PatientController::class, 'edit'])->name('patients.edit');
    Route::patch('/patients/{patient}', [PatientController::class, 'update'])->name('patients.update');

    // Settings
    Route::prefix('settings')->name('settings.')->group(function (): void {
        Route::get('/ai', [AiSettingsController::class, 'edit'])->name('ai');
        Route::post('/ai', [AiSettingsController::class, 'update'])->name('ai.update');
        Route::get('/ai/preview', [AiSettingsController::class, 'preview'])->name('ai.preview');
        Route::post('/ai/preview', [AiSettingsController::class, 'preview']);

        Route::get('/channels', [ChannelSettingsController::class, 'edit'])->name('channels');
        Route::post('/channels', [ChannelSettingsController::class, 'update'])->name('channels.update');
        Route::post('/channels/{channel}/test', [ChannelSettingsController::class, 'test'])->name('channels.test');

        Route::get('/integrations', [IntegrationSettingsController::class, 'edit'])->name('integrations');
        Route::post('/integrations', [IntegrationSettingsController::class, 'update'])->name('integrations.update');
        Route::get('/integrations/google/oauth/{service}', [IntegrationSettingsController::class, 'oauthRedirect'])->name('integrations.oauth.redirect');
        Route::get('/integrations/google/callback', [IntegrationSettingsController::class, 'oauthCallback'])->name('integrations.oauth.callback');
        Route::post('/integrations/{service}/test', [IntegrationSettingsController::class, 'test'])->name('integrations.test');

        Route::get('/reminders', [ReminderSettingsController::class, 'edit'])->name('reminders');
        Route::post('/reminders', [ReminderSettingsController::class, 'update'])->name('reminders.update');

        Route::get('/voice', [VoiceSettingsController::class, 'edit'])->name('voice');
        Route::post('/voice', [VoiceSettingsController::class, 'update'])->name('voice.update');
        Route::post('/voice/channels', [VoiceSettingsController::class, 'storeChannel'])->name('voice.channels.store');
        Route::patch('/voice/channels/{voiceChannel}/toggle', [VoiceSettingsController::class, 'toggleChannel'])->name('voice.channels.toggle');
        Route::post('/voice/test/{component}', [VoiceSettingsController::class, 'testProvider'])->name('voice.test');

        Route::get('/subscription', [SubscriptionSettingsController::class, 'index'])->name('subscription');

        Route::get('/diagnostics', [DiagnosticsController::class, 'index'])->name('diagnostics');
        Route::post('/diagnostics/channels/{channel}/test-send', [DiagnosticsController::class, 'sendChannelTest'])->name('diagnostics.channels.test-send');
        Route::post('/diagnostics/integrations/{service}/test', [DiagnosticsController::class, 'testGoogle'])->name('diagnostics.integrations.test');
        Route::post('/diagnostics/failed-jobs/retry', [DiagnosticsController::class, 'retryFailedJobs'])->name('diagnostics.failed-jobs.retry');
        Route::post('/diagnostics/inbound/replay', [DiagnosticsController::class, 'replayLastInboundWebhook'])->name('diagnostics.inbound.replay');
    });
});

require __DIR__.'/auth.php';
