<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qc_inspections', function (Blueprint $table): void {
            if (!Schema::hasColumn('qc_inspections', 'work_instance_step_id')) {
                $table->string('work_instance_step_id')->nullable()->after('inspector_id');
                $table->foreign('work_instance_step_id')->references('id')->on('work_instance_steps')->nullOnDelete();
                $table->index(['tenant_id', 'work_instance_step_id'], 'qc_inspections_tenant_step_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('qc_inspections', function (Blueprint $table): void {
            if (Schema::hasColumn('qc_inspections', 'work_instance_step_id')) {
                $table->dropForeign(['work_instance_step_id']);
                $table->dropIndex('qc_inspections_tenant_step_index');
                $table->dropColumn('work_instance_step_id');
            }
        });
    }
};
