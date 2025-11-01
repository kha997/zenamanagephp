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
                if (!Schema::hasColumn('templates', 'status')) {
                    $table->enum('status', ['draft', 'active', 'archived', 'deprecated'])->default('draft')->after('is_active');
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
            $table->dropColumn('status');
        });
    }
};