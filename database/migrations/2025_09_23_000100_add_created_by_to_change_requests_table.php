<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('change_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('change_requests', 'created_by')) {
                $table->string('created_by', 26)->nullable()->after('requested_by');
                $table->index(['created_by', 'created_at']);
                $table->foreign('created_by')
                      ->references('id')
                      ->on('users')
                      ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('change_requests', function (Blueprint $table) {
            if (Schema::hasColumn('change_requests', 'created_by')) {
                $table->dropForeign(['created_by']);
                $table->dropIndex(['created_by', 'created_at']);
                $table->dropColumn('created_by');
            }
        });
    }
};
