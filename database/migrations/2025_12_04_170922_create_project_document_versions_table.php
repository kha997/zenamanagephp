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
        Schema::create('project_document_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('document_id');
            $table->string('project_id');
            $table->string('tenant_id')->nullable();
            $table->integer('version_number');
            $table->string('name')->nullable();
            $table->string('original_name')->nullable();
            $table->string('file_path');
            $table->string('file_type')->nullable();
            $table->string('mime_type')->nullable();
            $table->bigInteger('file_size');
            $table->string('file_hash')->nullable();
            $table->ulid('uploaded_by');
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('document_id')->references('id')->on('documents')->onDelete('cascade');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('cascade');
            
            // Indexes for efficient queries
            $table->index('document_id');
            $table->index(['project_id', 'tenant_id']);
            $table->index(['document_id', 'version_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_document_versions');
    }
};
