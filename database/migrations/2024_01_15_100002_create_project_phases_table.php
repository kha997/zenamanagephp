<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tạo bảng project_phases để quản lý các giai đoạn trong dự án
     * Được tạo từ template hoặc thêm thủ công
     */
    public function up(): void
    {
        Schema::create('project_phases', function (Blueprint $table) {
            // Primary key sử dụng ULID
            $table->ulid('id')->primary();
            
            // Foreign key tới projects
            $table->foreignUlid('project_id')->constrained('projects')->onDelete('cascade');
            
            // Phase information
            $table->string('name', 255);
            $table->integer('order')->default(0); // Thứ tự của phase trong project
            
            // Template reference (nullable nếu tạo thủ công)
            $table->string('template_id', 26)->nullable();
            $table->string('template_phase_id', 100)->nullable(); // ID của phase trong template JSON
            
            // Audit fields
            $table->string('created_by', 26)->nullable();
            $table->string('updated_by', 26)->nullable();
            
            // Timestamps
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('deleted_at')->nullable();
            
            // Indexes
            $table->index(['project_id', 'order']);
            $table->index(['project_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_phases');
    }
};