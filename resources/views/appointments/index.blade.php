<x-layouts.app title="Appointments">

{{-- Toolbar --}}
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div class="flex items-center gap-2">
        {{-- Provider filter --}}
        <select name="provider_id" onchange="this.form.submit()"
                form="filter-form"
                class="bg-gray-800 border border-gray-700 text-gray-300 text-sm rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
            <option value="">All Providers</option>
            @foreach($providers as $provider)
                <option value="{{ $provider->id }}"
                    {{ request('provider_id') == $provider->id ? 'selected' : '' }}>
                    {{ $provider->displayName() }}
                </option>
            @endforeach
        </select>

        {{-- Status filter --}}
        <select name="status" onchange="this.form.submit()"
                form="filter-form"
                class="bg-gray-800 border border-gray-700 text-gray-300 text-sm rounded-lg px-3 py-2">
            <option value="">All Statuses</option>
            @foreach(['confirmed', 'completed', 'cancelled', 'no_show', 'pending'] as $s)
                <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>
                    {{ ucfirst(str_replace('_', ' ', $s)) }}
                </option>
            @endforeach
        </select>

        <form id="filter-form" method="GET" action="{{ route('appointments.index') }}">
            <input type="hidden" name="from" value="{{ $from }}">
            <input type="hidden" name="to"   value="{{ $to }}">
        </form>
    </div>

    {{-- New Appointment button --}}
    <a href="{{ route('appointments.create') }}"
       class="flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        New Appointment
    </a>
</div>

{{-- =====================================================================
     CALENDAR GRID — Alpine.js renders events as colored blocks
     ===================================================================== --}}
<div
    x-data="{
        view: 'month',
        appointments: @js($appointments->resolve(request())),
        currentDate: new Date(),

        get daysInView() {
            if (this.view === 'day') return [this.currentDate];
            // simplified: month view returns all days
            const start = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth(), 1);
            const end   = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth() + 1, 0);
            const days  = [];
            for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
                days.push(new Date(d));
            }
            return days;
        },

        appointmentsForDay(date) {
            const ds = date.toISOString().slice(0, 10);
            return this.appointments.filter(a => a.start_time_local.slice(0, 10) === ds);
        },

        selectedAppointment: null,
    }"
    class="rounded-2xl bg-gray-900/60 backdrop-blur border border-gray-800 overflow-hidden">

    {{-- Calendar header --}}
    <div class="px-5 py-4 border-b border-gray-800 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <button @click="currentDate = new Date(currentDate.setMonth(currentDate.getMonth()-1))"
                    class="p-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-gray-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>
            <h2 class="text-sm font-semibold text-white min-w-32 text-center"
                x-text="currentDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })">
            </h2>
            <button @click="currentDate = new Date(currentDate.setMonth(currentDate.getMonth()+1))"
                    class="p-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-gray-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>
        </div>

        {{-- View switcher --}}
        <div class="flex bg-gray-800 rounded-lg p-0.5 gap-0.5">
            @foreach(['month' => 'Month', 'week' => 'Week', 'day' => 'Day'] as $key => $label)
                <button @click="view = '{{ $key }}'"
                        :class="view === '{{ $key }}' ? 'bg-gray-700 text-white' : 'text-gray-400 hover:text-white'"
                        class="px-3 py-1 rounded-md text-xs font-medium transition-colors">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- Day-of-week headers --}}
    <div class="grid grid-cols-7 border-b border-gray-800">
        @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $day)
            <div class="px-2 py-2 text-xs font-semibold text-gray-600 text-center">{{ $day }}</div>
        @endforeach
    </div>

    {{-- Calendar cells --}}
    <div class="grid grid-cols-7">
        <template x-for="day in daysInView" :key="day.toISOString()">
            <div class="min-h-24 p-1.5 border-b border-r border-gray-800/60
                        hover:bg-gray-800/20 transition-colors cursor-pointer"
                 @click="selectedAppointment = null">

                <p class="text-xs font-medium text-gray-500 mb-1" x-text="day.getDate()"></p>

                <template x-for="appt in appointmentsForDay(day)" :key="appt.id">
                    <button
                        @click.stop="selectedAppointment = appt"
                        class="w-full text-left px-1.5 py-0.5 rounded text-xs font-medium mb-0.5 truncate"
                        :style="'background-color: ' + (appt.provider?.color ?? '#6366f1') + '33; color: ' + (appt.provider?.color ?? '#6366f1')"
                        x-text="appt.time_display + ' ' + (appt.patient?.name ?? '')">
                    </button>
                </template>
            </div>
        </template>
    </div>
