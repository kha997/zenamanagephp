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
        Schema::table('qc_inspections', function (Blueprint $table) {
            if (! Schema::hasColumn('qc_inspections', 'project_id')) {
                $table->ulid('project_id')->nullable()->index()->after('tenant_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('qc_inspections', function (Blueprint $table) {
            if (Schema::hasColumn('qc_inspections', 'project_id')) {
                $table->dropColumn('project_id');
            }
        });
    }
};
