@extends('admin.layouts.admin')

@section('title', 'Credentials - Super Admin')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-white">Credential Metadata</h2>
        <div class="flex gap-2">
            <form method="GET" class="flex items-center gap-2">
                <select name="provider" class="bg-gray-700 text-white border border-gray-600 rounded-lg px-3 py-2 text-sm">
                    <option value="">All Providers</option>
                    @foreach($providers as $provider)
                        <option value="{{ $provider }}" {{ request('provider') === $provider ? 'selected' : '' }}>{{ $provider }}</option>
                    @endforeach
                </select>
                <button type="submit" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white text-sm font-medium rounded-lg">Filter</button>
            </form>
        </div>
    </div>

    {{-- Credential Metadata Info Card --}}
    <div class="bg-amber-900/20 border border-amber-500/30 rounded-lg p-4">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-amber-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div>
                <h3 class="text-sm font-semibold text-amber-400">Metadata Workspace Only</h3>
                <p class="text-xs text-amber-200/80 mt-1">
                    This page displays credential metadata only - rotation schedules, verification status, and provider information.
                    Actual credential values are stored separately and never exposed here.
                </p>
            </div>
        </div>
    </div>

    {{-- Credentials Table --}}
    <div class="bg-gray-800 rounded-lg border border-gray-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-700 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-white">{{ $credentials->total() }} credentials</h3>
            <div class="text-sm text-gray-400">
                Showing {{ $credentials->firstItem() }}-{{ $credentials->lastItem() }} of {{ $credentials->total() }}
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-900/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Provider</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Source</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Key Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Rotation</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Verification</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Active</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    @foreach($credentials as $credential)
                        <tr class="hover:bg-gray-700/30 transition-colors">
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 rounded text-xs font-medium bg-gray-700 text-gray-300">
                                    {{ $credential->providerLabel() }}
                                </span>
                                <div class="text-xs text-gray-500 mt-1">{{ $credential->provider }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-white">{{ Str::beforeLast($credential->source_type, '_') }}</div>
                                <div class="text-xs text-gray-500">
                                    ID: {{ $credential->source_id }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-white">{{ $credential->key_name }}</div>
                                @if($credential->description)
                                    <div class="text-xs text-gray-500 line-clamp-1">{{ $credential->description }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-300">
                                    @if($credential->last_rotated_at)
                                        {{ $credential->last_rotated_at->diffForHumans() }}
                                    @else
                                        Never
                                    @endif
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    Due: {{ $credential->next_rotation_due_at ? $credential->next_rotation_due_at->format('M j, Y') : 'Not scheduled' }}
                                </div>
                                @if($credential->isRotationOverdue())
                                    <span class="inline-block mt-1 px-2 py-0.5 bg-red-500/20 text-red-400 rounded text-xs">
                                        Overdue
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if($credential->last_verified_at)
                                    <span class="text-xs text-gray-400">
                                        {{ $credential->last_verified_at->diffForHumans() }}
                                    </span>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="px-2 py-0.5 rounded text-xs font-medium {{ $credential->verificationStatusClass() }}">
                                            {{ ucfirst($credential->last_verification_status ?? 'unknown') }}
                                        </span>
                                    </div>
                                @else
                                    <span class="text-sm text-gray-500">Not verified</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full {{ $credential->is_active ? 'bg-emerald-500' : 'bg-gray-500' }}"></span>
                                    <span class="text-sm {{ $credential->is_active ? 'text-emerald-400' : 'text-gray-400' }}">
                                        {{ $credential->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right space-x-2">
                                <a href="{{ route('admin.credentials.show', [$credential->source_type, $credential->source_id]) }}"
                                   class="px-2 py-1 bg-gray-600 text-white text-xs font-medium rounded hover:bg-gray-500">
                                    View
                                </a>
                                <form action="{{ route('admin.credentials.deactivate', $credential) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="px-2 py-1 bg-red-600 text-white text-xs font-medium rounded hover:bg-red-700"
                                            onclick="return confirm('Deactivate this credential?')">Deactivate</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t border-gray-700">
            {{ $credentials->links() }}
        </div>
    </div>
</div>
@endsection
