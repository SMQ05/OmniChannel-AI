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

class AdminBillingPriceCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_update_unattached_billing_price_in_place(): void
    {
        [$admin, $plan, $billingPrice] = $this->seedCatalogContext();

        $this->actingAs($admin)
            ->patch(route('admin.billing.prices.update', $billingPrice), [
                'plan_id' => $plan->id,
                'code' => 'starter-monthly',
                'name' => 'Starter Monthly Updated',
                'description' => 'Updated commercial description',
                'status' => 'active',
                'currency' => 'USD',
                'recurring_amount' => '59.00',
                'recurring_interval_unit' => 'month',
                'recurring_interval_count' => 1,
                'setup_fee_amount' => '10.00',
                'setup_fee_behavior' => 'invoice_once',
                'trial_days' => 7,
                'provider_driver' => '',
                'provider_sellable_ref' => '',
                'provider_variant_ref' => '',
                'provider_metadata' => '{}',
                'metric_rates' => json_encode([[
                    'metric' => 'messages_sent',
                    'currency' => 'USD',
                    'billable_unit' => 'message',
                    'unit_size' => 1,
                    'aggregation_strategy' => 'sum_quantity',
                    'rounding_mode' => 'none',
                    'pricing_model' => 'per_unit',
                    'unit_amount_minor' => 2,
                    'balance_bucket' => 'messaging_credit',
                    'is_active' => true,
                ]], JSON_THROW_ON_ERROR),
                'credit_policies' => json_encode([[
                    'code' => 'messaging-credit',
                    'balance_bucket' => 'messaging_credit',
                    'currency' => 'USD',
                    'amount_minor' => 1000,
                    'grant_cadence' => 'per_billing_cycle',
                    'expires_with_period' => true,
                    'carry_forward' => false,
                    'is_active' => true,
                ]], JSON_THROW_ON_ERROR),
            ])
            ->assertRedirect(route('admin.billing.prices.index'));

        $this->assertDatabaseHas('billing_prices', [
            'id' => $billingPrice->id,
            'name' => 'Starter Monthly Updated',
            'recurring_amount_minor' => 5900,
        ]);
        $this->assertSame(1, BillingPrice::query()->where('code', 'starter-monthly')->count());
    }

    public function test_updating_attached_billing_price_versions_it(): void
    {
        [$admin, $plan, $billingPrice] = $this->seedCatalogContext();

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

        BusinessSubscription::query()->create([
            'business_id' => $business->id,
            'plan_id' => $plan->id,
            'billing_price_id' => $billingPrice->id,
            'status' => 'starter',
            'lifecycle_status' => 'active',
            'warn_at_ratio' => 0.8,
            'enforce_limits' => false,
            'admin_override' => false,
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->endOfMonth(),
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.billing.prices.update', $billingPrice), [
                'plan_id' => $plan->id,
                'code' => 'starter-monthly',
                'name' => 'Starter Monthly v2',
                'description' => 'Versioned update',
                'status' => 'active',
                'currency' => 'USD',
                'recurring_amount' => '69.00',
                'recurring_interval_unit' => 'month',
                'recurring_interval_count' => 1,
                'setup_fee_amount' => '',
                'setup_fee_behavior' => 'invoice_once',
                'trial_days' => '',
                'provider_driver' => '',
                'provider_sellable_ref' => '',
                'provider_variant_ref' => '',
                'provider_metadata' => '{}',
                'metric_rates' => '[]',
                'credit_policies' => '[]',
            ])
            ->assertRedirect(route('admin.billing.prices.index'));

        $this->assertSame(2, BillingPrice::query()->where('code', 'starter-monthly')->count());
        $this->assertDatabaseHas('billing_prices', [
            'code' => 'starter-monthly',
            'version' => 2,
            'name' => 'Starter Monthly v2',
            'recurring_amount_minor' => 6900,
        ]);
    }

    /**
     * @return array{0: User, 1: Plan, 2: BillingPrice}
     */
    private function seedCatalogContext(): array
    {
        $admin = User::query()->create([
            'business_id' => null,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

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
            'recurring_amount_minor' => 4900,
            'recurring_interval_unit' => 'month',
            'recurring_interval_count' => 1,
        ]);

        return [$admin, $plan, $billingPrice];
    }
}
