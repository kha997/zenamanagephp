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
            if (!Schema::hasColumn('template_versions', 'template_data')) {
                $table->json('template_data')->nullable()->after('description');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('template_versions', function (Blueprint $table) {
            if (Schema::hasColumn('template_versions', 'template_data')) {
                $table->dropColumn('template_data');
            }
        });
    }
};
