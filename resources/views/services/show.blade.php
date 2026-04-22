<x-layouts.app :title="$service->name">

<div class="mb-6 flex items-center justify-between gap-3">
    <a href="{{ route('services.index') }}" class="text-sm text-[var(--text-muted)] hover:text-[var(--text-strong)] transition-colors">← Services</a>
    <form method="POST" action="{{ route('services.toggle', $service) }}">
        @csrf
        @method('PATCH')
        <button type="submit" class="btn-secondary px-4 py-2 text-xs">{{ $service->is_active ? 'Deactivate' : 'Activate' }} service</button>
    </form>
</div>

<div class="grid grid-cols-1 gap-6 xl:grid-cols-[1.05fr,0.95fr]">
    <section class="panel p-5">
        <div class="page-eyebrow">Service Details</div>
        <h2 class="mt-2 text-lg font-semibold text-white">Edit operational service</h2>
        <p class="mt-2 text-sm text-gray-400">Appointments linked here still keep their own `service_type` snapshot. That additive transition remains intentional until the structured path is fully proven.</p>

        <form method="POST" action="{{ route('services.update', $service) }}" class="mt-5 space-y-4">
            @csrf
            @method('PATCH')

            <div>
                <label class="mb-1 block text-xs text-gray-500">Name *</label>
                <input type="text" name="name" value="{{ old('name', $service->name) }}" class="field w-full" required>
            </div>

            <div>
                <label class="mb-1 block text-xs text-gray-500">Description</label>
                <textarea name="description" rows="3" class="field w-full">{{ old('description', $service->description) }}</textarea>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Duration *</label>
                    <input type="number" name="duration_minutes" min="5" max="480" value="{{ old('duration_minutes', $service->duration_minutes) }}" class="field w-full" required>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Price</label>
                    <input type="number" step="0.01" name="price" min="0" value="{{ old('price', $service->price) }}" class="field w-full">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Sort order</label>
                    <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $service->sort_order) }}" class="field w-full">
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Buffer before</label>
                    <input type="number" name="booking_rules[buffer_before_minutes]" min="0" max="240" value="{{ old('booking_rules.buffer_before_minutes', $service->rules()['buffer_before_minutes'] ?? 0) }}" class="field w-full">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Buffer after</label>
                    <input type="number" name="booking_rules[buffer_after_minutes]" min="0" max="240" value="{{ old('booking_rules.buffer_after_minutes', $service->rules()['buffer_after_minutes'] ?? 0) }}" class="field w-full">
                </div>
            </div>

            <div class="space-y-2 rounded-xl border border-gray-800 p-4">
                <label class="flex items-center gap-2 text-sm text-gray-300">
                    <input type="checkbox" name="booking_rules[allow_online_booking]" value="1" class="rounded border-gray-700 bg-gray-800 text-indigo-500" {{ old('booking_rules.allow_online_booking', $service->rules()['allow_online_booking'] ?? true) ? 'checked' : '' }}>
                    Allow online booking
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-300">
                    <input type="checkbox" name="booking_rules[requires_manual_confirmation]" value="1" class="rounded border-gray-700 bg-gray-800 text-indigo-500" {{ old('booking_rules.requires_manual_confirmation', $service->rules()['requires_manual_confirmation'] ?? false) ? 'checked' : '' }}>
                    Requires manual confirmation
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-300">
                    <input type="checkbox" name="is_active" value="1" class="rounded border-gray-700 bg-gray-800 text-indigo-500" {{ old('is_active', $service->is_active) ? 'checked' : '' }}>
                    Service is active
                </label>
            </div>

            <div class="flex items-center justify-between pt-2">
                <div class="text-xs text-gray-500">
                    {{ $service->appointments->count() }} appointment{{ $service->appointments->count() === 1 ? '' : 's' }} currently linked to this service.
                </div>
                <button type="submit" class="btn-primary px-4 py-2 text-sm">Save service</button>
            </div>
        </form>
    </section>

    <section class="panel overflow-hidden">
        <div class="border-b border-gray-800 px-5 py-4">
            <div class="page-eyebrow">Provider Mapping</div>
            <h2 class="mt-2 text-lg font-semibold text-white">Assign providers</h2>
            <p class="mt-2 text-sm text-gray-400">Mappings are explicit so availability stays understandable. If this service has no providers, the dashboard will continue to flag it as partial.</p>
        </div>

        <form method="POST" action="{{ route('services.providers.sync', $service) }}" class="p-5">
            @csrf
            @method('PATCH')

            <div class="space-y-2">
                @forelse($providers as $provider)
                    <label class="flex items-center justify-between gap-3 rounded-xl border border-gray-800 px-4 py-3 text-sm text-gray-300">
                        <div>
                            <div class="font-medium text-white">{{ $provider->displayName() }}</div>
                            <div class="mt-1 text-xs text-gray-500">
                                {{ $provider->services_count }} mapped service{{ $provider->services_count === 1 ? '' : 's' }} · {{ $provider->slot_duration_minutes }} min base slots
                            </div>
                        </div>
                        <input type="checkbox" name="provider_ids[]" value="{{ $provider->id }}" class="rounded border-gray-700 bg-gray-800 text-indigo-500" {{ in_array((string) $provider->id, array_map('strval', old('provider_ids', $service->providers->pluck('id')->all())), true) ? 'checked' : '' }}>
                    </label>
                @empty
                    <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-200">
                        No active providers yet. You can still define services now, but service-aware booking will remain partial until providers are created and mapped.
                    </div>
                @endforelse
            </div>

            <div class="mt-5 flex items-center justify-between">
                <div class="text-xs text-gray-500">
                    Current mapping: {{ $service->providers->count() }} provider{{ $service->providers->count() === 1 ? '' : 's' }}
                </div>
                <button type="submit" class="btn-primary px-4 py-2 text-sm">Save mapping</button>
            </div>
        </form>
    </section>
</div>

</x-layouts.app>
