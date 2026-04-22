<x-layouts.app title="Services">

<div class="grid grid-cols-1 gap-6 xl:grid-cols-[1.1fr,0.9fr]">
    <section class="panel overflow-hidden">
        <div class="border-b border-gray-800 px-5 py-4">
            <div class="page-eyebrow">Business Operations</div>
            <h2 class="mt-2 text-lg font-semibold text-white">Structured service catalog</h2>
            <p class="mt-2 text-sm text-gray-400">This adds local business structure on top of the legacy `ai_config.services` list. The shared brain/control-plane is still not centralized enough, so the UI keeps that weakness visible instead of styling around it.</p>
        </div>

        <div class="divide-y divide-gray-800">
            @forelse($services as $service)
                <div class="px-5 py-4">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-base font-semibold text-white">{{ $service->name }}</h3>
                                <span class="rounded-full px-2.5 py-1 text-xs {{ $service->is_active ? 'bg-emerald-500/20 text-emerald-400' : 'bg-gray-700 text-gray-400' }}">
                                    {{ $service->is_active ? 'Active' : 'Inactive' }}
                                </span>
                                @if($service->providers_count === 0)
                                    <span class="rounded-full bg-amber-500/20 px-2.5 py-1 text-xs text-amber-300">Unmapped</span>
                                @endif
                            </div>
                            <p class="mt-2 text-sm text-gray-400">{{ $service->description ?: 'No description yet.' }}</p>
                            <div class="mt-3 flex flex-wrap gap-3 text-xs text-gray-500">
                                <span>{{ $service->duration_minutes }} min</span>
                                <span>{{ $service->price ? '$' . number_format((float) $service->price, 2) : 'No price set' }}</span>
                                <span>{{ $service->providers_count }} mapped provider{{ $service->providers_count === 1 ? '' : 's' }}</span>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('services.toggle', $service) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn-secondary px-3 py-2 text-xs">
                                    {{ $service->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </form>
                            <a href="{{ route('services.show', $service) }}" class="btn-primary px-3 py-2 text-xs">Manage service</a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="px-5 py-12 text-center">
                    <p class="text-sm text-gray-400">No structured services yet. Legacy `ai_config.services` still powers the fallback path until you add your first operational service.</p>
                </div>
            @endforelse
        </div>
    </section>

    <section class="panel p-5">
        <div class="page-eyebrow">Add Service</div>
        <h2 class="mt-2 text-lg font-semibold text-white">Create a structured service</h2>
        <p class="mt-2 text-sm text-gray-400">New bookings can link to this service with `service_id` while still preserving the `service_type` snapshot for reminders, exports, conversations, and voice summaries.</p>

        <form method="POST" action="{{ route('services.store') }}" class="mt-5 space-y-4">
            @csrf

            <div>
                <label class="mb-1 block text-xs text-gray-500">Name *</label>
                <input type="text" name="name" value="{{ old('name') }}" class="field w-full" required>
            </div>

            <div>
                <label class="mb-1 block text-xs text-gray-500">Description</label>
                <textarea name="description" rows="3" class="field w-full">{{ old('description') }}</textarea>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Duration *</label>
                    <input type="number" name="duration_minutes" min="5" max="480" value="{{ old('duration_minutes', 30) }}" class="field w-full" required>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Price</label>
                    <input type="number" step="0.01" name="price" min="0" value="{{ old('price') }}" class="field w-full">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Sort order</label>
                    <input type="number" name="sort_order" min="0" value="{{ old('sort_order', 0) }}" class="field w-full">
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Buffer before</label>
                    <input type="number" name="booking_rules[buffer_before_minutes]" min="0" max="240" value="{{ old('booking_rules.buffer_before_minutes', 0) }}" class="field w-full">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Buffer after</label>
                    <input type="number" name="booking_rules[buffer_after_minutes]" min="0" max="240" value="{{ old('booking_rules.buffer_after_minutes', 0) }}" class="field w-full">
                </div>
            </div>

            <div class="space-y-2 rounded-xl border border-gray-800 p-4">
                <label class="flex items-center gap-2 text-sm text-gray-300">
                    <input type="checkbox" name="booking_rules[allow_online_booking]" value="1" class="rounded border-gray-700 bg-gray-800 text-indigo-500" {{ old('booking_rules.allow_online_booking', true) ? 'checked' : '' }}>
                    Allow online booking
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-300">
                    <input type="checkbox" name="booking_rules[requires_manual_confirmation]" value="1" class="rounded border-gray-700 bg-gray-800 text-indigo-500" {{ old('booking_rules.requires_manual_confirmation') ? 'checked' : '' }}>
                    Requires manual confirmation
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-300">
                    <input type="checkbox" name="is_active" value="1" class="rounded border-gray-700 bg-gray-800 text-indigo-500" {{ old('is_active', true) ? 'checked' : '' }}>
                    Active immediately
                </label>
            </div>

            @if($providers->isNotEmpty())
                <div>
                    <label class="mb-2 block text-xs text-gray-500">Initial provider mapping</label>
                    <div class="space-y-2 rounded-xl border border-gray-800 p-4">
                        @foreach($providers as $provider)
                            <label class="flex items-center justify-between gap-3 text-sm text-gray-300">
                                <span>{{ $provider->displayName() }}</span>
                                <input type="checkbox" name="provider_ids[]" value="{{ $provider->id }}" class="rounded border-gray-700 bg-gray-800 text-indigo-500" {{ in_array((string) $provider->id, array_map('strval', old('provider_ids', [])), true) ? 'checked' : '' }}>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="flex items-center justify-between pt-2">
                <p class="text-xs text-gray-500">If you skip provider mapping, the dashboard will flag that partial state.</p>
                <button type="submit" class="btn-primary px-4 py-2 text-sm">Create service</button>
            </div>
        </form>
    </section>
</div>

</x-layouts.app>
