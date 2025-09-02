<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Chuyển đổi cột user_id từ unsignedBigInteger sang ULID
     */
    public function up(): void
    {
        // Tạm tắt kiểm tra FK để tránh lỗi trong dev
        Schema::disableForeignKeyConstraints();
        
        // Xóa dữ liệu cũ nếu có (vì không thể convert)
        DB::table('task_assignments')->truncate();
        
        // 1) Drop FK cho user_id nếu tồn tại - sử dụng query information_schema
        if (DB::getDriverName() !== 'sqlite') {
            $userIdFk = DB::selectOne("
                SELECT CONSTRAINT_NAME AS name
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'task_assignments'
                  AND COLUMN_NAME = 'user_id'
                  AND REFERENCED_TABLE_NAME IS NOT NULL
                LIMIT 1
            ");
            
            if ($userIdFk && isset($userIdFk->name)) {
                Schema::table('task_assignments', function (Blueprint $table) use ($userIdFk) {
                    $table->dropForeign($userIdFk->name);
                });
            }
            
            // Drop FK cho task_id nếu tồn tại
            $taskIdFk = DB::selectOne("
                SELECT CONSTRAINT_NAME AS name
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'task_assignments'
                  AND COLUMN_NAME = 'task_id'
                  AND REFERENCED_TABLE_NAME IS NOT NULL
                LIMIT 1
            ");
            
            if ($taskIdFk && isset($taskIdFk->name)) {
                Schema::table('task_assignments', function (Blueprint $table) use ($taskIdFk) {
                    $table->dropForeign($taskIdFk->name);
                });
            }
        }
        
        // 2) Drop UNIQUE index nếu tồn tại
        if (DB::getDriverName() !== 'sqlite') {
            $uniqueIdx = DB::selectOne("
                SELECT INDEX_NAME AS name
                FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'task_assignments'
                  AND INDEX_NAME = 'task_assignments_task_id_user_id_unique'
                LIMIT 1
            ");
            
            if ($uniqueIdx) {
                Schema::table('task_assignments', function (Blueprint $table) {
                    $table->dropUnique('task_assignments_task_id_user_id_unique');
                });
            }
        }
        
        // 3) Drop các index khác nếu tồn tại
        Schema::table('task_assignments', function (Blueprint $table) {
            try {
                $table->dropIndex(['user_id', 'role']);
            } catch (Exception $e) {
                // Ignore if index doesn't exist
            }
        });
        
        // 4) Thay đổi kiểu cột user_id
        Schema::table('task_assignments', function (Blueprint $table) {
            $table->string('user_id', 26)->change();
        });
        
        // 5) Tạo lại unique constraints và FK
        Schema::table('task_assignments', function (Blueprint $table) {
            $table->unique(['task_id', 'user_id'], 'task_assignments_task_id_user_id_unique');
            $table->index(['user_id', 'role']);
            
            // Tạo FK constraints - tham chiếu đến bảng đúng
            $table->foreign('task_id')->references('id')->on('tasks')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
        
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Rollback migration
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        
        // Xóa dữ liệu
        DB::table('task_assignments')->truncate();
        
        // Drop FK và constraints hiện tại
        if (DB::getDriverName() !== 'sqlite') {
            $userIdFk = DB::selectOne("
                SELECT CONSTRAINT_NAME AS name
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'task_assignments'
                  AND COLUMN_NAME = 'user_id'
                  AND REFERENCED_TABLE_NAME IS NOT NULL
                LIMIT 1
            ");
            
            if ($userIdFk && isset($userIdFk->name)) {
                Schema::table('task_assignments', function (Blueprint $table) use ($userIdFk) {
                    $table->dropForeign($userIdFk->name);
                });
            }
            
            $taskIdFk = DB::selectOne("
                SELECT CONSTRAINT_NAME AS name
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'task_assignments'
                  AND COLUMN_NAME = 'task_id'
                  AND REFERENCED_TABLE_NAME IS NOT NULL
                LIMIT 1
            ");
            
            if ($taskIdFk && isset($taskIdFk->name)) {
                Schema::table('task_assignments', function (Blueprint $table) use ($taskIdFk) {
                    $table->dropForeign($taskIdFk->name);
                });
            }
        }
        
        Schema::table('task_assignments', function (Blueprint $table) {
            try {
                $table->dropIndex(['user_id', 'role']);
                $table->dropUnique(['task_id', 'user_id']);
            } catch (Exception $e) {
                // Ignore if constraints don't exist
            }
        });
        
        Schema::table('task_assignments', function (Blueprint $table) {
            // Đổi lại kiểu cột về unsignedBigInteger
            $table->unsignedBigInteger('user_id')->change();
            
            // Tạo lại indexes cũ
            $table->unique(['task_id', 'user_id']);
            $table->index(['user_id', 'role']);
        });
        
        Schema::enableForeignKeyConstraints();
    }
};