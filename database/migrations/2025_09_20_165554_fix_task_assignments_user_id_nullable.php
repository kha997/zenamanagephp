<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite không hỗ trợ drop foreign keys / change column được dùng trong migration này.
            return;
        }
        Schema::table('task_assignments', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('task_assignments', function (Blueprint $table) {
            $table->char('user_id', 26)->nullable()->change();
        });

        Schema::table('task_assignments', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_assignments', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('task_assignments', function (Blueprint $table) {
            $table->char('user_id', 26)->nullable(false)->change();
        });

        Schema::table('task_assignments', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
