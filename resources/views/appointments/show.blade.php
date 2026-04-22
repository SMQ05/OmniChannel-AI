<x-layouts.app title="Appointment">

<div class="max-w-2xl mx-auto">

    <div class="mb-6 flex items-center justify-between">
        <a href="{{ route('appointments.index') }}" class="text-sm text-[var(--text-muted)] hover:text-[var(--text-strong)] transition-colors">
            ← Appointments
        </a>
        <a href="{{ route('appointments.edit', $appointment) }}"
           class="px-3 py-1.5 bg-gray-700 hover:bg-gray-600 text-gray-300 text-xs font-medium rounded-lg transition-colors">
            Edit Appointment
        </a>
    </div>

    <div class="panel overflow-hidden">
        <div class="p-6">
            <div class="flex items-start justify-between mb-6">
                <div>
                    <h2 class="text-xl font-bold text-white">{{ $appointment->service_type }}</h2>
                    <p class="text-sm text-gray-400">
                        {{ \Carbon\Carbon::parse($appointment->start_time)->setTimezone($timezone)->format('l, F j, Y \a\t g:i A') }}
                    </p>
                    @if($appointment->service)
                        <p class="mt-2 text-xs text-gray-500">
                            Structured service: <a href="{{ route('services.show', $appointment->service) }}" class="text-[var(--brand)] hover:text-[var(--brand-strong)]">{{ $appointment->service->name }}</a>
                            · snapshot preserved for reminders and messaging
                        </p>
                    @endif
                </div>
                <span class="px-3 py-1 rounded-full text-sm font-medium
                    {{ match($appointment->status) {
                        'confirmed'  => 'bg-emerald-500/20 text-emerald-400',
                        'completed'  => 'bg-gray-500/20 text-gray-400',
                        'cancelled'  => 'bg-red-500/20 text-red-400',
                        'no_show'    => 'bg-yellow-500/20 text-yellow-400',
                        default      => 'bg-gray-500/20 text-gray-400',
                    } }}">
                    {{ ucfirst(str_replace('_', ' ', $appointment->status)) }}
                </span>
            </div>

            <div class="grid grid-cols-2 gap-6 mb-6">
                <div>
                    <p class="text-xs text-gray-600 uppercase tracking-wider mb-1">Patient</p>
                    <p class="text-sm font-semibold text-white">{{ $appointment->patient?->name ?? '—' }}</p>
                    <p class="text-xs text-gray-500">{{ $appointment->patient?->phone ?? '' }}</p>
                    @if($appointment->patient)
                        <a href="{{ route('patients.show', $appointment->patient) }}"
                           class="text-xs text-[var(--brand)] hover:text-[var(--brand-strong)] mt-1 inline-block">
                            View patient →
                        </a>
                    @endif
                </div>
                <div>
                    <p class="text-xs text-gray-600 uppercase tracking-wider mb-1">Provider</p>
                    <p class="text-sm font-semibold text-white">{{ $appointment->provider?->displayName() ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-600 uppercase tracking-wider mb-1">Booked Via</p>
                    <p class="text-sm text-white capitalize">{{ $appointment->booked_via }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-600 uppercase tracking-wider mb-1">Duration</p>
                    <p class="text-sm text-white">
                        {{ \Carbon\Carbon::parse($appointment->start_time)->diffInMinutes($appointment->end_time) }} min
                    </p>
                </div>
            </div>

            @if($appointment->notes && !str_starts_with($appointment->notes ?? '', 'geid:'))
                <div class="mb-6">
                    <p class="text-xs text-gray-600 uppercase tracking-wider mb-1">Notes</p>
                    <p class="text-sm text-gray-300">{{ $appointment->notes }}</p>
                </div>
            @endif

            {{-- Sync status --}}
            <div class="flex gap-2 mb-6">
                <span class="flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs
                    {{ $appointment->synced_to_calendar ? 'bg-emerald-500/20 text-emerald-400' : 'bg-gray-700 text-gray-500' }}">
                    Calendar {{ $appointment->synced_to_calendar ? '✓' : '—' }}
                </span>
                <span class="flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs
                    {{ $appointment->synced_to_sheets ? 'bg-emerald-500/20 text-emerald-400' : 'bg-gray-700 text-gray-500' }}">
                    Sheets {{ $appointment->synced_to_sheets ? '✓' : '—' }}
                </span>
            </div>

            {{-- Quick status change --}}
            <div class="pt-4 border-t border-gray-800">
                <p class="text-xs text-gray-600 uppercase tracking-wider mb-3">Quick Status Change</p>
                <div class="flex flex-wrap gap-2">
                    @foreach(['confirmed' => 'Confirmed', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'no_show' => 'No Show'] as $val => $label)
                        <form method="POST" action="{{ route('appointments.update', $appointment) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="provider_id"  value="{{ $appointment->provider_id }}">
                            <input type="hidden" name="patient_id"   value="{{ $appointment->patient_id }}">
                            <input type="hidden" name="service_id"   value="{{ $appointment->service_id }}">
                            <input type="hidden" name="service_type" value="{{ $appointment->service_type }}">
                            <input type="hidden" name="start_time"   value="{{ \Carbon\Carbon::parse($appointment->start_time)->setTimezone($timezone)->format('Y-m-d\TH:i') }}">
                            <input type="hidden" name="status"       value="{{ $val }}">
                            <button type="submit"
                                    class="px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors
                                           {{ $appointment->status === $val
                                               ? 'bg-indigo-600 border-indigo-500 text-white'
                                               : 'border-gray-700 text-gray-400 hover:border-gray-600 hover:text-white' }}">
                                {{ $label }}
                            </button>
                        </form>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

</x-layouts.app>
