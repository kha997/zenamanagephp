<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Chuyển đổi bảng projects và các bảng liên quan sang sử dụng ULID thay vì auto-increment ID
 * Bao gồm: projects, components, work_templates, tasks, task_assignments
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Chuyển đổi bảng projects
        $this->convertProjectsToUlid();
        
        // 2. Chuyển đổi bảng components
        $this->convertComponentsToUlid();
        
        // 3. Chuyển đổi bảng work_templates
        $this->convertWorkTemplatesToUlid();
        
        // 4. Chuyển đổi bảng tasks
        $this->convertTasksToUlid();
        
        // 5. Chuyển đổi bảng task_assignments
        $this->convertTaskAssignmentsToUlid();
    }

    /**
     * Chuyển đổi bảng projects sang ULID
     */
    private function convertProjectsToUlid(): void
    {
        // Tạo bảng projects mới với ULID
        Schema::create('projects_new', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id'); // Foreign key đến tenants
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
            $table->ulid('created_by')->nullable();
            $table->ulid('updated_by')->nullable();
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            
            // Indexes
            $table->index(['tenant_id', 'status']);
            $table->index(['start_date', 'end_date']);
            
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
        
        // Copy dữ liệu từ bảng cũ (sử dụng ULID có sẵn hoặc tạo mới)
        if (Schema::hasTable('projects')) {
            DB::statement('
                INSERT INTO projects_new (id, tenant_id, name, description, start_date, end_date, status, progress, planned_cost, actual_cost, tags, visibility, client_approved, created_by, updated_by, created_at, updated_at)
                SELECT 
                    COALESCE(ulid, LOWER(CONCAT(
                        SUBSTR(HEX(RANDOM_BYTES(5)), 1, 10),
                        SUBSTR(HEX(RANDOM_BYTES(5)), 1, 4),
                        SUBSTR(HEX(RANDOM_BYTES(5)), 1, 4),
                        SUBSTR(HEX(RANDOM_BYTES(5)), 1, 4),
                        SUBSTR(HEX(RANDOM_BYTES(5)), 1, 12)
                    ))) as id,
                    (SELECT t.id FROM tenants t WHERE t.id = CAST(projects.tenant_id AS CHAR) LIMIT 1) as tenant_id,
                    name, description, start_date, end_date, status, progress, planned_cost, actual_cost, tags, visibility, client_approved,
                    (SELECT u.id FROM users u WHERE u.id = CAST(projects.created_by AS CHAR) LIMIT 1) as created_by,
                    (SELECT u.id FROM users u WHERE u.id = CAST(projects.updated_by AS CHAR) LIMIT 1) as updated_by,
                    created_at, updated_at
                FROM projects
            ');
        }
        
        Schema::dropIfExists('projects');
        Schema::rename('projects_new', 'projects');
    }

    /**
     * Chuyển đổi bảng components sang ULID
     */
    private function convertComponentsToUlid(): void
    {
        Schema::create('components_new', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('project_id');
            $table->ulid('parent_component_id')->nullable();
            $table->string('name', 255)->index();
            $table->text('description')->nullable();
            $table->decimal('progress_percent', 5, 2)->default(0);
            $table->decimal('planned_cost', 15, 2)->default(0);
            $table->decimal('actual_cost', 15, 2)->default(0);
            $table->json('tags')->nullable();
            $table->enum('visibility', ['internal', 'client'])->default('internal');
            $table->boolean('client_approved')->default(false);
            $table->ulid('created_by')->nullable();
            $table->ulid('updated_by')->nullable();
            $table->timestamps();
            
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('parent_component_id')->references('id')->on('components_new')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['project_id', 'parent_component_id']);
            $table->index(['progress_percent']);
            
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
        
        if (Schema::hasTable('components')) {
            DB::statement('
                INSERT INTO components_new (id, project_id, parent_component_id, name, description, progress_percent, planned_cost, actual_cost, tags, visibility, client_approved, created_by, updated_by, created_at, updated_at)
                SELECT 
                    COALESCE(ulid, LOWER(CONCAT(
                        SUBSTR(HEX(RANDOM_BYTES(5)), 1, 10),
                        SUBSTR(HEX(RANDOM_BYTES(5)), 1, 4),
                        SUBSTR(HEX(RANDOM_BYTES(5)), 1, 4),
                        SUBSTR(HEX(RANDOM_BYTES(5)), 1, 4),
                        SUBSTR(HEX(RANDOM_BYTES(5)), 1, 12)
                    ))) as id,
                    (SELECT p.id FROM projects p WHERE p.id = (SELECT pp.id FROM projects_old pp WHERE pp.id = components.project_id LIMIT 1) LIMIT 1) as project_id,
                    NULL as parent_component_id, -- Sẽ update sau
                    name, description, progress_percent, planned_cost, actual_cost, tags, visibility, client_approved,
                    (SELECT u.id FROM users u WHERE u.id = CAST(components.created_by AS CHAR) LIMIT 1) as created_by,
                    (SELECT u.id FROM users u WHERE u.id = CAST(components.updated_by AS CHAR) LIMIT 1) as updated_by,
                    created_at, updated_at
                FROM components
            ');
        }
        
        Schema::dropIfExists('components');
        Schema::rename('components_new', 'components');
    }

    /**
     * Chuyển đổi bảng work_templates sang ULID
     */
    private function convertWorkTemplatesToUlid(): void
    {
        Schema::create('work_templates_new', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 255)->index();
            $table->text('description')->nullable();
            $table->enum('category', ['design', 'construction', 'qc', 'inspection'])->index();
            $table->json('template_data');
            $table->integer('version')->default(1);
            $table->boolean('is_active')->default(true)->index();
            $table->json('tags')->nullable();
            $table->ulid('created_by')->nullable();
            $table->ulid('updated_by')->nullable();
            $table->timestamps();
            
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['category', 'is_active']);
            $table->index(['name', 'version']);
            
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
        
        if (Schema::hasTable('work_templates')) {
            DB::statement('
                INSERT INTO work_templates_new (id, name, description, category, template_data, version, is_active, tags, created_by, updated_by, created_at, updated_at)
                SELECT 
                    COALESCE(ulid, LOWER(CONCAT(
                        SUBSTR(HEX(RANDOM_BYTES(5)), 1, 10),
                        SUBSTR(HEX(RANDOM_BYTES(5)), 1, 4),
                        SUBSTR(HEX(RANDOM_BYTES(5)), 1, 4),
                        SUBSTR(HEX(RANDOM_BYTES(5)), 1, 4),
                        SUBSTR(HEX(RANDOM_BYTES(5)), 1, 12)
                    ))) as id,
                    name, description, category, template_data, version, is_active, tags,
                    (SELECT u.id FROM users u WHERE u.id = CAST(work_templates.created_by AS CHAR) LIMIT 1) as created_by,
                    (SELECT u.id FROM users u WHERE u.id = CAST(work_templates.updated_by AS CHAR) LIMIT 1) as updated_by,
                    created_at, updated_at
                FROM work_templates
            ');
        }
        
        Schema::dropIfExists('work_templates');
        Schema::rename('work_templates_new', 'work_templates');
    }

    /**
     * Chuyển đổi bảng tasks sang ULID
     */
    private function convertTasksToUlid(): void
    {
        Schema::create('tasks_new', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('project_id');
            $table->ulid('component_id')->nullable();
            $table->ulid('phase_id')->nullable();
            $table->string('name', 255)->index();
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled', 'on_hold'])->default('pending')->index();
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium')->index();
            $table->json('dependencies')->nullable();
            $table->string('conditional_tag', 100)->nullable();
            $table->boolean('is_hidden')->default(false)->index();
            $table->decimal('estimated_hours', 8, 2)->default(0);
            $table->decimal('actual_hours', 8, 2)->default(0);
            $table->decimal('progress_percent', 5, 2)->default(0);
            $table->json('tags')->nullable();
            $table->enum('visibility', ['internal', 'client'])->default('internal');
            $table->boolean('client_approved')->default(false);
            $table->ulid('created_by')->nullable();
            $table->ulid('updated_by')->nullable();
            $table->timestamps();
            
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('component_id')->references('id')->on('components')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['project_id', 'status']);
            $table->index(['start_date', 'end_date']);
            $table->index(['component_id', 'phase_id']);
            $table->index(['conditional_tag', 'is_hidden']);
            
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
        
        if (Schema::hasTable('tasks')) {
            DB::statement('
                INSERT INTO tasks_new (id, project_id, component_id, phase_id, name, description, start_date, end_date, status, priority, dependencies, conditional_tag, is_hidden, estimated_hours, actual_hours, progress_percent, tags, visibility, client_approved, created_by, updated_by, created_at, updated_at)
                SELECT 
                    COALESCE(ulid, LOWER(CONCAT(
                        SUBSTR(HEX(RANDOM_BYTES(5)), 1, 10),
                        SUBSTR(HEX(RANDOM_BYTES(5)), 1, 4),
                        SUBSTR(HEX(RANDOM_BYTES(5)), 1, 4),
                        SUBSTR(HEX(RANDOM_BYTES(5)), 1, 4),
                        SUBSTR(HEX(RANDOM_BYTES(5)), 1, 12)
                    ))) as id,
                    (SELECT p.id FROM projects p WHERE p.id = (SELECT pp.id FROM projects_old pp WHERE pp.id = tasks.project_id LIMIT 1) LIMIT 1) as project_id,
                    (SELECT c.id FROM components c WHERE c.id = (SELECT cc.id FROM components_old cc WHERE cc.id = tasks.component_id LIMIT 1) LIMIT 1) as component_id,
                    phase_id, name, description, start_date, end_date, status, priority, dependencies, conditional_tag, is_hidden, estimated_hours, actual_hours, progress_percent, tags, visibility, client_approved,
                    (SELECT u.id FROM users u WHERE u.id = CAST(tasks.created_by AS CHAR) LIMIT 1) as created_by,
                    (SELECT u.id FROM users u WHERE u.id = CAST(tasks.updated_by AS CHAR) LIMIT 1) as updated_by,
                    created_at, updated_at
                FROM tasks
            ');
        }
        
        Schema::dropIfExists('tasks');
        Schema::rename('tasks_new', 'tasks');
    }

    /**
     * Chuyển đổi bảng task_assignments sang ULID
     */
    private function convertTaskAssignmentsToUlid(): void
    {
        Schema::create('task_assignments_new', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('task_id');
            $table->ulid('user_id');
            $table->decimal('split_percentage', 5, 2)->default(100);
            $table->enum('role', ['assignee', 'reviewer', 'observer'])->default('assignee');
            $table->timestamps();
            
            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            $table->unique(['task_id', 'user_id']);
            $table->index(['user_id', 'role']);
            
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
        
        if (Schema::hasTable('task_assignments')) {
            DB::statement('
                INSERT INTO task_assignments_new (id, task_id, user_id, split_percentage, role, created_at, updated_at)
                SELECT 
                    LOWER(CONCAT(
                        SUBSTR(HEX(RANDOM_BYTES(5)), 1, 10),
                        SUBSTR(HEX(RANDOM_BYTES(5)), 1, 4),
                        SUBSTR(HEX(RANDOM_BYTES(5)), 1, 4),
                        SUBSTR(HEX(RANDOM_BYTES(5)), 1, 4),
                        SUBSTR(HEX(RANDOM_BYTES(5)), 1, 12)
                    )) as id,
                    (SELECT t.id FROM tasks t WHERE t.id = (SELECT tt.id FROM tasks_old tt WHERE tt.id = task_assignments.task_id LIMIT 1) LIMIT 1) as task_id,
                    (SELECT u.id FROM users u WHERE u.id = CAST(task_assignments.user_id AS CHAR) LIMIT 1) as user_id,
                    split_percentage, role, created_at, updated_at
                FROM task_assignments
            ');
        }
        
        Schema::dropIfExists('task_assignments');
        Schema::rename('task_assignments_new', 'task_assignments');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rollback theo thứ tự ngược lại
        // Lưu ý: Việc rollback sẽ mất dữ liệu ULID
        
        // 1. Rollback task_assignments
        Schema::create('task_assignments_old', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->onDelete('cascade');
            $table->unsignedBigInteger('user_id')->index();
            $table->decimal('split_percentage', 5, 2)->default(100);
            $table->enum('role', ['assignee', 'reviewer', 'observer'])->default('assignee');
            $table->timestamps();
            
            $table->unique(['task_id', 'user_id']);
            $table->index(['user_id', 'role']);
        });
        
        // Copy data back (losing ULID references)
        DB::statement('
            INSERT INTO task_assignments_old (task_id, user_id, split_percentage, role, created_at, updated_at)
            SELECT 
                (SELECT t.id FROM tasks_old t WHERE t.ulid = task_assignments.task_id LIMIT 1) as task_id,
                (SELECT u.id FROM users_old u WHERE u.id = task_assignments.user_id LIMIT 1) as user_id,
                split_percentage, role, created_at, updated_at
            FROM task_assignments
        ');
        
        Schema::dropIfExists('task_assignments');
        Schema::rename('task_assignments_old', 'task_assignments');
        
        // Tương tự cho các bảng khác...
        // (Code rollback cho tasks, work_templates, components, projects)
    }
};