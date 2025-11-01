<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add data retention configuration table
        Schema::create('data_retention_policies', function (Blueprint $table) {
            $table->id();
            $table->string('table_name');
            $table->string('retention_period'); // e.g., '90 days', '1 year', 'permanent'
            $table->string('retention_type'); // e.g., 'soft_delete', 'hard_delete', 'archive'
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->unique('table_name');
            $table->index(['is_active', 'retention_type']);
        });
        
        // Insert default retention policies
        DB::table('data_retention_policies')->insert([
            [
                'table_name' => 'audit_logs',
                'retention_period' => '2 years',
                'retention_type' => 'soft_delete',
                'is_active' => true,
                'description' => 'Audit logs are soft deleted after 2 years',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'table_name' => 'project_activities',
                'retention_period' => '1 year',
                'retention_type' => 'soft_delete',
                'is_active' => true,
                'description' => 'Project activities are soft deleted after 1 year',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'table_name' => 'query_logs',
                'retention_period' => '30 days',
                'retention_type' => 'hard_delete',
                'is_active' => true,
                'description' => 'Query logs are permanently deleted after 30 days',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'table_name' => 'notifications',
                'retention_period' => '90 days',
                'retention_type' => 'soft_delete',
                'is_active' => true,
                'description' => 'Notifications are soft deleted after 90 days',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_retention_policies');
    }
};