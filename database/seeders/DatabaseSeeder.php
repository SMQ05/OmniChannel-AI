<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Business;
use App\Models\TeamInvite;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        // Super Admin (no business_id)
        User::create([
            'name'     => 'Super Admin',
            'email'    => 'admin@kynex.ai',
            'password' => Hash::make('ChangeMe123!'),
            'role'     => 'super_admin',
        ]);

        // Demo business
        $business = Business::create([
            'name'          => 'Demo Clinic',
            'business_type' => 'clinic',
            'slug'          => 'demo-clinic',
            'timezone'      => 'Asia/Karachi',
            'locale'        => 'en',
            'channel_config' => [
                'whatsapp'  => ['enabled' => false, 'phone_number_id' => '', 'access_token' => '', 'verify_token' => ''],
                'messenger' => ['enabled' => false, 'page_id' => '', 'access_token' => '', 'verify_token' => ''],
            ],
            'integration_config' => [
                'google_credentials' => ['client_id' => '', 'client_secret' => ''],
                'google_calendar' => ['enabled' => false, 'calendar_id' => '', 'token' => []],
                'google_sheets'   => ['enabled' => false, 'spreadsheet_id' => '', 'sheet_name' => 'Appointments', 'token' => []],
            ],
            'reminder_settings' => [
                'reminders' => [
                    ['offset_hours' => 24, 'label' => 'Day before',     'message_template' => 'Hi {patient_name}, reminder: your {service_type} with {provider_name} at {business_name} is tomorrow at {time}. Reply CANCEL to cancel.'],
                    ['offset_hours' => 2,  'label' => '2 hours before', 'message_template' => 'Hi {patient_name}, your appointment is in 2 hours at {time}. See you soon!'],
                ],
            ],
            'ai_config' => [
                'ai_name'      => 'Sara',
                'persona'      => 'You are warm, patient, and professional. Greet returning patients by name. Always confirm appointment details before finalizing.',
                'tone'         => 'friendly',
                'language'     => 'English',
                'llm_provider' => 'claude',
                'services'     => [
                    ['name' => 'General Consultation', 'duration_min' => 30, 'price' => 1500],
                    ['name' => 'Follow-up Visit',      'duration_min' => 15, 'price' => 800],
                ],
                'faqs' => [
                    ['q' => 'Do you accept walk-ins?',       'a' => 'We prefer appointments but walk-ins are welcome subject to availability.'],
                    ['q' => 'What is your cancellation policy?', 'a' => 'Please cancel at least 4 hours in advance to avoid a cancellation fee.'],
                ],
            ],
            'is_active' => true,
            'plan'      => 'trial',
        ]);

        // Business Owner for the demo clinic
        $owner = User::create([
            'name'        => 'Demo Owner',
            'email'       => 'owner@demo-clinic.com',
            'password'    => Hash::make('ChangeMe123!'),
            'role'        => 'business_owner',
            'business_id' => $business->id,
        ]);

        User::create([
            'name' => 'Demo Manager',
            'email' => 'manager@demo-clinic.com',
            'password' => Hash::make('ChangeMe123!'),
            'role' => 'manager',
            'business_id' => $business->id,
        ]);

        User::create([
            'name' => 'Demo Reception',
            'email' => 'reception@demo-clinic.com',
            'password' => Hash::make('ChangeMe123!'),
            'role' => 'receptionist',
            'business_id' => $business->id,
        ]);

        TeamInvite::query()->create([
            'business_id' => $business->id,
            'invited_by_user_id' => $owner->id,
            'email' => 'newhire@demo-clinic.com',
            'role' => 'staff',
            'token_hash' => hash('sha256', Str::random(64)),
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
            'meta' => ['seeded' => true],
        ]);
    }
}
