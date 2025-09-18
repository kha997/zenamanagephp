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
        Schema::table('zena_notifications', function (Blueprint $table) {
            $table->boolean('is_read')->default(false)->after('message');
            
            // Add index
            $table->index(['is_read']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zena_notifications', function (Blueprint $table) {
            // Drop index first
            $table->dropIndex(['is_read']);
            
            // Drop column
            $table->dropColumn('is_read');
        });
    }
};