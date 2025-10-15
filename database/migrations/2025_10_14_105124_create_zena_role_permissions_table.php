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
        if (!Schema::hasTable('zena_role_permissions')) {
            Schema::create('zena_role_permissions', function (Blueprint $table) {
                $table->ulid('role_id');
                $table->ulid('permission_id');
                $table->timestamps();

                $table->primary(['role_id', 'permission_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zena_role_permissions');
    }
};