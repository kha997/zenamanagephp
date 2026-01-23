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
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
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
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

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
