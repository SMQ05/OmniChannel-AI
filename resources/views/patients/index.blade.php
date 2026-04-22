<x-layouts.app title="Patients">

{{-- Search / filter --}}
<form method="GET" action="{{ route('patients.index') }}" class="flex flex-wrap gap-2 mb-6">
    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
           placeholder="Search by name, phone, or email…"
           class="flex-1 min-w-48 field
                  placeholder-gray-600 focus:ring-indigo-500 focus:border-indigo-500">

    <select name="platform"
            class="field">
        <option value="">All Platforms</option>
        <option value="whatsapp"  {{ ($filters['platform'] ?? '') === 'whatsapp'  ? 'selected' : '' }}>WhatsApp</option>
        <option value="messenger" {{ ($filters['platform'] ?? '') === 'messenger' ? 'selected' : '' }}>Messenger</option>
    </select>

    <button type="submit"
            class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white text-sm font-medium rounded-lg transition-colors">
        Search
    </button>

    <a href="{{ route('patients.create') }}"
       class="flex items-center gap-2 px-4 py-2 btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Add Patient
    </a>
</form>

{{-- Patient table --}}
<div class="panel overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-800">
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Patient</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Platform</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Appointments</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Last Activity</th>
                <th class="px-5 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-800">
            @forelse($patients as $patient)
                <tr class="hover:bg-gray-800/30 transition-colors">
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-gray-700 flex items-center justify-center text-sm font-bold text-white">
                                {{ strtoupper(substr($patient->name ?? '?', 0, 1)) }}
                            </div>
                            <div>
                                <p class="font-medium text-white">{{ $patient->name ?? '—' }}</p>
                                <p class="text-xs text-gray-500">{{ $patient->phone ?? $patient->email ?? '—' }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-3.5">
                        @if($patient->platform)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs
                                {{ $patient->platform === 'whatsapp' ? 'bg-green-500/20 text-green-400' : 'bg-blue-500/20 text-blue-400' }}">
                                {{ ucfirst($patient->platform) }}
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-gray-700 text-gray-400">
                                Manual
                            </span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-gray-400">
                        {{ $patient->appointments_count }}
                    </td>
                    <td class="px-5 py-3.5 text-gray-500 text-xs">
                        {{ $patient->conversation_logs_max_updated_at
                            ? \Carbon\Carbon::parse($patient->conversation_logs_max_updated_at)->diffForHumans()
                            : '—' }}
                    </td>
                    <td class="px-5 py-3.5 text-right">
                        <div class="flex items-center justify-end gap-3">
                            <a href="{{ route('patients.edit', $patient) }}"
                               class="text-xs text-[var(--text-muted)] hover:text-[var(--text-strong)] transition-colors">
                                Edit
                            </a>
                            <a href="{{ route('patients.show', $patient) }}"
                               class="text-xs text-[var(--brand)] hover:text-[var(--brand-strong)] transition-colors">
                                View →
                            </a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-5 py-16 text-center text-gray-600">
                        No patients found.
                        <a href="{{ route('patients.create') }}" class="text-[var(--brand)] hover:text-[var(--brand-strong)] ml-1">Add one →</a>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if($patients->hasPages())
        <div class="px-5 py-4 border-t border-gray-800">
            {{ $patients->links() }}
        </div>
    @endif
</div>

</x-layouts.app>
