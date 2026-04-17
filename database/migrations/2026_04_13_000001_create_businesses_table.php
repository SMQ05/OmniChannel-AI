<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the businesses table — the tenant root record.
 *
 * Every tenant-owned row in the system carries a business_id FK
 * back to this table. The slug is used as the public webhook URL
 * segment and must therefore be globally unique.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table): void {
            $table->id();
            $table->string('name');

            $table->enum('business_type', [
                'clinic',
                'salon',
                'barbershop',
                'law_firm',
                'physio',
                'dental',
                'spa',
                'other',
            ]);

            $table->string('slug')->unique();
            $table->string('timezone')->default('UTC');
            $table->string('locale')->default('en');

            /**
             * Messaging channel credentials.
             * Shape: { whatsapp: { enabled, phone_number_id, access_token, verify_token },
             *          messenger: { enabled, page_id, access_token, verify_token } }
             */
            $table->jsonb('channel_config')->nullable();

            /**
             * Third-party integration credentials.
             * Shape: { google_calendar: { enabled, calendar_id, credentials, token },
             *          google_sheets: { enabled, spreadsheet_id, sheet_name, credentials, token } }
             */
            $table->jsonb('integration_config')->nullable();

            /**
             * Reminder rules array.
             * Shape: { reminders: [{ offset_hours, label, message_template }] }
             */
            $table->jsonb('reminder_settings')->nullable();

            /**
             * AI persona & configuration.
             * Shape: { ai_name, persona, tone, language, llm_provider, services[], faqs[] }
             */
            $table->jsonb('ai_config')->nullable();

            $table->boolean('is_active')->default(true);

            $table->enum('plan', [
                'trial',
                'starter',
                'pro',
                'enterprise',
            ])->default('trial');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
