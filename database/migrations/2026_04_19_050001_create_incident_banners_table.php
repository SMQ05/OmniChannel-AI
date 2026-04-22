<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_banners', function (Blueprint $table): void {
            $table->id();

            // Title and message
            $table->string('title', 120);
            $table->text('message');

            // Severity levels
            $table->string('severity', 16)->default('info'); // info | warning | critical

            // Scope: platform-wide vs business-specific
            $table->boolean('is_platform_wide')->default(true);
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();

            // Display window
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();

            // Status
            $table->string('status', 16)->default('draft'); // draft | published | archived | resolved

            // Admin tracking
            $table->foreignId('published_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('archived_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('archived_at')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('resolved_at')->nullable();

            $table->timestampsTz();

            // Indexes for efficient querying
            $table->index(['status', 'is_platform_wide'], 'incident_banners_status_platform_index');
            $table->index(['business_id', 'status'], 'incident_banners_business_status_index');
            $table->index(['starts_at', 'ends_at'], 'incident_banners_date_range_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_banners');
    }
};
