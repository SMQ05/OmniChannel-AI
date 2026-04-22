@extends('admin.layouts.admin')

@section('title', 'AI Policy - ' . $business->name)

@section('content')
<div class="max-w-5xl">
    <nav class="flex items-center justify-between text-sm text-gray-400 mb-6">
        <div class="flex items-center">
            <a href="{{ route('admin.ai-policy.index') }}" class="hover:text-white">AI Policy</a>
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
                        <div class="text-gray-300">{{ $plan?->name ?? 'No Plan' }}</div>
                    </div>
                </div>
            </div>

            <div class="bg-gray-800 rounded-lg border border-gray-700 p-5">
                <h3 class="text-lg font-semibold text-white mb-4">AI Policy Actions</h3>
                <form action="{{ route('admin.ai-policy.reset-defaults', $business) }}" method="POST" class="space-y-3">
                    @csrf
                    <button type="submit" class="w-full px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white text-sm font-medium rounded-lg transition-colors">
                        Reset to Plan Defaults
                    </button>
                </form>
            </div>
        </div>

        <div class="lg:col-span-2 space-y-6">
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-5">
                <h3 class="text-lg font-semibold text-white mb-4">AI Rate Limits</h3>
                <form action="{{ route('admin.ai-policy.update', $business) }}" method="POST" class="space-y-4">
                    @csrf
                    @method('PATCH')
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Hourly Limit</label>
                            <input type="number" name="ai_rate_limit_per_hour" value="{{ $featureFlags['rate_limit_per_hour'] ?? '' }}" min="1" max="10000" class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Daily Limit</label>
                            <input type="number" name="ai_rate_limit_per_day" value="{{ $featureFlags['rate_limit_per_day'] ?? '' }}" min="1" max="100000" class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg px-3 py-2">
                        </div>
                    </div>
                    <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition-colors">
                        Update Rate Limits
                    </button>
                </form>
            </div>

            <div class="bg-gray-800 rounded-lg border border-gray-700 p-5">
                <h3 class="text-lg font-semibold text-white mb-4">AI Guardrails</h3>
                <form action="{{ route('admin.ai-policy.update', $business) }}" method="POST" class="space-y-4">
                    @csrf
                    @method('PATCH')
                    <div class="space-y-3">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="ai_content_safety_enabled" value="1" {{ $featureFlags['content_safety_enabled'] ?? false ? 'checked' : '' }} class="rounded border-gray-600 bg-gray-700 text-red-600 focus:ring-red-500">
                            <span class="text-sm text-gray-300">Content Safety</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="ai_pii_detection_enabled" value="1" {{ $featureFlags['pii_detection_enabled'] ?? false ? 'checked' : '' }} class="rounded border-gray-600 bg-gray-700 text-red-600 focus:ring-red-500">
                            <span class="text-sm text-gray-300">PII Detection</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="ai_hallucination_guard_enabled" value="1" {{ $featureFlags['hallucination_guard_enabled'] ?? false ? 'checked' : '' }} class="rounded border-gray-600 bg-gray-700 text-red-600 focus:ring-red-500">
                            <span class="text-sm text-gray-300">Hallucination Guard</span>
                        </label>
                    </div>
                    <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition-colors">
                        Update Guardrails
                    </button>
                </form>
            </div>

            <div class="bg-gray-800 rounded-lg border border-gray-700 p-5">
                <h3 class="text-lg font-semibold text-white mb-4">AI Feature Flags</h3>
                <form action="{{ route('admin.ai-policy.update', $business) }}" method="POST" class="space-y-4">
                    @csrf
                    @method('PATCH')
                    <div class="space-y-3">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="ai_voice_agent_enabled" value="1" {{ $featureFlags['voice_agent_enabled'] ?? false ? 'checked' : '' }} class="rounded border-gray-600 bg-gray-700 text-red-600 focus:ring-red-500">
                            <span class="text-sm text-gray-300">Voice Agent</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="ai_email_agent_enabled" value="1" {{ $featureFlags['email_agent_enabled'] ?? false ? 'checked' : '' }} class="rounded border-gray-600 bg-gray-700 text-red-600 focus:ring-red-500">
                            <span class="text-sm text-gray-300">Email Agent</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="ai_chat_only_enabled" value="1" {{ $featureFlags['chat_only_enabled'] ?? false ? 'checked' : '' }} class="rounded border-gray-600 bg-gray-700 text-red-600 focus:ring-red-500">
                            <span class="text-sm text-gray-300">Chat Only Mode</span>
                        </label>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Fallback Provider</label>
                        <select name="ai_fallback_provider" class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg px-3 py-2">
                            <option value="">None</option>
                            <option value="openrouter" {{ $featureFlags['fallback_provider'] ?? '' === 'openrouter' ? 'selected' : '' }}>OpenRouter</option>
                            <option value="claude" {{ $featureFlags['fallback_provider'] ?? '' === 'claude' ? 'selected' : '' }}>Claude</option>
                            <option value="gpt4o" {{ $featureFlags['fallback_provider'] ?? '' === 'gpt4o' ? 'selected' : '' }}>GPT-4o</option>
                            <option value="minimax" {{ $featureFlags['fallback_provider'] ?? '' === 'minimax' ? 'selected' : '' }}>Minimax</option>
                        </select>
                    </div>
                    <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition-colors">
                        Update Feature Flags
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
