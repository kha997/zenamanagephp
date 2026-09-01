<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * GAP-042 §2b: the custom-role assignment layer (Src\RBAC\Models\UserRoleCustom /
     * App\Models\UserRoleCustom) has always pointed at this table name, but no
     * migration for it ever existed on any environment. Mirrors the
     * project_user_roles sibling convention (plain nullable deleted_at column,
     * single ulid primary key — not the composite [user_id, role_id] key
     * system_user_roles uses).
     */
    public function up(): void
    {
        if (Schema::hasTable('custom_user_roles')) {
            return;
        }

        Schema::create('custom_user_roles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user_id');
            $table->ulid('role_id');
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();

            $table->index(['user_id']);
            $table->index(['role_id']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_user_roles');
    }
};
