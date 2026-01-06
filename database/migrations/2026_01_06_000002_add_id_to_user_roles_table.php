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
        if (!Schema::hasTable('user_roles') || Schema::hasColumn('user_roles', 'id')) {
            return;
        }

        Schema::table('user_roles', function (Blueprint $table) {
            $table->ulid('id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('user_roles') || !Schema::hasColumn('user_roles', 'id')) {
            return;
        }

        Schema::table('user_roles', function (Blueprint $table) {
            $table->dropColumn('id');
        });
    }
};
