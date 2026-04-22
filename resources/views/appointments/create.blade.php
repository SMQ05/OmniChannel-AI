<x-layouts.app title="New Appointment">

<div class="max-w-xl mx-auto">
    <div class="panel p-6">
        <h2 class="text-sm font-semibold text-white mb-5">New Appointment</h2>

        <form method="POST" action="{{ route('appointments.store') }}" class="space-y-4">
            @csrf

            {{-- Patient --}}
            <div>
                <label class="block text-xs text-gray-500 mb-1">Patient *</label>
                <select name="patient_id" required
                        class="w-full field
                               focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">Select patient…</option>
                    @foreach($patients as $patient)
                        <option value="{{ $patient->id }}"
                            {{ old('patient_id', request('patient_id')) == $patient->id ? 'selected' : '' }}>
                            {{ $patient->name }}
                            @if($patient->phone) ({{ $patient->phone }}) @endif
                        </option>
                    @endforeach
                </select>
                @error('patient_id') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
                <p class="text-xs text-gray-600 mt-1">
                    Patient not listed?
                    <a href="{{ route('patients.create') }}" class="text-[var(--brand)] hover:text-[var(--brand-strong)]">Add them first →</a>
                </p>
            </div>

            {{-- Provider --}}
            <div>
                <label class="block text-xs text-gray-500 mb-1">Provider *</label>
                <select name="provider_id" required
                        class="w-full field
                               focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">Select provider…</option>
                    @foreach($providers as $provider)
                        <option value="{{ $provider->id }}"
                            {{ old('provider_id') == $provider->id ? 'selected' : '' }}>
                            {{ $provider->displayName() }}
                        </option>
                    @endforeach
                </select>
                @error('provider_id') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Service --}}
            @if($services->isNotEmpty())
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Structured Service</label>
                    <select name="service_id" class="w-full field focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">Use custom / legacy service label</option>
                        @foreach($services as $service)
                            <option value="{{ $service->id }}" {{ (string) old('service_id') === (string) $service->id ? 'selected' : '' }}>
                                {{ $service->name }} · {{ $service->duration_minutes }} min
                            </option>
                        @endforeach
                    </select>
                    @error('service_id') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-gray-600">Selecting a structured service writes both the internal `service_id` and the visible `service_type` snapshot.</p>
                </div>
            @endif
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ $services->isNotEmpty() ? 'Service Snapshot / Custom Label' : 'Service *' }}</label>
                <input type="text" name="service_type" value="{{ old('service_type') }}"
                       @if($services->isEmpty()) required @endif
                       placeholder="e.g. General Checkup, Dental Cleaning…"
                       class="w-full field
                              focus:ring-indigo-500 focus:border-indigo-500">
                @error('service_type') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
                @if($services->isNotEmpty())
                    <p class="mt-1 text-xs text-gray-600">Leave this blank when you pick a structured service. Use it when you need a legacy/custom label.</p>
                @endif
            </div>

            {{-- Date & Time --}}
            <div>
                <label class="block text-xs text-gray-500 mb-1">
                    Date &amp; Time * <span class="text-gray-600">({{ $timezone }})</span>
                </label>
                <input type="datetime-local" name="start_time"
                       value="{{ old('start_time') }}" required
                       class="w-full field
                              focus:ring-indigo-500 focus:border-indigo-500">
                @error('start_time') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Notes --}}
            <div>
                <label class="block text-xs text-gray-500 mb-1">Notes</label>
                <textarea name="notes" rows="3"
                          class="w-full field
                                 focus:ring-indigo-500 focus:border-indigo-500"
                          placeholder="Any relevant notes for this appointment…">{{ old('notes') }}</textarea>
                @error('notes') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center justify-between pt-2">
                <a href="{{ route('appointments.index') }}"
                   class="text-sm text-[var(--text-muted)] hover:text-[var(--text-strong)] transition-colors">
                    ← Back
                </a>
                <button type="submit"
                        class="px-5 py-2 btn-primary">
                    Create Appointment
                </button>
            </div>
        </form>
    </div>
</div>

</x-layouts.app>
