<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration tạo bảng event_logs cho audit trail
 */
class CreateEventLogsTable extends Migration
{
    /**
     * Chạy migration
     */
    public function up(): void
    {
        Schema::create('event_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('event_name', 100)->index()->comment('Tên sự kiện: Project.Component.ProgressUpdated');
            $table->string('entity_type', 50)->index()->comment('Loại entity: project, task, component');
            $table->string('entity_id', 26)->index()->comment('ID của entity');
            $table->foreignUlid('project_id')->nullable()->constrained('projects')->onDelete('cascade');
            $table->unsignedBigInteger('actor_id')->nullable()->index()->comment('User thực hiện hành động');
            $table->json('payload')->comment('Dữ liệu sự kiện đầy đủ');
            $table->json('changed_fields')->nullable()->comment('Các trường đã thay đổi');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('occurred_at')->index()->comment('Thời điểm xảy ra sự kiện');
            $table->timestamps();
            
            // Indexes
            $table->index(['event_name', 'occurred_at']);
            $table->index(['entity_type', 'entity_id']);
            $table->index(['project_id', 'occurred_at']);
            $table->index(['actor_id', 'occurred_at']);
        });
    }

    /**
     * Rollback migration
     */
    public function down(): void
    {
        Schema::dropIfExists('event_logs');
    }
}