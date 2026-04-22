<x-layouts.app title="Booking Rules">

<div class="mx-auto max-w-4xl">
    <div class="panel p-6">
        <div class="page-eyebrow">Operations Guardrails</div>
        <h2 class="mt-2 text-xl font-semibold text-white">Business-level booking rules</h2>
        <p class="mt-2 text-sm text-gray-400">These rules live on the business record so local operations stay configurable. The shared brain still exists outside this page, and that architectural weakness remains visible rather than hidden.</p>

        <form method="POST" action="{{ route('settings.booking-rules.update') }}" class="mt-6 space-y-6">
            @csrf

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Lead time (minutes) *</label>
                    <input type="number" name="lead_time_minutes" min="0" max="10080" value="{{ old('lead_time_minutes', $bookingRules['lead_time_minutes']) }}" class="field w-full" required>
                    <p class="mt-1 text-xs text-gray-600">Minimum gap between now and the earliest allowed booking.</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Max advance days *</label>
                    <input type="number" name="max_advance_days" min="1" max="365" value="{{ old('max_advance_days', $bookingRules['max_advance_days']) }}" class="field w-full" required>
                    <p class="mt-1 text-xs text-gray-600">How far into the future customers can book.</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Cancellation notice (hours) *</label>
                    <input type="number" name="cancellation_notice_hours" min="0" max="720" value="{{ old('cancellation_notice_hours', $bookingRules['cancellation_notice_hours']) }}" class="field w-full" required>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Reschedule notice (hours) *</label>
                    <input type="number" name="reschedule_notice_hours" min="0" max="720" value="{{ old('reschedule_notice_hours', $bookingRules['reschedule_notice_hours']) }}" class="field w-full" required>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-xs text-gray-500">Default booking status *</label>
                <select name="default_booking_status" class="field w-full" required>
                    @foreach(['confirmed' => 'Confirmed', 'pending' => 'Pending manual review'] as $value => $label)
                        <option value="{{ $value }}" {{ old('default_booking_status', $bookingRules['default_booking_status']) === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <label class="flex items-start gap-3 rounded-xl border border-gray-800 p-4 text-sm text-gray-300">
                    <input type="checkbox" name="allow_same_day_booking" value="1" class="mt-1 rounded border-gray-700 bg-gray-800 text-indigo-500" {{ old('allow_same_day_booking', $bookingRules['allow_same_day_booking']) ? 'checked' : '' }}>
                    <span>
                        <span class="font-medium text-white">Allow same-day booking</span>
                        <span class="mt-1 block text-xs text-gray-500">If disabled, the lead-time rule becomes stricter for current-day slots.</span>
                    </span>
                </label>
                <label class="flex items-start gap-3 rounded-xl border border-gray-800 p-4 text-sm text-gray-300">
                    <input type="checkbox" name="require_provider_selection" value="1" class="mt-1 rounded border-gray-700 bg-gray-800 text-indigo-500" {{ old('require_provider_selection', $bookingRules['require_provider_selection']) ? 'checked' : '' }}>
                    <span>
                        <span class="font-medium text-white">Require provider selection</span>
                        <span class="mt-1 block text-xs text-gray-500">Keeps bookings explicit when you do not want the system to suggest any mapped provider automatically.</span>
                    </span>
                </label>
            </div>

            <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-200">
                Current architectural weakness: the shared brain is not visually or structurally centralized enough. These rules improve local business operations, but they do not unify the platform control-plane.
            </div>

            <div class="flex items-center justify-between pt-2">
                <a href="{{ route('services.index') }}" class="text-sm text-[var(--text-muted)] hover:text-[var(--text-strong)] transition-colors">Open services →</a>
                <button type="submit" class="btn-primary px-5 py-2 text-sm">Save booking rules</button>
            </div>
        </form>
    </div>
</div>

</x-layouts.app>
