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
        // Z.E.N.A Permissions table
        Schema::create('zena_permissions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code')->unique();
            $table->string('module');
            $table->string('action');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Z.E.N.A Roles table
        Schema::create('zena_roles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name')->unique();
            $table->string('scope')->default('system');
            $table->boolean('allow_override')->default(false);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Z.E.N.A Role permissions pivot table
        Schema::create('zena_role_permissions', function (Blueprint $table) {
            $table->ulid('role_id');
            $table->ulid('permission_id');
            $table->boolean('allow_override')->default(false);
            $table->timestamps();

            $table->foreign('role_id')->references('id')->on('zena_roles')->onDelete('cascade');
            $table->foreign('permission_id')->references('id')->on('zena_permissions')->onDelete('cascade');
            $table->primary(['role_id', 'permission_id']);
        });

        // Z.E.N.A User roles pivot table
        Schema::create('zena_user_roles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user_id');
            $table->ulid('role_id');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('zena_roles')->onDelete('cascade');
            $table->unique(['user_id', 'role_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zena_user_roles');
        Schema::dropIfExists('zena_role_permissions');
        Schema::dropIfExists('zena_roles');
        Schema::dropIfExists('zena_permissions');
    }
};
