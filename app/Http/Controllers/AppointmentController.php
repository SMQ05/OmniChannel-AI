<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\AppointmentResource;
use App\Jobs\SyncAppointmentJob;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Provider;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Manages the appointments calendar view and manual booking / editing.
 *
 * All queries are automatically scoped to the authenticated tenant via
 * TenantScope on the Appointment model.
 */
class AppointmentController extends Controller
{
    /**
     * Render the appointments calendar index.
     */
    public function index(Request $request): View
    {
        $business = $request->user()->business;
        $timezone = $business->timezone;
        $now      = Carbon::now($timezone);

        $from = Carbon::parse($request->input('from', $now->copy()->startOfMonth()))->utc();
        $to   = Carbon::parse($request->input('to',   $now->copy()->endOfMonth()))->utc();

        $appointments = Appointment::query()
            ->with(['patient', 'provider'])
            ->whereBetween('start_time', [$from, $to])
            ->when($request->filled('provider_id'), fn ($q) => $q->where('provider_id', $request->provider_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderBy('start_time')
            ->get();

        $providers = Provider::query()->where('is_active', true)->orderBy('name')->get();

        return view('appointments.index', [
            'appointments' => AppointmentResource::collection($appointments),
            'providers'    => $providers,
            'timezone'     => $timezone,
            'from'         => $from->toDateString(),
            'to'           => $to->toDateString(),
        ]);
    }

    /**
     * Show the create appointment form.
     */
    public function create(Request $request): View
    {
        $business  = $request->user()->business;
        $providers = Provider::query()->where('is_active', true)->orderBy('name')->get();
        $patients  = Patient::query()->orderBy('name')->get();

        return view('appointments.create', [
            'providers' => $providers,
            'patients'  => $patients,
            'timezone'  => $business->timezone,
        ]);
    }

    /**
     * Persist a manually-created appointment.
     */
    public function store(Request $request): RedirectResponse
    {
        $business = $request->user()->business;

        $validated = $request->validate([
            'provider_id'  => ['required', 'integer', Rule::exists('providers', 'id')->where('business_id', $business->id)],
            'patient_id'   => ['required', 'integer', Rule::exists('patients', 'id')->where('business_id', $business->id)],
            'service_type' => ['required', 'string', 'max:255'],
            'start_time'   => ['required', 'date'],
            'notes'        => ['nullable', 'string', 'max:2000'],
        ]);

        $startLocal = Carbon::parse($validated['start_time'], $business->timezone);
        $provider   = Provider::findOrFail($validated['provider_id']);
        $endUtc     = $startLocal->copy()->addMinutes($provider->slot_duration_minutes)->utc();

        $appointment = Appointment::create([
            'business_id'  => $business->id,
            'provider_id'  => $validated['provider_id'],
            'patient_id'   => $validated['patient_id'],
            'service_type' => $validated['service_type'],
            'start_time'   => $startLocal->utc(),
            'end_time'     => $endUtc,
            'status'       => 'confirmed',
            'booked_via'   => 'manual',
            'notes'        => $validated['notes'] ?? null,
        ]);

        SyncAppointmentJob::dispatch($appointment);

        return redirect()
            ->route('appointments.show', $appointment)
            ->with('success', 'Appointment created successfully.');
    }

    /**
     * Render the appointment detail view.
     */
    public function show(Request $request, Appointment $appointment): View
    {
        $appointment->loadMissing(['patient', 'provider', 'business']);

        return view('appointments.show', [
            'appointment' => $appointment,
            'timezone'    => $request->user()->business->timezone,
        ]);
    }

    /**
     * Show the edit form for an existing appointment.
     */
    public function edit(Request $request, Appointment $appointment): View
    {
        $business  = $request->user()->business;
        $providers = Provider::query()->where('is_active', true)->orderBy('name')->get();
        $patients  = Patient::query()->orderBy('name')->get();

        return view('appointments.edit', [
            'appointment' => $appointment,
            'providers'   => $providers,
            'patients'    => $patients,
            'timezone'    => $business->timezone,
        ]);
    }

    /**
     * Persist updated appointment details.
     */
    public function update(Request $request, Appointment $appointment): RedirectResponse
    {
        $business = $request->user()->business;

        $validated = $request->validate([
            'provider_id'  => ['required', 'integer', Rule::exists('providers', 'id')->where('business_id', $business->id)],
            'patient_id'   => ['required', 'integer', Rule::exists('patients', 'id')->where('business_id', $business->id)],
            'service_type' => ['required', 'string', 'max:255'],
            'start_time'   => ['required', 'date'],
            'status'       => ['required', Rule::in(['pending', 'confirmed', 'cancelled', 'completed', 'no_show'])],
            'notes'        => ['nullable', 'string', 'max:2000'],
        ]);

        $startLocal = Carbon::parse($validated['start_time'], $business->timezone);
        $provider   = Provider::findOrFail($validated['provider_id']);
        $endUtc     = $startLocal->copy()->addMinutes($provider->slot_duration_minutes)->utc();

        $appointment->update([
            'provider_id'  => $validated['provider_id'],
            'patient_id'   => $validated['patient_id'],
            'service_type' => $validated['service_type'],
            'start_time'   => $startLocal->utc(),
            'end_time'     => $endUtc,
            'status'       => $validated['status'],
            'notes'        => $validated['notes'] ?? null,
        ]);

        SyncAppointmentJob::dispatch($appointment->fresh());

        return redirect()
            ->route('appointments.show', $appointment)
            ->with('success', 'Appointment updated successfully.');
    }

    /**
     * Update only the status of an appointment (AJAX call from calendar).
     */
    public function updateStatus(Request $request, Appointment $appointment): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['confirmed', 'cancelled', 'completed', 'no_show'])],
        ]);

        $appointment->update(['status' => $validated['status']]);
        SyncAppointmentJob::dispatch($appointment);

        return response()->json(['status' => 'ok', 'appointment' => new AppointmentResource($appointment)]);
    }

    /**
     * Return a JSON feed of today's appointments for Alpine.js polling.
     */
    public function feed(Request $request): JsonResponse
    {
        $business   = $request->user()->business;
        $timezone   = $business->timezone;
        $now        = Carbon::now($timezone);
        $todayStart = $now->copy()->startOfDay()->utc();
        $todayEnd   = $now->copy()->endOfDay()->utc();

        $appointments = Appointment::query()
            ->with(['patient', 'provider'])
            ->whereBetween('start_time', [$todayStart, $todayEnd])
            ->orderBy('start_time')
            ->get();

        return response()->json(AppointmentResource::collection($appointments));
    }
}
