<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GAP-106 S-2 (Owner Gate 2): evidence files of Treasury money documents. A Treasury-only table so the files never
 * reach the general document list; files live on the existing private disk and are served only through Treasury
 * routes. Composite FK keeps the attachment in its document's tenant; one file (sha256) at most once per document.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('treasury_document_attachments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('project_id');
            $table->ulid('financial_document_id');
            $table->string('evidence_type', 40);
            $table->string('original_name', 255);
            $table->string('file_path', 500);
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('file_size');
            $table->char('file_hash', 64);
            $table->string('note', 500)->nullable();
            $table->ulid('uploaded_by');
            $table->timestamps();

            $table->foreign('tenant_id', 'tda_tenant_id_fk')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('project_id', 'tda_project_id_fk')->references('id')->on('projects');
            $table->foreign(['tenant_id', 'financial_document_id'], 'tda_document_fk')
                ->references(['tenant_id', 'id'])->on('treasury_financial_documents');
            $table->unique(['financial_document_id', 'file_hash'], 'tda_document_hash_unique');
            $table->index(['tenant_id', 'project_id'], 'tda_tenant_project_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_document_attachments');
    }
};
