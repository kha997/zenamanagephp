<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_templates', function (Blueprint $table): void {
            if (!Schema::hasColumn('work_templates', 'deleted_at')) {
                $table->softDeletes();
            }

            if (!Schema::hasColumn('work_templates', 'deleted_by')) {
                $table->string('deleted_by')->nullable()->after('updated_by');
                $table->foreign('deleted_by')->references('id')->on('users')->nullOnDelete();
            }
        });

        Schema::table('work_template_versions', function (Blueprint $table): void {
            if (!Schema::hasColumn('work_template_versions', 'schema_version')) {
                // Default to 1 (legacy steps-based schema): any row inserted
                // without explicitly setting this column — direct factory
                // creates in existing V1 tests, older code paths — has
                // `content_json.steps` populated, not `phases`, so it must
                // be treated as V1 by WorkTemplateCrudService::cloneVersionStructure()
                // et al. Only requests that actually send `phases` get 2,
                // via WorkTemplateCrudService::resolveSchemaVersion().
                $table->unsignedTinyInteger('schema_version')->default(1)->after('semver');
            }

            if (!Schema::hasColumn('work_template_versions', 'source_version_id')) {
                $table->string('source_version_id')->nullable()->after('published_by');
                $table->foreign('source_version_id')->references('id')->on('work_template_versions')->nullOnDelete();
                $table->index(['tenant_id', 'work_template_id', 'schema_version'], 'wt_versions_tenant_template_schema_index');
            }
        });

        Schema::create('work_template_phases', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('tenant_id');
            $table->string('work_template_version_id');
            $table->string('phase_key');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('phase_order');
            $table->unsignedInteger('default_offset_days')->nullable();
            $table->json('config_json')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('work_template_version_id')->references('id')->on('work_template_versions')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->unique(['work_template_version_id', 'phase_key'], 'wt_phases_version_key_unique');
            $table->index(['work_template_version_id', 'phase_order'], 'wt_phases_version_order_index');
            $table->index(['tenant_id', 'work_template_version_id'], 'wt_phases_tenant_version_index');
        });

        Schema::create('work_template_tasks', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('tenant_id');
            $table->string('work_template_phase_id');
            $table->string('task_key');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('task_type');
            $table->unsignedInteger('task_order');
            $table->unsignedInteger('default_duration_days')->nullable();
            $table->boolean('is_required')->default(true);
            $table->json('config_json')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('work_template_phase_id')->references('id')->on('work_template_phases')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->unique(['work_template_phase_id', 'task_key'], 'wt_tasks_phase_key_unique');
            $table->index(['work_template_phase_id', 'task_order'], 'wt_tasks_phase_order_index');
            $table->index(['tenant_id', 'work_template_phase_id'], 'wt_tasks_tenant_phase_index');
        });

        Schema::create('work_template_checklist_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('tenant_id');
            $table->string('work_template_task_id');
            $table->string('checklist_key');
            $table->string('label');
            $table->text('help_text')->nullable();
            $table->unsignedInteger('item_order');
            $table->boolean('is_required')->default(true);
            $table->json('validation_json')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('work_template_task_id')->references('id')->on('work_template_tasks')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->unique(['work_template_task_id', 'checklist_key'], 'wt_checklists_task_key_unique');
            $table->index(['work_template_task_id', 'item_order'], 'wt_checklists_task_order_index');
            $table->index(['tenant_id', 'work_template_task_id'], 'wt_checklists_tenant_task_index');
        });

        Schema::create('work_template_required_documents', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('tenant_id');
            $table->string('work_template_task_id');
            $table->string('work_template_checklist_item_id')->nullable();
            $table->string('doc_key');
            $table->string('document_type');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('doc_order');
            $table->boolean('is_required')->default(true);
            $table->json('rules_json')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('work_template_task_id')->references('id')->on('work_template_tasks')->cascadeOnDelete();
            $table->foreign('work_template_checklist_item_id', 'wt_req_docs_checklist_fk')
                ->references('id')->on('work_template_checklist_items')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->unique(['work_template_task_id', 'doc_key'], 'wt_req_docs_task_key_unique');
            $table->index(['work_template_task_id', 'doc_order'], 'wt_req_docs_task_order_index');
            $table->index(['tenant_id', 'work_template_task_id'], 'wt_req_docs_tenant_task_index');
            $table->index('work_template_checklist_item_id', 'wt_req_docs_checklist_index');
        });

        Schema::create('work_template_task_assignments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('tenant_id');
            $table->string('work_template_task_id');
            $table->string('assignment_key');
            $table->string('assignment_type');
            $table->string('role_code');
            $table->unsignedInteger('approval_order')->nullable();
            $table->boolean('is_required')->default(true);
            $table->json('conditions_json')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('work_template_task_id')->references('id')->on('work_template_tasks')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->unique(['work_template_task_id', 'assignment_key'], 'wt_assignments_task_key_unique');
            $table->index(['work_template_task_id', 'assignment_type'], 'wt_assignments_task_type_index');
            $table->index(['tenant_id', 'work_template_task_id'], 'wt_assignments_tenant_task_index');
        });

        Schema::create('work_template_triggers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('tenant_id');
            $table->string('work_template_task_id');
            $table->string('trigger_key');
            $table->string('event');
            $table->string('action');
            $table->unsignedInteger('trigger_order');
            $table->boolean('is_active')->default(true);
            $table->json('conditions_json')->nullable();
            $table->json('payload_json')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('work_template_task_id')->references('id')->on('work_template_tasks')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->unique(['work_template_task_id', 'trigger_key'], 'wt_triggers_task_key_unique');
            $table->index(['work_template_task_id', 'trigger_order'], 'wt_triggers_task_order_index');
            $table->index(['tenant_id', 'work_template_task_id'], 'wt_triggers_tenant_task_index');
            $table->index(['event', 'action'], 'wt_triggers_event_action_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_template_triggers');
        Schema::dropIfExists('work_template_task_assignments');
        Schema::dropIfExists('work_template_required_documents');
        Schema::dropIfExists('work_template_checklist_items');
        Schema::dropIfExists('work_template_tasks');
        Schema::dropIfExists('work_template_phases');

        Schema::table('work_template_versions', function (Blueprint $table): void {
            if (Schema::hasColumn('work_template_versions', 'source_version_id')) {
                $table->dropForeign(['source_version_id']);
                $table->dropIndex('wt_versions_tenant_template_schema_index');
                $table->dropColumn('source_version_id');
            }

            if (Schema::hasColumn('work_template_versions', 'schema_version')) {
                $table->dropColumn('schema_version');
            }
        });

        Schema::table('work_templates', function (Blueprint $table): void {
            if (Schema::hasColumn('work_templates', 'deleted_by')) {
                $table->dropForeign(['deleted_by']);
                $table->dropColumn('deleted_by');
            }

            if (Schema::hasColumn('work_templates', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
