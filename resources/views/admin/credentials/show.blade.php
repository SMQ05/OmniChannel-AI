@extends('admin.layouts.admin')

@section('title', 'Credentials - ' . $source_type)

@section('content')
<div class="max-w-4xl">
    <nav class="flex items-center text-sm text-gray-400 mb-6">
        <a href="{{ route('admin.credentials.index') }}" class="hover:text-white">Credentials</a>
        <svg class="w-4 h-4 mx-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-white">{{ Str::beforeLast($source_type, '_') }}</span>
        <span class="text-gray-500 ml-2">ID: {{ $source_id }}</span>
    </nav>

    <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
        <h2 class="text-xl font-bold text-white mb-6">Credential Metadata for {{ Str::beforeLast($source_type, '_') }} #{{ $source_id }}</h2>

        @if($credentials->isEmpty())
            <div class="text-center py-12">
                <p class="text-gray-400">No credential metadata exists for this source.</p>
                <a href="{{ route('admin.credentials.index') }}" class="mt-4 inline-block px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg">
                    Browse Credentials
                </a>
            </div>
        @else
            <div class="space-y-4">
                @foreach($credentials as $credential)
                    <div class="border border-gray-700 rounded-lg p-4 bg-gray-700/30">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <h3 class="font-medium text-white">{{ $credential->key_name }}</h3>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="px-2 py-0.5 bg-gray-700 text-gray-300 rounded text-xs">{{ $credential->providerLabel() }}</span>
                                    <span class="text-xs text-gray-500">Provider: {{ $credential->provider }}</span>
                                </div>
                            </div>
                            <span class="px-2 py-1 rounded text-xs font-medium {{ $credential->is_active ? 'bg-emerald-500/20 text-emerald-400' : 'bg-gray-500/20 text-gray-400' }}">
                                {{ $credential->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>

                        @if($credential->description)
                            <p class="text-sm text-gray-400 mb-4">{{ $credential->description }}</p>
                        @endif

                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                            <div>
                                <div class="text-gray-500 text-xs">Created</div>
                                <div class="text-gray-300">{{ $credential->created_at?->diffForHumans() }}</div>
                            </div>
                            <div>
                                <div class="text-gray-500 text-xs">Last Rotated</div>
                                <div class="text-gray-300">{{ $credential->last_rotated_at ? $credential->last_rotated_at->diffForHumans() : 'Never' }}</div>
                            </div>
                            <div>
                                <div class="text-gray-500 text-xs">Next Rotation Due</div>
                                <div class="{{ $credential->isRotationOverdue() ? 'text-red-400' : 'text-gray-300' }}">
                                    {{ $credential->next_rotation_due_at ? $credential->next_rotation_due_at->format('M j, Y') : 'Not scheduled' }}
                                </div>
                            </div>
                            <div>
                                <div class="text-gray-500 text-xs">Rotation Interval</div>
                                <div class="text-gray-300">{{ $credential->rotation_interval_days }} days</div>
                            </div>
                        </div>

                        @if($credential->last_verified_at)
                            <div class="mt-4 pt-4 border-t border-gray-700">
                                <h4 class="text-sm font-medium text-gray-400 mb-2">Last Verification</h4>
                                <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                                    <div>
                                        <div class="text-gray-500 text-xs">Verified At</div>
                                        <div class="text-gray-300">{{ $credential->last_verified_at->diffForHumans() }}</div>
                                    </div>
                                    <div>
                                        <div class="text-gray-500 text-xs">Status</div>
                                        <span class="px-2 py-1 rounded text-xs font-medium {{ $credential->verificationStatusClass() }}">
                                            {{ ucfirst($credential->last_verification_status ?? 'unknown') }}
                                        </span>
                                    </div>
                                </div>
                                @if($credential->last_verification_message)
                                    <div class="mt-2">
                                        <div class="text-gray-500 text-xs">Message</div>
                                        <p class="text-sm text-gray-400">{{ $credential->last_verification_message }}</p>
                                    </div>
                                @endif
                            </div>
                        @endif

                        <div class="mt-4 pt-4 border-t border-gray-700 flex gap-2">
                            <form action="{{ route('admin.credentials.record-rotation', [$source_type, $source_id]) }}" method="POST" class="inline">
                                @csrf
                                <input type="hidden" name="credential_id" value="{{ $credential->id }}">
                                <button type="submit" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded">
                                    Record Rotation
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
