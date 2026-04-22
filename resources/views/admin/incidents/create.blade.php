@extends('admin.layouts.admin')

@section('title', 'Create Incident - Super Admin')

@section('content')
<div class="max-w-3xl">
    <nav class="flex items-center text-sm text-gray-400 mb-6">
        <a href="{{ route('admin.incidents.index') }}" class="hover:text-white">Incidents</a>
        <svg class="w-4 h-4 mx-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-white">Create</span>
    </nav>

    <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
        <h2 class="text-xl font-bold text-white mb-6">Create Incident Banner</h2>

        <form method="POST" action="{{ route('admin.incidents.store') }}" class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Title</label>
                    <input type="text" name="title" value="{{ old('title') }}" required maxlength="120"
                           class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-red-500 focus:border-transparent">
                    <p class="text-xs text-gray-500 mt-1">Brief title for the incident</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Message</label>
                    <textarea name="message" rows="4" required class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-red-500 focus:border-transparent">{{ old('message') }}</textarea>
                    <p class="text-xs text-gray-500 mt-1">Detailed message to display to users</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Severity</label>
                        <select name="severity" required class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg px-3 py-2">
                            <option value="">Select severity</option>
                            <option value="info" {{ old('severity') === 'info' ? 'selected' : '' }}>Info</option>
                            <option value="warning" {{ old('severity') === 'warning' ? 'selected' : '' }}>Warning</option>
                            <option value="critical" {{ old('severity') === 'critical' ? 'selected' : '' }}>Critical</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Scope</label>
                        <select name="is_platform_wide" id="platformScope" required onchange="toggleBusinessSelect()"
                                class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg px-3 py-2">
                            <option value="1" {{ !old('is_platform_wide') ? 'selected' : '' }}>Platform-wide</option>
                            <option value="0" {{ old('is_platform_wide') === '0' ? 'selected' : '' }}>Business-specific</option>
                        </select>
                    </div>

                    <div id="businessSelect" class="hidden">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Business</label>
                        <select name="business_id" class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg px-3 py-2">
                            <option value="">Select business</option>
                            @foreach($businesses as $business)
                                <option value="{{ $business->id }}" {{ old('business_id') == $business->id ? 'selected' : '' }}>
                                    {{ $business->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Starts At (Optional)</label>
                        <input type="datetime-local" name="starts_at" value="{{ old('starts_at') }}"
                               class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg px-3 py-2">
                        <p class="text-xs text-gray-500 mt-1">If empty, starts immediately</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Ends At (Optional)</label>
                        <input type="datetime-local" name="ends_at" value="{{ old('ends_at') }}"
                               class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg px-3 py-2">
                        <p class="text-xs text-gray-500 mt-1">If empty, no end time</p>
                    </div>
                </div>

                <div>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="publish_now" class="rounded border-gray-600 bg-gray-700 text-red-600 focus:ring-red-500">
                        <span class="text-sm font-medium text-gray-300">Publish immediately</span>
                    </label>
                </div>

                <div class="pt-4 border-t border-gray-700">
                    <button type="submit" class="px-6 py-2 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg transition-colors">
                        Create Incident
                    </button>
                    <a href="{{ route('admin.incidents.index') }}" class="px-6 py-2 bg-gray-700 hover:bg-gray-600 text-white font-medium rounded-lg transition-colors ml-2">
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
