<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('documents')) {
            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            if (!Schema::hasColumn('documents', 'document_type')) {
                $table->string('document_type')->nullable()->after('title');
            }

            if (!Schema::hasColumn('documents', 'discipline')) {
                $table->string('discipline')->nullable()->after('document_type');
            }

            if (!Schema::hasColumn('documents', 'package')) {
                $table->string('package')->nullable()->after('discipline');
            }

            if (!Schema::hasColumn('documents', 'revision')) {
                $table->string('revision')->nullable()->after('status');
            }
        });

        $this->addIndex('documents', ['tenant_id', 'document_type'], 'documents_tenant_document_type_index');
        $this->addIndex('documents', ['tenant_id', 'discipline'], 'documents_tenant_discipline_index');
        $this->addIndex('documents', ['tenant_id', 'package'], 'documents_tenant_package_index');
        $this->addIndex('documents', ['tenant_id', 'status'], 'documents_tenant_status_lookup_index');
        $this->addIndex('documents', ['tenant_id', 'revision'], 'documents_tenant_revision_index');
    }

    public function down(): void
    {
        if (!Schema::hasTable('documents')) {
            return;
        }

        $this->dropIndex('documents', 'documents_tenant_document_type_index');
        $this->dropIndex('documents', 'documents_tenant_discipline_index');
        $this->dropIndex('documents', 'documents_tenant_package_index');
        $this->dropIndex('documents', 'documents_tenant_status_lookup_index');
        $this->dropIndex('documents', 'documents_tenant_revision_index');

        Schema::table('documents', function (Blueprint $table) {
            foreach (['revision', 'package', 'discipline', 'document_type'] as $column) {
                if (Schema::hasColumn('documents', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function addIndex(string $tableName, array $columns, string $indexName): void
    {
        try {
            Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName) {
                $table->index($columns, $indexName);
            });
        } catch (QueryException $exception) {
            if ($this->isDuplicateIndexError($exception)) {
                return;
            }

            throw $exception;
        }
    }

    private function dropIndex(string $tableName, string $indexName): void
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

    private function isDuplicateIndexError(QueryException $exception): bool
    {
        $errorInfo = $exception->errorInfo ?? [];
        $message = $exception->getMessage();

        return (isset($errorInfo[1]) && (int) $errorInfo[1] === 1061)
            || str_contains($message, 'Duplicate key name')
            || str_contains($message, 'already exists');
    }

    private function isMissingIndexError(QueryException $exception): bool
    {
        $errorInfo = $exception->errorInfo ?? [];
        $message = $exception->getMessage();

        return (isset($errorInfo[1]) && (int) $errorInfo[1] === 1091)
            || str_contains($message, "Can't DROP")
            || str_contains($message, 'check that column/key exists')
            || str_contains($message, 'no such index');
    }
};
