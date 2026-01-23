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
        if (! Schema::hasTable('change_requests')) {
            return;
        }

        Schema::table('change_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('change_requests', 'created_by')) {
                $table->ulid('created_by')->nullable();
                $table->index('created_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('change_requests')) {
            return;
        }

        Schema::table('change_requests', function (Blueprint $table) {
            if (Schema::hasColumn('change_requests', 'created_by')) {
                $table->dropIndex(['created_by']);
                $table->dropColumn('created_by');
            }
        });
    }
};
