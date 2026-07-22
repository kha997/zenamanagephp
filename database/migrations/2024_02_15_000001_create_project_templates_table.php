<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('project_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category'); // residential, commercial, industrial, mixed-use, custom
            $table->text('description')->nullable();
            $table->json('phases'); // Array of design phases with tasks
            $table->json('default_settings'); // Default project settings
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // template_tasks/zena_template_tasks có FK trỏ vào project_templates —
        // phải drop trước, nếu không MySQL ném lỗi 3730 (constraint FK chặn drop).
        // Logic này chuyển từ down() của migration 2025_09_16 (đã gỡ 22/07 vì up()
        // của nó là no-op vĩnh viễn — bị guard `if (!Schema::hasTable())` chặn do
        // bảng đã được migration này tạo trước — nhưng down() của nó vẫn có tác
        // dụng dọn dẹp thật, không được xoá theo).
        if (Schema::hasTable('template_tasks')) {
            try {
                Schema::drop('template_tasks');
            } catch (\Throwable) {
                // Intentionally swallow for idempotent rollback in partial DB states.
            }
        }

        if (Schema::hasTable('zena_template_tasks')) {
            try {
                Schema::drop('zena_template_tasks');
            } catch (\Throwable) {
                // Intentionally swallow for idempotent rollback in partial DB states.
            }
        }

        Schema::dropIfExists('project_templates');
    }
};
