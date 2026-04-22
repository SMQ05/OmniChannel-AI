<x-admin.layouts.admin title="Billing">
<div class="space-y-6">
    <div class="panel p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-white">Billing Control Plane</h2>
                <p class="mt-1 text-sm text-gray-400">Commercial pricing, billing accounts, billing documents, ledger truth, and lifecycle controls live here. The current architecture is still split; this page does not pretend messaging, voice, webhook, and runtime control are unified.</p>
            </div>
            <a href="{{ route('admin.billing.prices.index') }}" class="btn-primary px-4 py-2">Pricing Catalog</a>
        </div>
    </div>

    <div class="panel overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-800 text-left text-xs uppercase tracking-wide text-gray-500">
                    <th class="px-5 py-3">Business</th>
                    <th class="px-5 py-3">Lifecycle</th>
                    <th class="px-5 py-3">Billing Price</th>
                    <th class="px-5 py-3">Account</th>
                    <th class="px-5 py-3">Open Invoices</th>
                    <th class="px-5 py-3">Open Balance</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-800">
                @foreach($businesses as $business)
                    <tr>
                        <td class="px-5 py-4">
                            <div class="font-medium text-white">{{ $business->name }}</div>
                            <div class="text-xs text-gray-500">{{ $business->slug }}</div>
                        </td>
                        <td class="px-5 py-4">{{ ucfirst(str_replace('_', ' ', $business->subscription?->lifecycle_status ?? 'unconfigured')) }}</td>
                        <td class="px-5 py-4">
                            {{ $business->subscription?->billingPrice?->name ?? 'Not assigned' }}
                        </td>
                        <td class="px-5 py-4">
                            {{ $business->billingAccount?->provider_driver ?? 'Not configured' }}
                        </td>
                        <td class="px-5 py-4">{{ number_format((int) ($business->open_invoices_count ?? 0)) }}</td>
                        <td class="px-5 py-4">${{ number_format(((int) ($business->open_balance_minor ?? 0)) / 100, 2) }}</td>
                        <td class="px-5 py-4 text-right">
                            <a href="{{ route('admin.billing.show', $business) }}" class="text-sm font-medium text-indigo-300 hover:text-indigo-200">Open Billing →</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{ $businesses->links() }}
</div>
</x-admin.layouts.admin>
