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
                'description' => 'Internal onboarding trial before a clinic is moved to a paid rollout plan.',
                'included_quotas' => [
                    'messages_received' => 250,
                    'messages_sent' => 250,
                    'llm_tokens_estimated' => 100000,
                    'reminders_sent' => 50,
                    'voice_minutes' => 0,
                    'staff_users' => 1,
                    'messaging_credit_usd' => 0,
                ],
                'feature_flags' => [
                    'whatsapp' => true,
                    'messenger' => true,
                    'voice_agent' => false,
                    'google_calendar' => false,
                    'google_sheets' => false,
                    'after_hours_logic' => false,
                    'transfer_rules' => false,
                    'advanced_reporting' => false,
                    'custom_intake_logic' => false,
                    'custom_prompt_tuning' => false,
                    'priority_support' => false,
                ],
            ],
            'starter' => [
                'name' => 'Launch',
                'description' => 'Sellable entry plan for solo clinics and small teams.',
                'included_quotas' => [
                    'messages_received' => 2000,
                    'messages_sent' => 2000,
                    'llm_tokens_estimated' => 350000,
                    'reminders_sent' => 400,
                    'voice_minutes' => 120,
                    'staff_users' => 2,
                    'messaging_credit_usd' => 10,
                ],
                'feature_flags' => [
                    'whatsapp' => true,
                    'messenger' => true,
                    'voice_agent' => true,
                    'google_calendar' => true,
                    'google_sheets' => true,
                    'after_hours_logic' => false,
                    'transfer_rules' => false,
                    'advanced_reporting' => false,
                    'custom_intake_logic' => false,
                    'custom_prompt_tuning' => false,
                    'priority_support' => false,
                ],
            ],
            'pro' => [
                'name' => 'Growth',
                'description' => 'Sellable plan for active clinics that need stronger automation and handoff logic.',
                'included_quotas' => [
                    'messages_received' => 5000,
                    'messages_sent' => 5000,
                    'llm_tokens_estimated' => 1000000,
                    'reminders_sent' => 1000,
                    'voice_minutes' => 300,
                    'staff_users' => 5,
                    'messaging_credit_usd' => 25,
                ],
                'feature_flags' => [
                    'whatsapp' => true,
                    'messenger' => true,
                    'voice_agent' => true,
                    'google_calendar' => true,
                    'google_sheets' => true,
                    'after_hours_logic' => true,
                    'transfer_rules' => true,
                    'advanced_reporting' => false,
                    'custom_intake_logic' => false,
                    'custom_prompt_tuning' => false,
                    'priority_support' => true,
                ],
            ],
            'enterprise' => [
                'name' => 'Pro',
                'description' => 'Sellable plan for busy clinics and multi-provider front-desk operations.',
                'included_quotas' => [
                    'messages_received' => 12000,
                    'messages_sent' => 12000,
                    'llm_tokens_estimated' => 2500000,
                    'reminders_sent' => 2500,
                    'voice_minutes' => 800,
                    'staff_users' => 15,
                    'messaging_credit_usd' => 60,
                ],
                'feature_flags' => [
                    'whatsapp' => true,
                    'messenger' => true,
                    'voice_agent' => true,
                    'google_calendar' => true,
                    'google_sheets' => true,
                    'after_hours_logic' => true,
                    'transfer_rules' => true,
                    'advanced_reporting' => true,
                    'custom_intake_logic' => true,
                    'custom_prompt_tuning' => true,
                    'priority_support' => true,
                    'multi_provider_calendar' => true,
                ],
            ],
        ];

        foreach ($plans as $code => $plan) {
            DB::table('plans')
                ->where('code', $code)
                ->update([
                    'name' => $plan['name'],
                    'description' => $plan['description'],
                    'included_quotas' => json_encode($plan['included_quotas'], JSON_THROW_ON_ERROR),
                    'feature_flags' => json_encode($plan['feature_flags'], JSON_THROW_ON_ERROR),
                    'is_active' => true,
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
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
            DB::table('plans')
                ->where('code', $code)
                ->update([
                    'name' => $plan['name'],
                    'description' => $plan['description'],
                    'included_quotas' => json_encode($plan['included_quotas'], JSON_THROW_ON_ERROR),
                    'feature_flags' => json_encode($plan['feature_flags'], JSON_THROW_ON_ERROR),
                    'updated_at' => $now,
                ]);
        }
    }
};
