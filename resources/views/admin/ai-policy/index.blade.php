@extends('admin.layouts.admin')

@section('title', 'AI Policy - Super Admin')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-white">AI Policy Management</h2>
    </div>

    {{-- AI Policy Info Card --}}
    <div class="bg-blue-900/20 border border-blue-500/30 rounded-lg p-4">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-blue-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            <div>
                <h3 class="text-sm font-semibold text-blue-400">AI Policy Controls</h3>
                <p class="text-xs text-blue-200/80 mt-1">
                    This page provides visibility and controls for AI-related policies.
                    Note: This does NOT imply centralized runtime behavior. Runtime logic still reads from business ai_config.
                </p>
            </div>
        </div>
    </div>

    {{-- Search and Filter --}}
    <div class="bg-gray-800 rounded-lg border border-gray-700 p-4">
        <form method="GET" class="flex gap-4 items-center">
            <div class="flex-1">
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Search businesses..." class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg px-4 py-2">
            </div>
            <label class="flex items-center gap-2">
                <input type="checkbox" name="has_override" value="1" {{ request('has_override') ? 'checked' : '' }}
                       class="rounded border-gray-600 bg-gray-700 text-red-600 focus:ring-red-500">
                <span class="text-sm text-gray-300">Only businesses with overrides</span>
            </label>
            <button type="submit" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white text-sm font-medium rounded-lg">Filter</button>
        </form>
    </div>

    {{-- Businesses Table --}}
    <div class="bg-gray-800 rounded-lg border border-gray-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-700">
            <h3 class="text-lg font-semibold text-white">{{ $businesses->total() }} businesses</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-900/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Business</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Plan</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Rate Limits</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Guardrails</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Feature Flags</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    @foreach($businesses as $business)
                        @php
                            $hasOverride = $business->ai_rate_limit_per_hour !== null ||
                                          $business->ai_fallback_provider !== null;
                        @endphp
                        <tr class="hover:bg-gray-700/30 transition-colors {{ request('has_override') && !$hasOverride ? 'opacity-50' : '' }}">
                            <td class="px-6 py-4">
                                <div class="font-medium text-white">{{ $business->name }}</div>
                                <div class="text-sm text-gray-400">{{ $business->slug }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-sm text-gray-300">{{ $business->subscription?->plan?->name ?? 'No Plan' }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-300">
                                    Hour: {{ $business->ai_rate_limit_per_hour ?? 'Default' }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    Day: {{ $business->ai_rate_limit_per_day ?? 'Default' }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3 text-xs">
                                    <span class="{{ $business->ai_content_safety_enabled ? 'text-emerald-400' : 'text-red-400' }}" title="Content Safety">
                                        <svg class="w-3 h-3 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                        {{ $business->ai_content_safety_enabled ? 'On' : 'Off' }}
                                    </span>
                                    <span class="{{ $business->ai_pii_detection_enabled ? 'text-emerald-400' : 'text-red-400' }}" title="PII Detection">
                                        <svg class="w-3 h-3 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                        {{ $business->ai_pii_detection_enabled ? 'On' : 'Off' }}
                                    </span>
                                    <span class="{{ $business->ai_hallucination_guard_enabled ? 'text-emerald-400' : 'text-red-400' }}" title="Hallucination Guard">
                                        <svg class="w-3 h-3 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                        {{ $business->ai_hallucination_guard_enabled ? 'On' : 'Off' }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-2">
                                    @if($business->ai_voice_agent_enabled)
                                        <span class="px-2 py-0.5 bg-purple-500/20 text-purple-400 rounded text-xs">Voice Agent</span>
                                    @endif
                                    @if($business->ai_email_agent_enabled)
                                        <span class="px-2 py-0.5 bg-purple-500/20 text-purple-400 rounded text-xs">Email Agent</span>
                                    @endif
                                    @if($business->ai_chat_only_enabled)
                                        <span class="px-2 py-0.5 bg-indigo-500/20 text-indigo-400 rounded text-xs">Chat Only</span>
                                    @endif
                                    @if($business->ai_fallback_provider)
                                        <span class="px-2 py-0.5 bg-gray-700 text-gray-400 rounded text-xs">Fallback: {{ $business->ai_fallback_provider }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('admin.ai-policy.show', $business) }}"
                                   class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white text-xs font-medium rounded-lg transition-colors">
                                    Manage
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t border-gray-700">
            {{ $businesses->links() }}
        </div>
    </div>
</div>
@endsection
