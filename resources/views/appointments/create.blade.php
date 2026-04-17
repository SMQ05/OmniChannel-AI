<x-layouts.app title="New Appointment">

<div class="max-w-xl mx-auto">
    <div class="rounded-2xl bg-gray-900/60 backdrop-blur border border-gray-800 p-6">
        <h2 class="text-sm font-semibold text-white mb-5">New Appointment</h2>

        <form method="POST" action="{{ route('appointments.store') }}" class="space-y-4">
            @csrf

            {{-- Patient --}}
            <div>
                <label class="block text-xs text-gray-500 mb-1">Patient *</label>
                <select name="patient_id" required
                        class="w-full bg-gray-800 border border-gray-700 text-gray-300 text-sm rounded-lg px-3 py-2
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
                    <a href="{{ route('patients.create') }}" class="text-indigo-400 hover:text-indigo-300">Add them first →</a>
                </p>
            </div>

            {{-- Provider --}}
            <div>
                <label class="block text-xs text-gray-500 mb-1">Provider *</label>
                <select name="provider_id" required
                        class="w-full bg-gray-800 border border-gray-700 text-gray-300 text-sm rounded-lg px-3 py-2
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
            <div>
                <label class="block text-xs text-gray-500 mb-1">Service *</label>
                <input type="text" name="service_type" value="{{ old('service_type') }}" required
                       placeholder="e.g. General Checkup, Dental Cleaning…"
                       class="w-full bg-gray-800 border border-gray-700 text-gray-100 text-sm rounded-lg px-3 py-2
                              focus:ring-indigo-500 focus:border-indigo-500">
                @error('service_type') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Date & Time --}}
            <div>
                <label class="block text-xs text-gray-500 mb-1">
                    Date &amp; Time * <span class="text-gray-600">({{ $timezone }})</span>
                </label>
                <input type="datetime-local" name="start_time"
                       value="{{ old('start_time') }}" required
                       class="w-full bg-gray-800 border border-gray-700 text-gray-100 text-sm rounded-lg px-3 py-2
                              focus:ring-indigo-500 focus:border-indigo-500">
                @error('start_time') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Notes --}}
            <div>
                <label class="block text-xs text-gray-500 mb-1">Notes</label>
                <textarea name="notes" rows="3"
                          class="w-full bg-gray-800 border border-gray-700 text-gray-100 text-sm rounded-lg px-3 py-2
                                 focus:ring-indigo-500 focus:border-indigo-500"
                          placeholder="Any relevant notes for this appointment…">{{ old('notes') }}</textarea>
                @error('notes') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center justify-between pt-2">
                <a href="{{ route('appointments.index') }}"
                   class="text-sm text-gray-500 hover:text-white transition-colors">
                    ← Back
                </a>
                <button type="submit"
                        class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-lg transition-colors">
                    Create Appointment
                </button>
            </div>
        </form>
    </div>
</div>

</x-layouts.app>
