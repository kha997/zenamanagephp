<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration tạo các bảng cho Notification System với Rules Engine
 * Bao gồm: notifications, notification_rules
 */
class CreateNotificationTables extends Migration
{
    /**
     * Chạy migration
     */
    public function up(): void
    {
        // Bảng notifications - Quản lý thông báo
        Schema::create('notifications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('priority', ['critical', 'normal', 'low'])->default('normal')->index();
            $table->string('title', 255)->index();
            $table->text('body')->comment('Nội dung thông báo');
            $table->string('link_url', 500)->nullable()->comment('URL liên kết');
            $table->enum('channel', ['inapp', 'email', 'webhook'])->default('inapp')->index();
            $table->timestamp('read_at')->nullable()->comment('Thời gian đọc');
            $table->json('metadata')->nullable()->comment('Dữ liệu bổ sung');
            $table->string('event_key', 100)->nullable()->index()->comment('Key của event trigger');
            $table->foreignUlid('project_id')->nullable()->constrained('projects')->onDelete('cascade');
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'read_at']);
            $table->index(['priority', 'created_at']);
            $table->index(['channel', 'created_at']);
            $table->index(['project_id', 'user_id']);
        });

        // Bảng notification_rules - Quản lý quy tắc thông báo
        Schema::create('notification_rules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignUlid('project_id')->nullable()->constrained('projects')->onDelete('cascade');
            $table->string('event_key', 100)->index()->comment('Key của event cần theo dõi');
            $table->enum('min_priority', ['critical', 'normal', 'low'])->default('normal')->comment('Mức độ ưu tiên tối thiểu');
            $table->json('channels')->comment('Các kênh thông báo ["inapp", "email", "webhook"]');
            $table->boolean('is_enabled')->default(true)->index();
            $table->json('conditions')->nullable()->comment('Điều kiện bổ sung cho rule');
            $table->text('description')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'is_enabled']);
            $table->index(['event_key', 'is_enabled']);
            $table->index(['project_id', 'user_id', 'is_enabled']);
            
            // Unique constraint để tránh duplicate rules
            $table->unique(['user_id', 'project_id', 'event_key'], 'unique_user_project_event');
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