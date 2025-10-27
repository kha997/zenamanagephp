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
        Schema::table('template_versions', function (Blueprint $table) {
            if (!Schema::hasColumn('template_versions', 'changes')) {
                $table->json('changes')->nullable()->after('template_data');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('template_versions', function (Blueprint $table) {
            if (Schema::hasColumn('template_versions', 'changes')) {
                $table->dropColumn('changes');
            }
        });
    }
};
