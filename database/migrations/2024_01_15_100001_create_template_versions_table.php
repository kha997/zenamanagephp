<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tạo bảng template_versions để lưu trữ lịch sử versions của templates
     * Đảm bảo không mất dữ liệu khi cập nhật template
     */
    public function up(): void
    {
        Schema::create('template_versions', function (Blueprint $table) {
            // Primary key sử dụng ULID
            $table->string('id', 26)->primary();
            
            // Foreign key tới templates
            $table->string('template_id', 26)->index();
            $table->foreign('template_id')->references('id')->on('templates')->onDelete('cascade');
            
            // Version information
            $table->integer('version');
            $table->json('json_body');
            $table->text('note')->nullable();
            
            // Audit fields
            $table->string('created_by', 26)->nullable();
            
            // Timestamps
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            
            // Unique constraint để đảm bảo không trùng version
            $table->unique(['template_id', 'version']);
            
            // Index for performance
            $table->index(['template_id', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_versions');
    }
};