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
            if (!Schema::hasColumn('template_versions', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('template_versions', function (Blueprint $table) {
            if (Schema::hasColumn('template_versions', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
};
