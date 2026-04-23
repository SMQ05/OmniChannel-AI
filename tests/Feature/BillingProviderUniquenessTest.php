<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BillingAccount;
use App\Models\BillingDocument;
use App\Models\BillingTransaction;
use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingProviderUniquenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_account_reference_cannot_be_reused_across_businesses(): void
    {
        $admin = User::query()->create([
            'business_id' => null,
            'name' => 'Admin',
            'email' => 'billing-uniqueness@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $businessA = $this->makeBusiness('a');
        $businessB = $this->makeBusiness('b');

        BillingAccount::query()->create([
            'business_id' => $businessA->id,
            'provider_driver' => 'configured_portal',
            'provider_account_ref' => 'acct_shared',
            'currency' => 'USD',
            'collection_status' => 'active',
            'default_payment_state' => 'ready',
            'portal_capable' => true,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.billing.show', $businessB))
            ->patch(route('admin.billing.account.update', $businessB), [
                'provider_driver' => 'configured_portal',
                'provider_account_ref' => 'acct_shared',
                'currency' => 'USD',
                'collection_status' => 'active',
                'default_payment_state' => 'ready',
                'portal_capable' => '1',
            ])
            ->assertRedirect(route('admin.billing.show', $businessB))
            ->assertSessionHasErrors('provider_account_ref');
    }

    public function test_provider_document_reference_is_unique_across_businesses(): void
    {
        $businessA = $this->makeBusiness('doc-a');
        $businessB = $this->makeBusiness('doc-b');

        $documentA = BillingDocument::query()->create([
            'business_id' => $businessA->id,
            'type' => 'invoice',
            'status' => 'issued',
            'document_key' => 'invoice:doc-a',
            'currency' => 'USD',
            'total_minor' => 1000,
            'amount_due_minor' => 1000,
            'provider_driver' => 'configured_portal',
            'provider_document_ref' => 'inv_shared',
        ]);

        $this->assertNotNull($documentA->id);

        $this->expectException(QueryException::class);

        BillingDocument::query()->create([
            'business_id' => $businessB->id,
            'type' => 'invoice',
            'status' => 'issued',
            'document_key' => 'invoice:doc-b',
            'currency' => 'USD',
            'total_minor' => 1000,
            'amount_due_minor' => 1000,
            'provider_driver' => 'configured_portal',
            'provider_document_ref' => 'inv_shared',
        ]);
    }

    public function test_provider_transaction_reference_is_unique_across_businesses(): void
    {
        [$businessA, $subscriptionA] = $this->makeBusinessWithSubscription('tx-a');
        [$businessB, $subscriptionB] = $this->makeBusinessWithSubscription('tx-b');

        $transactionA = BillingTransaction::query()->create([
            'business_id' => $businessA->id,
            'business_subscription_id' => $subscriptionA->id,
            'type' => 'payment',
            'status' => 'settled',
            'direction' => 'inbound',
            'currency' => 'USD',
            'amount_minor' => 1000,
            'provider_driver' => 'configured_portal',
            'provider_transaction_ref' => 'tx_shared',
        ]);

        $this->assertNotNull($transactionA->id);

        $this->expectException(QueryException::class);

        BillingTransaction::query()->create([
            'business_id' => $businessB->id,
            'business_subscription_id' => $subscriptionB->id,
            'type' => 'payment',
            'status' => 'settled',
            'direction' => 'inbound',
            'currency' => 'USD',
            'amount_minor' => 1000,
            'provider_driver' => 'configured_portal',
            'provider_transaction_ref' => 'tx_shared',
        ]);
    }

    private function makeBusiness(string $suffix): Business
    {
        return Business::query()->create([
            'name' => 'Billing ' . $suffix,
            'business_type' => 'clinic',
            'slug' => 'billing-' . $suffix,
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
    }

    /**
     * @return array{0: Business, 1: BusinessSubscription}
     */
    private function makeBusinessWithSubscription(string $suffix): array
    {
        $plan = Plan::query()->create([
            'code' => 'starter-' . $suffix,
            'name' => 'Starter ' . $suffix,
            'description' => 'Starter plan',
            'included_quotas' => ['messages_sent' => 100],
            'feature_flags' => ['voice_agent' => false],
            'is_active' => true,
        ]);

        $business = $this->makeBusiness($suffix);
        $subscription = BusinessSubscription::query()->create([
            'business_id' => $business->id,
            'plan_id' => $plan->id,
            'status' => $plan->code,
            'lifecycle_status' => 'active',
            'warn_at_ratio' => 0.8,
            'enforce_limits' => false,
            'admin_override' => false,
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->endOfMonth(),
        ]);

        return [$business, $subscription];
    }
}
