<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration để tạo bảng event_logs cho Event Bus system
 * Lưu trữ tất cả events trong hệ thống để audit và debug
 */
return new class extends Migration
{
    /**
     * Chạy migration để tạo bảng event_logs
     */
    public function up(): void
    {
        Schema::create('event_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_name')->index(); // Tên event (e.g., Project.Component.ProgressUpdated)
            $table->string('event_class')->index(); // Class name của event
            $table->string('entity_id', 26)->nullable()->index(); // ID của entity chính (ULID)
            $table->foreignUlid('project_id')->nullable()->constrained('projects')->onDelete('cascade');
            $table->foreignUlid('actor_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignUlid('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->json('payload'); // Dữ liệu đầy đủ của event
            $table->json('changed_fields')->nullable(); // Các field đã thay đổi
            $table->string('source_module', 50)->index(); // Module phát sinh event
            $table->enum('severity', ['info', 'warning', 'error', 'critical'])->default('info');
            $table->timestamp('event_timestamp'); // Thời gian event xảy ra
            $table->timestamps();
            
            // Indexes để tối ưu query
            $table->index(['tenant_id', 'project_id', 'event_timestamp']);
            $table->index(['event_name', 'event_timestamp']);
            $table->index(['actor_id', 'event_timestamp']);
        });
    }

    /**
     * Rollback migration
     */
    public function down(): void
    {
        Schema::dropIfExists('event_logs');
    }
};