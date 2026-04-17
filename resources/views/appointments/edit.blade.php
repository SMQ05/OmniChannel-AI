<x-layouts.app title="Edit Appointment">

<div class="max-w-xl mx-auto">
    <div class="rounded-2xl bg-gray-900/60 backdrop-blur border border-gray-800 p-6">
        <h2 class="text-sm font-semibold text-white mb-5">Edit Appointment</h2>

        <form method="POST" action="{{ route('appointments.update', $appointment) }}" class="space-y-4">
            @csrf
            @method('PATCH')

            {{-- Patient --}}
            <div>
                <label class="block text-xs text-gray-500 mb-1">Patient *</label>
                <select name="patient_id" required
                        class="w-full bg-gray-800 border border-gray-700 text-gray-300 text-sm rounded-lg px-3 py-2
                               focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">Select patient…</option>
                    @foreach($patients as $patient)
                        <option value="{{ $patient->id }}"
                            {{ old('patient_id', $appointment->patient_id) == $patient->id ? 'selected' : '' }}>
                            {{ $patient->name }}
                            @if($patient->phone) ({{ $patient->phone }}) @endif
                        </option>
                    @endforeach
                </select>
                @error('patient_id') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
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
                            {{ old('provider_id', $appointment->provider_id) == $provider->id ? 'selected' : '' }}>
                            {{ $provider->displayName() }}
                        </option>
                    @endforeach
                </select>
                @error('provider_id') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Service --}}
            <div>
                <label class="block text-xs text-gray-500 mb-1">Service *</label>
                <input type="text" name="service_type" required
                       value="{{ old('service_type', $appointment->service_type) }}"
                       class="w-full bg-gray-800 border border-gray-700 text-gray-100 text-sm rounded-lg px-3 py-2
                              focus:ring-indigo-500 focus:border-indigo-500">
                @error('service_type') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Date & Time --}}
            <div>
                <label class="block text-xs text-gray-500 mb-1">
                    Date &amp; Time * <span class="text-gray-600">({{ $timezone }})</span>
                </label>
                <input type="datetime-local" name="start_time" required
                       value="{{ old('start_time', \Carbon\Carbon::parse($appointment->start_time)->setTimezone($timezone)->format('Y-m-d\TH:i')) }}"
                       class="w-full bg-gray-800 border border-gray-700 text-gray-100 text-sm rounded-lg px-3 py-2
                              focus:ring-indigo-500 focus:border-indigo-500">
                @error('start_time') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Status --}}
            <div>
                <label class="block text-xs text-gray-500 mb-1">Status *</label>
                <select name="status" required
                        class="w-full bg-gray-800 border border-gray-700 text-gray-300 text-sm rounded-lg px-3 py-2
                               focus:ring-indigo-500 focus:border-indigo-500">
                    @foreach(['pending' => 'Pending', 'confirmed' => 'Confirmed', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'no_show' => 'No Show'] as $val => $label)
                        <option value="{{ $val }}"
                            {{ old('status', $appointment->status) === $val ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                @error('status') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Notes --}}
            <div>
                <label class="block text-xs text-gray-500 mb-1">Notes</label>
                <textarea name="notes" rows="3"
                          class="w-full bg-gray-800 border border-gray-700 text-gray-100 text-sm rounded-lg px-3 py-2
                                 focus:ring-indigo-500 focus:border-indigo-500">{{ old('notes', $appointment->notes) }}</textarea>
                @error('notes') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center justify-between pt-2">
                <a href="{{ route('appointments.show', $appointment) }}"
                   class="text-sm text-gray-500 hover:text-white transition-colors">
                    ← Back
                </a>
                <button type="submit"
                        class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-lg transition-colors">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

</x-layouts.app>
