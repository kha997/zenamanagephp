<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tạo bảng templates để lưu trữ các mẫu công việc
     * Hỗ trợ versioning và categorization theo loại công việc
     */
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            // Primary key sử dụng ULID theo Foundation standards
            $table->string('id', 26)->primary();
            
            // Thông tin cơ bản của template
            $table->string('template_name', 255)->index();
            $table->enum('category', ['Design', 'Construction', 'QC', 'Inspection'])->index();
            
            // JSON body chứa cấu trúc phases và tasks
            $table->json('json_body');
            
            // Version management
            $table->integer('version')->default(1);
            $table->boolean('is_active')->default(true)->index();
            
            // Audit fields theo Foundation standards
            $table->string('created_by', 26)->nullable();
            $table->string('updated_by', 26)->nullable();
            
            // Timestamps theo ISO 8601 UTC
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('deleted_at')->nullable();
            
            // Indexes for performance
            $table->index(['category', 'is_active']);
            $table->index(['template_name', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};