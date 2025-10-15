<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (!Schema::hasColumn('documents', 'file_type')) {
                $table->string('file_type')->default('pdf')->after('file_path');
            }
        });
        
        // Update existing records to have file_type
        DB::table('documents')->whereNull('file_type')->update(['file_type' => 'pdf']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasColumn('documents', 'file_type')) {
                $table->dropColumn('file_type');
            }
        });
    }
};