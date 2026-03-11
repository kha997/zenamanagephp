<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('zena_documents', function (Blueprint $table) {
            $table->foreign('parent_document_id')->references('id')->on('zena_documents')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (! Schema::hasTable('zena_documents')) {
            return;
        }

        if (! Schema::hasColumn('zena_documents', 'parent_document_id')) {
            return;
        }

        try {
            Schema::table('zena_documents', function (Blueprint $table) {
                $table->dropForeign(['parent_document_id']);
            });
        } catch (\Throwable $e) {
            // Ignore missing foreign key in partial rollback states.
        }
    }
};
