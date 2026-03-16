<?php declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkTemplate;
use App\Models\WorkTemplateField;
use App\Models\WorkTemplateStep;
use App\Models\WorkTemplateVersion;
use Illuminate\Database\Seeder;

class WorkTemplateBaselineSeeder extends Seeder
{
    public const VERSION = '1.0.0';

    public const TEMPLATE_DEFINITIONS = [
        [
            'code' => 'WT-BL-DESIGN',
            'name' => 'Design',
            'description' => 'Baseline design workflow covering design intake, coordination, review, and release.',
            'steps' => [
                [
                    'key' => 'design-intake',
                    'name' => 'Capture Design Inputs',
                    'type' => 'task',
                    'order' => 1,
                    'depends_on' => [],
                    'assignee_rule' => ['role' => 'project_manager'],
                    'sla_hours' => 24,
                    'fields' => [
                        [
                            'key' => 'design_basis',
                            'label' => 'Design Basis',
                            'type' => 'text',
                            'required' => true,
                        ],
                        [
                            'key' => 'discipline',
                            'label' => 'Discipline',
                            'type' => 'enum',
                            'required' => true,
                            'enum_options' => ['architectural', 'structural', 'mep'],
                        ],
                        [
                            'key' => 'target_issue_date',
                            'label' => 'Target Issue Date',
                            'type' => 'date',
                            'required' => true,
                        ],
                    ],
                    'config' => [
                        'description' => 'Confirm scope, constraints, and reference information before design starts.',
                        'priority' => 'high',
                        'phase_key' => 'design-planning',
                        'phase_name' => 'Design Planning',
                        'phase_order' => 1,
                        'checklist_items' => [
                            ['key' => 'confirm_scope', 'label' => 'Confirm scope and client requirements', 'required' => true],
                            ['key' => 'collect_references', 'label' => 'Collect reference drawings and codes', 'required' => true],
                        ],
                        'required_docs' => [
                            ['key' => 'design-brief', 'label' => 'Design Brief', 'required' => true],
                            ['key' => 'reference-drawings', 'label' => 'Reference Drawings', 'required' => true],
                        ],
                        'assignment_rules' => [
                            'reviewers' => [],
                            'watchers' => [],
                        ],
                    ],
                ],
                [
                    'key' => 'design-coordination',
                    'name' => 'Develop and Coordinate Design Package',
                    'type' => 'task',
                    'order' => 2,
                    'depends_on' => ['design-intake'],
                    'assignee_rule' => ['role' => 'project_manager'],
                    'sla_hours' => 72,
                    'fields' => [
                        [
                            'key' => 'package_revision',
                            'label' => 'Package Revision',
                            'type' => 'string',
                            'required' => true,
                        ],
                        [
                            'key' => 'coordination_status',
                            'label' => 'Coordination Status',
                            'type' => 'enum',
                            'required' => true,
                            'enum_options' => ['in_progress', 'ready_for_review'],
                        ],
                    ],
                    'config' => [
                        'description' => 'Prepare the coordinated design package and record discipline coordination.',
                        'priority' => 'high',
                        'phase_key' => 'design-development',
                        'phase_name' => 'Design Development',
                        'phase_order' => 2,
                        'checklist_items' => [
                            ['key' => 'complete_calculations', 'label' => 'Complete calculations and markups', 'required' => true],
                            ['key' => 'run_coordination', 'label' => 'Run interdisciplinary coordination review', 'required' => true],
                        ],
                        'required_docs' => [
                            ['key' => 'calculation-package', 'label' => 'Calculation Package', 'required' => true],
                            ['key' => 'coordination-model', 'label' => 'Coordination Model', 'required' => false],
                        ],
                        'assignment_rules' => [
                            'reviewers' => [],
                            'watchers' => [],
                        ],
                    ],
                ],
                [
                    'key' => 'design-review',
                    'name' => 'Internal Design Review',
                    'type' => 'approval',
                    'order' => 3,
                    'depends_on' => ['design-coordination'],
                    'assignee_rule' => ['role' => 'project_manager'],
                    'sla_hours' => 24,
                    'fields' => [
                        [
                            'key' => 'review_decision',
                            'label' => 'Review Decision',
                            'type' => 'enum',
                            'required' => true,
                            'enum_options' => ['approved', 'revise_and_resubmit'],
                        ],
                        [
                            'key' => 'review_notes',
                            'label' => 'Review Notes',
                            'type' => 'text',
                            'required' => false,
                        ],
                    ],
                    'config' => [
                        'description' => 'Review the coordinated package before external issue.',
                        'priority' => 'high',
                        'phase_key' => 'design-review',
                        'phase_name' => 'Design Review',
                        'phase_order' => 3,
                        'checklist_items' => [
                            ['key' => 'verify_comments_closed', 'label' => 'Verify internal comments are closed', 'required' => true],
                            ['key' => 'verify_revision_clouds', 'label' => 'Verify revision clouds and notes', 'required' => true],
                        ],
                        'required_docs' => [
                            ['key' => 'review-markups', 'label' => 'Review Markups', 'required' => true],
                        ],
                        'assignment_rules' => [
                            'reviewers' => [],
                            'watchers' => [],
                        ],
                    ],
                ],
                [
                    'key' => 'issue-design-package',
                    'name' => 'Issue Design Package',
                    'type' => 'deliverable',
                    'order' => 4,
                    'depends_on' => ['design-review'],
                    'assignee_rule' => ['role' => 'project_manager'],
                    'sla_hours' => 12,
                    'fields' => [
                        [
                            'key' => 'issue_reference',
                            'label' => 'Issue Reference',
                            'type' => 'string',
                            'required' => true,
                        ],
                    ],
                    'config' => [
                        'description' => 'Issue the approved design package for downstream use.',
                        'priority' => 'medium',
                        'phase_key' => 'design-issue',
                        'phase_name' => 'Design Issue',
                        'phase_order' => 4,
                        'checklist_items' => [
                            ['key' => 'publish_package', 'label' => 'Publish approved package', 'required' => true],
                        ],
                        'required_docs' => [
                            ['key' => 'issued-drawing-set', 'label' => 'Issued Drawing Set', 'required' => true],
                            ['key' => 'transmittal', 'label' => 'Transmittal', 'required' => true],
                        ],
                        'assignment_rules' => [
                            'reviewers' => [],
                            'watchers' => [],
                        ],
                    ],
                ],
            ],
        ],
        [
            'code' => 'WT-BL-CONSTRUCTION',
            'name' => 'Construction',
            'description' => 'Baseline construction workflow covering mobilization, procurement, execution, and handover readiness.',
            'steps' => [
                [
                    'key' => 'construction-mobilization',
                    'name' => 'Mobilize Site Team',
                    'type' => 'task',
                    'order' => 1,
                    'depends_on' => [],
                    'assignee_rule' => ['role' => 'project_manager'],
                    'sla_hours' => 24,
                    'fields' => [
                        [
                            'key' => 'site_ready_date',
                            'label' => 'Site Ready Date',
                            'type' => 'date',
                            'required' => true,
                        ],
                        [
                            'key' => 'mobilization_notes',
                            'label' => 'Mobilization Notes',
                            'type' => 'text',
                            'required' => false,
                        ],
                    ],
                    'config' => [
                        'description' => 'Confirm labor, equipment, and logistics readiness before field work begins.',
                        'priority' => 'high',
                        'phase_key' => 'construction-planning',
                        'phase_name' => 'Construction Planning',
                        'phase_order' => 1,
                        'checklist_items' => [
                            ['key' => 'confirm_access', 'label' => 'Confirm site access and permits', 'required' => true],
                            ['key' => 'brief_site_team', 'label' => 'Brief site team on scope and safety', 'required' => true],
                        ],
                        'required_docs' => [
                            ['key' => 'site-logistics-plan', 'label' => 'Site Logistics Plan', 'required' => true],
                            ['key' => 'mobilization-checklist', 'label' => 'Mobilization Checklist', 'required' => true],
                        ],
                        'assignment_rules' => [
                            'reviewers' => [],
                            'watchers' => [],
                        ],
                    ],
                ],
                [
                    'key' => 'procurement-release',
                    'name' => 'Release Procurement and Submittals',
                    'type' => 'task',
                    'order' => 2,
                    'depends_on' => ['construction-mobilization'],
                    'assignee_rule' => ['role' => 'project_manager'],
                    'sla_hours' => 48,
                    'fields' => [
                        [
                            'key' => 'vendor_status',
                            'label' => 'Vendor Status',
                            'type' => 'enum',
                            'required' => true,
                            'enum_options' => ['pending', 'released', 'approved'],
                        ],
                        [
                            'key' => 'long_lead_items',
                            'label' => 'Long Lead Items',
                            'type' => 'text',
                            'required' => false,
                        ],
                    ],
                    'config' => [
                        'description' => 'Release procurement packages and confirm required submittals are in flight.',
                        'priority' => 'high',
                        'phase_key' => 'procurement',
                        'phase_name' => 'Procurement',
                        'phase_order' => 2,
                        'checklist_items' => [
                            ['key' => 'release_po', 'label' => 'Release purchase orders', 'required' => true],
                            ['key' => 'submit_materials', 'label' => 'Submit material approvals', 'required' => true],
                        ],
                        'required_docs' => [
                            ['key' => 'procurement-log', 'label' => 'Procurement Log', 'required' => true],
                            ['key' => 'material-submittal', 'label' => 'Material Submittal', 'required' => true],
                        ],
                        'assignment_rules' => [
                            'reviewers' => [],
                            'watchers' => [],
                        ],
                    ],
                ],
                [
                    'key' => 'field-execution',
                    'name' => 'Execute Field Work',
                    'type' => 'task',
                    'order' => 3,
                    'depends_on' => ['procurement-release'],
                    'assignee_rule' => ['role' => 'project_manager'],
                    'sla_hours' => 120,
                    'fields' => [
                        [
                            'key' => 'work_area',
                            'label' => 'Work Area',
                            'type' => 'string',
                            'required' => true,
                        ],
                        [
                            'key' => 'percent_complete',
                            'label' => 'Percent Complete',
                            'type' => 'number',
                            'required' => true,
                        ],
                    ],
                    'config' => [
                        'description' => 'Track construction execution progress and field quality readiness.',
                        'priority' => 'high',
                        'phase_key' => 'execution',
                        'phase_name' => 'Execution',
                        'phase_order' => 3,
                        'checklist_items' => [
                            ['key' => 'complete_daily_reports', 'label' => 'Complete daily reports', 'required' => true],
                            ['key' => 'update_as_built_notes', 'label' => 'Update as-built notes', 'required' => true],
                        ],
                        'required_docs' => [
                            ['key' => 'daily-report', 'label' => 'Daily Report', 'required' => true],
                            ['key' => 'inspection-request', 'label' => 'Inspection Request', 'required' => false],
                        ],
                        'assignment_rules' => [
                            'reviewers' => [],
                            'watchers' => [],
                        ],
                    ],
                ],
                [
                    'key' => 'handover-readiness',
                    'name' => 'Confirm Handover Readiness',
                    'type' => 'inspection',
                    'order' => 4,
                    'depends_on' => ['field-execution'],
                    'assignee_rule' => ['role' => 'project_manager'],
                    'sla_hours' => 24,
                    'fields' => [
                        [
                            'key' => 'punch_status',
                            'label' => 'Punch Status',
                            'type' => 'enum',
                            'required' => true,
                            'enum_options' => ['open', 'closed'],
                        ],
                    ],
                    'config' => [
                        'description' => 'Verify punch closure and package the final turnover evidence.',
                        'priority' => 'medium',
                        'phase_key' => 'handover',
                        'phase_name' => 'Handover',
                        'phase_order' => 4,
                        'checklist_items' => [
                            ['key' => 'close_punch_items', 'label' => 'Close punch items', 'required' => true],
                            ['key' => 'collect_turnover_docs', 'label' => 'Collect turnover documents', 'required' => true],
                        ],
                        'required_docs' => [
                            ['key' => 'as-built-package', 'label' => 'As-Built Package', 'required' => true],
                            ['key' => 'handover-checklist', 'label' => 'Handover Checklist', 'required' => true],
                        ],
                        'assignment_rules' => [
                            'reviewers' => [],
                            'watchers' => [],
                        ],
                    ],
                ],
            ],
        ],
        [
            'code' => 'WT-BL-INSPECTION',
            'name' => 'Inspection',
            'description' => 'Baseline inspection workflow covering planning, execution, reporting, and closeout.',
            'steps' => [
                [
                    'key' => 'inspection-plan',
                    'name' => 'Prepare Inspection Plan',
                    'type' => 'task',
                    'order' => 1,
                    'depends_on' => [],
                    'assignee_rule' => ['role' => 'project_manager'],
                    'sla_hours' => 12,
                    'fields' => [
                        [
                            'key' => 'inspection_type',
                            'label' => 'Inspection Type',
                            'type' => 'enum',
                            'required' => true,
                            'enum_options' => ['incoming', 'in_process', 'final'],
                        ],
                        [
                            'key' => 'planned_inspection_date',
                            'label' => 'Planned Inspection Date',
                            'type' => 'date',
                            'required' => true,
                        ],
                    ],
                    'config' => [
                        'description' => 'Define the inspection scope, timing, and acceptance criteria.',
                        'priority' => 'high',
                        'phase_key' => 'inspection-planning',
                        'phase_name' => 'Inspection Planning',
                        'phase_order' => 1,
                        'checklist_items' => [
                            ['key' => 'define_acceptance_criteria', 'label' => 'Define acceptance criteria', 'required' => true],
                            ['key' => 'confirm_witnesses', 'label' => 'Confirm witnesses and hold points', 'required' => true],
                        ],
                        'required_docs' => [
                            ['key' => 'itp', 'label' => 'Inspection and Test Plan', 'required' => true],
                            ['key' => 'approved_method_statement', 'label' => 'Approved Method Statement', 'required' => false],
                        ],
                        'assignment_rules' => [
                            'reviewers' => [],
                            'watchers' => [],
                        ],
                    ],
                ],
                [
                    'key' => 'perform-inspection',
                    'name' => 'Perform Field Inspection',
                    'type' => 'inspection',
                    'order' => 2,
                    'depends_on' => ['inspection-plan'],
                    'assignee_rule' => ['role' => 'project_manager'],
                    'sla_hours' => 24,
                    'fields' => [
                        [
                            'key' => 'inspection_result',
                            'label' => 'Inspection Result',
                            'type' => 'enum',
                            'required' => true,
                            'enum_options' => ['pass', 'fail', 'conditional_pass'],
                        ],
                        [
                            'key' => 'observations',
                            'label' => 'Observations',
                            'type' => 'text',
                            'required' => false,
                        ],
                    ],
                    'config' => [
                        'description' => 'Inspect installed work and record observations against the acceptance criteria.',
                        'priority' => 'high',
                        'phase_key' => 'inspection-execution',
                        'phase_name' => 'Inspection Execution',
                        'phase_order' => 2,
                        'checklist_items' => [
                            ['key' => 'capture_measurements', 'label' => 'Capture measurements and photos', 'required' => true],
                            ['key' => 'record_nonconformances', 'label' => 'Record nonconformances', 'required' => true],
                        ],
                        'required_docs' => [
                            ['key' => 'inspection-report', 'label' => 'Inspection Report', 'required' => true],
                            ['key' => 'photo-log', 'label' => 'Photo Log', 'required' => false],
                        ],
                        'assignment_rules' => [
                            'reviewers' => [],
                            'watchers' => [],
                        ],
                    ],
                ],
                [
                    'key' => 'inspection-closeout',
                    'name' => 'Close Inspection Findings',
                    'type' => 'approval',
                    'order' => 3,
                    'depends_on' => ['perform-inspection'],
                    'assignee_rule' => ['role' => 'project_manager'],
                    'sla_hours' => 24,
                    'fields' => [
                        [
                            'key' => 'closeout_status',
                            'label' => 'Closeout Status',
                            'type' => 'enum',
                            'required' => true,
                            'enum_options' => ['open', 'closed'],
                        ],
                        [
                            'key' => 'closeout_comments',
                            'label' => 'Closeout Comments',
                            'type' => 'text',
                            'required' => false,
                        ],
                    ],
                    'config' => [
                        'description' => 'Verify corrective actions and close the inspection cycle.',
                        'priority' => 'medium',
                        'phase_key' => 'inspection-closeout',
                        'phase_name' => 'Inspection Closeout',
                        'phase_order' => 3,
                        'checklist_items' => [
                            ['key' => 'verify_corrections', 'label' => 'Verify corrective actions', 'required' => true],
                            ['key' => 'archive_evidence', 'label' => 'Archive final inspection evidence', 'required' => true],
                        ],
                        'required_docs' => [
                            ['key' => 'closeout-record', 'label' => 'Closeout Record', 'required' => true],
                        ],
                        'assignment_rules' => [
                            'reviewers' => [],
                            'watchers' => [],
                        ],
                    ],
                ],
            ],
        ],
    ];

