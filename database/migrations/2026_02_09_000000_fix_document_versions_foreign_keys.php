<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COPY_COLUMNS = [
        'id',
        'document_id',
        'version_number',
        'file_path',
        'storage_driver',
        'comment',
        'metadata',
        'created_by',
        'reverted_from_version_number',
        'created_at',
        'updated_at',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('document_versions')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->rebuildForSqlite();
            return;
        }

        Schema::table('document_versions', function (Blueprint $table) {
            if (!Schema::hasColumn('document_versions', 'document_id')) {
                return;
            }

            try {
                $table->dropForeign(['document_id']);
            } catch (\Throwable) {
                // ignore if foreign key already removed or invalid
            }

            $table->foreign('document_id')
                  ->references('id')
                  ->on('documents')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('document_versions')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->rebuildForSqlite();
            return;
        }

        Schema::table('document_versions', function (Blueprint $table) {
            if (!Schema::hasColumn('document_versions', 'document_id')) {
                return;
            }

            try {
                $table->dropForeign(['document_id']);
            } catch (\Throwable) {
                // ignore if foreign key already removed
            }

            $table->foreign('document_id')
                  ->references('id')
                  ->on('documents')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();
        });
    }

    private function rebuildForSqlite(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

        $backup = 'document_versions_backup';
        Schema::disableForeignKeyConstraints();

        try {
            if (Schema::hasTable($backup)) {
                Schema::dropIfExists($backup);
            }

            DB::statement('ALTER TABLE document_versions RENAME TO ' . $backup);
            $indexesToDrop = [
                'document_versions_document_id_version_number_index',
                'document_versions_document_id_created_at_index',
                'document_versions_storage_driver_index',
                'document_versions_created_by_index',
                'document_versions_document_id_version_number_unique',
            ];

            foreach ($indexesToDrop as $index) {
                DB::statement('DROP INDEX IF EXISTS ' . $index);
            }

            Schema::create('document_versions', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->ulid('document_id');
                $table->integer('version_number');
                $table->string('file_path');
                $table->string('storage_driver')->default('local');
                $table->text('comment')->nullable();
                $table->json('metadata')->nullable();
                $table->ulid('created_by');
                $table->integer('reverted_from_version_number')->nullable();
                $table->timestamps();

                $table->index(['document_id', 'version_number']);
                $table->index(['document_id', 'created_at']);
                $table->index(['storage_driver']);
                $table->index(['created_by']);

                $table->foreign('document_id')
                      ->references('id')
                      ->on('documents')
                      ->cascadeOnDelete()
                      ->cascadeOnUpdate();

                $table->foreign('created_by')
                      ->references('id')
                      ->on('users')
                      ->onDelete('cascade');

                $table->unique(['document_id', 'version_number']);
            });

            DB::table('document_versions')->insertUsing(self::COPY_COLUMNS, DB::table($backup)->select(self::COPY_COLUMNS));
        } finally {
            Schema::dropIfExists($backup);
            Schema::enableForeignKeyConstraints();
        }
    }
};
