<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BillingPrice;
use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBillingControlPlaneTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_configure_account_assign_price_and_run_cycle(): void
    {
        [$admin, $business, $billingPrice] = $this->seedAdminBillingContext();

        $this->actingAs($admin)
            ->patch(route('admin.billing.account.update', $business), [
                'provider_driver' => 'configured_portal',
                'provider_account_ref' => 'acct_123',
                'currency' => 'USD',
                'billing_email' => 'billing@example.com',
                'invoice_email' => 'billing@example.com',
                'collection_status' => 'active',
                'default_payment_state' => 'ready',
                'portal_capable' => '1',
                'provider_metadata' => json_encode(['portal_url' => 'https://billing.example.test/portal'], JSON_THROW_ON_ERROR),
            ])
            ->assertRedirect(route('admin.billing.show', $business));

        $this->actingAs($admin)
            ->patch(route('admin.billing.subscription.update', $business), [
                'billing_price_id' => $billingPrice->id,
                'billing_cycle_anchor_at' => now()->startOfMonth()->toDateTimeString(),
                'next_invoice_at' => now()->subMinute()->toDateTimeString(),
            ])
            ->assertRedirect(route('admin.billing.show', $business));

        $this->actingAs($admin)
            ->post(route('admin.billing.documents.run-cycle', $business))
            ->assertRedirect(route('admin.billing.show', $business));

        $this->assertDatabaseHas('billing_documents', [
            'business_id' => $business->id,
            'status' => 'issued',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'business_id' => $business->id,
            'action' => 'billing.cycle_run',
        ]);
    }

    public function test_super_admin_can_apply_manual_credit_mark_paid_and_suspend_or_reactivate(): void
    {
        [$admin, $business, $billingPrice] = $this->seedAdminBillingContext();

        $subscription = $business->subscription()->create([
            'plan_id' => $billingPrice->plan_id,
            'billing_price_id' => $billingPrice->id,
            'status' => 'starter',
            'lifecycle_status' => 'active',
            'warn_at_ratio' => 0.8,
            'enforce_limits' => false,
            'admin_override' => false,
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->endOfMonth(),
            'billing_cycle_anchor_at' => now()->startOfMonth(),
            'next_invoice_at' => now()->endOfMonth(),
        ]);

        $document = $business->billingDocuments()->create([
            'business_subscription_id' => $subscription->id,
            'type' => 'invoice',
            'status' => 'issued',
            'document_key' => 'invoice:test:manual',
            'currency' => 'USD',
            'subtotal_minor' => 5000,
            'total_minor' => 5000,
            'amount_due_minor' => 5000,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'issued_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.billing.ledger.adjustment.store', $business), [
                'entry_type' => 'manual_credit',
                'balance_bucket' => 'account_credit',
                'amount' => '25.00',
                'reason' => 'Support recovery credit',
                'billing_document_id' => $document->id,
            ])
            ->assertRedirect(route('admin.billing.show', $business));

        $this->assertDatabaseHas('billing_balance_entries', [
            'business_id' => $business->id,
            'entry_type' => 'manual_credit',
            'balance_bucket' => 'account_credit',
            'amount_minor' => 2500,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.billing.documents.mark-paid', $document), [
                'amount' => '50.00',
                'reference' => 'manual-payment-1',
            ])
            ->assertRedirect(route('admin.billing.show', $business));

        $this->assertDatabaseHas('billing_documents', [
            'id' => $document->id,
            'status' => 'paid',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.billing.suspend', $business), [
                'confirm_suspend' => '1',
                'reason' => 'Manual suspension',
            ])
            ->assertRedirect(route('admin.billing.show', $business));

        $this->assertDatabaseHas('business_subscriptions', [
            'id' => $subscription->id,
            'lifecycle_status' => 'suspended',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.billing.reactivate', $business), [
                'confirm_reactivate' => '1',
            ])
            ->assertRedirect(route('admin.billing.show', $business));

        $this->assertDatabaseHas('business_subscriptions', [
            'id' => $subscription->id,
            'lifecycle_status' => 'active',
        ]);
    }

    /**
     * @return array{0: User, 1: Business, 2: BillingPrice}
     */
    private function seedAdminBillingContext(): array
    {
        $plan = Plan::query()->firstOrCreate(
            ['code' => 'starter'],
            [
                'name' => 'Starter',
                'description' => 'Starter plan',
                'included_quotas' => ['messages_sent' => 100],
                'feature_flags' => ['voice_agent' => false],
                'is_active' => true,
            ],
        );

        $billingPrice = BillingPrice::query()->create([
            'plan_id' => $plan->id,
            'code' => 'starter-monthly',
            'version' => 1,
            'name' => 'Starter Monthly',
            'status' => 'active',
            'currency' => 'USD',
            'recurring_amount_minor' => 5000,
            'recurring_interval_unit' => 'month',
            'recurring_interval_count' => 1,
        ]);

        $business = Business::query()->create([
            'name' => 'Clinic',
            'business_type' => 'clinic',
            'slug' => 'clinic',
            'timezone' => 'UTC',
            'locale' => 'en',
            'channel_config' => [],
            'integration_config' => [],
            'reminder_settings' => [],
            'ai_config' => [],
            'is_active' => true,
            'plan' => 'starter',
        ]);

        $admin = User::query()->create([
            'business_id' => null,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        return [$admin, $business, $billingPrice];
    }
}
