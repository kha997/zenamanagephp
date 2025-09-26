<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Project;
use App\Models\QcPlan;
use App\Models\QcInspection;
use App\Models\Ncr;
use App\Models\Tenant;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Test Inspection & NCR Workflow
 * 
 * Kịch bản: Tạo QC Plan → Thực hiện Inspection → Tạo NCR từ findings → Resolve NCR
 */
class InspectionNcrWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private $tenant;
    private $project;
    private $qcInspector;
    private $projectManager;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Tạo tenant
        $this->tenant = Tenant::create([
            'name' => 'Test Company',
            'slug' => 'test-company',
            'domain' => 'test.com',
            'settings' => json_encode(['timezone' => 'Asia/Ho_Chi_Minh']),
            'status' => 'active',
            'is_active' => true,
        ]);

        // Tạo QC Inspector
        $this->qcInspector = User::create([
            'name' => 'QC Inspector',
            'email' => 'qc.inspector@example.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'profile_data' => '{}',
        ]);

        // Tạo Project Manager
        $this->projectManager = User::create([
            'name' => 'Project Manager',
            'email' => 'project.manager@example.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'profile_data' => '{}',
        ]);

        // Tạo project
        $this->project = Project::create([
            'name' => 'Test Project',
            'code' => 'INSP-TEST-001',
            'description' => 'Test Description',
            'status' => 'active',
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->projectManager->id,
        ]);

        Storage::fake('public');
    }

    /**
     * Test tạo QC Plan với checklist items
     */
    public function test_can_create_qc_plan_with_checklist(): void
    {
        $checklistItems = [
            [
                'item' => 'Foundation concrete strength',
                'specification' => 'Minimum 25 MPa',
                'method' => 'Core sampling test',
                'acceptance_criteria' => '≥ 25 MPa'
            ],
            [
                'item' => 'Steel reinforcement placement',
                'specification' => 'As per drawing',
                'method' => 'Visual inspection',
                'acceptance_criteria' => '100% compliance'
            ],
            [
                'item' => 'Formwork alignment',
                'specification' => '±5mm tolerance',
                'method' => 'Measurement',
                'acceptance_criteria' => 'Within tolerance'
            ]
        ];

        $qcPlan = QcPlan::create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'title' => 'Foundation QC Plan',
            'description' => 'Quality control plan for foundation work',
            'status' => 'active',
            'start_date' => now()->addDays(1),
            'end_date' => now()->addDays(30),
            'created_by' => $this->projectManager->id,
            'checklist_items' => $checklistItems,
        ]);

        // Kiểm tra QC Plan được tạo thành công
        $this->assertDatabaseHas('qc_plans', [
            'id' => $qcPlan->id,
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'title' => 'Foundation QC Plan',
            'status' => 'active',
        ]);

        // Kiểm tra checklist items
        $this->assertNotNull($qcPlan->checklist_items);
        $this->assertCount(3, $qcPlan->checklist_items);
        $this->assertEquals('Foundation concrete strength', $qcPlan->checklist_items[0]['item']);

        // Kiểm tra relationships
        $this->assertEquals($this->project->id, $qcPlan->project->id);
        $this->assertEquals($this->tenant->id, $qcPlan->tenant->id);
        $this->assertEquals($this->projectManager->id, $qcPlan->creator->id);
    }

    /**
     * Test tạo QC Inspection với checklist results
     */
    public function test_can_create_qc_inspection_with_results(): void
    {
        // Tạo QC Plan trước
        $qcPlan = QcPlan::create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'title' => 'Foundation QC Plan',
            'description' => 'Quality control plan for foundation work',
            'status' => 'active',
            'start_date' => now()->addDays(1),
            'end_date' => now()->addDays(30),
            'created_by' => $this->projectManager->id,
            'checklist_items' => [
                ['item' => 'Foundation concrete strength', 'specification' => 'Minimum 25 MPa'],
                ['item' => 'Steel reinforcement placement', 'specification' => 'As per drawing'],
            ],
        ]);

        $checklistResults = [
            [
                'item' => 'Foundation concrete strength',
                'result' => 'PASS',
                'actual_value' => '28 MPa',
                'notes' => 'Core sample tested successfully'
            ],
            [
                'item' => 'Steel reinforcement placement',
                'result' => 'FAIL',
                'actual_value' => 'Incorrect spacing',
                'notes' => 'Rebar spacing not according to drawing'
            ]
        ];

        $inspection = QcInspection::create([
            'qc_plan_id' => $qcPlan->id,
            'tenant_id' => $this->tenant->id,
            'title' => 'Foundation Inspection #1',
            'description' => 'First foundation inspection',
            'status' => 'completed',
            'inspection_date' => now(),
            'inspector_id' => $this->qcInspector->id,
            'findings' => 'Concrete strength OK, but rebar spacing issue found',
            'recommendations' => 'Correct rebar spacing before proceeding',
            'checklist_results' => $checklistResults,
            'photos' => [
                ['filename' => 'foundation_1.jpg', 'path' => 'inspections/foundation_1.jpg'],
                ['filename' => 'rebar_spacing.jpg', 'path' => 'inspections/rebar_spacing.jpg'],
            ],
        ]);

        // Kiểm tra Inspection được tạo thành công
        $this->assertDatabaseHas('qc_inspections', [
            'id' => $inspection->id,
            'qc_plan_id' => $qcPlan->id,
            'tenant_id' => $this->tenant->id,
            'title' => 'Foundation Inspection #1',
            'status' => 'completed',
        ]);

        // Kiểm tra checklist results
        $this->assertNotNull($inspection->checklist_results);
        $this->assertCount(2, $inspection->checklist_results);
        $this->assertEquals('PASS', $inspection->checklist_results[0]['result']);
        $this->assertEquals('FAIL', $inspection->checklist_results[1]['result']);

        // Kiểm tra photos
        $this->assertNotNull($inspection->photos);
        $this->assertCount(2, $inspection->photos);

        // Kiểm tra relationships
        $this->assertEquals($qcPlan->id, $inspection->qcPlan->id);
        $this->assertEquals($this->tenant->id, $inspection->tenant->id);
        $this->assertEquals($this->qcInspector->id, $inspection->inspector->id);
    }

    /**
     * Test tạo NCR từ inspection failure
     */
    public function test_can_create_ncr_from_inspection_failure(): void
    {
        // Tạo QC Plan và Inspection với failure
        $qcPlan = QcPlan::create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'title' => 'Foundation QC Plan',
            'description' => 'Quality control plan for foundation work',
            'status' => 'active',
            'created_by' => $this->projectManager->id,
        ]);

        $inspection = QcInspection::create([
            'qc_plan_id' => $qcPlan->id,
            'tenant_id' => $this->tenant->id,
            'title' => 'Foundation Inspection #1',
            'description' => 'First foundation inspection',
            'status' => 'failed',
            'inspection_date' => now(),
            'inspector_id' => $this->qcInspector->id,
            'findings' => 'Critical rebar spacing issue found',
            'recommendations' => 'Stop work and correct rebar placement',
        ]);

        // Tạo NCR từ inspection failure
        $ncr = Ncr::create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'inspection_id' => $inspection->id,
            'ncr_number' => 'NCR-001',
            'title' => 'Rebar Spacing Non-Conformance',
            'description' => 'Steel reinforcement spacing not according to drawing specifications',
            'status' => 'open',
            'severity' => 'high',
            'created_by' => $this->qcInspector->id,
            'assigned_to' => $this->projectManager->id,
            'attachments' => [
                ['filename' => 'rebar_spacing.jpg', 'path' => 'ncrs/rebar_spacing.jpg'],
                ['filename' => 'drawing_reference.pdf', 'path' => 'ncrs/drawing_reference.pdf'],
            ],
        ]);

        // Kiểm tra NCR được tạo thành công
        $this->assertDatabaseHas('ncrs', [
            'id' => $ncr->id,
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'inspection_id' => $inspection->id,
            'ncr_number' => 'NCR-001',
            'title' => 'Rebar Spacing Non-Conformance',
            'status' => 'open',
            'severity' => 'high',
        ]);

        // Kiểm tra attachments
        $this->assertNotNull($ncr->attachments);
        $this->assertCount(2, $ncr->attachments);

        // Kiểm tra relationships
        $this->assertEquals($this->project->id, $ncr->project->id);
        $this->assertEquals($this->tenant->id, $ncr->tenant->id);
        $this->assertEquals($inspection->id, $ncr->inspection->id);
        $this->assertEquals($this->qcInspector->id, $ncr->creator->id);
        $this->assertEquals($this->projectManager->id, $ncr->assignee->id);
    }

    /**
     * Test NCR workflow từ open đến closed
     */
    public function test_ncr_workflow_from_open_to_closed(): void
    {
        // Tạo NCR
        $ncr = Ncr::create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'ncr_number' => 'NCR-002',
            'title' => 'Material Quality Issue',
            'description' => 'Concrete mix does not meet specifications',
            'status' => 'open',
            'severity' => 'critical',
            'created_by' => $this->qcInspector->id,
            'assigned_to' => $this->projectManager->id,
        ]);

        // 1. Under Review
        $ncr->update([
            'status' => 'under_review',
            'root_cause' => 'Supplier provided incorrect mix design',
        ]);

        $this->assertDatabaseHas('ncrs', [
            'id' => $ncr->id,
            'status' => 'under_review',
            'root_cause' => 'Supplier provided incorrect mix design',
        ]);

        // 2. In Progress
        $ncr->update([
            'status' => 'in_progress',
            'corrective_action' => 'Return incorrect batch, order new batch with correct specifications',
            'preventive_action' => 'Implement supplier verification process',
        ]);

        $this->assertDatabaseHas('ncrs', [
            'id' => $ncr->id,
            'status' => 'in_progress',
            'corrective_action' => 'Return incorrect batch, order new batch with correct specifications',
            'preventive_action' => 'Implement supplier verification process',
        ]);

        // 3. Resolved
        $ncr->update([
            'status' => 'resolved',
            'resolution' => 'New batch received and tested, meets specifications',
            'resolved_at' => now(),
        ]);

        $this->assertDatabaseHas('ncrs', [
            'id' => $ncr->id,
            'status' => 'resolved',
            'resolution' => 'New batch received and tested, meets specifications',
        ]);

        $this->assertNotNull($ncr->resolved_at);

        // 4. Closed
        $ncr->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        $this->assertDatabaseHas('ncrs', [
            'id' => $ncr->id,
            'status' => 'closed',
        ]);

        $this->assertNotNull($ncr->closed_at);
    }

    /**
     * Test NCR severity levels và overdue tracking
     */
    public function test_ncr_severity_levels_and_overdue_tracking(): void
    {
        // Tạo NCRs với different severity levels
        $criticalNcr = Ncr::create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'ncr_number' => 'NCR-CRIT-001',
            'title' => 'Critical Safety Issue',
            'description' => 'Safety barrier not installed',
            'status' => 'open',
            'severity' => 'critical',
            'created_by' => $this->qcInspector->id,
            'assigned_to' => $this->projectManager->id,
        ]);

        $highNcr = Ncr::create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'ncr_number' => 'NCR-HIGH-001',
            'title' => 'High Priority Issue',
            'description' => 'Structural element not according to spec',
            'status' => 'open',
            'severity' => 'high',
            'created_by' => $this->qcInspector->id,
            'assigned_to' => $this->projectManager->id,
        ]);

        $mediumNcr = Ncr::create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'ncr_number' => 'NCR-MED-001',
            'title' => 'Medium Priority Issue',
            'description' => 'Minor cosmetic defect',
            'status' => 'open',
            'severity' => 'medium',
            'created_by' => $this->qcInspector->id,
            'assigned_to' => $this->projectManager->id,
        ]);

        $lowNcr = Ncr::create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'ncr_number' => 'NCR-LOW-001',
            'title' => 'Low Priority Issue',
            'description' => 'Minor documentation issue',
            'status' => 'open',
            'severity' => 'low',
            'created_by' => $this->qcInspector->id,
            'assigned_to' => $this->projectManager->id,
        ]);

        // Test severity scopes
        $criticalNcrs = Ncr::bySeverity('critical')->get();
        $highNcrs = Ncr::bySeverity('high')->get();
        $mediumNcrs = Ncr::bySeverity('medium')->get();
        $lowNcrs = Ncr::bySeverity('low')->get();

        $this->assertCount(1, $criticalNcrs);
        $this->assertTrue($criticalNcrs->contains($criticalNcr));

        $this->assertCount(1, $highNcrs);
        $this->assertTrue($highNcrs->contains($highNcr));

        $this->assertCount(1, $mediumNcrs);
        $this->assertTrue($mediumNcrs->contains($mediumNcr));

        $this->assertCount(1, $lowNcrs);
        $this->assertTrue($lowNcrs->contains($lowNcr));

        // Test severity badge colors
        $this->assertEquals('bg-red-100 text-red-800', $criticalNcr->severity_badge_color);
        $this->assertEquals('bg-orange-100 text-orange-800', $highNcr->severity_badge_color);
        $this->assertEquals('bg-yellow-100 text-yellow-800', $mediumNcr->severity_badge_color);
        $this->assertEquals('bg-green-100 text-green-800', $lowNcr->severity_badge_color);
    }

    /**
     * Test inspection và NCR workflow end-to-end
     */
    public function test_inspection_ncr_workflow_end_to_end(): void
    {
        // 1. Tạo QC Plan
        $qcPlan = QcPlan::create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'title' => 'E2E QC Plan',
            'description' => 'End-to-end test QC plan',
            'status' => 'active',
            'start_date' => now()->addDays(1),
            'end_date' => now()->addDays(30),
            'created_by' => $this->projectManager->id,
            'checklist_items' => [
                ['item' => 'Concrete strength', 'specification' => 'Minimum 25 MPa'],
                ['item' => 'Rebar placement', 'specification' => 'As per drawing'],
            ],
        ]);

        // 2. Tạo Inspection với mixed results
        $inspection = QcInspection::create([
            'qc_plan_id' => $qcPlan->id,
            'tenant_id' => $this->tenant->id,
            'title' => 'E2E Inspection',
            'description' => 'End-to-end test inspection',
            'status' => 'completed',
            'inspection_date' => now(),
            'inspector_id' => $this->qcInspector->id,
            'findings' => 'Concrete OK, but rebar spacing issue',
            'recommendations' => 'Correct rebar spacing',
            'checklist_results' => [
                ['item' => 'Concrete strength', 'result' => 'PASS', 'actual_value' => '28 MPa'],
                ['item' => 'Rebar placement', 'result' => 'FAIL', 'actual_value' => 'Incorrect spacing'],
            ],
        ]);

        // 3. Tạo NCR từ inspection failure
        $ncr = Ncr::create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'inspection_id' => $inspection->id,
            'ncr_number' => 'NCR-E2E-001',
            'title' => 'E2E NCR',
            'description' => 'End-to-end test NCR',
            'status' => 'open',
            'severity' => 'high',
            'created_by' => $this->qcInspector->id,
            'assigned_to' => $this->projectManager->id,
        ]);

        // 4. Resolve NCR
        $ncr->update([
            'status' => 'resolved',
            'root_cause' => 'Contractor misinterpreted drawing',
            'corrective_action' => 'Rebar repositioned according to drawing',
            'preventive_action' => 'Enhanced drawing review process',
            'resolution' => 'Issue resolved, work can proceed',
            'resolved_at' => now(),
        ]);

        // 5. Close NCR
        $ncr->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        // Test complete workflow
        $this->assertCount(1, $qcPlan->inspections);
        $this->assertTrue($qcPlan->inspections->contains($inspection));

        $this->assertCount(1, $inspection->ncrs);
        $this->assertTrue($inspection->ncrs->contains($ncr));

        // Test final status
        $this->assertEquals('completed', $inspection->status);
        $this->assertEquals('closed', $ncr->status);
        $this->assertNotNull($ncr->resolved_at);
        $this->assertNotNull($ncr->closed_at);

        // Test relationships
        $this->assertEquals($this->project->id, $ncr->project->id);
        $this->assertEquals($this->tenant->id, $ncr->tenant->id);
        $this->assertEquals($inspection->id, $ncr->inspection->id);
        $this->assertEquals($this->qcInspector->id, $ncr->creator->id);
        $this->assertEquals($this->projectManager->id, $ncr->assignee->id);
    }

    /**
     * Test inspection và NCR với bulk operations
     */
    public function test_inspection_ncr_bulk_operations(): void
    {
        // Tạo QC Plan
        $qcPlan = QcPlan::create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'title' => 'Bulk QC Plan',
            'description' => 'Bulk test QC plan',
            'status' => 'active',
            'created_by' => $this->projectManager->id,
        ]);

        // Tạo multiple inspections
        $inspections = [];
        for ($i = 1; $i <= 5; $i++) {
            $inspections[] = QcInspection::create([
                'qc_plan_id' => $qcPlan->id,
                'tenant_id' => $this->tenant->id,
                'title' => "Bulk Inspection {$i}",
                'description' => "Bulk test inspection {$i}",
                'status' => $i <= 3 ? 'completed' : 'scheduled',
                'inspection_date' => now()->addDays($i),
                'inspector_id' => $this->qcInspector->id,
                'findings' => "Findings for inspection {$i}",
            ]);
        }

        // Tạo multiple NCRs
        $ncrs = [];
        for ($i = 1; $i <= 3; $i++) {
            $ncrs[] = Ncr::create([
                'project_id' => $this->project->id,
                'tenant_id' => $this->tenant->id,
                'inspection_id' => $inspections[$i-1]->id,
                'ncr_number' => "NCR-BULK-{$i}",
                'title' => "Bulk NCR {$i}",
                'description' => "Bulk test NCR {$i}",
                'status' => 'open',
                'severity' => ['low', 'medium', 'high'][$i-1],
                'created_by' => $this->qcInspector->id,
                'assigned_to' => $this->projectManager->id,
            ]);
        }

        // Test bulk queries
        $allInspections = QcInspection::where('qc_plan_id', $qcPlan->id)->get();
        $allNcrs = Ncr::where('project_id', $this->project->id)->get();

        $this->assertCount(5, $allInspections);
        $this->assertCount(3, $allNcrs);

        // Test bulk updates
        QcInspection::where('qc_plan_id', $qcPlan->id)
            ->where('status', 'scheduled')
            ->update(['status' => 'in_progress']);

        $inProgressInspections = QcInspection::where('qc_plan_id', $qcPlan->id)
            ->where('status', 'in_progress')
            ->get();

        $this->assertCount(2, $inProgressInspections);

        // Test bulk deletes (soft delete)
        $ncrs[0]->delete();
        $this->assertSoftDeleted('ncrs', ['id' => $ncrs[0]->id]);

        // NCR should still exist in database but soft deleted
        $this->assertDatabaseHas('ncrs', [
            'id' => $ncrs[0]->id,
        ]);
    }
}
