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
            if (!Schema::hasColumn('template_versions', 'name')) {
                $table->string('name')->nullable()->after('version');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('template_versions', function (Blueprint $table) {
            if (Schema::hasColumn('template_versions', 'name')) {
                $table->dropColumn('name');
            }
        });
    }
};
