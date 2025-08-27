<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Migration sửa lỗi schema role_permissions
 * Chuyển từ permission_code (string) sang permission_id (ULID)
 */
class FixRolePermissionsSchema extends Migration
{
    /**
     * Chạy migration
     */
    public function up(): void
    {
        // Tạo bảng tạm để backup dữ liệu
        Schema::create('role_permissions_backup', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('role_id');
            $table->string('permission_code', 100);
            $table->timestamps();
        });

        // Backup dữ liệu hiện tại
        DB::statement('INSERT INTO role_permissions_backup SELECT * FROM role_permissions');

        // Xóa bảng cũ
        Schema::dropIfExists('role_permissions');

        // Tạo lại bảng với cấu trúc đúng
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('role_id')->constrained('roles')->onDelete('cascade');
            $table->foreignUlid('permission_id')->constrained('permissions')->onDelete('cascade');
            $table->boolean('allow_override')->default(false)->comment('Cho phép ghi đè quyền cụ thể');
            $table->timestamps();
            
            // Unique constraint
            $table->unique(['role_id', 'permission_id']);
        });

        // Migrate dữ liệu từ backup với JOIN để lấy permission_id
        DB::statement('
            INSERT INTO role_permissions (id, role_id, permission_id, allow_override, created_at, updated_at)
            SELECT 
                rp.id,
                rp.role_id,
                p.id as permission_id,
                false as allow_override,
                rp.created_at,
                rp.updated_at
            FROM role_permissions_backup rp
            INNER JOIN permissions p ON p.code = rp.permission_code
        ');

        // Xóa bảng backup
        Schema::dropIfExists('role_permissions_backup');
    }

    /**
     * Rollback migration
     */
    public function down(): void
    {
        // Tạo bảng tạm để backup dữ liệu
        Schema::create('role_permissions_backup', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('role_id');
            $table->foreignUlid('permission_id');
            $table->boolean('allow_override')->default(false);
            $table->timestamps();
        });

        // Backup dữ liệu hiện tại
        DB::statement('INSERT INTO role_permissions_backup SELECT * FROM role_permissions');

        // Xóa bảng hiện tại
        Schema::dropIfExists('role_permissions');

        // Tạo lại bảng với cấu trúc cũ
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('role_id')->constrained('roles')->onDelete('cascade');
            $table->string('permission_code', 100);
            $table->timestamps();
            
            $table->foreign('permission_code')->references('code')->on('permissions')->onDelete('cascade');
            $table->unique(['role_id', 'permission_code']);
        });

        // Migrate dữ liệu từ backup với JOIN để lấy permission_code
        DB::statement('
            INSERT INTO role_permissions (id, role_id, permission_code, created_at, updated_at)
            SELECT 
                rp.id,
                rp.role_id,
                p.code as permission_code,
                rp.created_at,
                rp.updated_at
            FROM role_permissions_backup rp
            INNER JOIN permissions p ON p.id = rp.permission_id
        ');

        // Xóa bảng backup
        Schema::dropIfExists('role_permissions_backup');
    }
}