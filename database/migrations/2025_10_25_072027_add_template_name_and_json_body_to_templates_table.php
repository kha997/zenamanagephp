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
        if (Schema::hasTable('templates')) {
            Schema::table('templates', function (Blueprint $table) {
                // Add template_name column if it doesn't exist
                if (!Schema::hasColumn('templates', 'template_name')) {
                    $table->string('template_name')->nullable()->after('name');
                }
                
                // Add json_body column if it doesn't exist
                if (!Schema::hasColumn('templates', 'json_body')) {
                    $table->json('json_body')->nullable()->after('structure');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            if (Schema::hasColumn('templates', 'template_name')) {
                $table->dropColumn('template_name');
            }
            
            if (Schema::hasColumn('templates', 'json_body')) {
                $table->dropColumn('json_body');
            }
        });
    }
};