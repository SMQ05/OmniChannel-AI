<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $plans = [
            'trial' => [
                'name' => 'Trial',
                'description' => 'Default trial plan',
                'included_quotas' => [
                    'messages_received' => 500,
                    'messages_sent' => 500,
                    'llm_tokens_estimated' => 100000,
                    'reminders_sent' => 100,
                    'voice_minutes' => 0,
                ],
                'feature_flags' => [
                    'whatsapp' => true,
                    'messenger' => true,
                    'voice_agent' => false,
                ],
            ],
            'starter' => [
                'name' => 'Starter',
                'description' => 'Starter plan for growing clinics',
                'included_quotas' => [
                    'messages_received' => 2000,
                    'messages_sent' => 2000,
                    'llm_tokens_estimated' => 350000,
                    'reminders_sent' => 400,
                    'voice_minutes' => 0,
                ],
                'feature_flags' => [
                    'whatsapp' => true,
                    'messenger' => true,
                    'voice_agent' => false,
                ],
            ],
            'pro' => [
                'name' => 'Pro',
                'description' => 'Production SaaS plan',
                'included_quotas' => [
                    'messages_received' => 5000,
                    'messages_sent' => 5000,
                    'llm_tokens_estimated' => 1000000,
                    'reminders_sent' => 1000,
                    'voice_minutes' => 300,
                ],
                'feature_flags' => [
                    'whatsapp' => true,
                    'messenger' => true,
                    'voice_agent' => true,
                ],
            ],
            'enterprise' => [
                'name' => 'Enterprise',
                'description' => 'Enterprise plan with elevated quotas and voice access',
                'included_quotas' => [
                    'messages_received' => 25000,
                    'messages_sent' => 25000,
                    'llm_tokens_estimated' => 5000000,
                    'reminders_sent' => 5000,
                    'voice_minutes' => 2000,
                ],
                'feature_flags' => [
                    'whatsapp' => true,
                    'messenger' => true,
                    'voice_agent' => true,
                ],
            ],
        ];

        foreach ($plans as $code => $plan) {
            $existingId = DB::table('plans')->where('code', $code)->value('id');

            if ($existingId === null) {
                DB::table('plans')->insert([
                    'code' => $code,
                    'name' => $plan['name'],
                    'description' => $plan['description'],
                    'included_quotas' => json_encode($plan['included_quotas'], JSON_THROW_ON_ERROR),
                    'feature_flags' => json_encode($plan['feature_flags'], JSON_THROW_ON_ERROR),
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $planIds = DB::table('plans')->pluck('id', 'code');

        $businesses = DB::table('businesses')->select('id', 'plan')->get();

        foreach ($businesses as $business) {
            $existingSubscription = DB::table('business_subscriptions')
                ->where('business_id', $business->id)
                ->exists();

            if ($existingSubscription) {
                continue;
            }

            $planCode = $planIds->has($business->plan) ? $business->plan : 'trial';

            DB::table('business_subscriptions')->insert([
                'business_id' => $business->id,
                'plan_id' => $planIds[$planCode] ?? $planIds['trial'],
                'status' => $planCode,
                'included_quotas' => null,
                'overage_counters' => json_encode([], JSON_THROW_ON_ERROR),
                'feature_flags' => null,
                'warn_at_ratio' => 0.80,
                'enforce_limits' => false,
                'admin_override' => false,
                'current_period_start' => $now->copy()->startOfMonth(),
                'current_period_end' => $now->copy()->endOfMonth(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('business_subscriptions')
            ->whereIn('status', ['trial', 'starter', 'pro', 'enterprise'])
            ->delete();

        DB::table('plans')
            ->whereIn('code', ['trial', 'starter', 'pro', 'enterprise'])
            ->delete();
    }
};
