<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GAP-117 (Gate 2 T-1, O-1) — expand: project_milestones gets a nullable tenant_id (indexed with project_id), backfilled
 * from the project; rows that stored the old "overdue" status go back to "pending" (overdue is now computed live from
 * target_date, so no information is lost). up() is safe to run again.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('project_milestones', 'tenant_id')) {
            Schema::table('project_milestones', function (Blueprint $table): void {
                $table->ulid('tenant_id')->nullable()->after('id');
                $table->index(['tenant_id', 'project_id'], 'project_milestones_tenant_project_index');
            });
        }

        DB::table('project_milestones')
            ->whereNull('tenant_id')
            ->update([
                'tenant_id' => DB::raw('(SELECT projects.tenant_id FROM projects WHERE projects.id = project_milestones.project_id)'),
            ]);

        DB::table('project_milestones')->where('status', 'overdue')->update(['status' => 'pending']);
    }

    public function down(): void
    {
        if (!Schema::hasColumn('project_milestones', 'tenant_id')) {
            return;
        }

        Schema::table('project_milestones', function (Blueprint $table): void {
            $table->dropIndex('project_milestones_tenant_project_index');
            $table->dropColumn('tenant_id');
        });
    }
};
