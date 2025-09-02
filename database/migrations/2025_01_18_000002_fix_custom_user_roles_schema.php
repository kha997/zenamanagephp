<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Migration sửa lỗi schema custom_user_roles
 * Thêm cột id ULID primary key
 */
class FixCustomUserRolesSchema extends Migration
{
    /**
     * Chạy migration
     */
    public function up(): void
    {
        // Kiểm tra xem bảng có tồn tại không
        if (!Schema::hasTable('custom_user_roles')) {
            // Nếu chưa có, tạo bảng với cấu trúc đúng
            Schema::create('custom_user_roles', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignUlid('role_id')->constrained('roles')->onDelete('cascade');
                $table->timestamps();
                
                $table->unique(['user_id', 'role_id']);
            });
            return;
        }

        // Nếu đã có bảng, kiểm tra xem có cột id chưa
        if (Schema::hasColumn('custom_user_roles', 'id')) {
            return; // Đã có cột id, không cần làm gì
        }

        // Tạo bảng tạm để backup dữ liệu
        Schema::create('custom_user_roles_backup', function (Blueprint $table) {
            $table->foreignUlid('user_id');
            $table->foreignUlid('role_id');
            $table->timestamps();
        });

        // Backup dữ liệu hiện tại
        DB::statement('INSERT INTO custom_user_roles_backup SELECT * FROM custom_user_roles');

        // Xóa bảng cũ
        Schema::dropIfExists('custom_user_roles');

        // Tạo lại bảng với cấu trúc đúng
        Schema::create('custom_user_roles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignUlid('role_id')->constrained('roles')->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['user_id', 'role_id']);
        });

        // Migrate dữ liệu từ backup với ULID mới
        $backupData = DB::table('custom_user_roles_backup')->get();
        foreach ($backupData as $row) {
            DB::table('custom_user_roles')->insert([
                'id' => Str::ulid(),
                'user_id' => $row->user_id,
                'role_id' => $row->role_id,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        // Xóa bảng backup
        Schema::dropIfExists('custom_user_roles_backup');
    }

    /**
     * Rollback migration
     */
    public function down(): void
    {
        if (!Schema::hasTable('custom_user_roles')) {
            return;
        }

        // Tạo bảng tạm để backup dữ liệu
        Schema::create('custom_user_roles_backup', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id');
            $table->foreignUlid('role_id');
            $table->timestamps();
        });

        // Backup dữ liệu hiện tại
        DB::statement('INSERT INTO custom_user_roles_backup SELECT * FROM custom_user_roles');

        // Xóa bảng hiện tại
        Schema::dropIfExists('custom_user_roles');

        // Tạo lại bảng không có cột id (composite primary key)
        Schema::create('custom_user_roles', function (Blueprint $table) {
            $table->foreignUlid('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignUlid('role_id')->constrained('roles')->onDelete('cascade');
            $table->timestamps();
            
            $table->primary(['user_id', 'role_id']);
        });

        // Migrate dữ liệu từ backup (bỏ cột id)
        DB::statement('
            INSERT INTO custom_user_roles (user_id, role_id, created_at, updated_at)
            SELECT user_id, role_id, created_at, updated_at
            FROM custom_user_roles_backup
        ');

        // Xóa bảng backup
        Schema::dropIfExists('custom_user_roles_backup');
    }
}