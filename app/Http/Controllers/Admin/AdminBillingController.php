<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingDocument;
use App\Models\BillingPrice;
use App\Models\Business;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\BillingAccountService;
use App\Services\Billing\BillingDocumentService;
use App\Services\Billing\BillingLedgerService;
use App\Services\Billing\BillingLifecycleService;
use App\Services\Billing\BillingPaymentService;
use App\Services\Billing\BillingPriceCatalogService;
use App\Services\Billing\BillingSummaryService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminBillingController extends Controller
{
    public function index(): View
    {
        $businesses = Business::query()
            ->with(['billingAccount', 'subscription.billingPrice', 'subscription.plan'])
            ->withCount([
                'billingDocuments as open_invoices_count' => fn ($query) => $query->whereIn('status', ['issued', 'partial']),
            ])
            ->withSum([
                'billingDocuments as open_balance_minor' => fn ($query) => $query->whereIn('status', ['issued', 'partial']),
            ], 'amount_due_minor')
            ->orderBy('name')
            ->paginate(25);

        return view('admin.billing.index', [
            'businesses' => $businesses,
        ]);
    }

    public function show(Business $business, BillingSummaryService $billingSummaryService): View
    {
        $business->loadMissing([
            'subscription.plan',
            'subscription.billingPrice.metricRates',
            'subscription.billingPrice.creditPolicies',
            'billingAccount',
        ]);

        return view('admin.billing.show', [
            'business' => $business,
            'billingSummary' => $billingSummaryService->forAdminBusiness($business),
            'billingPrices' => BillingPrice::query()
                ->with('plan')
                ->orderBy('code')
                ->orderByDesc('version')
                ->get(),
        ]);
    }

    public function updateAccount(
        Request $request,
        Business $business,
        BillingAccountService $billingAccountService,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $validated = $request->validate([
            'provider_driver' => ['nullable', 'string', 'max:64'],
            'provider_account_ref' => ['nullable', 'string', 'max:191'],
            'currency' => ['required', 'string', 'size:3'],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'invoice_email' => ['nullable', 'email', 'max:255'],
            'collection_status' => ['required', 'string', 'max:32'],
            'default_payment_state' => ['required', 'string', 'max:32'],
            'portal_capable' => ['sometimes', 'boolean'],
            'provider_metadata' => ['nullable', 'string'],
        ]);

        $providerDriver = $this->emptyToNull($validated['provider_driver'] ?? null);
        $providerAccountRef = $this->emptyToNull($validated['provider_account_ref'] ?? null);

        if ($providerDriver !== null && $providerAccountRef !== null) {
            $duplicateExists = DB::table('billing_accounts')
                ->where('provider_driver', $providerDriver)
                ->where('provider_account_ref', $providerAccountRef)
                ->where('business_id', '!=', $business->id)
                ->exists();

            if ($duplicateExists) {
                throw ValidationException::withMessages([
                    'provider_account_ref' => 'That provider account reference is already assigned to another business.',
                ]);
            }
        }

        $account = $billingAccountService->ensure($business, [
            'provider_driver' => $providerDriver,
            'provider_account_ref' => $providerAccountRef,
            'currency' => strtoupper((string) $validated['currency']),
            'billing_email' => $this->emptyToNull($validated['billing_email'] ?? null),
            'invoice_email' => $this->emptyToNull($validated['invoice_email'] ?? null),
            'collection_status' => $validated['collection_status'],
            'default_payment_state' => $validated['default_payment_state'],
            'portal_capable' => $request->boolean('portal_capable'),
            'provider_metadata' => $this->decodeJsonObject($validated['provider_metadata'] ?? null, 'provider_metadata'),
        ]);

        $auditLogger->log(
            actor: $request->user(),
            action: 'billing.account_updated',
            subjectType: $account::class,
            subjectId: $account->id,
            payload: [
                'provider_driver' => $account->provider_driver,
                'portal_capable' => $account->portal_capable,
            ],
            request: $request,
            businessId: $business->id,
        );

        return redirect()->route('admin.billing.show', $business)
            ->with('success', 'Billing account updated.');
    }

    public function updateSubscription(
        Request $request,
        Business $business,
        BillingPriceCatalogService $billingPriceCatalogService,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $validated = $request->validate([
            'billing_price_id' => ['required', Rule::exists('billing_prices', 'id')],
            'billing_cycle_anchor_at' => ['nullable', 'date'],
            'next_invoice_at' => ['nullable', 'date'],
        ]);

        $billingPrice = BillingPrice::query()->findOrFail((int) $validated['billing_price_id']);
        $subscription = $billingPriceCatalogService->assignToBusiness(
            business: $business,
            billingPrice: $billingPrice,
            billingCycleAnchor: !empty($validated['billing_cycle_anchor_at']) ? CarbonImmutable::parse($validated['billing_cycle_anchor_at']) : null,
            nextInvoiceAt: !empty($validated['next_invoice_at']) ? CarbonImmutable::parse($validated['next_invoice_at']) : null,
        );

        $auditLogger->log(
            actor: $request->user(),
            action: 'billing.price_assigned',
            subjectType: $subscription::class,
            subjectId: $subscription->id,
            payload: [
                'billing_price_id' => $billingPrice->id,
                'billing_price_code' => $billingPrice->code,
                'billing_price_version' => $billingPrice->version,
            ],
            request: $request,
            businessId: $business->id,
        );

        return redirect()->route('admin.billing.show', $business)
            ->with('success', 'Billing price assigned to business subscription.');
    }

    public function runCycle(
        Request $request,
        Business $business,
        BillingDocumentService $billingDocumentService,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $subscription = $business->subscription;

        if ($subscription === null) {
            return back()->withErrors([
                'billing' => 'No business subscription exists to run billing for.',
            ]);
        }

        $document = $billingDocumentService->runCycle($subscription);

        $auditLogger->log(
            actor: $request->user(),
            action: 'billing.cycle_run',
            subjectType: $document::class,
            subjectId: $document->id,
            payload: [
                'document_key' => $document->document_key,
                'amount_due_minor' => $document->amount_due_minor,
            ],
            request: $request,
            businessId: $business->id,
        );

        return redirect()->route('admin.billing.show', $business)
            ->with('success', 'Billing cycle generated or refreshed.');
    }

    public function markPaid(
        Request $request,
        BillingDocument $billingDocument,
        BillingPaymentService $billingPaymentService,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $validated = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:191'],
        ]);

        $billingDocument = $billingPaymentService->markPaid(
            document: $billingDocument,
            amountMinor: isset($validated['amount']) ? $this->toMinorUnits((string) $validated['amount']) : null,
            reference: $this->emptyToNull($validated['reference'] ?? null),
        );

        $auditLogger->log(
            actor: $request->user(),
            action: 'billing.document_marked_paid',
            subjectType: $billingDocument::class,
            subjectId: $billingDocument->id,
            payload: [
                'amount_paid_minor' => $billingDocument->amount_paid_minor,
                'amount_due_minor' => $billingDocument->amount_due_minor,
                'reference' => $validated['reference'] ?? null,
            ],
            request: $request,
            businessId: $billingDocument->business_id,
        );

        return redirect()->route('admin.billing.show', $billingDocument->business_id)
            ->with('success', 'Billing document payment recorded.');
    }

    public function storeManualAdjustment(
        Request $request,
        Business $business,
        BillingLedgerService $billingLedgerService,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $validated = $request->validate([
            'entry_type' => ['required', Rule::in(['manual_credit', 'manual_debit'])],
            'balance_bucket' => ['required', 'string', 'max:64'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:255'],
            'billing_document_id' => ['nullable', Rule::exists('billing_documents', 'id')],
        ]);

        $subscription = $business->subscription;

        if ($subscription === null) {
            return back()->withErrors([
                'billing' => 'No business subscription exists for ledger adjustments.',
            ]);
        }

        $document = null;

        if (!empty($validated['billing_document_id'])) {
            $document = BillingDocument::query()
                ->where('business_id', $business->id)
                ->find($validated['billing_document_id']);
        }

        $entry = $billingLedgerService->manualAdjustment(
            subscription: $subscription,
            entryType: $validated['entry_type'],
            bucket: $validated['balance_bucket'],
            amountMinor: $this->toMinorUnits((string) $validated['amount']),
            reason: $validated['reason'],
            document: $document,
            entryKey: sprintf(
                'manual_adjustment:%d:%s:%s:%d:%s',
                $subscription->id,
                $validated['entry_type'],
                $validated['balance_bucket'],
                $this->toMinorUnits((string) $validated['amount']),
                sha1($validated['reason']),
            ),
        );

        $auditLogger->log(
            actor: $request->user(),
            action: 'billing.balance_manual_adjustment',
            subjectType: $entry::class,
            subjectId: $entry->id,
            payload: [
                'entry_type' => $entry->entry_type,
                'balance_bucket' => $entry->balance_bucket,
                'amount_minor' => $entry->amount_minor,
            ],
            request: $request,
            businessId: $business->id,
        );

        return redirect()->route('admin.billing.show', $business)
            ->with('success', 'Ledger adjustment applied.');
    }

    public function suspend(
        Request $request,
        Business $business,
        BillingLifecycleService $billingLifecycleService,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $validated = $request->validate([
            'confirm_suspend' => ['accepted'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $subscription = $business->subscription;

        if ($subscription === null) {
            return back()->withErrors([
                'billing' => 'No business subscription exists to suspend.',
            ]);
        }

        $subscription = $billingLifecycleService->suspend($subscription, trim((string) ($validated['reason'] ?? '')));

        $auditLogger->log(
            actor: $request->user(),
            action: 'billing.subscription_suspended',
            subjectType: $subscription::class,
            subjectId: $subscription->id,
            payload: [
                'reason' => $subscription->suspension_reason,
            ],
            request: $request,
            businessId: $business->id,
        );

        return redirect()->route('admin.billing.show', $business)
            ->with('success', 'Billing lifecycle suspended.');
    }

    public function reactivate(
        Request $request,
        Business $business,
        BillingLifecycleService $billingLifecycleService,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $request->validate([
            'confirm_reactivate' => ['accepted'],
        ]);

        $subscription = $business->subscription;

        if ($subscription === null) {
            return back()->withErrors([
                'billing' => 'No business subscription exists to reactivate.',
            ]);
        }

        $subscription = $billingLifecycleService->reactivate($subscription);

        $auditLogger->log(
            actor: $request->user(),
            action: 'billing.subscription_reactivated',
            subjectType: $subscription::class,
            subjectId: $subscription->id,
            payload: [
                'lifecycle_status' => $subscription->lifecycle_status,
            ],
            request: $request,
            businessId: $business->id,
        );

        return redirect()->route('admin.billing.show', $business)
            ->with('success', 'Billing lifecycle reactivated.');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonObject(?string $value, string $field): ?array
    {
        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        /** @var mixed $decoded */
        $decoded = json_decode($trimmed, true);

        if (!is_array($decoded)) {
            throw ValidationException::withMessages([
                $field => 'Must be valid JSON.',
            ]);
        }

        return $decoded;
    }

    private function toMinorUnits(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function emptyToNull(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
