@extends('admin.layouts.admin')

@section('title', 'Edit Incident - ' . $incident->title)

@section('content')
<div class="max-w-3xl">
    <nav class="flex items-center text-sm text-gray-400 mb-6">
        <a href="{{ route('admin.incidents.index') }}" class="hover:text-white">Incidents</a>
        <svg class="w-4 h-4 mx-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-white">Edit</span>
    </nav>

    <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
        <h2 class="text-xl font-bold text-white mb-6">Edit Incident Banner</h2>

        <form method="POST" action="{{ route('admin.incidents.update', $incident) }}" class="space-y-6">
            @csrf
            @method('PATCH')

            <div class="grid grid-cols-1 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Title</label>
                    <input type="text" name="title" value="{{ old('title', $incident->title) }}" required maxlength="120"
                           class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-red-500 focus:border-transparent">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Message</label>
                    <textarea name="message" rows="4" required class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-red-500 focus:border-transparent">{{ old('message', $incident->message) }}</textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Severity</label>
                        <select name="severity" required class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg px-3 py-2">
                            <option value="info" {{ old('severity', $incident->severity) === 'info' ? 'selected' : '' }}>Info</option>
                            <option value="warning" {{ old('severity', $incident->severity) === 'warning' ? 'selected' : '' }}>Warning</option>
                            <option value="critical" {{ old('severity', $incident->severity) === 'critical' ? 'selected' : '' }}>Critical</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Scope</label>
                        <select name="is_platform_wide" id="platformScope" required onchange="toggleBusinessSelect()"
                                class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg px-3 py-2">
                            <option value="1" {{ $incident->is_platform_wide ? 'selected' : '' }}>Platform-wide</option>
                            <option value="0" {{ !$incident->is_platform_wide ? 'selected' : '' }}>Business-specific</option>
                        </select>
                    </div>

                    <div id="businessSelect" class="{{ $incident->is_platform_wide ? 'hidden' : '' }}">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Business</label>
                        <select name="business_id" class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg px-3 py-2">
                            <option value="">Select business</option>
                            @foreach($businesses as $business)
                                <option value="{{ $business->id }}" {{ old('business_id', $incident->business_id) == $business->id ? 'selected' : '' }}>
                                    {{ $business->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Starts At</label>
                        <input type="datetime-local" name="starts_at" value="{{ old('starts_at', $incident->starts_at?->format('Y-m-d\TH:i')) }}"
                               class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg px-3 py-2">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Ends At</label>
                        <input type="datetime-local" name="ends_at" value="{{ old('ends_at', $incident->ends_at?->format('Y-m-d\TH:i')) }}"
                               class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg px-3 py-2">
                    </div>
                </div>

                <div class="pt-4 border-t border-gray-700 flex gap-3">
                    <button type="submit" class="px-6 py-2 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg transition-colors">
                        Update Incident
                    </button>
                    <a href="{{ route('admin.incidents.index') }}" class="px-6 py-2 bg-gray-700 hover:bg-gray-600 text-white font-medium rounded-lg transition-colors">
                        Cancel
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function toggleBusinessSelect() {
    const isPlatform = document.getElementById('platformScope').value === '1';
    document.getElementById('businessSelect').classList.toggle('hidden', isPlatform);
}
</script>
@endsection
