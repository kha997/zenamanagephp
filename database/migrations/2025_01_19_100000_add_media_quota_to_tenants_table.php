<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds media quota columns to tenants table for storage management.
     */
    public function up(): void
    {
        if (Schema::hasTable('tenants')) {
            Schema::table('tenants', function (Blueprint $table) {
                if (!Schema::hasColumn('tenants', 'media_quota_mb')) {
                    $table->decimal('media_quota_mb', 10, 2)
                        ->default(10240)
                        ->after('preferences')
                        ->comment('Media storage quota in MB (default: 10GB)');
                }
                
                if (!Schema::hasColumn('tenants', 'media_used_mb')) {
                    $table->decimal('media_used_mb', 10, 2)
                        ->default(0)
                        ->after('media_quota_mb')
                        ->comment('Media storage used in MB');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('tenants')) {
            Schema::table('tenants', function (Blueprint $table) {
                if (Schema::hasColumn('tenants', 'media_used_mb')) {
                    $table->dropColumn('media_used_mb');
                }
                
                if (Schema::hasColumn('tenants', 'media_quota_mb')) {
                    $table->dropColumn('media_quota_mb');
                }
            });
        }
    }
};

