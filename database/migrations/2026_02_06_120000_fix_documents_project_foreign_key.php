<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COPY_COLUMNS = [
        'id',
        'project_id',
        'uploaded_by',
        'name',
        'original_name',
        'file_path',
        'file_type',
        'mime_type',
        'file_size',
        'file_hash',
        'category',
        'description',
        'metadata',
        'status',
        'version',
        'is_current_version',
        'parent_document_id',
        'created_at',
        'updated_at',
        'deleted_at',
        'deprecated_notice',
        'tenant_id',
        'created_by',
        'updated_by',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('documents')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->rebuildDocumentsTable('projects');
            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasColumn('documents', 'project_id')) {
                try {
                    $table->dropForeign(['project_id']);
                } catch (\Exception $e) {
                    // foreign key might already point somewhere else, ignore
                }

                $table->foreign('project_id')
                      ->references('id')
                      ->on('projects')
                      ->cascadeOnDelete()
                      ->cascadeOnUpdate();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('documents')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->rebuildDocumentsTable('zena_projects');
            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasColumn('documents', 'project_id')) {
                try {
                    $table->dropForeign(['project_id']);
                } catch (\Exception $e) {
                    // ignore if missing
                }

                $table->foreign('project_id')
                      ->references('id')
                      ->on('zena_projects')
                      ->cascadeOnDelete()
                      ->cascadeOnUpdate();
            }
        });
    }

    private function rebuildDocumentsTable(string $projectTable): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

        Schema::disableForeignKeyConstraints();
        $backupTable = 'documents_backup';

        if (Schema::hasTable($backupTable)) {
            Schema::dropIfExists($backupTable);
        }

        DB::statement('ALTER TABLE documents RENAME TO ' . $backupTable);

        $indexesToDrop = [
            'documents_tenant_status_index',
            'documents_project_id_category_index',
            'documents_project_category_status_index',
            'documents_parent_document_id_version_index',
            'documents_created_at_index',
            'documents_file_hash_unique',
            'idx_documents_tenant_id',
            'documents_created_by_index',
            'documents_updated_by_index',
        ];

        foreach ($indexesToDrop as $index) {
            try {
                DB::statement('DROP INDEX IF EXISTS ' . $index);
            } catch (\Throwable $e) {
                // ignore missing indexes
            }
        }

        try {
            Schema::create('documents', function (Blueprint $table) use ($projectTable) {
            $table->ulid('id')->primary();
            $table->ulid('project_id')->nullable();
            $table->ulid('uploaded_by');
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
            $table->string('status')->default('active');
            $table->integer('version')->default(1);
            $table->boolean('is_current_version')->default(true);
            $table->ulid('parent_document_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->string('deprecated_notice')->nullable();
            $table->ulid('tenant_id')->nullable();
            $table->ulid('created_by')->nullable();
            $table->ulid('updated_by')->nullable();

            $table->foreign('project_id')
                  ->references('id')
                  ->on($projectTable)
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();

            $table->foreign('tenant_id')
                  ->references('id')
                  ->on('tenants')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();

            $table->foreign('uploaded_by')
                  ->references('id')
                  ->on('users')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();

            $table->foreign('parent_document_id')
                  ->references('id')
                  ->on('documents')
                  ->nullOnDelete()
                  ->cascadeOnUpdate();

            $table->foreign('created_by')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();

            $table->foreign('updated_by')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();

            $table->index(['project_id', 'category']);
            $table->index(['file_hash']);
            $table->index(['parent_document_id', 'version']);
            $table->index(['tenant_id', 'id'], 'idx_documents_tenant_id');
            $table->index(['tenant_id', 'status'], 'documents_tenant_status_index');
            $table->index(['project_id', 'category', 'status'], 'documents_project_category_status_index');
            $table->index(['created_at'], 'documents_created_at_index');
            $table->unique(['file_hash'], 'documents_file_hash_unique');
            $table->index('created_by', 'documents_created_by_index');
            $table->index('updated_by', 'documents_updated_by_index');
        });

            DB::table('documents')->insertUsing(self::COPY_COLUMNS, DB::table($backupTable)->select(self::COPY_COLUMNS));

            Schema::dropIfExists($backupTable);
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
};
