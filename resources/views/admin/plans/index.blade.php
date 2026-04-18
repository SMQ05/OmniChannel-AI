<x-admin.layouts.admin title="Plans">

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

    <div class="xl:col-span-1 rounded-2xl bg-gray-900/60 backdrop-blur border border-gray-800 p-6">
        <h2 class="text-sm font-semibold text-white mb-4">Create Missing Plan</h2>

        @if(empty($missingCodes))
            <p class="text-sm text-gray-500">All standard plans already exist. Edit them on the right.</p>
        @else
            <form method="POST" action="{{ route('admin.plans.store') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-xs text-gray-500 mb-1">Code</label>
                    <select name="code" class="w-full bg-gray-800 border border-gray-700 text-gray-300 text-sm rounded-lg px-3 py-2">
                        @foreach($missingCodes as $code)
                            <option value="{{ $code }}">{{ ucfirst($code) }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs text-gray-500 mb-1">Name</label>
                    <input type="text" name="name" required class="w-full bg-gray-800 border border-gray-700 text-gray-100 text-sm rounded-lg px-3 py-2">
                </div>

                <div>
                    <label class="block text-xs text-gray-500 mb-1">Description</label>
                    <textarea name="description" rows="3" class="w-full bg-gray-800 border border-gray-700 text-gray-100 text-sm rounded-lg px-3 py-2"></textarea>
                </div>

                <div>
                    <label class="block text-xs text-gray-500 mb-1">Included Quotas JSON</label>
                    <textarea name="included_quotas" rows="6" class="w-full bg-gray-800 border border-gray-700 text-gray-100 text-xs rounded-lg px-3 py-2 font-mono" placeholder='{"messages_sent":500,"llm_tokens_estimated":100000}'></textarea>
                </div>

                <div>
                    <label class="block text-xs text-gray-500 mb-1">Feature Flags JSON</label>
                    <textarea name="feature_flags" rows="4" class="w-full bg-gray-800 border border-gray-700 text-gray-100 text-xs rounded-lg px-3 py-2 font-mono" placeholder='{"whatsapp":true,"messenger":true,"voice_agent":false}'></textarea>
                </div>

                <label class="flex items-center gap-2 text-sm text-gray-300">
                    <input type="checkbox" name="is_active" value="1" checked>
                    Active
                </label>

                <button type="submit" class="w-full px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-lg transition-colors">
                    Create Plan
                </button>
            </form>
        @endif
    </div>

    <div class="xl:col-span-2 space-y-4">
        @foreach($plans as $plan)
            <form method="POST" action="{{ route('admin.plans.update', $plan) }}"
                  class="rounded-2xl bg-gray-900/60 backdrop-blur border border-gray-800 p-6 space-y-4">
                @csrf
                @method('PATCH')

                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-white">{{ $plan->name }}</h2>
                        <p class="text-xs text-gray-500">{{ $plan->code }}</p>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-300">
                        <input type="checkbox" name="is_active" value="1" {{ $plan->is_active ? 'checked' : '' }}>
                        Active
                    </label>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Name</label>
                        <input type="text" name="name" value="{{ $plan->name }}" required class="w-full bg-gray-800 border border-gray-700 text-gray-100 text-sm rounded-lg px-3 py-2">
                    </div>

                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Description</label>
                        <input type="text" name="description" value="{{ $plan->description }}" class="w-full bg-gray-800 border border-gray-700 text-gray-100 text-sm rounded-lg px-3 py-2">
                    </div>
                </div>

                <div>
                    <label class="block text-xs text-gray-500 mb-1">Included Quotas JSON</label>
                    <textarea name="included_quotas" rows="6" class="w-full bg-gray-800 border border-gray-700 text-gray-100 text-xs rounded-lg px-3 py-2 font-mono">{{ json_encode($plan->included_quotas ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</textarea>
                </div>

                <div>
                    <label class="block text-xs text-gray-500 mb-1">Feature Flags JSON</label>
                    <textarea name="feature_flags" rows="4" class="w-full bg-gray-800 border border-gray-700 text-gray-100 text-xs rounded-lg px-3 py-2 font-mono">{{ json_encode($plan->feature_flags ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</textarea>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-lg transition-colors">
                        Save Plan
                    </button>
                </div>
            </form>
        @endforeach
    </div>

</div>

</x-admin.layouts.admin>
