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
        if (!Schema::hasTable('documents')) {
            Schema::create('documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('tenant_id')->nullable();
            $table->string('project_id');
            $table->string('name');
            $table->string('original_name');
            $table->string('file_path');
            $table->string('file_type');
            $table->string('mime_type');
            $table->bigInteger('file_size');
            $table->string('file_hash');
            $table->string('category')->default('general');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->string('status')->default('draft');
            $table->integer('version')->default(1);
            $table->boolean('is_current_version')->default(true);
            $table->ulid('parent_document_id')->nullable();
            $table->ulid('uploaded_by');
            $table->ulid('created_by')->nullable();
            $table->ulid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('parent_document_id')->references('id')->on('documents')->onDelete('set null');
            
            $table->index(['project_id', 'category']);
            $table->index(['file_hash']);
            $table->index(['parent_document_id', 'version']);
            $table->index(['tenant_id']);
        });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};