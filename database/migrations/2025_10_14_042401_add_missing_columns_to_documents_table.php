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
        Schema::table('documents', function (Blueprint $table) {
            if (!Schema::hasColumn('documents', 'uploaded_by')) {
                $table->ulid('uploaded_by')->nullable()->after('created_by');
                $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasColumn('documents', 'uploaded_by')) {
                $table->dropForeign(['uploaded_by']);
                $table->dropColumn('uploaded_by');
            }
        });
    }
};