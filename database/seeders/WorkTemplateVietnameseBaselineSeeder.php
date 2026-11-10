<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkTemplate;
use App\Models\WorkTemplateField;
use App\Models\WorkTemplateStep;
use App\Models\WorkTemplateVersion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * GAP-123 (Gate 2 S-1) — three published Vietnamese work templates for every company: the same steps, order,
 * dependencies, durations, types and custom field keys as the English baselines (WorkTemplateBaselineSeeder), with
 * Vietnamese names, descriptions, checklist items and required documents. The English templates are left as they are.
 * Idempotent: a company that already has a template with one of these codes is not touched.
 */
class WorkTemplateVietnameseBaselineSeeder extends Seeder
{
    public const VERSION = '1.0.0';

    private const PM = ['role' => 'project_manager'];

    private const NO_EXTRA_ASSIGNEES = ['reviewers' => [], 'watchers' => []];

    public const TEMPLATE_DEFINITIONS = [
        [
            'code' => 'WT-VN-THIETKE',
            'name' => 'Thiết kế',
            'description' => 'Quy trình thiết kế chuẩn: thu thập đầu vào, triển khai và phối hợp, duyệt nội bộ, phát hành hồ sơ.',
            'steps' => [
                [
                    'key' => 'design-intake', 'name' => 'Thu thập đầu vào thiết kế', 'type' => 'task', 'order' => 1, 'depends_on' => [],
                    'assignee_rule' => self::PM, 'sla_hours' => 24,
                    'fields' => [
                        ['key' => 'design_basis', 'label' => 'Cơ sở thiết kế', 'type' => 'text', 'required' => true],
                        ['key' => 'discipline', 'label' => 'Bộ môn', 'type' => 'enum', 'required' => true, 'enum_options' => ['architectural', 'structural', 'mep']],
                        ['key' => 'target_issue_date', 'label' => 'Ngày dự kiến phát hành', 'type' => 'date', 'required' => true],
                    ],
                    'config' => [
                        'description' => 'Xác nhận phạm vi, ràng buộc và tài liệu tham khảo trước khi bắt đầu thiết kế.',
                        'priority' => 'high', 'phase_key' => 'design-planning', 'phase_name' => 'Lập kế hoạch thiết kế', 'phase_order' => 1,
                        'checklist_items' => [
                            ['key' => 'confirm_scope', 'label' => 'Xác nhận phạm vi và yêu cầu của chủ nhà', 'required' => true],
                            ['key' => 'collect_references', 'label' => 'Thu thập bản vẽ tham khảo và tiêu chuẩn', 'required' => true],
                        ],
                        'required_docs' => [
                            ['key' => 'design-brief', 'label' => 'Nhiệm vụ thiết kế', 'required' => true],
                            ['key' => 'reference-drawings', 'label' => 'Bản vẽ tham khảo', 'required' => true],
                        ],
                        'assignment_rules' => self::NO_EXTRA_ASSIGNEES,
                    ],
                ],
                [
                    'key' => 'design-coordination', 'name' => 'Triển khai và phối hợp hồ sơ thiết kế', 'type' => 'task', 'order' => 2,
                    'depends_on' => ['design-intake'], 'assignee_rule' => self::PM, 'sla_hours' => 72,
                    'fields' => [
                        ['key' => 'package_revision', 'label' => 'Lần sửa đổi hồ sơ', 'type' => 'string', 'required' => true],
                        ['key' => 'coordination_status', 'label' => 'Tình trạng phối hợp', 'type' => 'enum', 'required' => true, 'enum_options' => ['in_progress', 'ready_for_review']],
                    ],
                    'config' => [
                        'description' => 'Hoàn thiện hồ sơ thiết kế đã phối hợp giữa các bộ môn và ghi nhận kết quả phối hợp.',
                        'priority' => 'high', 'phase_key' => 'design-development', 'phase_name' => 'Triển khai thiết kế', 'phase_order' => 2,
                        'checklist_items' => [
                            ['key' => 'complete_calculations', 'label' => 'Hoàn thành tính toán và ghi chú chỉnh sửa', 'required' => true],
                            ['key' => 'run_coordination', 'label' => 'Rà soát phối hợp giữa các bộ môn', 'required' => true],
                        ],
                        'required_docs' => [
                            ['key' => 'calculation-package', 'label' => 'Thuyết minh tính toán', 'required' => true],
                            ['key' => 'coordination-model', 'label' => 'Mô hình phối hợp', 'required' => false],
                        ],
                        'assignment_rules' => self::NO_EXTRA_ASSIGNEES,
                    ],
                ],
                [
                    'key' => 'design-review', 'name' => 'Duyệt thiết kế nội bộ', 'type' => 'approval', 'order' => 3,
                    'depends_on' => ['design-coordination'], 'assignee_rule' => self::PM, 'sla_hours' => 24,
                    'fields' => [
                        ['key' => 'review_decision', 'label' => 'Kết luận duyệt', 'type' => 'enum', 'required' => true, 'enum_options' => ['approved', 'revise_and_resubmit']],
                        ['key' => 'review_notes', 'label' => 'Ghi chú duyệt', 'type' => 'text', 'required' => false],
                    ],
                    'config' => [
                        'description' => 'Duyệt hồ sơ đã phối hợp trước khi phát hành ra ngoài.',
                        'priority' => 'high', 'phase_key' => 'design-review', 'phase_name' => 'Duyệt thiết kế', 'phase_order' => 3,
                        'checklist_items' => [
                            ['key' => 'verify_comments_closed', 'label' => 'Kiểm tra đã xử lý hết góp ý nội bộ', 'required' => true],
                            ['key' => 'verify_revision_clouds', 'label' => 'Kiểm tra đánh dấu và ghi chú sửa đổi', 'required' => true],
                        ],
                        'required_docs' => [
                            ['key' => 'review-markups', 'label' => 'Bản vẽ ghi chú duyệt', 'required' => true],
                        ],
                        'assignment_rules' => self::NO_EXTRA_ASSIGNEES,
                    ],
                ],
                [
                    'key' => 'issue-design-package', 'name' => 'Phát hành hồ sơ thiết kế', 'type' => 'deliverable', 'order' => 4,
                    'depends_on' => ['design-review'], 'assignee_rule' => self::PM, 'sla_hours' => 12,
                    'fields' => [
                        ['key' => 'issue_reference', 'label' => 'Số hiệu phát hành', 'type' => 'string', 'required' => true],
                    ],
                    'config' => [
                        'description' => 'Phát hành hồ sơ thiết kế đã duyệt cho các bước sau.',
                        'priority' => 'medium', 'phase_key' => 'design-issue', 'phase_name' => 'Phát hành thiết kế', 'phase_order' => 4,
                        'checklist_items' => [
                            ['key' => 'publish_package', 'label' => 'Phát hành hồ sơ đã duyệt', 'required' => true],
                        ],
                        'required_docs' => [
                            ['key' => 'issued-drawing-set', 'label' => 'Bộ bản vẽ phát hành', 'required' => true],
                            ['key' => 'transmittal', 'label' => 'Phiếu chuyển giao hồ sơ', 'required' => true],
                        ],
                        'assignment_rules' => self::NO_EXTRA_ASSIGNEES,
                    ],
                ],
            ],
        ],
        [
            'code' => 'WT-VN-THICONG',
            'name' => 'Thi công',
            'description' => 'Quy trình thi công chuẩn: huy động, mua sắm, thi công hiện trường, chuẩn bị bàn giao.',
            'steps' => [
                [
                    'key' => 'construction-mobilization', 'name' => 'Huy động đội thi công', 'type' => 'task', 'order' => 1, 'depends_on' => [],
                    'assignee_rule' => self::PM, 'sla_hours' => 24,
                    'fields' => [
                        ['key' => 'site_ready_date', 'label' => 'Ngày sẵn sàng công trường', 'type' => 'date', 'required' => true],
                        ['key' => 'mobilization_notes', 'label' => 'Ghi chú huy động', 'type' => 'text', 'required' => false],
                    ],
                    'config' => [
                        'description' => 'Xác nhận nhân công, máy móc và hậu cần sẵn sàng trước khi thi công.',
                        'priority' => 'high', 'phase_key' => 'construction-planning', 'phase_name' => 'Chuẩn bị thi công', 'phase_order' => 1,
                        'checklist_items' => [
                            ['key' => 'confirm_access', 'label' => 'Xác nhận lối vào công trường và giấy phép', 'required' => true],
                            ['key' => 'brief_site_team', 'label' => 'Phổ biến phạm vi và an toàn cho đội thi công', 'required' => true],
                        ],
                        'required_docs' => [
                            ['key' => 'site-logistics-plan', 'label' => 'Phương án mặt bằng thi công', 'required' => true],
                            ['key' => 'mobilization-checklist', 'label' => 'Danh mục kiểm tra huy động', 'required' => true],
                        ],
                        'assignment_rules' => self::NO_EXTRA_ASSIGNEES,
                    ],
                ],
                [
                    'key' => 'procurement-release', 'name' => 'Đặt hàng vật tư và trình mẫu', 'type' => 'task', 'order' => 2,
                    'depends_on' => ['construction-mobilization'], 'assignee_rule' => self::PM, 'sla_hours' => 48,
                    'fields' => [
                        ['key' => 'vendor_status', 'label' => 'Tình trạng nhà cung cấp', 'type' => 'enum', 'required' => true, 'enum_options' => ['pending', 'released', 'approved']],
                        ['key' => 'long_lead_items', 'label' => 'Vật tư cần đặt sớm', 'type' => 'text', 'required' => false],
                    ],
                    'config' => [
                        'description' => 'Đặt hàng các gói vật tư và xác nhận đã trình duyệt mẫu cần thiết.',
                        'priority' => 'high', 'phase_key' => 'procurement', 'phase_name' => 'Mua sắm', 'phase_order' => 2,
                        'checklist_items' => [
                            ['key' => 'release_po', 'label' => 'Phát hành đơn đặt hàng', 'required' => true],
                            ['key' => 'submit_materials', 'label' => 'Trình duyệt mẫu vật tư', 'required' => true],
                        ],
                        'required_docs' => [
                            ['key' => 'procurement-log', 'label' => 'Sổ theo dõi mua sắm', 'required' => true],
                            ['key' => 'material-submittal', 'label' => 'Hồ sơ trình duyệt vật tư', 'required' => true],
                        ],
                        'assignment_rules' => self::NO_EXTRA_ASSIGNEES,
                    ],
                ],
                [
                    'key' => 'field-execution', 'name' => 'Thi công hiện trường', 'type' => 'task', 'order' => 3,
                    'depends_on' => ['procurement-release'], 'assignee_rule' => self::PM, 'sla_hours' => 120,
                    'fields' => [
                        ['key' => 'work_area', 'label' => 'Khu vực thi công', 'type' => 'string', 'required' => true],
                        ['key' => 'percent_complete', 'label' => 'Phần trăm hoàn thành', 'type' => 'number', 'required' => true],
                    ],
                    'config' => [
                        'description' => 'Theo dõi tiến độ thi công và chất lượng tại hiện trường.',
                        'priority' => 'high', 'phase_key' => 'execution', 'phase_name' => 'Thi công', 'phase_order' => 3,
                        'checklist_items' => [
                            ['key' => 'complete_daily_reports', 'label' => 'Ghi nhật ký thi công hằng ngày', 'required' => true],
                            ['key' => 'update_as_built_notes', 'label' => 'Cập nhật ghi chú hoàn công', 'required' => true],
                        ],
                        'required_docs' => [
                            ['key' => 'daily-report', 'label' => 'Nhật ký thi công', 'required' => true],
                            ['key' => 'inspection-request', 'label' => 'Phiếu yêu cầu nghiệm thu', 'required' => false],
                        ],
                        'assignment_rules' => self::NO_EXTRA_ASSIGNEES,
                    ],
                ],
                [
                    'key' => 'handover-readiness', 'name' => 'Xác nhận sẵn sàng bàn giao', 'type' => 'inspection', 'order' => 4,
                    'depends_on' => ['field-execution'], 'assignee_rule' => self::PM, 'sla_hours' => 24,
                    'fields' => [
                        ['key' => 'punch_status', 'label' => 'Tình trạng tồn tại', 'type' => 'enum', 'required' => true, 'enum_options' => ['open', 'closed']],
                    ],
                    'config' => [
                        'description' => 'Kiểm tra đã khắc phục hết tồn tại và tập hợp hồ sơ bàn giao.',
                        'priority' => 'medium', 'phase_key' => 'handover', 'phase_name' => 'Bàn giao', 'phase_order' => 4,
                        'checklist_items' => [
                            ['key' => 'close_punch_items', 'label' => 'Khắc phục các tồn tại', 'required' => true],
                            ['key' => 'collect_turnover_docs', 'label' => 'Tập hợp hồ sơ bàn giao', 'required' => true],
                        ],
                        'required_docs' => [
                            ['key' => 'as-built-package', 'label' => 'Hồ sơ hoàn công', 'required' => true],
                            ['key' => 'handover-checklist', 'label' => 'Biên bản kiểm tra bàn giao', 'required' => true],
                        ],
                        'assignment_rules' => self::NO_EXTRA_ASSIGNEES,
                    ],
                ],
            ],
        ],
        [
            'code' => 'WT-VN-NGHIEMTHU',
            'name' => 'Nghiệm thu',
            'description' => 'Quy trình nghiệm thu chuẩn: lập kế hoạch, kiểm tra hiện trường, khắc phục và đóng nghiệm thu.',
            'steps' => [
                [
                    'key' => 'inspection-plan', 'name' => 'Lập kế hoạch nghiệm thu', 'type' => 'task', 'order' => 1, 'depends_on' => [],
                    'assignee_rule' => self::PM, 'sla_hours' => 12,
                    'fields' => [
                        ['key' => 'inspection_type', 'label' => 'Loại nghiệm thu', 'type' => 'enum', 'required' => true, 'enum_options' => ['incoming', 'in_process', 'final']],
                        ['key' => 'planned_inspection_date', 'label' => 'Ngày nghiệm thu dự kiến', 'type' => 'date', 'required' => true],
                    ],
                    'config' => [
                        'description' => 'Xác định phạm vi, thời điểm và tiêu chí nghiệm thu.',
                        'priority' => 'high', 'phase_key' => 'inspection-planning', 'phase_name' => 'Lập kế hoạch nghiệm thu', 'phase_order' => 1,
                        'checklist_items' => [
                            ['key' => 'define_acceptance_criteria', 'label' => 'Xác định tiêu chí nghiệm thu', 'required' => true],
                            ['key' => 'confirm_witnesses', 'label' => 'Xác nhận thành phần chứng kiến và điểm dừng kiểm tra', 'required' => true],
                        ],
                        'required_docs' => [
                            ['key' => 'itp', 'label' => 'Kế hoạch kiểm tra và thí nghiệm', 'required' => true],
                            ['key' => 'approved_method_statement', 'label' => 'Biện pháp thi công đã duyệt', 'required' => false],
                        ],
                        'assignment_rules' => self::NO_EXTRA_ASSIGNEES,
                    ],
                ],
                [
                    'key' => 'perform-inspection', 'name' => 'Kiểm tra tại hiện trường', 'type' => 'inspection', 'order' => 2,
                    'depends_on' => ['inspection-plan'], 'assignee_rule' => self::PM, 'sla_hours' => 24,
                    'fields' => [
                        ['key' => 'inspection_result', 'label' => 'Kết quả kiểm tra', 'type' => 'enum', 'required' => true, 'enum_options' => ['pass', 'fail', 'conditional_pass']],
                        ['key' => 'observations', 'label' => 'Ghi nhận', 'type' => 'text', 'required' => false],
                    ],
                    'config' => [
                        'description' => 'Kiểm tra phần việc đã thi công và ghi nhận theo tiêu chí nghiệm thu.',
                        'priority' => 'high', 'phase_key' => 'inspection-execution', 'phase_name' => 'Thực hiện nghiệm thu', 'phase_order' => 2,
                        'checklist_items' => [
                            ['key' => 'capture_measurements', 'label' => 'Đo đạc và chụp ảnh', 'required' => true],
                            ['key' => 'record_nonconformances', 'label' => 'Ghi nhận điểm không đạt', 'required' => true],
                        ],
                        'required_docs' => [
                            ['key' => 'inspection-report', 'label' => 'Biên bản nghiệm thu', 'required' => true],
                            ['key' => 'photo-log', 'label' => 'Ảnh hiện trường', 'required' => false],
                        ],
                        'assignment_rules' => self::NO_EXTRA_ASSIGNEES,
                    ],
                ],
                [
                    'key' => 'inspection-closeout', 'name' => 'Khắc phục và đóng nghiệm thu', 'type' => 'approval', 'order' => 3,
                    'depends_on' => ['perform-inspection'], 'assignee_rule' => self::PM, 'sla_hours' => 24,
                    'fields' => [
                        ['key' => 'closeout_status', 'label' => 'Tình trạng đóng', 'type' => 'enum', 'required' => true, 'enum_options' => ['open', 'closed']],
                        ['key' => 'closeout_comments', 'label' => 'Ý kiến khi đóng', 'type' => 'text', 'required' => false],
                    ],
                    'config' => [
                        'description' => 'Kiểm tra việc khắc phục và đóng đợt nghiệm thu.',
                        'priority' => 'medium', 'phase_key' => 'inspection-closeout', 'phase_name' => 'Đóng nghiệm thu', 'phase_order' => 3,
                        'checklist_items' => [
                            ['key' => 'verify_corrections', 'label' => 'Kiểm tra việc khắc phục', 'required' => true],
                            ['key' => 'archive_evidence', 'label' => 'Lưu trữ hồ sơ nghiệm thu', 'required' => true],
                        ],
                        'required_docs' => [
                            ['key' => 'closeout-record', 'label' => 'Biên bản đóng nghiệm thu', 'required' => true],
                        ],
                        'assignment_rules' => self::NO_EXTRA_ASSIGNEES,
                    ],
                ],
            ],
        ],
    ];

