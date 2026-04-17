<x-layouts.app title="Reminder Settings">

<div class="max-w-2xl mx-auto">
    <form method="POST" action="{{ route('settings.reminders.update') }}"
          x-data="{
              rules: @js($reminderSettings['reminders'] ?? []),
              add() {
                  this.rules.push({
                      offset_hours: 24,
                      label: '',
                      message_template: 'Hi {patient_name}, reminder: your {service_type} with {provider_name} at {business_name} is coming up at {time}.'
                  });
              },
              remove(i) { this.rules.splice(i, 1); }
          }">
        @csrf

        {{-- Header / Add button --}}
        <div class="flex items-center justify-between mb-4">
            <p class="text-sm text-gray-500">
                Reminders are sent via the same channel the patient used to book.
            </p>
            <button type="button" @click="add()"
                    class="flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-lg transition-colors">
                + Add Rule
            </button>
        </div>

        {{-- Rules list --}}
        <div class="space-y-4">
            <template x-for="(rule, i) in rules" :key="i">
                <div class="rounded-2xl bg-gray-900/60 backdrop-blur border border-gray-800 p-5 space-y-4">

                    <div class="flex items-start justify-between gap-4">
                        <div class="grid grid-cols-2 gap-3 flex-1">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Hours before appointment *</label>
                                <input type="number" :name="'reminders[' + i + '][offset_hours]'"
                                       x-model.number="rule.offset_hours" min="1" max="720" required
                                       class="w-full bg-gray-800 border border-gray-700 text-gray-100 text-sm rounded-lg px-3 py-2
                                              focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Label *</label>
                                <input type="text" :name="'reminders[' + i + '][label]'"
                                       x-model="rule.label" required
                                       placeholder="e.g. Day before, 2 hours before"
                                       class="w-full bg-gray-800 border border-gray-700 text-gray-100 text-sm rounded-lg px-3 py-2
                                              focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>
                        <button type="button" @click="remove(i)"
                                class="mt-5 text-gray-600 hover:text-red-400 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    {{-- Message template --}}
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="text-xs text-gray-500">Message Template *</label>
                            <span class="text-xs"
                                  :class="(rule.message_template?.length ?? 0) > 900 ? 'text-red-400' : 'text-gray-600'"
                                  x-text="(rule.message_template?.length ?? 0) + ' / 1024'">
                            </span>
                        </div>
                        <textarea :name="'reminders[' + i + '][message_template]'"
                                  x-model="rule.message_template"
                                  rows="3" required maxlength="1024"
                                  class="w-full bg-gray-800 border border-gray-700 text-gray-100 text-sm rounded-lg px-3 py-2
                                         focus:ring-indigo-500 focus:border-indigo-500 resize-none"></textarea>
                        <p class="text-xs text-gray-600 mt-1">
                            Available placeholders:
                            <code class="text-indigo-400">{patient_name}</code>
                            <code class="text-indigo-400">{provider_name}</code>
                            <code class="text-indigo-400">{provider_title}</code>
                            <code class="text-indigo-400">{service_type}</code>
                            <code class="text-indigo-400">{date}</code>
                            <code class="text-indigo-400">{time}</code>
                            <code class="text-indigo-400">{business_name}</code>
                            <code class="text-indigo-400">{business_phone}</code>
                        </p>
                    </div>

                    {{-- Preview --}}
                    <div class="bg-gray-800/50 rounded-lg px-4 py-3">
                        <p class="text-xs text-gray-600 uppercase tracking-wider mb-1">Preview</p>
                        <p class="text-sm text-gray-300"
                           x-text="rule.message_template
                               ?.replace('{patient_name}', 'Aliya')
                               ?.replace('{provider_name}', 'Dr. Ahmed')
                               ?.replace('{provider_title}', 'Dr.')
                               ?.replace('{service_type}', 'General Consultation')
                               ?.replace('{date}', 'Monday, April 20, 2026')
                               ?.replace('{time}', '2:00 PM')
                               ?.replace('{business_name}', '{{ $business->name }}')
                               ?.replace('{business_phone}', '+1234567890')">
                        </p>
                    </div>
                </div>
            </template>

            <template x-if="rules.length === 0">
                <div class="rounded-2xl bg-gray-900/40 border border-dashed border-gray-700 p-12 text-center">
                    <p class="text-gray-600 text-sm">No reminder rules yet. Click "Add Rule" to create your first reminder.</p>
                </div>
            </template>
        </div>

        <div class="flex justify-end mt-6">
            <button type="submit"
                    class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-lg transition-colors">
                Save Reminder Settings
            </button>
        </div>
    </form>
</div>

</x-layouts.app>
