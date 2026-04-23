<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BillingAccount;
use App\Models\BillingPrice;
use App\Models\BillingTransaction;
use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\Plan;
use App\Services\Billing\BillingDocumentService;
use App\Services\Billing\BillingLifecycleService;
use App\Services\Billing\BillingPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingLifecycleVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_from_documents_reconciles_cancel_at_period_end_subscription_to_expired(): void
    {
        [$business, $subscription] = $this->seedBillingBusiness('expired-check');

        $subscription->forceFill([
            'lifecycle_status' => 'active',
            'cancel_at_period_end' => true,
            'current_period_end' => now()->subDay(),
        ])->save();

        app(BillingLifecycleService::class)->refreshFromDocuments($subscription->fresh());

        $this->assertDatabaseHas('business_subscriptions', [
            'id' => $subscription->id,
            'lifecycle_status' => 'expired',
        ]);
        $this->assertDatabaseHas('billing_accounts', [
            'business_id' => $business->id,
            'collection_status' => 'active',
        ]);
    }

    public function test_manual_payment_is_idempotent_and_clears_past_due_status(): void
    {
        [$business, $subscription] = $this->seedBillingBusiness('payment-idempotency');

        $document = $business->billingDocuments()->create([
            'billing_account_id' => $business->billingAccount->id,
            'business_subscription_id' => $subscription->id,
            'type' => 'invoice',
            'status' => 'issued',
            'document_key' => 'invoice:payment-idempotency',
            'currency' => 'USD',
            'subtotal_minor' => 5000,
            'total_minor' => 5000,
            'amount_due_minor' => 5000,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'issued_at' => now()->subDays(10),
            'due_at' => now()->subDay(),
        ]);

        app(BillingLifecycleService::class)->refreshFromDocuments($subscription);

        $this->assertDatabaseHas('business_subscriptions', [
            'id' => $subscription->id,
            'lifecycle_status' => 'past_due',
        ]);

        $paymentService = app(BillingPaymentService::class);
        $paymentService->markPaid($document, 5000, 'provider-payment-1');
        $paymentService->markPaid($document->fresh(), 5000, 'provider-payment-1');

        $document->refresh();

        $this->assertSame('paid', $document->status);
        $this->assertSame(5000, (int) $document->amount_paid_minor);
        $this->assertSame(0, (int) $document->amount_due_minor);
        $this->assertSame(1, BillingTransaction::query()->where('provider_transaction_ref', 'provider-payment-1')->count());
        $this->assertDatabaseHas('business_subscriptions', [
            'id' => $subscription->id,
            'lifecycle_status' => 'active',
        ]);
        $this->assertDatabaseHas('billing_accounts', [
            'business_id' => $business->id,
            'collection_status' => 'active',
        ]);
    }

    public function test_billing_cycle_is_idempotent_for_same_due_period(): void
    {
        [, $subscription] = $this->seedBillingBusiness('cycle-idempotency');

        $documentService = app(BillingDocumentService::class);

        $first = $documentService->runCycle($subscription);
        $second = $documentService->runCycle($subscription->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $subscription->business->billingDocuments()->count());
        $this->assertSame(
            $first->lines()->count(),
            $second->fresh('lines')->lines->count(),
        );
    }

    /**
     * @return array{0: Business, 1: BusinessSubscription}
     */
    private function seedBillingBusiness(string $suffix): array
    {
        $plan = Plan::query()->create([
            'code' => 'starter-' . $suffix,
            'name' => 'Starter ' . $suffix,
            'description' => 'Starter plan',
            'included_quotas' => ['messages_sent' => 100],
            'feature_flags' => ['voice_agent' => false],
            'is_active' => true,
        ]);

        $billingPrice = BillingPrice::query()->create([
            'plan_id' => $plan->id,
            'code' => 'starter-monthly-' . $suffix,
            'version' => 1,
            'name' => 'Starter Monthly ' . $suffix,
            'status' => 'active',
            'currency' => 'USD',
            'recurring_amount_minor' => 5000,
            'recurring_interval_unit' => 'month',
            'recurring_interval_count' => 1,
        ]);

        $business = Business::query()->create([
            'name' => 'Clinic ' . $suffix,
            'business_type' => 'clinic',
            'slug' => 'clinic-' . $suffix,
            'timezone' => 'UTC',
            'locale' => 'en',
            'channel_config' => [],
            'integration_config' => [],
            'integration_secrets' => [],
            'reminder_settings' => [],
            'ai_config' => [],
            'operations_config' => [],
            'is_active' => true,
            'plan' => 'starter',
        ]);

        BillingAccount::query()->create([
            'business_id' => $business->id,
            'provider_driver' => 'configured_portal',
            'provider_account_ref' => 'acct-' . $suffix,
            'currency' => 'USD',
            'collection_status' => 'active',
            'default_payment_state' => 'ready',
            'portal_capable' => true,
        ]);

        $subscription = BusinessSubscription::query()->create([
            'business_id' => $business->id,
            'plan_id' => $plan->id,
            'billing_price_id' => $billingPrice->id,
            'status' => $plan->code,
            'lifecycle_status' => 'active',
            'warn_at_ratio' => 0.8,
            'enforce_limits' => false,
            'admin_override' => false,
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->endOfMonth(),
            'billing_cycle_anchor_at' => now()->startOfMonth(),
            'next_invoice_at' => now()->endOfMonth(),
        ]);

        return [$business, $subscription];
    }
}
