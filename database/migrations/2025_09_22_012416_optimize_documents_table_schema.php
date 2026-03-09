<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // Add composite indexes for common query patterns
            $table->index(['tenant_id', 'status'], 'documents_tenant_status_index');
            $table->index(['project_id', 'category', 'status'], 'documents_project_category_status_index');
            $table->index(['created_at'], 'documents_created_at_index');

            // Add unique constraint for file hash to prevent duplicate storage
            $table->unique(['file_hash'], 'documents_file_hash_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('documents', 'tenant_id')) {
            $this->ensureSingleColumnIndexExists('documents', 'tenant_id', 'idx_documents_tenant_id');
        }

        if (Schema::hasColumn('documents', 'project_id')) {
            $this->ensureSingleColumnIndexExists('documents', 'project_id', 'documents_project_id_index');
        }

        $this->dropIndexIfExists('documents', 'documents_tenant_status_index');
        $this->dropIndexIfExists('documents', 'documents_project_category_status_index');
        $this->dropIndexIfExists('documents', 'documents_created_at_index');
        $this->dropUniqueIfExists('documents', 'documents_file_hash_unique');
    }

    private function ensureSingleColumnIndexExists(string $tableName, string $columnName, string $indexName): void
    {
        try {
            Schema::table($tableName, function (Blueprint $table) use ($columnName, $indexName) {
                $table->index([$columnName], $indexName);
            });
        } catch (QueryException $exception) {
            if ($this->isDuplicateIndexError($exception)) {
                return;
            }

            throw $exception;
        }
    }

    private function dropIndexIfExists(string $tableName, string $indexName): void
    {
        try {
            Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                $table->dropIndex($indexName);
            });
        } catch (QueryException $exception) {
            if ($this->isMissingIndexError($exception)) {
                return;
            }

            throw $exception;
        }
    }

    private function dropUniqueIfExists(string $tableName, string $indexName): void
    {
        try {
            Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                $table->dropUnique($indexName);
            });
        } catch (QueryException $exception) {
            if ($this->isMissingIndexError($exception)) {
                return;
            }

            throw $exception;
        }
    }

    private function isMissingIndexError(QueryException $exception): bool
    {
        $errorInfo = $exception->errorInfo ?? [];
        $message = $exception->getMessage();

        return (isset($errorInfo[1]) && (int) $errorInfo[1] === 1091)
            || str_contains($message, "Can't DROP")
            || str_contains($message, 'check that column/key exists');
    }

    private function isDuplicateIndexError(QueryException $exception): bool
    {
        $errorInfo = $exception->errorInfo ?? [];
        $message = $exception->getMessage();

        return (isset($errorInfo[1]) && (int) $errorInfo[1] === 1061)
            || str_contains($message, 'Duplicate key name')
            || str_contains($message, 'already exists');
    }
};
