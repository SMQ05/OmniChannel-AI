<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allow manually-created patients that have no messaging platform identity.
 * The unique constraint is dropped and recreated as a partial index
 * so it only applies when platform_user_id is set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table): void {
            // Drop the old unique constraint
            $table->dropUnique(['business_id', 'platform_user_id', 'platform']);

            $table->string('platform_user_id')->nullable()->change();
            $table->string('platform')->nullable()->change();
        });

        Schema::table('patients', function (Blueprint $table): void {
            $table->unique(['business_id', 'platform_user_id', 'platform'], 'patients_platform_identity_unique');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table): void {
            $table->dropUnique('patients_platform_identity_unique');
        });

        Schema::table('patients', function (Blueprint $table): void {
            $table->string('platform_user_id')->nullable(false)->change();
            $table->string('platform')->nullable(false)->change();
            $table->unique(['business_id', 'platform_user_id', 'platform']);
        });
    }
};
