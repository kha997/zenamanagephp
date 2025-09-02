<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration tạo các bảng cho Document Management với versioning system
 * Bao gồm: documents, document_versions
 */
class CreateDocumentManagementTables extends Migration
{
    /**
     * Chạy migration
     */
    public function up(): void
    {
        // Bảng documents - Quản lý tài liệu
        Schema::create('documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained('projects')->onDelete('cascade');
            $table->string('title', 255)->index();
            $table->text('description')->nullable();
            $table->enum('linked_entity_type', ['task', 'diary', 'cr'])->nullable()->comment('Loại entity liên kết');
            $table->string('linked_entity_id', 26)->nullable()->comment('ID của entity liên kết');
            $table->unsignedBigInteger('current_version_id')->nullable()->comment('Version hiện tại');
            $table->json('tags')->nullable()->comment('Tags đa cấp');
            $table->enum('visibility', ['internal', 'client'])->default('internal');
            $table->boolean('client_approved')->default(false);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            // Indexes
            $table->index(['project_id', 'linked_entity_type', 'linked_entity_id']);
            $table->index(['visibility', 'client_approved']);
            $table->index(['created_by']);
        });

        // Bảng document_versions - Quản lý phiên bản tài liệu
        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('document_id')->constrained('documents')->onDelete('cascade');
            $table->integer('version_number')->comment('Số phiên bản');
            $table->string('file_path', 500)->comment('Đường dẫn file');
            $table->enum('storage_driver', ['local', 's3', 'gdrive'])->default('local')->comment('Driver lưu trữ');
            $table->text('comment')->nullable()->comment('Ghi chú về phiên bản');
            $table->json('metadata')->nullable()->comment('Metadata của file (size, mime_type, etc.)');
            $table->integer('reverted_from_version_number')->nullable()->comment('Phiên bản được revert từ');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            // Indexes
            $table->index(['document_id', 'version_number']);
            $table->index(['created_by']);
            $table->unique(['document_id', 'version_number']);
        });

        // Thêm foreign key constraint cho current_version_id
        Schema::table('documents', function (Blueprint $table) {
            $table->foreign('current_version_id')->references('id')->on('document_versions')->onDelete('set null');
        });
    }

    /**
     * Rollback migration
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });
        
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
    }
}