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
        Schema::table('document_versions', function (Blueprint $table) {
            // Add composite indexes for version history queries
            $table->index(['document_id', 'created_at'], 'document_versions_document_created_index');
            $table->index(['created_by', 'created_at'], 'document_versions_created_by_created_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropIndexIfExists('document_versions', 'document_versions_document_created_index');
        $this->dropIndexIfExists('document_versions', 'document_versions_created_by_created_index');
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