    public function run(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('work_templates')) {
            return;
        }

        Tenant::query()
            ->orderBy('created_at')
            ->get()
            ->each(function (Tenant $tenant): void {
                $actorId = User::query()
                    ->where('tenant_id', (string) $tenant->id)
                    ->orderBy('created_at')
                    ->value('id');

                foreach (self::TEMPLATE_DEFINITIONS as $definition) {
                    $this->seedTemplateForTenant((string) $tenant->id, $actorId ? (string) $actorId : null, $definition);
                }
            });
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function seedTemplateForTenant(string $tenantId, ?string $actorId, array $definition): void
    {
        $template = WorkTemplate::query()->firstOrNew([
            'tenant_id' => $tenantId,
            'code' => (string) $definition['code'],
        ]);

        if (!$template->exists) {
            $template->created_by = $actorId;
        }

        $template->fill([
            'name' => (string) $definition['name'],
            'description' => $definition['description'] ?? null,
            'status' => 'published',
            'updated_by' => $actorId,
        ]);
        $template->save();

        $version = WorkTemplateVersion::query()->firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'work_template_id' => (string) $template->id,
                'semver' => self::VERSION,
            ],
            [
                'content_json' => $this->buildContent($definition),
                'is_immutable' => true,
                'published_at' => now(),
                'published_by' => $actorId,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]
        );

        if (!WorkTemplateStep::query()
            ->where('tenant_id', $tenantId)
            ->where('work_template_version_id', (string) $version->id)
            ->exists()) {
            $this->seedStepsAndFields($version, $definition['steps']);
        }
    }

    /**
     * @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    private function buildContent(array $definition): array
    {
        return [
            'steps' => $definition['steps'],
            'approvals' => [],
            'rules' => [],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $steps
     */
    private function seedStepsAndFields(WorkTemplateVersion $version, array $steps): void
    {
        foreach ($steps as $stepData) {
            $step = WorkTemplateStep::query()->create([
                'tenant_id' => (string) $version->tenant_id,
                'work_template_version_id' => (string) $version->id,
                'step_key' => (string) $stepData['key'],
                'name' => $stepData['name'] ?? null,
                'type' => (string) ($stepData['type'] ?? 'task'),
                'step_order' => (int) ($stepData['order'] ?? 1),
                'depends_on' => $stepData['depends_on'] ?? [],
                'assignee_rule_json' => $stepData['assignee_rule'] ?? null,
                'sla_hours' => isset($stepData['sla_hours']) ? (int) $stepData['sla_hours'] : null,
                'config_json' => $stepData['config'] ?? null,
            ]);

            foreach (($stepData['fields'] ?? []) as $fieldData) {
                WorkTemplateField::query()->create([
                    'tenant_id' => (string) $version->tenant_id,
                    'work_template_step_id' => (string) $step->id,
                    'field_key' => (string) $fieldData['key'],
                    'label' => (string) ($fieldData['label'] ?? $fieldData['key']),
                    'type' => (string) ($fieldData['type'] ?? 'string'),
                    'is_required' => (bool) ($fieldData['required'] ?? false),
                    'default_value' => isset($fieldData['default'])
                        ? (is_scalar($fieldData['default']) ? (string) $fieldData['default'] : json_encode($fieldData['default']))
                        : null,
                    'validation_json' => is_array($fieldData['validation'] ?? null) ? $fieldData['validation'] : null,
                    'enum_options_json' => is_array($fieldData['enum_options'] ?? null) ? $fieldData['enum_options'] : null,
                    'visibility_rule_json' => is_array($fieldData['visibility_rule'] ?? null) ? $fieldData['visibility_rule'] : null,
                ]);
            }
        }
    }
}
