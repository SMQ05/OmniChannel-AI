<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BillingPrice;
use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingCycleCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_cycle_command_can_run_due_cycles_inline(): void
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
            'billing_cycle_anchor_at' => now()->startOfMonth(),
            'next_invoice_at' => now()->subHour(),
        ]);

        $this->artisan('billing:run-cycles', ['--sync' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('billing_documents', [
            'business_id' => $business->id,
            'status' => 'issued',
        ]);
    }
}
