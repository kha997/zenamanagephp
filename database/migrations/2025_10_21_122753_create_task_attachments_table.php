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
        Schema::create('task_attachments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('task_id');
            $table->ulid('tenant_id');
            $table->ulid('uploaded_by');
            $table->string('name');
            $table->string('original_name');
            $table->string('file_path');
            $table->string('disk')->default('public');
            $table->string('mime_type');
            $table->string('extension');
            $table->bigInteger('size');
            $table->string('hash');
            $table->string('category')->default('other');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->json('tags')->nullable();
            $table->boolean('is_public')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('download_count')->default(0);
            $table->timestamp('last_accessed_at')->nullable();
            $table->ulid('current_version_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign key constraints
            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('cascade');
            // Note: current_version_id foreign key removed for testing - task_attachment_versions table doesn't exist yet

            // Indexes
            $table->index(['task_id', 'tenant_id']);
            $table->index(['tenant_id', 'category']);
            $table->index(['uploaded_by', 'created_at']);
            $table->index(['is_active', 'is_public']);
            $table->index('created_at');
        });

        Schema::create('task_attachment_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('task_attachment_id');
            $table->ulid('uploaded_by');
            $table->integer('version_number');
            $table->string('file_path');
            $table->string('disk')->default('public');
            $table->bigInteger('size');
            $table->string('hash');
            $table->text('change_description')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();
            $table->softDeletes();

            // Foreign key constraints
            $table->foreign('task_attachment_id')->references('id')->on('task_attachments')->onDelete('cascade');
            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('cascade');

            // Indexes
            $table->index(['task_attachment_id', 'version_number']);
            $table->index(['task_attachment_id', 'is_current']);
            $table->index(['uploaded_by', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_attachment_versions');
        Schema::dropIfExists('task_attachments');
    }
};