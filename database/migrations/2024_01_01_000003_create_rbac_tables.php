<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration tạo các bảng cho hệ thống RBAC
 * Hỗ trợ 3 lớp quyền: system, custom, project
 */
class CreateRbacTables extends Migration
{
    /**
     * Chạy migration
     */
    public function up(): void
    {
        // Bảng roles - Quản lý các vai trò
        Schema::create('roles', function (Blueprint $table) {
            $table->ulid('id')->primary(); // Thay đổi từ id() sang ulid()
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
            $table->ulid('id')->primary(); // Thay đổi từ id() sang ulid()
            $table->string('code', 100)->unique()->index()->comment('Mã quyền dạng module.action');
            $table->string('module', 50)->index()->comment('Module/chức năng');
            $table->string('action', 50)->index()->comment('Hành động');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Bảng role_permissions - Liên kết role và permission
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->ulid('id')->primary(); // Thay đổi từ id() sang ulid()
            $table->foreignUlid('role_id')->constrained('roles')->onDelete('cascade'); // Thay đổi từ foreignId sang foreignUlid
            $table->string('permission_code', 100);
            $table->timestamps();
            
            // Foreign key constraint
            $table->foreign('permission_code')->references('code')->on('permissions')->onDelete('cascade');
            
            // Unique constraint
            $table->unique(['role_id', 'permission_code']);
        });

        // Bảng user_roles_system - Vai trò hệ thống
        Schema::create('user_roles_system', function (Blueprint $table) {
            $table->ulid('id')->primary(); // Thay đổi từ id() sang ulid()
            $table->foreignUlid('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignUlid('role_id')->constrained('roles')->onDelete('cascade'); // Thay đổi từ foreignId sang foreignUlid
            $table->timestamps();
            
            // Unique constraint
            $table->unique(['user_id', 'role_id']);
        });

        // Bảng user_roles_custom - Vai trò tùy chỉnh áp dụng trên nhiều dự án
        Schema::create('user_roles_custom', function (Blueprint $table) {
            $table->ulid('id')->primary(); // Thay đổi từ id() sang ulid()
            $table->foreignUlid('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignUlid('role_id')->constrained('roles')->onDelete('cascade'); // Thay đổi từ foreignId sang foreignUlid
            $table->timestamps();
            
            // Unique constraint
            $table->unique(['user_id', 'role_id']);
        });

        // Loại bỏ bảng user_roles_project - sẽ tạo trong migration riêng
    }

    /**
     * Rollback migration
     */
    public function down(): void
    {
        Schema::dropIfExists('user_roles_custom');
        Schema::dropIfExists('user_roles_system');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
}