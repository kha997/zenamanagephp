<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createOrUpdateWorkTemplatesTable();

        Schema::create('work_template_versions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('tenant_id');
            $table->string('work_template_id');
            $table->string('semver');
            $table->json('content_json');
            $table->boolean('is_immutable')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->string('published_by')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('work_template_id')->references('id')->on('work_templates')->cascadeOnDelete();
            $table->foreign('published_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->unique(['work_template_id', 'semver'], 'wt_versions_template_semver_unique');
            $table->index(['tenant_id', 'work_template_id'], 'wt_versions_tenant_template_index');
            $table->index(['tenant_id', 'published_at'], 'wt_versions_tenant_published_index');
        });

        Schema::create('work_template_steps', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('tenant_id');
            $table->string('work_template_version_id');
            $table->string('step_key');
            $table->string('name')->nullable();
            $table->string('type');
            $table->unsignedInteger('step_order');
            $table->json('depends_on')->nullable();
            $table->json('assignee_rule_json')->nullable();
            $table->unsignedInteger('sla_hours')->nullable();
            $table->json('config_json')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('work_template_version_id')->references('id')->on('work_template_versions')->cascadeOnDelete();
            $table->unique(['work_template_version_id', 'step_key'], 'wt_steps_version_step_key_unique');
            $table->index(['work_template_version_id', 'step_order'], 'wt_steps_version_order_index');
        });

        Schema::create('work_template_fields', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('tenant_id');
            $table->string('work_template_step_id');
            $table->string('field_key');
            $table->string('label');
            $table->string('type');
            $table->boolean('is_required')->default(false);
            $table->text('default_value')->nullable();
            $table->json('validation_json')->nullable();
            $table->json('enum_options_json')->nullable();
            $table->json('visibility_rule_json')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('work_template_step_id')->references('id')->on('work_template_steps')->cascadeOnDelete();
            $table->unique(['work_template_step_id', 'field_key'], 'wt_fields_step_field_unique');
        });

        Schema::create('work_instances', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('tenant_id');
            $table->string('project_id');
            $table->string('work_template_version_id');
            $table->string('status')->default('pending');
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('work_template_version_id')->references('id')->on('work_template_versions')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['tenant_id', 'project_id'], 'wi_tenant_project_index');
        });

        Schema::create('work_instance_steps', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('tenant_id');
            $table->string('work_instance_id');
            $table->string('work_template_step_id')->nullable();
            $table->string('step_key');
            $table->string('name')->nullable();
            $table->string('type');
            $table->unsignedInteger('step_order');
            $table->json('depends_on')->nullable();
            $table->json('assignee_rule_json')->nullable();
            $table->unsignedInteger('sla_hours')->nullable();
            $table->json('snapshot_fields_json')->nullable();
            $table->string('status')->default('pending');
            $table->string('assignee_id')->nullable();
            $table->timestamp('deadline_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('work_instance_id')->references('id')->on('work_instances')->cascadeOnDelete();
            $table->foreign('work_template_step_id')->references('id')->on('work_template_steps')->nullOnDelete();
            $table->foreign('assignee_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['work_instance_id', 'step_order'], 'wi_steps_instance_order_index');
        });

        Schema::create('work_instance_field_values', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('tenant_id');
            $table->string('work_instance_step_id');
            $table->string('field_key');
            $table->text('value_string')->nullable();
            $table->decimal('value_number', 18, 4)->nullable();
            $table->date('value_date')->nullable();
            $table->dateTime('value_datetime')->nullable();
            $table->json('value_json')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('work_instance_step_id')->references('id')->on('work_instance_steps')->cascadeOnDelete();
            $table->unique(['work_instance_step_id', 'field_key'], 'wi_field_values_step_field_unique');
        });

        Schema::create('approvals', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('tenant_id');
            $table->string('work_instance_step_id');
            $table->string('decision');
            $table->text('comment')->nullable();
            $table->string('requested_by')->nullable();
            $table->string('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('work_instance_step_id')->references('id')->on('work_instance_steps')->cascadeOnDelete();
            $table->foreign('requested_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['tenant_id', 'work_instance_step_id'], 'approvals_tenant_step_index');
        });

        Schema::create('deliverable_templates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('tenant_id');
            $table->string('work_template_id')->nullable();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('draft');
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('work_template_id')->references('id')->on('work_templates')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->unique(['tenant_id', 'code'], 'deliverable_templates_tenant_code_unique');
        });

        Schema::create('deliverable_template_versions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('tenant_id');
            $table->string('deliverable_template_id');
            $table->string('version');
            $table->string('document_id')->nullable();
            $table->string('document_version_id')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('published_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('deliverable_template_id')->references('id')->on('deliverable_templates')->cascadeOnDelete();
            $table->foreign('document_id')->references('id')->on('documents')->nullOnDelete();
            $table->foreign('document_version_id')->references('id')->on('document_versions')->nullOnDelete();
            $table->foreign('published_by')->references('id')->on('users')->nullOnDelete();
            $table->unique(['deliverable_template_id', 'version'], 'dt_versions_template_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliverable_template_versions');
        Schema::dropIfExists('deliverable_templates');
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('work_instance_field_values');
        Schema::dropIfExists('work_instance_steps');
        Schema::dropIfExists('work_instances');
        Schema::dropIfExists('work_template_fields');
        Schema::dropIfExists('work_template_steps');
        Schema::dropIfExists('work_template_versions');
        Schema::dropIfExists('work_templates');
    }

    private function createOrUpdateWorkTemplatesTable(): void
    {
        if (!Schema::hasTable('work_templates')) {
            Schema::create('work_templates', function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->string('tenant_id');
                $table->string('code');
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('status')->default('draft');
                $table->string('created_by')->nullable();
                $table->string('updated_by')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
                $table->unique(['tenant_id', 'code'], 'work_templates_tenant_code_unique');
                $table->index(['tenant_id', 'status'], 'work_templates_tenant_status_index');
            });

            return;
        }

        Schema::table('work_templates', function (Blueprint $table): void {
            if (!Schema::hasColumn('work_templates', 'tenant_id')) {
                $table->string('tenant_id')->nullable()->after('id');
            }

            if (!Schema::hasColumn('work_templates', 'code')) {
                $table->string('code')->nullable()->after('tenant_id');
            }

            if (!Schema::hasColumn('work_templates', 'status')) {
                $table->string('status')->default('draft');
            }

            if (!Schema::hasColumn('work_templates', 'name')) {
                $table->string('name')->default('Untitled Work Template');
            }

            if (!Schema::hasColumn('work_templates', 'description')) {
                $table->text('description')->nullable();
            }

            if (!Schema::hasColumn('work_templates', 'created_by')) {
                $table->string('created_by')->nullable();
            }

            if (!Schema::hasColumn('work_templates', 'updated_by')) {
                $table->string('updated_by')->nullable();
            }
        });
    }
};
