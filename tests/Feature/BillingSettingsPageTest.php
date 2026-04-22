<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BillingAccount;
use App\Models\BillingDocument;
use App\Models\BillingPrice;
use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_owner_can_view_billing_page_and_launch_portal(): void
    {
        [$business, $subscription] = $this->seedBillingBusiness();

        BillingAccount::query()->create([
            'business_id' => $business->id,
            'provider_driver' => 'configured_portal',
            'provider_account_ref' => 'acct_123',
            'currency' => 'USD',
            'billing_email' => 'billing@example.com',
            'invoice_email' => 'billing@example.com',
            'collection_status' => 'active',
            'default_payment_state' => 'ready',
            'portal_capable' => true,
            'provider_metadata' => [
                'portal_url' => 'https://billing.example.test/portal?return_url={return_url}&account_ref={account_ref}',
            ],
        ]);

        BillingDocument::query()->create([
            'business_id' => $business->id,
            'business_subscription_id' => $subscription->id,
            'type' => 'invoice',
            'status' => 'issued',
            'document_key' => 'invoice:test:1',
            'currency' => 'USD',
            'subtotal_minor' => 9900,
            'total_minor' => 9900,
            'amount_due_minor' => 9900,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'issued_at' => now(),
        ]);

        $user = User::query()->create([
            'business_id' => $business->id,
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
            'role' => 'business_owner',
        ]);

        $response = $this->actingAs($user)->get(route('settings.billing'));

        $response->assertOk();
        $response->assertSee('Billing Visibility');
        $response->assertSee('Legacy compatibility state only');
        $response->assertSee('invoice:test:1');

        $this->actingAs($user)
            ->post(route('settings.billing.portal'))
            ->assertRedirect(
                'https://billing.example.test/portal?return_url=' . urlencode(route('settings.billing')) . '&account_ref=acct_123',
            );
    }

    /**
     * @return array{0: Business, 1: BusinessSubscription}
     */
    private function seedBillingBusiness(): array
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
            'recurring_amount_minor' => 9900,
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

        $subscription = BusinessSubscription::query()->create([
            'business_id' => $business->id,
            'plan_id' => $plan->id,
            'billing_price_id' => $billingPrice->id,
            'status' => 'starter',
            'lifecycle_status' => 'active',
            'price_snapshot' => ['billing_price_code' => 'starter-monthly', 'billing_price_version' => 1],
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
