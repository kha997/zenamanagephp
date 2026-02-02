<?php

use Illuminate\Database\Migrations\Migration;
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
            if (!Schema::hasColumn('documents', 'created_by')) {
                $table->ulid('created_by')->nullable()->after('uploaded_by');
            }

            if (!Schema::hasColumn('documents', 'updated_by')) {
                $table->ulid('updated_by')->nullable()->after('created_by');
            }

            $table->index('created_by', 'documents_created_by_index');
            $table->index('updated_by', 'documents_updated_by_index');
        });

        if ($this->usersTableSupportsUlid()) {
            Schema::table('documents', function (Blueprint $table) {
                $table->foreign('created_by', 'documents_created_by_foreign')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->foreign('updated_by', 'documents_updated_by_foreign')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('documents')) {
            return;
        }

        if ($this->usersTableSupportsUlid()) {
            Schema::table('documents', function (Blueprint $table) {
                $table->dropForeign('documents_created_by_foreign');
                $table->dropForeign('documents_updated_by_foreign');
            });
        }

        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasColumn('documents', 'created_by')) {
                $table->dropIndex('documents_created_by_index');
                $table->dropColumn('created_by');
            }

            if (Schema::hasColumn('documents', 'updated_by')) {
                $table->dropIndex('documents_updated_by_index');
                $table->dropColumn('updated_by');
            }
        });
    }

    private function usersTableSupportsUlid(): bool
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'id')) {
            return false;
        }

        return Schema::getColumnType('users', 'id') === 'string';
    }
};
