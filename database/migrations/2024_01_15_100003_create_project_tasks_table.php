<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tạo bảng project_tasks để quản lý tasks trong dự án
     * Hỗ trợ conditional tags và dependencies
     */
    public function up(): void
    {
        Schema::create('project_tasks', function (Blueprint $table) {
            // Primary key sử dụng ULID
            $table->ulid('id')->primary();
            
            // Foreign keys
            $table->foreignUlid('project_id')->constrained('projects')->onDelete('cascade');
            $table->foreignUlid('phase_id')->nullable()->constrained('project_phases')->onDelete('set null');
            
            // Task information
            $table->string('name', 255);
            $table->integer('duration_days')->default(1);
            $table->string('role_suggested', 100)->nullable();
            $table->decimal('contract_value_percent', 5, 2)->default(0.00); // % của tổng giá trị hợp đồng
            
            // Dependencies và conditional logic
            $table->json('dependencies')->nullable(); // Array of task IDs
            $table->string('conditional_tag_path', 500)->nullable(); // Path của conditional tag
            $table->boolean('is_hidden')->default(false)->index(); // Ẩn task khi conditional tag off
            
            // Template reference
            $table->string('template_id', 26)->nullable();
            $table->string('template_task_id', 100)->nullable(); // ID của task trong template JSON
            
            // Progress tracking
            $table->decimal('progress_percent', 5, 2)->default(0.00);
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            
            // Dates
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();
            
            // Audit fields
            $table->string('created_by', 26)->nullable();
            $table->string('updated_by', 26)->nullable();
            
            // Timestamps
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('deleted_at')->nullable();
            
            // Indexes for performance
            $table->index(['project_id', 'phase_id']);
            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'is_hidden']);
            $table->index(['conditional_tag_path']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_tasks');
    }
};