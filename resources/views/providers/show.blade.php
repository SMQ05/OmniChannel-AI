<x-layouts.app :title="$provider ? 'Edit: ' . $provider->displayName() : 'New Provider'">

<div class="max-w-3xl mx-auto">

    <div class="mb-6">
        <a href="{{ route('providers.index') }}" class="text-sm text-gray-500 hover:text-white transition-colors">
            ← Providers
        </a>
    </div>

    <form method="POST"
          action="{{ $provider ? route('providers.update', $provider) : route('providers.store') }}"
          class="space-y-6">
        @csrf
        @if($provider) @method('PATCH') @endif

        {{-- Basic info --}}
        <div class="rounded-2xl bg-gray-900/60 backdrop-blur border border-gray-800 p-6 space-y-4">
            <h2 class="text-sm font-semibold text-white">Provider Details</h2>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Full Name *</label>
                    <input type="text" name="name" value="{{ old('name', $provider?->name) }}"
                           required
                           class="w-full bg-gray-800 border border-gray-700 text-gray-100 text-sm rounded-lg px-3 py-2
                                  focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Title / Prefix</label>
                    <input type="text" name="title" value="{{ old('title', $provider?->title) }}"
                           placeholder="Dr., Mr., Ms., …"
                           class="w-full bg-gray-800 border border-gray-700 text-gray-100 text-sm rounded-lg px-3 py-2
                                  focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs text-gray-500 mb-1">Specialization</label>
                    <input type="text" name="specialization" value="{{ old('specialization', $provider?->specialization) }}"
                           placeholder="e.g. General Physician, Hair Stylist, Family Law …"
                           class="w-full bg-gray-800 border border-gray-700 text-gray-100 text-sm rounded-lg px-3 py-2
                                  focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Slot Duration (minutes) *</label>
                    <input type="number" name="slot_duration_minutes" min="5" max="480"
                           value="{{ old('slot_duration_minutes', $provider?->slot_duration_minutes ?? 30) }}"
                           required
                           class="w-full bg-gray-800 border border-gray-700 text-gray-100 text-sm rounded-lg px-3 py-2
                                  focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>
        </div>

        {{-- Working Hours — Alpine.js drag-select grid --}}
        <div class="rounded-2xl bg-gray-900/60 backdrop-blur border border-gray-800 p-6"
             x-data="{
                 days: ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'],
                 hours: @js($provider?->working_hours ?? [
                     'monday'    => ['active' => true,  'start' => '09:00', 'end' => '17:00'],
                     'tuesday'   => ['active' => true,  'start' => '09:00', 'end' => '17:00'],
                     'wednesday' => ['active' => true,  'start' => '09:00', 'end' => '17:00'],
                     'thursday'  => ['active' => true,  'start' => '09:00', 'end' => '17:00'],
                     'friday'    => ['active' => true,  'start' => '09:00', 'end' => '15:00'],
                     'saturday'  => ['active' => false, 'start' => '09:00', 'end' => '17:00'],
                     'sunday'    => ['active' => false, 'start' => '09:00', 'end' => '17:00'],
                 ]),
             }">

            <h2 class="text-sm font-semibold text-white mb-4">Working Hours</h2>

            <div class="space-y-2">
                <template x-for="day in days" :key="day">
                    <div class="flex items-center gap-4">
                        {{-- Toggle --}}
                        <label class="flex items-center gap-2 w-28 cursor-pointer">
                            <input type="checkbox"
                                   :name="'working_hours[' + day + '][active]'"
                                   :value="1"
                                   x-model="hours[day].active"
                                   class="rounded border-gray-600 bg-gray-700 text-indigo-500">
                            <span class="text-sm text-gray-300 capitalize" x-text="day.slice(0,3)"></span>
                        </label>

                        {{-- Time inputs --}}
                        <template x-if="hours[day].active">
                            <div class="flex items-center gap-2">
                                <input type="time"
                                       :name="'working_hours[' + day + '][start]'"
                                       x-model="hours[day].start"
                                       class="bg-gray-800 border border-gray-700 text-gray-300 text-sm rounded-lg px-2 py-1.5">
                                <span class="text-gray-600 text-sm">to</span>
                                <input type="time"
                                       :name="'working_hours[' + day + '][end]'"
                                       x-model="hours[day].end"
                                       class="bg-gray-800 border border-gray-700 text-gray-300 text-sm rounded-lg px-2 py-1.5">
                            </div>
                        </template>

                        <template x-if="!hours[day].active">
                            <span class="text-xs text-gray-600">Day off</span>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        {{-- Submit --}}
        <div class="flex justify-end gap-3">
            <a href="{{ route('providers.index') }}"
               class="px-4 py-2 text-sm text-gray-400 hover:text-white transition-colors">
                Cancel
            </a>
            <button type="submit"
                    class="px-6 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-lg transition-colors">
                {{ $provider ? 'Save Changes' : 'Create Provider' }}
            </button>
        </div>
    </form>

    {{-- Blocked Dates --}}
    @if($provider)
        <div class="rounded-2xl bg-gray-900/60 backdrop-blur border border-gray-800 overflow-hidden mt-6">
            <div class="px-5 py-4 border-b border-gray-800">
                <h2 class="text-sm font-semibold text-white">Blocked Dates</h2>
            </div>

            <div class="p-5 space-y-4">
                {{-- Add blocked date form --}}
                <form method="POST" action="{{ route('providers.blocked-dates.store', $provider) }}"
                      class="flex gap-2">
                    @csrf
                    <input type="date" name="blocked_date" required min="{{ today()->toDateString() }}"
                           class="bg-gray-800 border border-gray-700 text-gray-300 text-sm rounded-lg px-3 py-2">
                    <input type="text" name="reason" placeholder="Reason (optional)"
                           class="flex-1 bg-gray-800 border border-gray-700 text-gray-300 text-sm rounded-lg px-3 py-2
                                  placeholder-gray-600">
                    <button type="submit"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-lg transition-colors">
                        Block Date
                    </button>
                </form>

                {{-- Existing blocked dates --}}
                @foreach($provider->blockedDates->sortBy('blocked_date') as $blocked)
                    <div class="flex items-center justify-between py-2 border-t border-gray-800/60">
                        <div>
                            <p class="text-sm text-white">
                                {{ \Carbon\Carbon::parse($blocked->blocked_date)->format('l, F j, Y') }}
                            </p>
                            @if($blocked->reason)
                                <p class="text-xs text-gray-500">{{ $blocked->reason }}</p>
                            @endif
                        </div>
                        <form method="POST"
                              action="{{ route('providers.blocked-dates.destroy', [$provider, $blocked]) }}">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    class="text-xs text-red-500 hover:text-red-400 transition-colors">
                                Remove
                            </button>
                        </form>
                    </div>
                @endforeach

                @if($provider->blockedDates->isEmpty())
                    <p class="text-sm text-gray-600">No blocked dates.</p>
                @endif
            </div>
        </div>
    @endif

</div>

</x-layouts.app>
