<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingRulesController extends Controller
{
    public function edit(Request $request): View
    {
        $business = $request->user()->business;

        return view('settings.booking-rules', [
            'business' => $business,
            'bookingRules' => array_merge($this->defaults(), $business->bookingRules()),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'lead_time_minutes' => ['required', 'integer', 'min:0', 'max:10080'],
            'max_advance_days' => ['required', 'integer', 'min:1', 'max:365'],
            'cancellation_notice_hours' => ['required', 'integer', 'min:0', 'max:720'],
            'reschedule_notice_hours' => ['required', 'integer', 'min:0', 'max:720'],
            'allow_same_day_booking' => ['nullable', 'boolean'],
            'require_provider_selection' => ['nullable', 'boolean'],
            'default_booking_status' => ['required', 'in:pending,confirmed'],
        ]);

        $business = $request->user()->business;
        $config = $business->operations_config ?? [];
        $config['booking_rules'] = [
            'lead_time_minutes' => (int) $validated['lead_time_minutes'],
            'max_advance_days' => (int) $validated['max_advance_days'],
            'cancellation_notice_hours' => (int) $validated['cancellation_notice_hours'],
            'reschedule_notice_hours' => (int) $validated['reschedule_notice_hours'],
            'allow_same_day_booking' => (bool) ($validated['allow_same_day_booking'] ?? false),
            'require_provider_selection' => (bool) ($validated['require_provider_selection'] ?? false),
            'default_booking_status' => $validated['default_booking_status'],
        ];

        $business->operations_config = $config;
        $business->save();

        return redirect()->route('settings.booking-rules')->with('success', 'Booking rules saved.');
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'lead_time_minutes' => 0,
            'max_advance_days' => 30,
            'cancellation_notice_hours' => 4,
            'reschedule_notice_hours' => 4,
            'allow_same_day_booking' => true,
            'require_provider_selection' => false,
            'default_booking_status' => 'confirmed',
        ];
    }
}
