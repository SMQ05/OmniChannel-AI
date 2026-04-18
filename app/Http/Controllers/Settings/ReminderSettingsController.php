<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Manages the reminder rules configuration (/settings/reminders).
 *
 * Persists the reminders array to businesses.reminder_settings.
 * Each rule has: offset_hours (int), label (string), message_template (string).
 */
class ReminderSettingsController extends Controller
{
    /**
     * Render the reminder settings page.
     *
     * @param  Request  $request
     * @return View
     */
    public function edit(Request $request): View
    {
        $business         = $request->user()->business;
        $reminderSettings = $business->reminder_settings ?? ['reminders' => []];

        return view('settings.reminders', [
            'business'         => $business,
            'reminderSettings' => $reminderSettings,
            'managedByAdmin' => $this->managedByAdmin($request),
        ]);
    }

    /**
     * Persist the updated reminder rules array to the business record.
     *
     * @param  Request  $request
     * @return RedirectResponse
     */
    public function update(Request $request): RedirectResponse
    {
        $this->ensureManagedByAdmin($request);

        $validated = $request->validate([
            'reminders'                      => ['required', 'array', 'min:0'],
            'reminders.*.offset_hours'       => ['required', 'integer', 'min:1', 'max:720'],
            'reminders.*.label'              => ['required', 'string', 'max:100'],
            'reminders.*.message_template'   => ['required', 'string', 'max:1024'],
        ]);

        $business = $request->user()->business;

        $business->reminder_settings = ['reminders' => $validated['reminders'] ?? []];
        $business->save();

        return redirect()->route('settings.reminders')->with('success', 'Reminder settings saved.');
    }

    private function managedByAdmin(Request $request): bool
    {
        return $request->session()->has('impersonating_as') || $request->user()?->role === 'super_admin';
    }

    private function ensureManagedByAdmin(Request $request): void
    {
        if (!$this->managedByAdmin($request)) {
            throw new AuthorizationException('Reminder setup is managed by Kynex Solutions.');
        }
    }
}
