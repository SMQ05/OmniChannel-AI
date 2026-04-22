<x-layouts.app title="Edit Appointment">

<div class="max-w-xl mx-auto">
    <div class="panel p-6">
        <h2 class="text-sm font-semibold text-white mb-5">Edit Appointment</h2>

        <form method="POST" action="{{ route('appointments.update', $appointment) }}" class="space-y-4">
            @csrf
            @method('PATCH')

            {{-- Patient --}}
            <div>
                <label class="block text-xs text-gray-500 mb-1">Patient *</label>
                <select name="patient_id" required
                        class="w-full field
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
                        class="w-full field
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
            @if($services->isNotEmpty())
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Structured Service</label>
                    <select name="service_id" class="w-full field focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">Legacy / snapshot-only appointment</option>
                        @foreach($services as $service)
                            <option value="{{ $service->id }}" {{ (string) old('service_id', $appointment->service_id) === (string) $service->id ? 'selected' : '' }}>
                                {{ $service->name }} · {{ $service->duration_minutes }} min
                                @unless($service->is_active) · inactive @endunless
                            </option>
                        @endforeach
                    </select>
                    @error('service_id') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
            @endif
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ $services->isNotEmpty() ? 'Service Snapshot / Custom Label' : 'Service *' }}</label>
                <input type="text" name="service_type"
                       @if($services->isEmpty()) required @endif
                       value="{{ old('service_type', $appointment->service_type) }}"
                       class="w-full field
                              focus:ring-indigo-500 focus:border-indigo-500">
                @error('service_type') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
                @if($services->isNotEmpty())
                    <p class="mt-1 text-xs text-gray-600">Structured services still preserve the `service_type` snapshot for reminders, exports, conversations, and voice summaries.</p>
                @endif
            </div>

            {{-- Date & Time --}}
            <div>
                <label class="block text-xs text-gray-500 mb-1">
                    Date &amp; Time * <span class="text-gray-600">({{ $timezone }})</span>
                </label>
                <input type="datetime-local" name="start_time" required
                       value="{{ old('start_time', \Carbon\Carbon::parse($appointment->start_time)->setTimezone($timezone)->format('Y-m-d\TH:i')) }}"
                       class="w-full field
                              focus:ring-indigo-500 focus:border-indigo-500">
                @error('start_time') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Status --}}
            <div>
                <label class="block text-xs text-gray-500 mb-1">Status *</label>
                <select name="status" required
                        class="w-full field
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
                          class="w-full field
                                 focus:ring-indigo-500 focus:border-indigo-500">{{ old('notes', $appointment->notes) }}</textarea>
                @error('notes') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center justify-between pt-2">
                <a href="{{ route('appointments.show', $appointment) }}"
                   class="text-sm text-[var(--text-muted)] hover:text-[var(--text-strong)] transition-colors">
                    ← Back
                </a>
                <button type="submit"
                        class="px-5 py-2 btn-primary">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

</x-layouts.app>
