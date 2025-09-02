<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration tạo bảng component_kpis
 * Lưu trữ các KPI của component
 */
class CreateComponentKpisTable extends Migration
{
    /**
     * Chạy migration
     */
    public function up(): void
    {
        Schema::create('component_kpis', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('component_id')->constrained('components')->onDelete('cascade');
            $table->string('kpi_code', 100)->index()->comment('Mã KPI (ví dụ: quality_score, safety_index)');
            $table->decimal('value', 15, 4)->comment('Giá trị KPI');
            $table->string('unit', 50)->nullable()->comment('Đơn vị đo (%, điểm, etc.)');
            $table->text('description')->nullable()->comment('Mô tả KPI');
            $table->date('measured_date')->nullable()->comment('Ngày đo KPI');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['component_id', 'kpi_code']);
            $table->index(['kpi_code', 'measured_date']);
            
            // Unique constraint để tránh trùng lặp KPI cho cùng component
            $table->unique(['component_id', 'kpi_code', 'measured_date']);
        });
    }

    /**
     * Rollback migration
     */
    public function down(): void
    {
        Schema::dropIfExists('component_kpis');
    }
}