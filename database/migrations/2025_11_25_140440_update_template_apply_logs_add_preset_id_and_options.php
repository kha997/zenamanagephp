<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Round 98: Add preset_id and options to template_apply_logs
     */
    public function up(): void
    {
        Schema::table('template_apply_logs', function (Blueprint $table) {
            $table->string('preset_id', 26)->nullable()->after('preset_code');
            $table->json('options')->nullable()->after('preset_id');
            
            // Add index for preset_id for query performance
            $table->index('preset_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('template_apply_logs', function (Blueprint $table) {
            $table->dropIndex(['preset_id']);
            $table->dropColumn(['preset_id', 'options']);
        });
    }
};
