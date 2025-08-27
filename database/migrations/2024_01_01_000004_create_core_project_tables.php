<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration tạo các bảng cho Core Project Structure
 * Bao gồm: projects, components, work_templates, tasks
 */
class CreateCoreProjectTables extends Migration
{
    /**
     * Chạy migration
     */
    public function up(): void
    {
        // Bảng projects - Quản lý dự án
        Schema::create('projects', function (Blueprint $table) {
            $table->ulid('id')->primary(); // Sử dụng ULID làm khóa chính
            $table->foreignUlid('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('name', 255)->index();
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->enum('status', ['planning', 'active', 'on_hold', 'completed', 'cancelled'])->default('planning')->index();
            $table->decimal('progress', 5, 2)->default(0)->comment('Tiến độ % (0-100)');
            $table->decimal('planned_cost', 15, 2)->default(0)->comment('Chi phí dự kiến');
            $table->decimal('actual_cost', 15, 2)->default(0)->comment('Chi phí thực tế');
            $table->json('tags')->nullable()->comment('Tags đa cấp');
            $table->enum('visibility', ['internal', 'client'])->default('internal');
            $table->boolean('client_approved')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['tenant_id', 'status']);
            $table->index(['start_date', 'end_date']);
        });

        // Bảng components - Quản lý thành phần dự án (có thể lồng nhau)
        Schema::create('components', function (Blueprint $table) {
            $table->ulid('id')->primary(); // Sử dụng ULID làm khóa chính
            $table->foreignUlid('project_id')->constrained('projects')->onDelete('cascade');
            $table->string('parent_component_id', 26)->nullable()->comment('Component cha (cho nested structure)');
            $table->string('name', 255)->index();
            $table->text('description')->nullable();
            $table->decimal('progress_percent', 5, 2)->default(0)->comment('Tiến độ % (0-100)');
            $table->decimal('planned_cost', 15, 2)->default(0)->comment('Chi phí dự kiến');
            $table->decimal('actual_cost', 15, 2)->default(0)->comment('Chi phí thực tế');
            $table->json('tags')->nullable()->comment('Tags đa cấp');
            $table->enum('visibility', ['internal', 'client'])->default('internal');
            $table->boolean('client_approved')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['project_id', 'parent_component_id']);
            $table->index(['progress_percent']);
        });

        // Thêm khóa ngoại self-referencing sau khi tạo bảng
        Schema::table('components', function (Blueprint $table) {
            $table->foreign('parent_component_id')->references('id')->on('components')->onDelete('cascade');
        });

        // Bảng work_templates - Mẫu công việc
        Schema::create('work_templates', function (Blueprint $table) {
            $table->ulid('id')->primary(); // Sử dụng ULID làm khóa chính
            $table->string('name', 255)->index();
            $table->text('description')->nullable();
            $table->enum('category', ['design', 'construction', 'qc', 'inspection'])->index();
            $table->json('template_data')->comment('Dữ liệu template (tasks, dependencies, etc.)');
            $table->integer('version')->default(1)->comment('Phiên bản template');
            $table->boolean('is_active')->default(true)->index();
            $table->json('tags')->nullable()->comment('Tags đa cấp');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['category', 'is_active']);
            $table->index(['name', 'version']);
        });

        // Bảng tasks - Quản lý công việc
        Schema::create('tasks', function (Blueprint $table) {
            $table->ulid('id')->primary(); // Sử dụng ULID làm khóa chính
            $table->foreignUlid('project_id')->constrained('projects')->onDelete('cascade');
            $table->foreignUlid('component_id')->nullable()->constrained('components')->onDelete('set null')->comment('Component liên quan');
            $table->unsignedBigInteger('phase_id')->nullable()->comment('Phase liên quan');
            $table->string('name', 255)->index();
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled', 'on_hold'])->default('pending')->index();
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium')->index();
            $table->json('dependencies')->nullable()->comment('Mảng task_ids phụ thuộc');
            $table->string('conditional_tag', 100)->nullable()->comment('Tag điều kiện để hiển thị task');
            $table->boolean('is_hidden')->default(false)->index()->comment('Ẩn task nếu conditional_tag không active');
            $table->decimal('estimated_hours', 8, 2)->default(0)->comment('Số giờ ước tính');
            $table->decimal('actual_hours', 8, 2)->default(0)->comment('Số giờ thực tế');
            $table->decimal('progress_percent', 5, 2)->default(0)->comment('Tiến độ % (0-100)');
            $table->json('tags')->nullable()->comment('Tags đa cấp');
            $table->enum('visibility', ['internal', 'client'])->default('internal');
            $table->boolean('client_approved')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['project_id', 'status']);
            $table->index(['start_date', 'end_date']);
            $table->index(['component_id', 'phase_id']);
            $table->index(['conditional_tag', 'is_hidden']);
        });

        // Bảng task_assignments - Phân công task cho user
        Schema::create('task_assignments', function (Blueprint $table) {
            $table->id(); // Giữ nguyên ID tự tăng cho bảng pivot
            $table->foreignUlid('task_id')->constrained('tasks')->onDelete('cascade');
            $table->unsignedBigInteger('user_id')->index();
            $table->decimal('split_percentage', 5, 2)->default(100)->comment('Phần trăm phân chia công việc');
            $table->enum('role', ['assignee', 'reviewer', 'observer'])->default('assignee');
            $table->timestamps();
            
            // Unique constraint
            $table->unique(['task_id', 'user_id']);
            
            // Indexes
            $table->index(['user_id', 'role']);
        });
    }

    /**
     * Rollback migration
     */
    public function down(): void
    {
        Schema::dropIfExists('task_assignments');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('work_templates');
        Schema::dropIfExists('components');
        Schema::dropIfExists('projects');
    }
}