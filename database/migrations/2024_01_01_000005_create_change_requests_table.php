<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration tạo bảng change_requests
 * Quản lý các yêu cầu thay đổi với workflow approval
 */
class CreateChangeRequestsTable extends Migration
{
    /**
     * Chạy migration
     */
    public function up(): void
    {
        Schema::create('change_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained('projects')->onDelete('cascade');
            $table->string('code', 50)->unique()->index()->comment('Mã CR (e.g., CR-001)');
            $table->string('title', 255)->index();
            $table->text('description')->comment('Mô tả chi tiết yêu cầu thay đổi');
            $table->enum('status', ['draft', 'awaiting_approval', 'approved', 'rejected'])->default('draft')->index();
            $table->integer('impact_days')->default(0)->comment('Ảnh hưởng số ngày');
            $table->decimal('impact_cost', 15, 2)->default(0)->comment('Ảnh hưởng chi phí');
            $table->json('impact_kpi')->nullable()->comment('Ảnh hưởng KPI (JSON)');
            $table->json('attachments')->nullable()->comment('Danh sách file đính kèm');
            $table->text('justification')->nullable()->comment('Lý do biện minh');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium')->index();
            $table->json('tags')->nullable()->comment('Tags đa cấp');
            $table->enum('visibility', ['internal', 'client'])->default('internal');
            $table->boolean('client_approved')->default(false);
            $table->foreignUlid('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignUlid('decided_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('decided_at')->nullable()->comment('Thời gian quyết định');
            $table->text('decision_note')->nullable()->comment('Ghi chú quyết định');
            $table->timestamps();
            
            // Indexes
            $table->index(['project_id', 'status']);
            $table->index(['created_by', 'created_at']);
            $table->index(['decided_by', 'decided_at']);
            $table->index(['priority', 'status']);
        });
    }

    /**
     * Rollback migration
     */
    public function down(): void
    {
        Schema::dropIfExists('change_requests');
    }
}