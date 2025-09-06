<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Migration tạo các bảng RBAC system
 * Bao gồm roles, permissions, role_permissions, system_user_roles, project_user_roles
 */
return new class extends Migration
{
    /**
     * Chạy migration
     */
    public function up(): void
    {
        // Bảng roles - Quản lý các vai trò
        Schema::create('roles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 100)->index();
            $table->enum('scope', ['system', 'custom', 'project'])->index();
            $table->boolean('allow_override')->default(false)->comment('Cho phép ghi đè quyền');
            $table->text('description')->nullable();
            $table->timestamps();
            
            // Unique constraint cho name + scope
            $table->unique(['name', 'scope']);
        });

        // Bảng permissions - Quản lý các quyền hạn
        Schema::create('permissions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 100)->unique()->index()->comment('Mã quyền: task.create, project.view');
            $table->string('module', 50)->index()->comment('Module: task, project, user');
            $table->string('action', 50)->index()->comment('Hành động: create, read, update, delete');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Bảng role_permissions - Liên kết roles và permissions
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('role_id')->index();
            $table->ulid('permission_id')->index();
            $table->boolean('allow_override')->default(false)->comment('Cho phép ghi đè quyền này');
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
            
            // Unique constraint
            $table->unique(['role_id', 'permission_id']);
        });

        // Bảng system_user_roles - Vai trò system-wide của users
        Schema::create('system_user_roles', function (Blueprint $table) {
            $table->ulid('user_id')->index();
            $table->ulid('role_id')->index();
            $table->timestamps();
            
            // Primary key composite
            $table->primary(['user_id', 'role_id']);
            
            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
        });

        // Bảng project_user_roles - Vai trò specific cho từng project
        Schema::create('project_user_roles', function (Blueprint $table) {
            $table->ulid('project_id')->index();
            $table->ulid('user_id')->index();
            $table->ulid('role_id')->index();
            $table->timestamps();
            
            // Primary key composite
            $table->primary(['project_id', 'user_id', 'role_id']);
            
            // Foreign keys sẽ được thêm sau khi có bảng projects
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
        });
    }

    /**
     * Rollback migration
     */
    public function down(): void
    {
        Schema::dropIfExists('project_user_roles');
        Schema::dropIfExists('system_user_roles');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
