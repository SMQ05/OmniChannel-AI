<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Patient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Patient directory — view, create, and edit patients manually.
 *
 * Patients are normally created automatically by ProcessIncomingMessage on
 * first contact. Staff may also add patients manually (walk-in, phone call)
 * and edit contact details at any time.
 */
class PatientController extends Controller
{
    /**
     * Render the searchable patient directory.
     */
    public function index(Request $request): View
    {
        $query = Patient::query()
            ->withCount(['appointments', 'conversationLogs'])
            ->withMax('conversationLogs', 'updated_at')
            ->orderByDesc('conversation_logs_max_updated_at');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('phone', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        if ($request->filled('platform')) {
            $query->where('platform', $request->input('platform'));
        }

        $patients = $query->paginate(30)->withQueryString();

        return view('patients.index', [
            'patients' => $patients,
            'filters'  => $request->only(['search', 'platform']),
        ]);
    }

    /**
     * Show the form to create a new patient manually.
     */
    public function create(): View
    {
        return view('patients.create');
    }

    /**
     * Persist a manually-created patient.
     */
    public function store(Request $request): RedirectResponse
    {
        $business = $request->user()->business;

        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $patient = Patient::create([
            'business_id'      => $business->id,
            'name'             => $validated['name'],
            'phone'            => $validated['phone'] ?? null,
            'email'            => $validated['email'] ?? null,
            'notes'            => $validated['notes'] ?? null,
            'platform_user_id' => null,
            'platform'         => null,
        ]);

        return redirect()
            ->route('patients.show', $patient)
            ->with('success', 'Patient added successfully.');
    }

    /**
     * Render a patient profile: all appointments and conversation history.
     */
    public function show(Request $request, Patient $patient): View
    {
        $appointments = $patient->appointments()
            ->with(['provider'])
            ->orderByDesc('start_time')
            ->get();

        $conversations = $patient->conversationLogs()
            ->orderByDesc('updated_at')
            ->get();

        return view('patients.show', [
            'patient'       => $patient,
            'appointments'  => $appointments,
            'conversations' => $conversations,
            'timezone'      => $request->user()->business->timezone,
        ]);
    }

    /**
     * Show the edit form for an existing patient.
     */
    public function edit(Patient $patient): View
    {
        return view('patients.edit', compact('patient'));
    }

    /**
     * Persist updated patient contact details.
     */
    public function update(Request $request, Patient $patient): RedirectResponse
    {
        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $patient->update($validated);

        return redirect()
            ->route('patients.show', $patient)
            ->with('success', 'Patient updated successfully.');
    }
}

