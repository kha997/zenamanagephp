<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Project;
use App\Models\ChangeRequest;
use App\Models\Tenant;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Test Change Request Workflow và Approval Process
 * 
 * Kịch bản: PM tạo CR → Client Rep phê duyệt → Apply impact
 */
class ChangeRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private $tenant;
    private $project;
    private $projectManager;
    private $clientRep;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Tạo tenant
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Company',
            'slug' => 'test-company',
            'domain' => 'test.com',
            'settings' => json_encode(['timezone' => 'Asia/Ho_Chi_Minh']),
            'status' => 'trial',
            'is_active' => true,
        ]);

        // Tạo project
        $this->project = Project::factory()->create([
            'name' => 'Test Project',
            'code' => 'CR-TEST-001',
            'description' => 'Test Description',
            'status' => 'active',
            'tenant_id' => $this->tenant->id,
            'created_by' => null, // Sẽ được set sau khi tạo users
        ]);

        // Tạo Project Manager
        $this->projectManager = User::factory()->create([
            'name' => 'Project Manager',
            'email' => 'project.manager@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'profile_data' => '{}',
        ]);

        // Tạo Client Representative
        $this->clientRep = User::factory()->create([
            'name' => 'Client Representative',
            'email' => 'client.rep@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'profile_data' => '{}',
        ]);

        // Cập nhật project với created_by
        $this->project->update(['created_by' => $this->projectManager->id]);
    }

    /**
     * Test tạo CR với impact analysis
     */
    public function test_can_create_change_request_with_impact_analysis(): void
    {
        // Project Manager tạo Change Request
        $crData = [
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'change_number' => 'CR-001',
            'title' => 'Design Change Request',
            'description' => 'Request to change foundation design due to soil conditions',
            'change_type' => 'design',
            'priority' => 'high',
            'status' => ChangeRequest::STATUS_DRAFT,
            'impact_level' => 'medium',
            'requested_by' => $this->projectManager->id,
            'requested_at' => now(),
            'estimated_cost' => 50000.00,
            'estimated_days' => 5,
            'impact_analysis' => [
                'schedule_delay' => 5,
                'cost_increase' => 50000,
                'quality_impact' => 'low',
                'risk_level' => 'medium'
            ],
            'risk_assessment' => [
                'technical_risk' => 'low',
                'schedule_risk' => 'medium',
                'cost_risk' => 'medium',
                'quality_risk' => 'low'
            ],
        ];

        $changeRequest = ChangeRequest::create($crData);

        // Kiểm tra CR được tạo thành công
        $this->assertDatabaseHas('change_requests', [
            'id' => $changeRequest->id,
            'project_id' => $this->project->id,
            'change_number' => 'CR-001',
            'title' => 'Design Change Request',
            'status' => ChangeRequest::STATUS_DRAFT,
            'estimated_days' => 5,
            'estimated_cost' => 50000.00,
        ]);

        // Kiểm tra relationships
        $this->assertEquals($this->project->id, $changeRequest->project->id);
        $this->assertEquals($this->projectManager->id, $changeRequest->requester->id);
        $this->assertEquals(5, $changeRequest->estimated_days);
        $this->assertEquals(50000.00, $changeRequest->estimated_cost);
        $this->assertIsArray($changeRequest->impact_analysis);
        $this->assertEquals('medium', $changeRequest->impact_analysis['risk_level']);
        $this->assertIsArray($changeRequest->risk_assessment);
        $this->assertEquals('low', $changeRequest->risk_assessment['technical_risk']);
    }

    /**
     * Test submit CR để phê duyệt
     */
    public function test_can_submit_change_request_for_approval(): void
    {
        // Tạo CR ở trạng thái draft
        $changeRequest = ChangeRequest::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'change_number' => 'CR-002',
            'title' => 'Material Change Request',
            'description' => 'Request to change material specifications',
            'change_type' => 'material',
            'priority' => 'medium',
            'status' => ChangeRequest::STATUS_DRAFT,
            'impact_level' => 'low',
            'requested_by' => $this->projectManager->id,
            'requested_at' => now(),
            'estimated_cost' => 25000.00,
            'estimated_days' => 3,
            'impact_analysis' => [
                'schedule_delay' => 3,
                'cost_increase' => 25000,
                'quality_impact' => 'medium',
                'risk_level' => 'low'
            ],
        ]);

        // Submit CR để phê duyệt
        $result = $changeRequest->submitForApproval();

        // Kiểm tra submit thành công
        $this->assertTrue($result);
        $this->assertEquals(ChangeRequest::STATUS_AWAITING_APPROVAL, $changeRequest->fresh()->status);
        $this->assertTrue($changeRequest->fresh()->isPending());

        // Kiểm tra database
        $this->assertDatabaseHas('change_requests', [
            'id' => $changeRequest->id,
            'status' => ChangeRequest::STATUS_AWAITING_APPROVAL,
        ]);
    }

    /**
     * Test approval workflow với audit trail
     */
    public function test_approval_workflow_with_audit_trail(): void
    {
        // Tạo CR
        $changeRequest = ChangeRequest::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'change_number' => 'CR-003',
            'title' => 'Process Change Request',
            'description' => 'Request to change construction process',
            'change_type' => 'process',
            'priority' => 'medium',
            'status' => ChangeRequest::STATUS_DRAFT,
            'impact_level' => 'medium',
            'requested_by' => $this->projectManager->id,
            'requested_at' => now(),
            'estimated_cost' => 75000.00,
            'estimated_days' => 7,
            'impact_analysis' => [
                'schedule_delay' => 7,
                'cost_increase' => 75000,
                'quality_impact' => 'medium',
                'risk_level' => 'medium'
            ],
        ]);

        // Submit để phê duyệt
        $changeRequest->submitForApproval();
        $this->assertEquals(ChangeRequest::STATUS_AWAITING_APPROVAL, $changeRequest->fresh()->status);

        // Client Rep approve với note
        $approvalResult = $changeRequest->approve(
            $this->clientRep->id, 
            'Approved with conditions: Monitor progress closely'
        );

        $this->assertTrue($approvalResult);
        
        // Kiểm tra audit trail
        $approvedCr = $changeRequest->fresh();
        $this->assertEquals(ChangeRequest::STATUS_APPROVED, $approvedCr->status);
        $this->assertEquals($this->clientRep->id, $approvedCr->approved_by);
        $this->assertNotNull($approvedCr->approved_at);
        $this->assertEquals('Approved with conditions: Monitor progress closely', $approvedCr->approval_notes);
    }

    /**
     * Test reject change request
     */
    public function test_can_reject_change_request(): void
    {
        // Tạo CR
        $changeRequest = ChangeRequest::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'change_number' => 'CR-004',
            'title' => 'Rejected Change Request',
            'description' => 'Request that will be rejected',
            'change_type' => 'design',
            'priority' => 'high',
            'status' => ChangeRequest::STATUS_DRAFT,
            'impact_level' => 'high',
            'requested_by' => $this->projectManager->id,
            'requested_at' => now(),
            'estimated_cost' => 100000.00,
            'estimated_days' => 10,
            'impact_analysis' => [
                'schedule_delay' => 10,
                'cost_increase' => 100000,
                'quality_impact' => 'high',
                'risk_level' => 'high'
            ],
        ]);

        // Submit để phê duyệt
        $changeRequest->submitForApproval();
        $this->assertEquals(ChangeRequest::STATUS_AWAITING_APPROVAL, $changeRequest->fresh()->status);

        // Client Rep reject với reason
        $rejectResult = $changeRequest->reject(
            $this->clientRep->id, 
            'Rejected due to budget constraints and timeline impact'
        );

        $this->assertTrue($rejectResult);
        
        // Kiểm tra rejection
        $rejectedCr = $changeRequest->fresh();
        $this->assertEquals(ChangeRequest::STATUS_REJECTED, $rejectedCr->status);
        $this->assertTrue($rejectedCr->isRejected());
        $this->assertEquals($this->clientRep->id, $rejectedCr->rejected_by);
        $this->assertNotNull($rejectedCr->rejected_at);
        $this->assertEquals('Rejected due to budget constraints and timeline impact', $rejectedCr->rejection_reason);
    }

    /**
     * Test apply CR vào project/baseline
     */
    public function test_can_apply_change_request_to_project(): void
    {
        // Tạo CR đã được approve
        $changeRequest = ChangeRequest::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'change_number' => 'CR-005',
            'title' => 'Applied Change Request',
            'description' => 'Change request that will be applied',
            'change_type' => 'design',
            'priority' => 'medium',
            'status' => ChangeRequest::STATUS_APPROVED,
            'impact_level' => 'medium',
            'requested_by' => $this->projectManager->id,
            'requested_at' => now(),
            'estimated_cost' => 50000.00,
            'estimated_days' => 5,
            'approved_by' => $this->clientRep->id,
            'approved_at' => now(),
            'approval_notes' => 'Approved for implementation',
            'impact_analysis' => [
                'schedule_delay' => 5,
                'cost_increase' => 50000,
                'quality_impact' => 'low',
                'risk_level' => 'medium'
            ],
        ]);

        // Apply CR vào project (simulate)
        $originalBudget = $this->project->budget_total ?? 0;

        // Update project với impact từ CR
        $this->project->update([
            'budget_total' => $originalBudget + $changeRequest->estimated_cost,
        ]);

        // Kiểm tra project được update
        $updatedProject = $this->project->fresh();
        $this->assertEquals($originalBudget + 50000.00, $updatedProject->budget_total);
    }

    /**
     * Test change request workflow end-to-end
     */
    public function test_change_request_workflow_end_to_end(): void
    {
        // 1. Project Manager tạo CR
        $changeRequest = ChangeRequest::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'change_number' => 'CR-006',
            'title' => 'E2E Change Request',
            'description' => 'End-to-end change request test',
            'change_type' => 'design',
            'priority' => 'high',
            'status' => ChangeRequest::STATUS_DRAFT,
            'impact_level' => 'medium',
            'requested_by' => $this->projectManager->id,
            'requested_at' => now(),
            'estimated_cost' => 60000.00,
            'estimated_days' => 6,
            'impact_analysis' => [
                'schedule_delay' => 6,
                'cost_increase' => 60000,
                'quality_impact' => 'medium',
                'risk_level' => 'medium'
            ],
        ]);

        $this->assertTrue($changeRequest->isDraft());

        // 2. Submit để phê duyệt
        $submitResult = $changeRequest->submitForApproval();
        $this->assertTrue($submitResult);
        $this->assertTrue($changeRequest->fresh()->isPending());

        // 3. Client Rep approve
        $approveResult = $changeRequest->approve($this->clientRep->id, 'E2E approval');
        $this->assertTrue($approveResult);
        $this->assertTrue($changeRequest->fresh()->isApproved());

        // 4. Apply impact vào project
        $originalBudget = $this->project->budget_total ?? 0;
        $this->project->update([
            'budget_total' => $originalBudget + $changeRequest->estimated_cost,
        ]);

        // Kiểm tra toàn bộ workflow
        $this->assertDatabaseHas('change_requests', [
            'id' => $changeRequest->id,
            'status' => ChangeRequest::STATUS_APPROVED,
            'requested_by' => $this->projectManager->id,
            'approved_by' => $this->clientRep->id,
        ]);

        $this->assertEquals($originalBudget + 60000.00, $this->project->fresh()->budget_total);
    }
}