<?php declare(strict_types=1);

namespace Tests\Feature\Api\Concerns;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkTemplate;
use App\Models\WorkTemplateChecklistItem;
use App\Models\WorkTemplatePhase;
use App\Models\WorkTemplateRequiredDocument;
use App\Models\WorkTemplateTask;
use App\Models\WorkTemplateTaskAssignment;
use App\Models\WorkTemplateTrigger;
use App\Models\WorkTemplateVersion;
use App\Models\WorkTemplateStep;
use Tests\Traits\RouteNameTrait;
use Tests\Traits\TenantUserFactoryTrait;

trait InteractsWithWorkTemplateV2
{
    use RouteNameTrait;
    use TenantUserFactoryTrait;

    protected function setUpWorkTemplateV2Routes(): void
    {
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
    }

    protected function authHeaders(User $user): array
    {
        $token = $user->createToken('test-token')->plainTextToken;

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Tenant-ID' => (string) $user->tenant_id,
            'Authorization' => 'Bearer ' . $token,
        ];
    }

    protected function workTemplateRoute(string $name, array $parameters = []): string
    {
        return route('api.zena.work-templates.' . $name, $parameters, false);
    }

    protected function workTemplateV2Payload(string $code = 'WT-V2-001'): array
    {
        return [
            'code' => $code,
            'name' => 'Design Baseline',
            'description' => 'Template for v2 structure',
            'phases' => [
                [
                    'key' => 'design',
                    'name' => 'Design',
                    'order' => 1,
                    'default_offset_days' => 0,
                    'tasks' => [
                        [
                            'key' => 'submit-drawings',
                            'name' => 'Submit Drawings',
                            'task_type' => 'standard',
                            'order' => 1,
                            'default_duration_days' => 5,
                            'is_required' => true,
                            'checklist_items' => [
                                [
                                    'key' => 'cover-sheet',
                                    'label' => 'Cover sheet attached',
                                    'order' => 1,
                                    'is_required' => true,
                                ],
                            ],
                            'required_documents' => [
                                [
                                    'key' => 'design-drawing',
                                    'document_type' => 'drawing',
                                    'name' => 'Design Drawing',
                                    'order' => 1,
                                    'is_required' => true,
                                    'checklist_item_key' => 'cover-sheet',
                                ],
                            ],
                            'assignments' => [
                                [
                                    'key' => 'designer-owner',
                                    'assignment_type' => 'assignee',
                                    'role_code' => 'designer',
                                    'is_required' => true,
                                ],
                                [
                                    'key' => 'pm-approval',
                                    'assignment_type' => 'approver',
                                    'role_code' => 'project_manager',
                                    'approval_order' => 1,
                                    'is_required' => true,
                                ],
                            ],
                            'triggers' => [
                                [
                                    'key' => 'notify-pm',
                                    'event' => 'task.completed',
                                    'action' => 'notify_role',
                                    'trigger_order' => 1,
                                    'payload' => [
                                        'role_code' => 'project_manager',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function seedV2Template(Tenant $tenant, User $user, string $code = 'WT-V2-SEED'): array
    {
        $template = WorkTemplate::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'code' => $code,
            'name' => 'Seeded V2 Template',
            'description' => 'Seeded template',
            'status' => 'draft',
            'created_by' => (string) $user->id,
            'updated_by' => (string) $user->id,
        ]);

        $version = WorkTemplateVersion::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'work_template_id' => (string) $template->id,
            'semver' => 'draft-seeded',
            'schema_version' => 2,
            'content_json' => [
                'phases' => $this->workTemplateV2Payload($code)['phases'],
                'steps' => [],
                'approvals' => [],
                'rules' => [],
            ],
            'is_immutable' => false,
            'created_by' => (string) $user->id,
            'updated_by' => (string) $user->id,
        ]);

        $phase = WorkTemplatePhase::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'work_template_version_id' => (string) $version->id,
            'phase_key' => 'design',
            'name' => 'Design',
            'phase_order' => 1,
            'created_by' => (string) $user->id,
            'updated_by' => (string) $user->id,
        ]);

        WorkTemplateStep::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'work_template_version_id' => (string) $version->id,
            'step_key' => 'submit-drawings',
            'name' => 'Submit Drawings',
            'type' => 'task',
            'step_order' => 1,
            'depends_on' => [],
            'assignee_rule_json' => ['role' => 'project_manager'],
            'config_json' => [
                'phase_key' => 'design',
                'phase_name' => 'Design',
                'checklist_items' => [
                    ['key' => 'cover-sheet', 'label' => 'Cover sheet attached', 'required' => true],
                ],
                'required_docs' => [
                    ['key' => 'design-drawing', 'label' => 'Design Drawing', 'required' => true],
                ],
            ],
        ]);

        $task = WorkTemplateTask::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'work_template_phase_id' => (string) $phase->id,
            'task_key' => 'submit-drawings',
            'name' => 'Submit Drawings',
            'task_type' => 'standard',
            'task_order' => 1,
            'created_by' => (string) $user->id,
            'updated_by' => (string) $user->id,
        ]);

        $checklist = WorkTemplateChecklistItem::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'work_template_task_id' => (string) $task->id,
            'checklist_key' => 'cover-sheet',
            'label' => 'Cover sheet attached',
            'item_order' => 1,
            'created_by' => (string) $user->id,
            'updated_by' => (string) $user->id,
        ]);

        WorkTemplateRequiredDocument::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'work_template_task_id' => (string) $task->id,
            'work_template_checklist_item_id' => (string) $checklist->id,
            'doc_key' => 'design-drawing',
            'document_type' => 'drawing',
            'name' => 'Design Drawing',
            'doc_order' => 1,
            'created_by' => (string) $user->id,
            'updated_by' => (string) $user->id,
        ]);

        WorkTemplateTaskAssignment::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'work_template_task_id' => (string) $task->id,
            'assignment_key' => 'designer-owner',
            'assignment_type' => 'assignee',
            'role_code' => 'designer',
            'created_by' => (string) $user->id,
            'updated_by' => (string) $user->id,
        ]);

        WorkTemplateTrigger::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'work_template_task_id' => (string) $task->id,
            'trigger_key' => 'notify-pm',
            'event' => 'task.completed',
            'action' => 'notify_role',
            'trigger_order' => 1,
            'created_by' => (string) $user->id,
            'updated_by' => (string) $user->id,
        ]);

        return [$template, $version];
    }
}
