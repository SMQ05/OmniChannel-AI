@extends('admin.layouts.admin')

@section('title', 'Support Workspace - ' . $business->name)

@section('content')
<div class="max-w-5xl">
    <nav class="flex items-center justify-between text-sm text-gray-400 mb-6">
        <div class="flex items-center">
            <a href="{{ route('admin.support.index') }}" class="hover:text-white">Support</a>
            <svg class="w-4 h-4 mx-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="text-white">{{ $business->name }}</span>
        </div>
        <a href="{{ route('admin.businesses.index') }}" class="text-sm font-medium hover:text-white">Back to Businesses</a>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-5">
                <h3 class="text-lg font-semibold text-white mb-4">Business Information</h3>
                <div class="space-y-3 text-sm">
                    <div>
                        <div class="text-gray-500">Name</div>
                        <div class="text-white font-medium">{{ $business->name }}</div>
                    </div>
                    <div>
                        <div class="text-gray-500">Slug</div>
                        <div class="text-gray-300">{{ $business->slug }}</div>
                    </div>
                    <div>
                        <div class="text-gray-500">Plan</div>
                        <div class="text-gray-300">{{ $business->subscription?->plan?->name ?? 'No Plan' }}</div>
                    </div>
                    <div>
                        <div class="text-gray-500">Lifecycle Status</div>
                        <div class="text-gray-300">{{ $business->subscription?->lifecycle_status ?? 'N/A' }}</div>
                    </div>
                    <div>
                        <div class="text-gray-500">Active</div>
                        <span class="inline-flex items-center gap-2 px-2 py-1 rounded text-xs font-medium {{ $business->is_active ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400' }}">
                            {{ $business->is_active ? 'Yes' : 'No' }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="bg-gray-800 rounded-lg border border-gray-700 p-5">
                <h3 class="text-lg font-semibold text-white mb-4">Quick Actions</h3>
                <div class="space-y-2">
                    <a href="{{ route('admin.onboarding.show', $business) }}" class="block p-3 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm font-medium text-white transition-colors">
                        View Launch Status
                    </a>
                    <a href="{{ route('admin.incidents.index', ['business_id' => $business->id]) }}" class="block p-3 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm font-medium text-white transition-colors">
                        View Open Incidents
                    </a>
                    <a href="{{ route('admin.audit-logs.for-business', $business) }}" class="block p-3 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm font-medium text-white transition-colors">
                        View Audit Trail
                    </a>
                </div>
            </div>
        </div>

        <div class="lg:col-span-2 space-y-6">
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-5">
                <h3 class="text-lg font-semibold text-white mb-4">Add Support Note</h3>
                <form action="{{ route('admin.support.add-note', $business) }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Note</label>
                        <textarea name="note" rows="3" required class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-red-500 focus:border-transparent"></textarea>
                    </div>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_private" value="1" checked
                               class="rounded border-gray-600 bg-gray-700 text-red-600 focus:ring-red-500">
                        <span class="text-sm text-gray-300">Private (not visible to tenant)</span>
                    </label>
                    <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition-colors">
                        Add Note
                    </button>
                </form>
            </div>

            <div class="bg-gray-800 rounded-lg border border-gray-700 p-5">
                <h3 class="text-lg font-semibold text-white mb-4">Recent Conversations</h3>
                @if($recentConversations->isEmpty())
                    <p class="text-gray-400 text-sm">No conversations found.</p>
                @else
                    <div class="space-y-3">
                        @foreach($recentConversations->take(5) as $conv)
                            <div class="flex items-start gap-3 p-3 bg-gray-700/30 rounded-lg">
                                <span class="px-2 py-1 bg-blue-500/20 text-blue-400 rounded text-xs font-medium mt-1">
                                    {{ Str::limit($conv->type ?? 'message', 10) }}
                                </span>
                                <div class="flex-1">
                                    <div class="text-sm text-gray-300">
                                        {{ Str::limit($conv->message ?? 'No message content', 150) }}
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        {{ $conv->created_at->diffForHumans() }} {{ $conv->is_inbound ? 'Inbound' : 'Outbound' }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="bg-gray-800 rounded-lg border border-gray-700 p-5">
                <h3 class="text-lg font-semibold text-white mb-4">Recent Outbound Attempts</h3>
                @if($recentOutboundAttempts->isEmpty())
                    <p class="text-gray-400 text-sm">No outbound attempts found.</p>
                @else
                    <div class="space-y-3">
                        @foreach($recentOutboundAttempts->take(5) as $attempt)
                            <div class="flex items-start gap-3 p-3 bg-gray-700/30 rounded-lg">
                                <span class="px-2 py-1 rounded text-xs font-medium mt-1
                                    {{ $attempt->status === 'sent' ? 'bg-emerald-500/20 text-emerald-400' :
                                       ($attempt->status === 'failed' ? 'bg-red-500/20 text-red-400' :
                                       'bg-gray-500/20 text-gray-400') }}">
                                    {{ ucfirst($attempt->status ?? 'unknown') }}
                                </span>
                                <div class="flex-1">
                                    <div class="text-sm text-gray-300">
                                        {{ Str::limit($attempt->message_body ?? 'No message', 150) }}
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        {{ $attempt->created_at->diffForHumans() }}
                                        @if($attempt->error)
                                            <span class="text-red-400">Error: {{ Str::limit($attempt->error, 50) }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="bg-gray-800 rounded-lg border border-gray-700 p-5">
                <h3 class="text-lg font-semibold text-white mb-4">Open Incidents for This Business</h3>
                @if($openIncidents->isEmpty())
                    <p class="text-gray-400 text-sm">No active incidents for this business.</p>
                @else
                    <div class="space-y-3">
                        @foreach($openIncidents as $incident)
                            <div class="p-3 bg-red-900/20 border border-red-500/30 rounded-lg">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-red-400 font-medium">{{ $incident->severity }}</span>
                                    <span class="text-gray-400 text-xs">{{ $incident->created_at->diffForHumans() }}</span>
                                </div>
                                <div class="text-sm text-gray-300">{{ $incident->title }}</div>
                                <div class="text-xs text-gray-500 mt-1">{{ Str::limit($incident->message, 200) }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
