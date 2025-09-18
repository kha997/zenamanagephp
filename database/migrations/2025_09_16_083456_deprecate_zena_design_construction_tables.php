<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add deprecation notice to zena_documents table
        if (Schema::hasTable('zena_documents')) {
            Schema::table('zena_documents', function (Blueprint $table) {
                $table->string('deprecated_notice')->nullable()->after('id');
            });
            
            // Add deprecation notice to all existing records
            DB::table('zena_documents')->update([
                'deprecated_notice' => 'This table is deprecated. Use documents table instead.'
            ]);
        }

        // Add deprecation notice to zena_components table
        if (Schema::hasTable('zena_components')) {
            Schema::table('zena_components', function (Blueprint $table) {
                $table->string('deprecated_notice')->nullable()->after('id');
            });
            
            // Add deprecation notice to all existing records
            DB::table('zena_components')->update([
                'deprecated_notice' => 'This table is deprecated. Use components table instead.'
            ]);
        }

        // Add deprecation notice to zena_projects table
        if (Schema::hasTable('zena_projects')) {
            Schema::table('zena_projects', function (Blueprint $table) {
                $table->string('deprecated_notice')->nullable()->after('id');
            });
            
            // Add deprecation notice to all existing records
            DB::table('zena_projects')->update([
                'deprecated_notice' => 'This table is deprecated. Use projects table instead.'
            ]);
        }

        // Add deprecation notice to zena_drawings table
        if (Schema::hasTable('zena_drawings')) {
            Schema::table('zena_drawings', function (Blueprint $table) {
                $table->string('deprecated_notice')->nullable()->after('id');
            });
            
            // Add deprecation notice to all existing records
            DB::table('zena_drawings')->update([
                'deprecated_notice' => 'This table is deprecated. Use documents table with type=drawing instead.'
            ]);
        }

        // Add deprecation notice to zena_rfis table
        if (Schema::hasTable('zena_rfis')) {
            Schema::table('zena_rfis', function (Blueprint $table) {
                $table->string('deprecated_notice')->nullable()->after('id');
            });
            
            // Add deprecation notice to all existing records
            DB::table('zena_rfis')->update([
                'deprecated_notice' => 'This table is deprecated. Use unified RFI model instead.'
            ]);
        }

        // Add deprecation notice to zena_submittals table
        if (Schema::hasTable('zena_submittals')) {
            Schema::table('zena_submittals', function (Blueprint $table) {
                $table->string('deprecated_notice')->nullable()->after('id');
            });
            
            // Add deprecation notice to all existing records
            DB::table('zena_submittals')->update([
                'deprecated_notice' => 'This table is deprecated. Use unified Submittal model instead.'
            ]);
        }

        // Add deprecation notice to zena_change_requests table
        if (Schema::hasTable('zena_change_requests')) {
            Schema::table('zena_change_requests', function (Blueprint $table) {
                $table->string('deprecated_notice')->nullable()->after('id');
            });
            
            // Add deprecation notice to all existing records
            DB::table('zena_change_requests')->update([
                'deprecated_notice' => 'This table is deprecated. Use unified ChangeRequest model instead.'
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove deprecation notices
        $tables = [
            'zena_documents',
            'zena_components', 
            'zena_projects',
            'zena_drawings',
            'zena_rfis',
            'zena_submittals',
            'zena_change_requests'
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropColumn('deprecated_notice');
                });
            }
        }
    }
};