<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration tạo bảng interaction_logs
 * Quản lý các tương tác với khách hàng và ghi chú dự án
 */
class CreateInteractionLogsTable extends Migration
{
    /**
     * Chạy migration
     */
    public function up(): void
    {
        Schema::create('interaction_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained('projects')->onDelete('cascade');
            $table->foreignUlid('linked_task_id')->nullable()->constrained('tasks')->onDelete('set null');
            $table->enum('type', ['call', 'email', 'meeting', 'note', 'feedback'])->index()->comment('Loại tương tác');
            $table->text('description')->comment('Nội dung mô tả tương tác');
            $table->string('tag_path', 255)->nullable()->comment('Đường dẫn tag (e.g., "Material/Flooring/Granite")');
            $table->enum('visibility', ['internal', 'client'])->default('internal')->index()->comment('Mức độ hiển thị');
            $table->boolean('client_approved')->default(false)->index()->comment('Đã được khách hàng phê duyệt');
            $table->foreignUlid('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            
            // LOẠI BỎ dòng này vì đã có ở trên:
            // $table->foreign('linked_task_id')->references('id')->on('tasks')->onDelete('set null');
            
            // Indexes
            $table->index(['project_id', 'type']);
            $table->index(['project_id', 'visibility']);
            $table->index(['project_id', 'client_approved']);
            $table->index(['created_by', 'created_at']);
            $table->index(['tag_path']);
        });
    }

    /**
     * Rollback migration
     */
    public function down(): void
    {
        Schema::dropIfExists('interaction_logs');
    }
}