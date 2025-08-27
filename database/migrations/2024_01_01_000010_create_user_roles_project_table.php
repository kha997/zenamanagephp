<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration tạo bảng user_roles_project
 * Phải chạy sau khi bảng projects đã được tạo
 */
class CreateUserRolesProjectTable extends Migration
{
    /**
     * Chạy migration
     */
    public function up(): void
    {
        // Bảng user_roles_project - Vai trò cụ thể cho từng dự án
        Schema::create('user_roles_project', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignUlid('role_id')->constrained('roles')->onDelete('cascade');
            $table->foreignUlid('project_id')->constrained('projects')->onDelete('cascade');
            $table->timestamps();
            
            // Unique constraint
            $table->unique(['user_id', 'role_id', 'project_id']);
        });
    }

    /**
     * Rollback migration
     */
    public function down(): void
    {
        Schema::dropIfExists('user_roles_project');
    }
}