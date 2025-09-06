<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration tạo các bảng notifications và notification_rules
 */
class CreateNotificationTables extends Migration
{
    /**
     * Chạy migration
     */
    public function up(): void
    {
        // Bảng notifications
        Schema::create('notifications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('user_id')->index();
            $table->enum('priority', ['critical', 'normal', 'low'])->default('normal')->index();
            $table->string('title', 255);
            $table->text('body');
            $table->string('link_url')->nullable();
            $table->enum('channel', ['inapp', 'email', 'webhook'])->default('inapp')->index();
            $table->timestamp('read_at')->nullable()->index();
            $table->json('metadata')->nullable()->comment('Dữ liệu bổ sung');
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'read_at']);
            $table->index(['priority', 'channel']);
        });

        // Bảng notification_rules
        Schema::create('notification_rules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('user_id')->index();
            $table->foreignUlid('project_id')->nullable()->constrained('projects')->onDelete('cascade')->comment('Quy tắc riêng cho dự án');
            $table->string('event_key', 100)->index()->comment('Khóa sự kiện: task.created, project.updated');
            $table->enum('min_priority', ['critical', 'normal', 'low'])->default('normal');
            $table->json('channels')->comment('Danh sách kênh thông báo');
            $table->boolean('is_enabled')->default(true)->index();
            $table->json('conditions')->nullable()->comment('Điều kiện bổ sung');
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'event_key']);
            $table->index(['project_id', 'is_enabled']);
        });
    }

    /**
     * Rollback migration
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_rules');
        Schema::dropIfExists('notifications');
    }
}