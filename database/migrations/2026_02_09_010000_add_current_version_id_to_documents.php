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
            return;
        }

        if (Schema::hasColumn('documents', 'current_version_id')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        Schema::table('documents', function (Blueprint $table) use ($driver) {
            $table->ulid('current_version_id')->nullable()->after('parent_document_id');
            $table->index('current_version_id', 'documents_current_version_index');

            if ($driver !== 'sqlite') {
                $table->foreign('current_version_id')
                      ->references('id')
                      ->on('document_versions')
                      ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('documents')) {
            return;
        }

        if (!Schema::hasColumn('documents', 'current_version_id')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        Schema::table('documents', function (Blueprint $table) use ($driver) {
            if ($driver !== 'sqlite') {
                try {
                    $table->dropForeign(['current_version_id']);
                } catch (\Throwable) {
                    // ignore if constraint missing
                }
            }

            $table->dropIndex('documents_current_version_index');
            $table->dropColumn('current_version_id');
        });
    }
};
