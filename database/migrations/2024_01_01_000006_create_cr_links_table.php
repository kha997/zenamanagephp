<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration tạo bảng cr_links
 * Quản lý liên kết giữa Change Request và các entity khác
 */
class CreateCrLinksTable extends Migration
{
    /**
     * Chạy migration
     */
    public function up(): void
    {
        Schema::create('cr_links', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('change_request_id')->constrained('change_requests')->onDelete('cascade');
            $table->enum('linked_type', ['task', 'document', 'component'])->comment('Loại entity được liên kết');
            $table->ulid('linked_id')->comment('ID của entity được liên kết');
            $table->text('link_description')->nullable()->comment('Mô tả mối liên kết');
            $table->timestamps();
            
            // Indexes
            $table->index(['change_request_id', 'linked_type']);
            $table->index(['linked_type', 'linked_id']);
            $table->unique(['change_request_id', 'linked_type', 'linked_id'], 'cr_links_unique');
        });
    }

    /**
     * Rollback migration
     */
    public function down(): void
    {
        Schema::dropIfExists('cr_links');
    }
}