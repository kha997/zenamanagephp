<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * Run the migrations.
     * 
     * Round 190: Add note column to store version notes/comments
     */
    public function up(): void
    {
        Schema::table('project_document_versions', function (Blueprint $table) {
            $table->text('note')->nullable()->after('file_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_document_versions', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
