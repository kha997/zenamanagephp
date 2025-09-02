<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration tạo các bảng cho KPI & Compensation module
 * Bao gồm: contracts, tasks_compensation
 */
return new class extends Migration
{
    /**
     * Tạo các bảng cho compensation system
     */
    public function up(): void
    {
        // Bảng contracts - Quản lý hợp đồng dự án
        Schema::create('contracts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained('projects')->onDelete('cascade');
            $table->string('contract_number', 100)->unique()->comment('Số hợp đồng');
            $table->string('title', 255)->comment('Tiêu đề hợp đồng');
            $table->text('description')->nullable()->comment('Mô tả hợp đồng');
            $table->decimal('total_value', 15, 2)->default(0)->comment('Tổng giá trị hợp đồng');
            $table->integer('version')->default(1)->comment('Phiên bản hợp đồng');
            $table->enum('status', ['draft', 'active', 'completed', 'cancelled'])->default('draft')->index();
            $table->date('start_date')->nullable()->comment('Ngày bắt đầu hợp đồng');
            $table->date('end_date')->nullable()->comment('Ngày kết thúc hợp đồng');
            $table->date('signed_date')->nullable()->comment('Ngày ký hợp đồng');
            $table->json('terms')->nullable()->comment('Điều khoản hợp đồng');
            $table->string('client_name', 255)->nullable()->comment('Tên khách hàng');
            $table->text('notes')->nullable()->comment('Ghi chú');
            $table->string('created_by', 26)->nullable();
            $table->string('updated_by', 26)->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['project_id', 'status']);
            $table->index(['contract_number']);
            $table->index(['version']);
        });

        // Bảng tasks_compensation - Quản lý compensation cho tasks
        Schema::create('tasks_compensation', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('task_id')->constrained('tasks')->onDelete('cascade');
            $table->decimal('base_contract_value_percent', 5, 2)->default(0)->comment('% giá trị hợp đồng cơ bản');
            $table->decimal('effective_contract_value_percent', 5, 2)->default(0)->comment('% giá trị hợp đồng hiệu lực');
            $table->decimal('snapshot_contract_value', 15, 2)->default(0)->comment('Giá trị hợp đồng snapshot khi apply');
            $table->enum('status', ['pending', 'locked'])->default('pending')->index()->comment('Trạng thái compensation');
            $table->foreignUlid('contract_id')->nullable()->constrained('contracts')->onDelete('set null')->comment('Hợp đồng áp dụng');
            $table->timestamp('locked_at')->nullable()->comment('Thời điểm lock compensation');
            $table->string('locked_by', 26)->nullable()->comment('Người lock compensation');
            $table->text('notes')->nullable()->comment('Ghi chú');
            $table->string('created_by', 26)->nullable();
            $table->string('updated_by', 26)->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['task_id', 'status']);
            $table->index(['contract_id']);
            $table->index(['status', 'locked_at']);
            
            // Unique constraint - mỗi task chỉ có 1 compensation record
            $table->unique(['task_id']);
        });

        // Cập nhật bảng task_assignments để đổi tên trường split_percentage thành split_percent
        Schema::table('task_assignments', function (Blueprint $table) {
            $table->renameColumn('split_percentage', 'split_percent');
        });
    }

    /**
     * Rollback migration
     */
    public function down(): void
    {
        // Rollback task_assignments changes
        Schema::table('task_assignments', function (Blueprint $table) {
            $table->renameColumn('split_percent', 'split_percentage');
        });
        
        Schema::dropIfExists('tasks_compensation');
        Schema::dropIfExists('contracts');
    }
};