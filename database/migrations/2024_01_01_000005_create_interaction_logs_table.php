<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration tạo bảng interaction_logs
 * Quản lý nhật ký tương tác dự án
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
            $table->enum('type', ['call', 'email', 'meeting', 'note', 'feedback'])->index();
            $table->text('description');
            $table->string('tag_path')->nullable()->comment('Đường dẫn tag: Material/Flooring/Granite');
            $table->enum('visibility', ['internal', 'client'])->default('internal')->index();
            $table->boolean('client_approved')->default(false)->index();
            $table->json('attachments')->nullable()->comment('Danh sách file đính kèm');
            $table->foreignUlid('created_by')->constrained('users')->onDelete('cascade'); // ✅ Thay đổi từ unsignedBigInteger
            $table->timestamps();
            
            // Indexes
            $table->index(['project_id', 'type']);
            $table->index(['visibility', 'client_approved']);
            $table->index(['created_by']);
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