</div>

{{-- =====================================================================
     APPOINTMENT DETAIL MODAL (slide-over)
     ===================================================================== --}}
<div
    x-data="{ open: false, appointment: null }"
    @open-appointment.window="open = true; appointment = $event.detail"
    x-show="open"
    class="fixed inset-0 z-50 flex justify-end"
    style="display:none">

    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="open = false"></div>

    <div x-show="open"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full"
         class="relative w-full max-w-md bg-gray-900 border-l border-gray-800 h-full overflow-y-auto p-6">

        <template x-if="appointment">
            <div>
                <div class="flex items-start justify-between mb-6">
                    <div>
                        <h2 class="text-lg font-bold text-white" x-text="appointment.service_type"></h2>
                        <p class="text-sm text-gray-400" x-text="appointment.date_display"></p>
                    </div>
                    <button @click="open = false" class="text-gray-500 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- Status badge --}}
                <div class="flex items-center gap-2 mb-6">
                    <span class="px-3 py-1 rounded-full text-sm font-medium"
                          :class="{
                              'bg-emerald-500/20 text-emerald-400': appointment.status === 'confirmed',
                              'bg-gray-500/20 text-gray-400':       appointment.status === 'completed',
                              'bg-red-500/20 text-red-400':         appointment.status === 'cancelled',
                              'bg-yellow-500/20 text-yellow-400':   appointment.status === 'no_show',
                          }"
                          x-text="appointment.status">
                    </span>
                    <span class="text-xs text-gray-600" x-text="appointment.time_display"></span>
                </div>

                {{-- Details --}}
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-gray-600 uppercase tracking-wider mb-1">Patient</p>
                            <p class="text-sm text-white font-medium" x-text="appointment.patient?.name"></p>
                            <p class="text-xs text-gray-500" x-text="appointment.patient?.phone ?? '—'"></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-600 uppercase tracking-wider mb-1">Provider</p>
                            <p class="text-sm text-white font-medium" x-text="appointment.provider?.display_name"></p>
                        </div>
                    </div>

                    {{-- Sync status badges --}}
                    <div class="flex gap-2">
                        <span class="flex items-center gap-1 px-2 py-1 rounded-md text-xs"
                              :class="appointment.synced_to_calendar ? 'bg-emerald-500/20 text-emerald-400' : 'bg-gray-700 text-gray-500'">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span x-text="appointment.synced_to_calendar ? 'Calendar ✓' : 'Calendar —'"></span>
                        </span>
                        <span class="flex items-center gap-1 px-2 py-1 rounded-md text-xs"
                              :class="appointment.synced_to_sheets ? 'bg-emerald-500/20 text-emerald-400' : 'bg-gray-700 text-gray-500'">
                            <span x-text="appointment.synced_to_sheets ? 'Sheets ✓' : 'Sheets —'"></span>
                        </span>
                    </div>
                </div>

                {{-- Status change --}}
                <div class="mt-6 pt-6 border-t border-gray-800">
                    <p class="text-xs text-gray-600 uppercase tracking-wider mb-2">Change Status</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach(['confirmed','completed','cancelled','no_show'] as $s)
                        <button
                            @click="
                                fetch('/appointments/' + appointment.id + '/status', {
                                    method: 'PATCH',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                        'X-Requested-With': 'XMLHttpRequest'
                                    },
                                    body: JSON.stringify({ status: '{{ $s }}' })
                                }).then(r => r.json()).then(d => { appointment = d.appointment; })
                            "
                            class="px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors"
                            :class="appointment.status === '{{ $s }}'
                                ? 'bg-indigo-600 border-indigo-500 text-white'
                                : 'border-gray-700 text-gray-400 hover:border-gray-600 hover:text-white'">
                            {{ ucfirst(str_replace('_', ' ', $s)) }}
                        </button>
                        @endforeach
                    </div>
                </div>

                {{-- Full page links --}}
                <div class="mt-4 pt-4 border-t border-gray-800 flex gap-3">
                    <a :href="'/appointments/' + appointment.id"
                       class="flex-1 text-center px-3 py-2 text-xs font-medium text-gray-400 hover:text-white border border-gray-700 hover:border-gray-600 rounded-lg transition-colors">
                        View Full Details
                    </a>
                    <a :href="'/appointments/' + appointment.id + '/edit'"
                       class="flex-1 text-center px-3 py-2 text-xs font-medium bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition-colors">
                        Edit Appointment
                    </a>
                </div>
            </div>
        </template>
    </div>
</div>

</x-layouts.app>
