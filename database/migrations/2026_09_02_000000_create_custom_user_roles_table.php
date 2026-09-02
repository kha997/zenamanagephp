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
     *
     * GAP-042 Gate-3 Round-1 Correction 7: no approved legacy
     * `custom_user_roles` production schema was ever found (§2b's finding
     * was precisely that no migration had ever created this table on any
     * environment) — there is nothing to silently preserve. Fail closed
     * instead: if a table with this name unexpectedly already exists
     * (e.g. a conflicting/unknown shape from some other source), let
     * Schema::create() throw rather than silently marking this migration
     * "applied" while leaving an unverified schema behind.
     */
    public function up(): void
    {
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