    public function run(): void
    {
        if (!Schema::hasTable('work_templates')) {
            return;
        }

        foreach (Tenant::query()->orderBy('created_at')->pluck('id') as $tenantId) {
            $actorId = User::query()->where('tenant_id', (string) $tenantId)->orderBy('created_at')->value('id');
            foreach (self::TEMPLATE_DEFINITIONS as $definition) {
                $this->seedTemplateForTenant((string) $tenantId, is_scalar($actorId) ? (string) $actorId : null, $definition);
            }
        }
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function seedTemplateForTenant(string $tenantId, ?string $actorId, array $definition): void
    {
        $exists = WorkTemplate::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('code', (string) $definition['code'])->exists();
        if ($exists) {
            return;
        }

        $template = WorkTemplate::query()->create([
            'tenant_id' => $tenantId,
            'code' => (string) $definition['code'],
            'name' => (string) $definition['name'],
            'description' => $definition['description'] ?? null,
            'status' => 'published',
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        $version = WorkTemplateVersion::query()->create([
            'tenant_id' => $tenantId,
            'work_template_id' => (string) $template->getKey(),
            'semver' => self::VERSION,
            'schema_version' => 1,
            'content_json' => ['steps' => $definition['steps'], 'approvals' => [], 'rules' => []],
            'is_immutable' => true,
            'published_at' => now(),
            'published_by' => $actorId,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        foreach ($definition['steps'] as $stepData) {
            $step = WorkTemplateStep::query()->create([
                'tenant_id' => $tenantId,
                'work_template_version_id' => (string) $version->getKey(),
                'step_key' => (string) $stepData['key'],
                'name' => (string) $stepData['name'],
                'type' => (string) $stepData['type'],
                'step_order' => (int) $stepData['order'],
                'depends_on' => $stepData['depends_on'],
                'assignee_rule_json' => $stepData['assignee_rule'],
                'sla_hours' => (int) $stepData['sla_hours'],
                'config_json' => $stepData['config'],
            ]);

            foreach ($stepData['fields'] as $fieldData) {
                WorkTemplateField::query()->create([
                    'tenant_id' => $tenantId,
                    'work_template_step_id' => (string) $step->getKey(),
                    'field_key' => (string) $fieldData['key'],
                    'label' => (string) $fieldData['label'],
                    'type' => (string) $fieldData['type'],
                    'is_required' => (bool) $fieldData['required'],
                    'enum_options_json' => $fieldData['enum_options'] ?? null,
                ]);
            }
        }
    }
}
