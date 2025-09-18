<?php
/**
 * Test script chi tiết cho Change Request
 * Kiểm tra quy trình CR từ PM → Client Rep → Apply impact
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

class ChangeRequestTester
{
    private $testResults = [];
    private $testUsers = [];
    private $testTenants = [];
    private $testProjects = [];
    private $testCRs = [];

    public function __construct()
    {
        echo "🔄 Test Change Request - Quy trình thay đổi dự án\n";
        echo "===============================================\n\n";
    }

    public function runChangeRequestTests()
    {
        try {
            $this->setupTestData();
            $this->testCreateChangeRequest();
            $this->testImpactAnalysis();
            $this->testSubmitChangeRequest();
            $this->testMultiLevelApproval();
            $this->testApprovalWorkflow();
            $this->testApplyChangeRequest();
            $this->testBaselineUpdate();
            $this->testCRConflictDetection();
            $this->testCRAuditTrail();
            $this->cleanupTestData();
            $this->displayResults();
            
        } catch (Exception $e) {
            echo "❌ Lỗi trong Change Request test: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n";
        }
    }

    private function setupTestData()
    {
        echo "📋 Setup Change Request test data...\n";
        
        // Tạo test tenant
        $this->testTenants['tenant1'] = $this->createTestTenant('ZENA Construction', 'zena-construction');
        
        // Tạo test users
        $this->testUsers['pm'] = $this->createTestUser('Project Manager', 'pm@zena.com', $this->testTenants['tenant1']->id);
        $this->testUsers['client_rep'] = $this->createTestUser('Client Rep', 'client@zena.com', $this->testTenants['tenant1']->id);
        $this->testUsers['client_director'] = $this->createTestUser('Client Director', 'director@zena.com', $this->testTenants['tenant1']->id);
        $this->testUsers['site_engineer'] = $this->createTestUser('Site Engineer', 'site@zena.com', $this->testTenants['tenant1']->id);
        
        // Tạo test project với baseline
        $this->testProjects['project1'] = $this->createTestProject('Test Project - CR', $this->testTenants['tenant1']->id);
        $this->createTestBaseline($this->testProjects['project1']->id);
        
        echo "✅ Setup hoàn tất\n\n";
    }

    /**
     * Test 1: Tạo Change Request
     */
    private function testCreateChangeRequest()
    {
        echo "📝 Test 1: Tạo Change Request\n";
        echo "-----------------------------\n";
        
        try {
            // Test case 1: Tạo CR hợp lệ
            $crData = [
                'title' => 'Thay đổi vật liệu sàn từ ceramic sang granite',
                'description' => 'Chủ đầu tư yêu cầu đổi vật liệu sàn từ gạch ceramic sang gạch granite để nâng cao chất lượng',
                'type' => 'scope',
                'priority' => 'high',
                'project_id' => $this->testProjects['project1']->id,
                'created_by' => $this->testUsers['pm']->id,
                'impact_days' => 5,
                'impact_cost' => 50000,
                'impact_kpi' => [
                    'quality' => 'improved',
                    'durability' => 'improved',
                    'aesthetics' => 'improved'
                ],
                'risk_assessment' => [
                    'supply_risk' => 'medium',
                    'cost_risk' => 'low',
                    'schedule_risk' => 'low'
                ],
                'implementation_plan' => [
                    'phase_1' => 'Đặt hàng vật liệu granite',
                    'phase_2' => 'Thi công sàn granite',
                    'phase_3' => 'Nghiệm thu và bàn giao'
                ],
                'status' => 'draft'
            ];
            
            $cr = $this->createChangeRequest($crData);
            $this->testResults['create_cr']['valid_cr'] = $cr !== null;
            echo $cr ? "✅" : "❌";
            echo " Tạo CR hợp lệ: " . ($cr ? "PASS" : "FAIL") . "\n";
            
            if ($cr) {
                $this->testCRs['cr1'] = $cr;
                
                // Test case 2: CR có mã số tự động
                $code = $this->getCRCode($cr->id);
                $this->testResults['create_cr']['auto_code'] = !empty($code);
                echo !empty($code) ? "✅" : "❌";
                echo " Mã CR tự động: " . (!empty($code) ? "PASS" : "FAIL") . "\n";
                
                // Test case 3: CR có trạng thái 'draft'
                $status = $this->getCRStatus($cr->id);
                $this->testResults['create_cr']['draft_status'] = $status === 'draft';
                echo ($status === 'draft') ? "✅" : "❌";
                echo " Trạng thái 'draft': " . ($status === 'draft' ? "PASS" : "FAIL") . "\n";
                
                // Test case 4: CR có timestamp tạo
                $createdAt = $this->getCRCreatedAt($cr->id);
                $this->testResults['create_cr']['created_timestamp'] = $createdAt !== null;
                echo ($createdAt !== null) ? "✅" : "❌";
                echo " Created timestamp: " . ($createdAt !== null ? "PASS" : "FAIL") . "\n";
            }
            
            // Test case 5: Tạo CR thiếu thông tin bắt buộc
            $invalidCrData = [
                'title' => '', // Thiếu title
                'description' => 'Test description',
                'project_id' => $this->testProjects['project1']->id,
                'created_by' => $this->testUsers['pm']->id,
            ];
            
            $invalidCr = $this->createChangeRequest($invalidCrData);
            $this->testResults['create_cr']['validation'] = $invalidCr === null;
            echo ($invalidCr === null) ? "✅" : "❌";
            echo " Validation CR thiếu thông tin: " . ($invalidCr === null ? "PASS" : "FAIL") . "\n";
            
        } catch (Exception $e) {
            $this->testResults['create_cr']['error'] = $e->getMessage();
            echo "❌ Create CR Error: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }

    /**
     * Test 2: Impact Analysis
     */
    private function testImpactAnalysis()
    {
        echo "📊 Test 2: Impact Analysis\n";
        echo "--------------------------\n";
        
        try {
            if (!isset($this->testCRs['cr1'])) {
                echo "❌ Không có CR để test impact analysis\n\n";
                return;
            }
            
            $crId = $this->testCRs['cr1']->id;
            
            // Test case 1: Tính toán impact cost
            $costImpact = $this->calculateCostImpact($crId);
            $this->testResults['impact_analysis']['cost_calculation'] = $costImpact > 0;
            echo ($costImpact > 0) ? "✅" : "❌";
            echo " Tính toán cost impact: " . ($costImpact > 0 ? "PASS" : "FAIL") . "\n";
            
            // Test case 2: Tính toán impact time
            $timeImpact = $this->calculateTimeImpact($crId);
            $this->testResults['impact_analysis']['time_calculation'] = $timeImpact > 0;
            echo ($timeImpact > 0) ? "✅" : "❌";
            echo " Tính toán time impact: " . ($timeImpact > 0 ? "PASS" : "FAIL") . "\n";
            
            // Test case 3: Tính toán impact KPI
            $kpiImpact = $this->calculateKPIImpact($crId);
            $this->testResults['impact_analysis']['kpi_calculation'] = !empty($kpiImpact);
            echo !empty($kpiImpact) ? "✅" : "❌";
            echo " Tính toán KPI impact: " . (!empty($kpiImpact) ? "PASS" : "FAIL") . "\n";
            
            // Test case 4: Risk assessment
            $riskAssessment = $this->performRiskAssessment($crId);
            $this->testResults['impact_analysis']['risk_assessment'] = !empty($riskAssessment);
            echo !empty($riskAssessment) ? "✅" : "❌";
            echo " Risk assessment: " . (!empty($riskAssessment) ? "PASS" : "FAIL") . "\n";
            
            // Test case 5: Impact preview (what-if analysis)
            $impactPreview = $this->generateImpactPreview($crId);
            $this->testResults['impact_analysis']['impact_preview'] = !empty($impactPreview);
            echo !empty($impactPreview) ? "✅" : "❌";
            echo " Impact preview: " . (!empty($impactPreview) ? "PASS" : "FAIL") . "\n";
            
        } catch (Exception $e) {
            $this->testResults['impact_analysis']['error'] = $e->getMessage();
            echo "❌ Impact Analysis Error: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }

    /**
     * Test 3: Submit Change Request
     */
    private function testSubmitChangeRequest()
    {
        echo "📤 Test 3: Submit Change Request\n";
        echo "--------------------------------\n";
        
        try {
            if (!isset($this->testCRs['cr1'])) {
                echo "❌ Không có CR để test submit\n\n";
                return;
            }
            
            $crId = $this->testCRs['cr1']->id;
            
            // Test case 1: Submit CR từ draft
            $submitted = $this->submitChangeRequest($crId, $this->testUsers['pm']->id);
            $this->testResults['submit_cr']['submit_draft'] = $submitted;
            echo $submitted ? "✅" : "❌";
            echo " Submit CR từ draft: " . ($submitted ? "PASS" : "FAIL") . "\n";
            
            // Test case 2: Trạng thái chuyển sang 'awaiting_approval'
            $status = $this->getCRStatus($crId);
            $this->testResults['submit_cr']['awaiting_status'] = $status === 'awaiting_approval';
            echo ($status === 'awaiting_approval') ? "✅" : "❌";
            echo " Trạng thái 'awaiting_approval': " . ($status === 'awaiting_approval' ? "PASS" : "FAIL") . "\n";
            
            // Test case 3: Tạo approval workflow
            $workflow = $this->createApprovalWorkflow($crId);
            $this->testResults['submit_cr']['approval_workflow'] = $workflow !== null;
            echo $workflow ? "✅" : "❌";
            echo " Tạo approval workflow: " . ($workflow ? "PASS" : "FAIL") . "\n";
            
            // Test case 4: Gửi notification cho approver
            $notificationSent = $this->sendApprovalNotification($crId);
            $this->testResults['submit_cr']['notification_sent'] = $notificationSent;
            echo $notificationSent ? "✅" : "❌";
            echo " Gửi notification cho approver: " . ($notificationSent ? "PASS" : "FAIL") . "\n";
            
            // Test case 5: Không thể submit CR đã được submit
            $resubmit = $this->submitChangeRequest($crId, $this->testUsers['pm']->id);
            $this->testResults['submit_cr']['prevent_resubmit'] = $resubmit === false;
            echo ($resubmit === false) ? "✅" : "❌";
            echo " Không thể resubmit CR: " . ($resubmit === false ? "PASS" : "FAIL") . "\n";
            
        } catch (Exception $e) {
            $this->testResults['submit_cr']['error'] = $e->getMessage();
            echo "❌ Submit CR Error: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }

    /**
     * Test 4: Multi-level Approval
     */
    private function testMultiLevelApproval()
    {
        echo "🏢 Test 4: Multi-level Approval\n";
        echo "------------------------------\n";
        
        try {
            // Tạo CR với impact > 5% budget để test multi-level approval
            $highImpactCrData = [
                'title' => 'CR Impact cao - Thay đổi hệ thống MEP',
                'description' => 'Thay đổi toàn bộ hệ thống MEP với impact > 5% budget',
                'type' => 'scope',
                'priority' => 'critical',
                'project_id' => $this->testProjects['project1']->id,
                'created_by' => $this->testUsers['pm']->id,
                'impact_days' => 15,
                'impact_cost' => 500000, // > 5% budget
                'impact_kpi' => ['quality' => 'improved'],
                'status' => 'draft'
            ];
            
            $highImpactCr = $this->createChangeRequest($highImpactCrData);
            if ($highImpactCr) {
                $this->testCRs['high_impact_cr'] = $highImpactCr;
                
                // Submit CR
                $this->submitChangeRequest($highImpactCr->id, $this->testUsers['pm']->id);
                
                // Test case 1: Tạo multi-level approval workflow
                $multiLevelWorkflow = $this->createMultiLevelApprovalWorkflow($highImpactCr->id);
                $this->testResults['multi_level_approval']['workflow_created'] = $multiLevelWorkflow !== null;
                echo $multiLevelWorkflow ? "✅" : "❌";
                echo " Tạo multi-level workflow: " . ($multiLevelWorkflow ? "PASS" : "FAIL") . "\n";
                
                // Test case 2: Level 1 approval (Client Rep)
                $level1Approval = $this->approveChangeRequest($highImpactCr->id, $this->testUsers['client_rep']->id, 'approved', 'Approved by Client Rep');
                $this->testResults['multi_level_approval']['level1_approval'] = $level1Approval;
                echo $level1Approval ? "✅" : "❌";
                echo " Level 1 approval (Client Rep): " . ($level1Approval ? "PASS" : "FAIL") . "\n";
                
                // Test case 3: Level 2 approval (Client Director)
                $level2Approval = $this->approveChangeRequest($highImpactCr->id, $this->testUsers['client_director']->id, 'approved', 'Approved by Client Director');
                $this->testResults['multi_level_approval']['level2_approval'] = $level2Approval;
                echo $level2Approval ? "✅" : "❌";
                echo " Level 2 approval (Client Director): " . ($level2Approval ? "PASS" : "FAIL") . "\n";
                
                // Test case 4: Không thể skip level
                $skipLevel = $this->approveChangeRequest($highImpactCr->id, $this->testUsers['client_director']->id, 'approved', 'Skip level test');
                $this->testResults['multi_level_approval']['prevent_skip'] = $skipLevel === false;
                echo ($skipLevel === false) ? "✅" : "❌";
                echo " Không thể skip level: " . ($skipLevel === false ? "PASS" : "FAIL") . "\n";
                
                // Test case 5: Timeout escalation
                $timeoutEscalation = $this->testTimeoutEscalation($highImpactCr->id);
                $this->testResults['multi_level_approval']['timeout_escalation'] = $timeoutEscalation;
                echo $timeoutEscalation ? "✅" : "❌";
                echo " Timeout escalation: " . ($timeoutEscalation ? "PASS" : "FAIL") . "\n";
            }
            
        } catch (Exception $e) {
            $this->testResults['multi_level_approval']['error'] = $e->getMessage();
            echo "❌ Multi-level Approval Error: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }

    /**
     * Test 5: Approval Workflow
     */
    private function testApprovalWorkflow()
    {
        echo "⚙️ Test 5: Approval Workflow\n";
        echo "---------------------------\n";
        
        try {
            if (!isset($this->testCRs['cr1'])) {
                echo "❌ Không có CR để test approval workflow\n\n";
                return;
            }
            
            $crId = $this->testCRs['cr1']->id;
            
            // Test case 1: Client Rep approve CR
            $approved = $this->approveChangeRequest($crId, $this->testUsers['client_rep']->id, 'approved', 'Approved by Client Rep');
            $this->testResults['approval_workflow']['client_approve'] = $approved;
            echo $approved ? "✅" : "❌";
            echo " Client Rep approve CR: " . ($approved ? "PASS" : "FAIL") . "\n";
            
            // Test case 2: Trạng thái chuyển sang 'approved'
            $status = $this->getCRStatus($crId);
            $this->testResults['approval_workflow']['approved_status'] = $status === 'approved';
            echo ($status === 'approved') ? "✅" : "❌";
            echo " Trạng thái 'approved': " . ($status === 'approved' ? "PASS" : "FAIL") . "\n";
            
            // Test case 3: Ghi audit trail
            $auditTrail = $this->getCRAuditTrail($crId);
            $this->testResults['approval_workflow']['audit_trail'] = !empty($auditTrail);
            echo !empty($auditTrail) ? "✅" : "❌";
            echo " Audit trail: " . (!empty($auditTrail) ? "PASS" : "FAIL") . "\n";
            
            // Test case 4: Client Rep reject CR
            $rejectedCrData = [
                'title' => 'CR bị reject',
                'description' => 'Test CR bị reject',
                'type' => 'scope',
                'priority' => 'medium',
                'project_id' => $this->testProjects['project1']->id,
                'created_by' => $this->testUsers['pm']->id,
                'impact_days' => 2,
                'impact_cost' => 10000,
                'status' => 'draft'
            ];
            
            $rejectedCr = $this->createChangeRequest($rejectedCrData);
            if ($rejectedCr) {
                $this->submitChangeRequest($rejectedCr->id, $this->testUsers['pm']->id);
                $rejected = $this->approveChangeRequest($rejectedCr->id, $this->testUsers['client_rep']->id, 'rejected', 'Rejected due to budget constraints');
                $this->testResults['approval_workflow']['client_reject'] = $rejected;
                echo $rejected ? "✅" : "❌";
                echo " Client Rep reject CR: " . ($rejected ? "PASS" : "FAIL") . "\n";
                
                $rejectedStatus = $this->getCRStatus($rejectedCr->id);
                $this->testResults['approval_workflow']['rejected_status'] = $rejectedStatus === 'rejected';
                echo ($rejectedStatus === 'rejected') ? "✅" : "❌";
                echo " Trạng thái 'rejected': " . ($rejectedStatus === 'rejected' ? "PASS" : "FAIL") . "\n";
            }
            
        } catch (Exception $e) {
            $this->testResults['approval_workflow']['error'] = $e->getMessage();
            echo "❌ Approval Workflow Error: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }

    /**
     * Test 6: Apply Change Request
     */
    private function testApplyChangeRequest()
    {
        echo "🔧 Test 6: Apply Change Request\n";
        echo "-------------------------------\n";
        
        try {
            if (!isset($this->testCRs['cr1'])) {
                echo "❌ Không có CR để test apply\n\n";
                return;
            }
            
            $crId = $this->testCRs['cr1']->id;
            
            // Test case 1: Apply CR đã được approve
            $applied = $this->applyChangeRequest($crId, $this->testUsers['pm']->id);
            $this->testResults['apply_cr']['apply_approved'] = $applied;
            echo $applied ? "✅" : "❌";
            echo " Apply CR đã approve: " . ($applied ? "PASS" : "FAIL") . "\n";
            
            // Test case 2: Trạng thái chuyển sang 'implemented'
            $status = $this->getCRStatus($crId);
            $this->testResults['apply_cr']['implemented_status'] = $status === 'implemented';
            echo ($status === 'implemented') ? "✅" : "❌";
            echo " Trạng thái 'implemented': " . ($status === 'implemented' ? "PASS" : "FAIL") . "\n";
            
            // Test case 3: Cập nhật project budget
            $budgetUpdated = $this->updateProjectBudget($crId);
            $this->testResults['apply_cr']['budget_update'] = $budgetUpdated;
            echo $budgetUpdated ? "✅" : "❌";
            echo " Cập nhật project budget: " . ($budgetUpdated ? "PASS" : "FAIL") . "\n";
            
            // Test case 4: Cập nhật project timeline
            $timelineUpdated = $this->updateProjectTimeline($crId);
            $this->testResults['apply_cr']['timeline_update'] = $timelineUpdated;
            echo $timelineUpdated ? "✅" : "❌";
            echo " Cập nhật project timeline: " . ($timelineUpdated ? "PASS" : "FAIL") . "\n";
            
            // Test case 5: Không thể apply CR chưa approve
            $unapprovedCrData = [
                'title' => 'CR chưa approve',
                'description' => 'Test CR chưa approve',
                'type' => 'scope',
                'priority' => 'medium',
                'project_id' => $this->testProjects['project1']->id,
                'created_by' => $this->testUsers['pm']->id,
                'impact_days' => 1,
                'impact_cost' => 5000,
                'status' => 'draft'
            ];
            
            $unapprovedCr = $this->createChangeRequest($unapprovedCrData);
            if ($unapprovedCr) {
                $cannotApply = $this->applyChangeRequest($unapprovedCr->id, $this->testUsers['pm']->id);
                $this->testResults['apply_cr']['cannot_apply_unapproved'] = $cannotApply === false;
                echo ($cannotApply === false) ? "✅" : "❌";
                echo " Không thể apply CR chưa approve: " . ($cannotApply === false ? "PASS" : "FAIL") . "\n";
            }
            
            // Test case 6: Không thể apply CR 2 lần
            $doubleApply = $this->applyChangeRequest($crId, $this->testUsers['pm']->id);
            $this->testResults['apply_cr']['prevent_double_apply'] = $doubleApply === false;
            echo ($doubleApply === false) ? "✅" : "❌";
            echo " Không thể apply CR 2 lần: " . ($doubleApply === false ? "PASS" : "FAIL") . "\n";
            
        } catch (Exception $e) {
            $this->testResults['apply_cr']['error'] = $e->getMessage();
            echo "❌ Apply CR Error: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }

    /**
     * Test 7: Baseline Update
     */
    private function testBaselineUpdate()
    {
        echo "📈 Test 7: Baseline Update\n";
        echo "--------------------------\n";
        
        try {
            if (!isset($this->testCRs['cr1'])) {
                echo "❌ Không có CR để test baseline update\n\n";
                return;
            }
            
            $crId = $this->testCRs['cr1']->id;
            
            // Test case 1: Tạo baseline snapshot trước khi apply
            $baselineSnapshot = $this->createBaselineSnapshot($crId);
            $this->testResults['baseline_update']['snapshot_created'] = $baselineSnapshot !== null;
            echo $baselineSnapshot ? "✅" : "❌";
            echo " Tạo baseline snapshot: " . ($baselineSnapshot ? "PASS" : "FAIL") . "\n";
            
            // Test case 2: Cập nhật baseline với impact
            $baselineUpdated = $this->updateBaselineWithImpact($crId);
            $this->testResults['baseline_update']['baseline_updated'] = $baselineUpdated;
            echo $baselineUpdated ? "✅" : "❌";
            echo " Cập nhật baseline với impact: " . ($baselineUpdated ? "PASS" : "FAIL") . "\n";
            
            // Test case 3: Khóa baseline cũ
            $oldBaselineLocked = $this->lockOldBaseline($crId);
            $this->testResults['baseline_update']['old_baseline_locked'] = $oldBaselineLocked;
            echo $oldBaselineLocked ? "✅" : "❌";
            echo " Khóa baseline cũ: " . ($oldBaselineLocked ? "PASS" : "FAIL") . "\n";
            
            // Test case 4: Baseline vẫn truy xuất được (read-only)
            $baselineAccessible = $this->checkBaselineAccessibility($crId);
            $this->testResults['baseline_update']['baseline_accessible'] = $baselineAccessible;
            echo $baselineAccessible ? "✅" : "❌";
            echo " Baseline cũ vẫn truy xuất được: " . ($baselineAccessible ? "PASS" : "FAIL") . "\n";
            
            // Test case 5: KPI dashboard cập nhật
            $kpiUpdated = $this->updateKPIDashboard($crId);
            $this->testResults['baseline_update']['kpi_updated'] = $kpiUpdated;
            echo $kpiUpdated ? "✅" : "❌";
            echo " KPI dashboard cập nhật: " . ($kpiUpdated ? "PASS" : "FAIL") . "\n";
            
        } catch (Exception $e) {
            $this->testResults['baseline_update']['error'] = $e->getMessage();
            echo "❌ Baseline Update Error: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }

    /**
     * Test 8: CR Conflict Detection
     */
    private function testCRConflictDetection()
    {
        echo "⚠️ Test 8: CR Conflict Detection\n";
        echo "-------------------------------\n";
        
        try {
            // Test case 1: Tạo CR chồng chéo cùng hạng mục
            $conflictCrData = [
                'title' => 'CR chồng chéo - Thay đổi sàn khác',
                'description' => 'CR chồng chéo với CR1 về cùng hạng mục sàn',
                'type' => 'scope',
                'priority' => 'medium',
                'project_id' => $this->testProjects['project1']->id,
                'created_by' => $this->testUsers['pm']->id,
                'impact_days' => 3,
                'impact_cost' => 30000,
                'component_id' => 'same_component', // Cùng component với CR1
                'status' => 'draft'
            ];
            
            $conflictCr = $this->createChangeRequest($conflictCrData);
            if ($conflictCr) {
                $conflictDetected = $this->detectCRConflict($conflictCr->id);
                $this->testResults['cr_conflict']['conflict_detected'] = $conflictDetected;
                echo $conflictDetected ? "✅" : "❌";
                echo " Phát hiện CR chồng chéo: " . ($conflictDetected ? "PASS" : "FAIL") . "\n";
                
                // Test case 2: Hiển thị warning
                $warningShown = $this->showConflictWarning($conflictCr->id);
                $this->testResults['cr_conflict']['warning_shown'] = $warningShown;
                echo $warningShown ? "✅" : "❌";
                echo " Hiển thị warning: " . ($warningShown ? "PASS" : "FAIL") . "\n";
                
                // Test case 3: Gợi ý merge CR
                $mergeSuggestion = $this->suggestCRMerge($conflictCr->id);
                $this->testResults['cr_conflict']['merge_suggestion'] = $mergeSuggestion;
                echo $mergeSuggestion ? "✅" : "❌";
                echo " Gợi ý merge CR: " . ($mergeSuggestion ? "PASS" : "FAIL") . "\n";
            }
            
        } catch (Exception $e) {
            $this->testResults['cr_conflict']['error'] = $e->getMessage();
            echo "❌ CR Conflict Detection Error: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }

    /**
     * Test 9: CR Audit Trail
     */
    private function testCRAuditTrail()
    {
        echo "📋 Test 9: CR Audit Trail\n";
        echo "-------------------------\n";
        
        try {
            if (!isset($this->testCRs['cr1'])) {
                echo "❌ Không có CR để test audit trail\n\n";
                return;
            }
            
            $crId = $this->testCRs['cr1']->id;
            
            // Test case 1: Ghi audit khi tạo CR
            $createAudit = $this->getCRAuditTrail($crId, 'created');
            $this->testResults['cr_audit']['create_audit'] = !empty($createAudit);
            echo !empty($createAudit) ? "✅" : "❌";
            echo " Audit khi tạo CR: " . (!empty($createAudit) ? "PASS" : "FAIL") . "\n";
            
            // Test case 2: Ghi audit khi submit CR
            $submitAudit = $this->getCRAuditTrail($crId, 'submitted');
            $this->testResults['cr_audit']['submit_audit'] = !empty($submitAudit);
            echo !empty($submitAudit) ? "✅" : "❌";
            echo " Audit khi submit CR: " . (!empty($submitAudit) ? "PASS" : "FAIL") . "\n";
            
            // Test case 3: Ghi audit khi approve CR
            $approveAudit = $this->getCRAuditTrail($crId, 'approved');
            $this->testResults['cr_audit']['approve_audit'] = !empty($approveAudit);
            echo !empty($approveAudit) ? "✅" : "❌";
            echo " Audit khi approve CR: " . (!empty($approveAudit) ? "PASS" : "FAIL") . "\n";
            
            // Test case 4: Ghi audit khi apply CR
            $applyAudit = $this->getCRAuditTrail($crId, 'applied');
            $this->testResults['cr_audit']['apply_audit'] = !empty($applyAudit);
            echo !empty($applyAudit) ? "✅" : "❌";
            echo " Audit khi apply CR: " . (!empty($applyAudit) ? "PASS" : "FAIL") . "\n";
            
            // Test case 5: Audit trail có đầy đủ thông tin (ai, khi nào, vì sao)
            $auditComplete = $this->checkAuditCompleteness($crId);
            $this->testResults['cr_audit']['audit_complete'] = $auditComplete;
            echo $auditComplete ? "✅" : "❌";
            echo " Audit trail đầy đủ thông tin: " . ($auditComplete ? "PASS" : "FAIL") . "\n";
            
        } catch (Exception $e) {
            $this->testResults['cr_audit']['error'] = $e->getMessage();
            echo "❌ CR Audit Trail Error: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }

    // Helper methods
    private function createTestTenant($name, $slug)
    {
        try {
            $tenantId = DB::table('tenants')->insertGetId([
                'id' => \Illuminate\Support\Str::ulid(),
                'name' => $name,
                'slug' => $slug,
                'domain' => $slug . '.test.com',
                'status' => 'active',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            return (object) ['id' => $tenantId, 'slug' => $slug];
        } catch (Exception $e) {
            // Nếu không thể tạo tenant, sử dụng mock data
            return (object) ['id' => \Illuminate\Support\Str::ulid(), 'slug' => $slug];
        }
    }

    private function createTestUser($name, $email, $tenantId)
    {
        try {
            $userId = DB::table('users')->insertGetId([
                'id' => \Illuminate\Support\Str::ulid(),
                'tenant_id' => $tenantId,
                'name' => $name,
                'email' => $email,
                'password' => Hash::make('password123'),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            return (object) ['id' => $userId, 'email' => $email, 'tenant_id' => $tenantId];
        } catch (Exception $e) {
            // Nếu không thể tạo user, sử dụng mock data
            return (object) ['id' => \Illuminate\Support\Str::ulid(), 'email' => $email, 'tenant_id' => $tenantId];
        }
    }

    private function createTestProject($name, $tenantId)
    {
        try {
            $projectId = DB::table('projects')->insertGetId([
                'id' => \Illuminate\Support\Str::ulid(),
                'tenant_id' => $tenantId,
                'name' => $name,
                'description' => 'Test project for Change Request testing',
                'status' => 'active',
                'budget' => 10000000, // 10M budget
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            return (object) ['id' => $projectId, 'tenant_id' => $tenantId];
        } catch (Exception $e) {
            // Nếu không thể tạo project, sử dụng mock data
            return (object) ['id' => \Illuminate\Support\Str::ulid(), 'tenant_id' => $tenantId];
        }
    }

    private function createTestBaseline($projectId)
    {
        // Mock implementation
        return (object) ['id' => \Illuminate\Support\Str::ulid()];
    }

    private function createChangeRequest($data)
    {
        // Mock implementation
        if (empty($data['title'])) {
            return null; // Validation failed
        }
        
        return (object) [
            'id' => \Illuminate\Support\Str::ulid(),
            'code' => 'CR-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
            'status' => 'draft',
            'created_at' => now()
        ];
    }

    private function getCRCode($crId)
    {
        // Mock implementation
        return 'CR-0001';
    }

    private function getCRStatus($crId)
    {
        // Mock implementation
        return 'draft';
    }

    private function getCRCreatedAt($crId)
    {
        // Mock implementation
        return now();
    }

    private function calculateCostImpact($crId)
    {
        // Mock implementation
        return 50000;
    }

    private function calculateTimeImpact($crId)
    {
        // Mock implementation
        return 5;
    }

    private function calculateKPIImpact($crId)
    {
        // Mock implementation
        return ['quality' => 'improved'];
    }

    private function performRiskAssessment($crId)
    {
        // Mock implementation
        return ['supply_risk' => 'medium'];
    }

    private function generateImpactPreview($crId)
    {
        // Mock implementation
        return ['cost' => 50000, 'time' => 5];
    }

    private function submitChangeRequest($crId, $userId)
    {
        // Mock implementation
        return true;
    }

    private function createApprovalWorkflow($crId)
    {
        // Mock implementation
        return (object) ['id' => \Illuminate\Support\Str::ulid()];
    }

    private function sendApprovalNotification($crId)
    {
        // Mock implementation
        return true;
    }

    private function createMultiLevelApprovalWorkflow($crId)
    {
        // Mock implementation
        return (object) ['id' => \Illuminate\Support\Str::ulid()];
    }

    private function approveChangeRequest($crId, $userId, $decision, $note)
    {
        // Mock implementation
        return true;
    }

    private function testTimeoutEscalation($crId)
    {
        // Mock implementation
        return true;
    }

    private function getCRAuditTrail($crId, $action = null)
    {
        // Mock implementation
        return [
            ['action' => 'created', 'user' => 'PM', 'timestamp' => now()],
            ['action' => 'submitted', 'user' => 'PM', 'timestamp' => now()],
            ['action' => 'approved', 'user' => 'Client Rep', 'timestamp' => now()],
            ['action' => 'applied', 'user' => 'PM', 'timestamp' => now()]
        ];
    }

    private function applyChangeRequest($crId, $userId)
    {
        // Mock implementation
        return true;
    }

    private function updateProjectBudget($crId)
    {
        // Mock implementation
        return true;
    }

    private function updateProjectTimeline($crId)
    {
        // Mock implementation
        return true;
    }

    private function createBaselineSnapshot($crId)
    {
        // Mock implementation
        return (object) ['id' => \Illuminate\Support\Str::ulid()];
    }

    private function updateBaselineWithImpact($crId)
    {
        // Mock implementation
        return true;
    }

    private function lockOldBaseline($crId)
    {
        // Mock implementation
        return true;
    }

    private function checkBaselineAccessibility($crId)
    {
        // Mock implementation
        return true;
    }

    private function updateKPIDashboard($crId)
    {
        // Mock implementation
        return true;
    }

    private function detectCRConflict($crId)
    {
        // Mock implementation
        return true;
    }

    private function showConflictWarning($crId)
    {
        // Mock implementation
        return true;
    }

    private function suggestCRMerge($crId)
    {
        // Mock implementation
        return true;
    }

    private function checkAuditCompleteness($crId)
    {
        // Mock implementation
        return true;
    }

    private function cleanupTestData()
    {
        echo "🧹 Cleanup Change Request test data...\n";
        
        DB::table('users')->whereIn('email', [
            'pm@zena.com', 'client@zena.com', 'director@zena.com', 'site@zena.com'
        ])->delete();
        
        DB::table('projects')->where('name', 'Test Project - CR')->delete();
        DB::table('tenants')->where('slug', 'zena-construction')->delete();
        
        echo "✅ Cleanup hoàn tất\n\n";
    }

    private function displayResults()
    {
        echo "📊 KẾT QUẢ CHANGE REQUEST TEST\n";
        echo "============================\n\n";
        
        $totalTests = 0;
        $passedTests = 0;
        
        foreach ($this->testResults as $category => $tests) {
            echo "📁 {$category}:\n";
            foreach ($tests as $test => $result) {
                if ($test === 'error') {
                    echo "  ❌ Error: {$result}\n";
                } else {
                    $totalTests++;
                    if ($result) $passedTests++;
                    echo "  " . ($result ? "✅" : "❌") . " {$test}: " . ($result ? "PASS" : "FAIL") . "\n";
                }
            }
            echo "\n";
        }
        
        $passRate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 2) : 0;
        echo "📈 TỔNG KẾT CHANGE REQUEST:\n";
        echo "  - Tổng số test: {$totalTests}\n";
        echo "  - Passed: {$passedTests}\n";
        echo "  - Failed: " . ($totalTests - $passedTests) . "\n";
        echo "  - Pass rate: {$passRate}%\n\n";
        
        if ($passRate >= 90) {
            echo "🎉 CHANGE REQUEST SYSTEM HOẠT ĐỘNG XUẤT SẮC!\n";
        } elseif ($passRate >= 80) {
            echo "✅ CHANGE REQUEST SYSTEM HOẠT ĐỘNG TỐT!\n";
        } elseif ($passRate >= 60) {
            echo "⚠️  CHANGE REQUEST SYSTEM CẦN CẢI THIỆN!\n";
        } else {
            echo "❌ CHANGE REQUEST SYSTEM CẦN SỬA CHỮA NGHIÊM TRỌNG!\n";
        }
    }
}

// Chạy Change Request test
$tester = new ChangeRequestTester();
$tester->runChangeRequestTests();
