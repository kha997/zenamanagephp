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
        if (Schema::hasTable('system_user_roles')) {
            return;
        }

        Schema::create('system_user_roles', function (Blueprint $table) {
            $table->ulid('user_id');
            $table->ulid('role_id');
            $table->timestamps();
            $table->softDeletes();

            $table->primary(['user_id', 'role_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_user_roles');
    }
};
