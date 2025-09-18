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
        Schema::table('task_dependencies', function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->after('id');
            
            // Add foreign key constraint
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            
            // Add index
            $table->index(['tenant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_dependencies', function (Blueprint $table) {
            // Drop foreign key and index first
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id']);
            
            // Drop column
            $table->dropColumn('tenant_id');
        });
    }
};