<x-layouts.app :title="$patient->name ?? 'Patient Profile'">

<div class="max-w-4xl mx-auto">

    <div class="mb-6 flex items-center justify-between">
        <a href="{{ route('patients.index') }}" class="text-sm text-[var(--text-muted)] hover:text-[var(--text-strong)] transition-colors">
            ← Patients
        </a>
        <div class="flex gap-2">
            <a href="{{ route('appointments.create', ['patient_id' => $patient->id]) }}"
               class="flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-medium rounded-lg transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Book Appointment
            </a>
            <a href="{{ route('patients.edit', $patient) }}"
               class="px-3 py-1.5 bg-gray-700 hover:bg-gray-600 text-gray-300 text-xs font-medium rounded-lg transition-colors">
                Edit Patient
            </a>
        </div>
    </div>

    {{-- Patient card --}}
    <div class="panel p-6 mb-6">
        <div class="flex items-start gap-4">
            <div class="w-14 h-14 rounded-full bg-indigo-600/20 border border-indigo-500/30 flex items-center justify-center text-xl font-bold text-indigo-300">
                {{ strtoupper(substr($patient->name ?? '?', 0, 1)) }}
            </div>
            <div class="flex-1">
                <h2 class="text-xl font-bold text-white">{{ $patient->name ?? '—' }}</h2>
                <div class="flex flex-wrap gap-3 mt-2">
                    @if($patient->phone)
                        <span class="text-sm text-gray-400">📞 {{ $patient->phone }}</span>
                    @endif
                    @if($patient->email)
                        <span class="text-sm text-gray-400">✉️ {{ $patient->email }}</span>
                    @endif
                    @if($patient->platform)
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs
                            {{ $patient->platform === 'whatsapp' ? 'bg-green-500/20 text-green-400' : 'bg-blue-500/20 text-blue-400' }}">
                            {{ ucfirst($patient->platform) }}
                        </span>
                    @else
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs bg-gray-700 text-gray-400">
                            Manual
                        </span>
                    @endif
                </div>
                @if($patient->notes)
                    <p class="text-sm text-gray-500 mt-2">{{ $patient->notes }}</p>
                @endif
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Appointments --}}
        <div class="panel overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-800">
                <h3 class="text-sm font-semibold text-white">Appointments ({{ $appointments->count() }})</h3>
            </div>
            <div class="divide-y divide-gray-800">
                @forelse($appointments as $appt)
                    <div class="px-5 py-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-white">{{ $appt->service_type }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    {{ \Carbon\Carbon::parse($appt->start_time)->setTimezone($timezone)->format('M j, Y g:i A') }}
                                    @if($appt->provider)
                                        · {{ $appt->provider->displayName() }}
                                    @endif
                                </p>
                            </div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                {{ match($appt->status) {
                                    'confirmed' => 'bg-emerald-500/20 text-emerald-400',
                                    'completed' => 'bg-gray-500/20 text-gray-400',
                                    'cancelled' => 'bg-red-500/20 text-red-400',
                                    default     => 'bg-yellow-500/20 text-yellow-400',
                                } }}">
                                {{ ucfirst(str_replace('_',' ',$appt->status)) }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-gray-600 text-sm">No appointments yet.</div>
                @endforelse
            </div>
        </div>

        {{-- Conversation History --}}
        <div class="panel overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-800">
                <h3 class="text-sm font-semibold text-white">Conversations ({{ $conversations->count() }})</h3>
            </div>
            <div class="divide-y divide-gray-800">
                @forelse($conversations as $log)
                    <a href="{{ route('conversations.show', $log) }}"
                       class="flex items-center justify-between px-5 py-3 hover:bg-gray-800/40 transition-colors block">
                        <div>
                            <p class="text-sm text-gray-300">
                                {{ $log->updated_at->format('M j, Y') }}
                            </p>
                            <p class="text-xs text-gray-600 mt-0.5">
                                {{ count($log->messages ?? []) }} messages · {{ ucfirst($log->channel) }}
                                @if($log->human_mode)
                                    · <span class="text-red-400">Handoff</span>
                                @endif
                            </p>
                        </div>
                        <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                @empty
                    <div class="px-5 py-8 text-center text-gray-600 text-sm">No conversations yet.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

</x-layouts.app>
