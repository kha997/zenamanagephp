<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_instances', function (Blueprint $table): void {
            if (!Schema::hasColumn('work_instances', 'scope_type')) {
                $table->string('scope_type')->default('project')->after('project_id');
            }

            if (!Schema::hasColumn('work_instances', 'scope_id')) {
                $table->string('scope_id')->nullable()->after('scope_type');
            }

            if (!Schema::hasColumn('work_instances', 'apply_fingerprint')) {
                $table->string('apply_fingerprint')->nullable()->after('status');
            }
        });

        Schema::table('work_instances', function (Blueprint $table): void {
            if (!Schema::hasColumn('work_instances', 'scope_id')) {
                return;
            }

            $table->index(['tenant_id', 'scope_type', 'scope_id'], 'wi_tenant_scope_index');
            $table->unique(['tenant_id', 'apply_fingerprint'], 'wi_tenant_apply_fingerprint_unique');
        });

        Schema::table('tasks', function (Blueprint $table): void {
            if (!Schema::hasColumn('tasks', 'work_instance_id')) {
                $table->string('work_instance_id')->nullable()->after('component_id');
            }

            if (!Schema::hasColumn('tasks', 'work_instance_step_id')) {
                $table->string('work_instance_step_id')->nullable()->after('work_instance_id');
            }
        });

        Schema::table('tasks', function (Blueprint $table): void {
            if (Schema::hasColumn('tasks', 'work_instance_id')) {
                $table->foreign('work_instance_id')->references('id')->on('work_instances')->nullOnDelete();
                $table->index(['tenant_id', 'work_instance_id'], 'tasks_tenant_work_instance_index');
            }

            if (Schema::hasColumn('tasks', 'work_instance_step_id')) {
                $table->foreign('work_instance_step_id')->references('id')->on('work_instance_steps')->nullOnDelete();
                $table->unique(['work_instance_step_id'], 'tasks_work_instance_step_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            if (Schema::hasColumn('tasks', 'work_instance_step_id')) {
                $table->dropForeign(['work_instance_step_id']);
                $table->dropUnique('tasks_work_instance_step_unique');
                $table->dropColumn('work_instance_step_id');
            }

            if (Schema::hasColumn('tasks', 'work_instance_id')) {
                $table->dropForeign(['work_instance_id']);
                $table->dropIndex('tasks_tenant_work_instance_index');
                $table->dropColumn('work_instance_id');
            }
        });

        Schema::table('work_instances', function (Blueprint $table): void {
            if (Schema::hasColumn('work_instances', 'apply_fingerprint')) {
                $table->dropUnique('wi_tenant_apply_fingerprint_unique');
                $table->dropColumn('apply_fingerprint');
            }

            if (Schema::hasColumn('work_instances', 'scope_id')) {
                $table->dropIndex('wi_tenant_scope_index');
                $table->dropColumn('scope_id');
            }

            if (Schema::hasColumn('work_instances', 'scope_type')) {
                $table->dropColumn('scope_type');
            }
        });
    }
};
