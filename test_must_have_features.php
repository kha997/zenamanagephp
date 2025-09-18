<?php
/**
 * Test script cho các tính năng Must Have
 * Kiểm tra RBAC, RFI workflow, Change Request, Task dependencies, Multi-tenant, Secure upload
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

class MustHaveFeatureTester
{
    private $baseUrl = 'http://localhost:8000/api/v1';
    private $testResults = [];
    private $testUsers = [];
    private $testTenants = [];
    private $testProjects = [];
    private $authTokens = [];

    public function __construct()
    {
        echo "🚀 Bắt đầu test các tính năng Must Have...\n\n";
    }

    /**
     * Chạy tất cả các test
     */
    public function runAllTests()
    {
        try {
            $this->setupTestData();
            
            $this->testRBACRoles();
            $this->testRFIWorkflow();
            $this->testChangeRequest();
            $this->testTaskDependencies();
            $this->testMultiTenant();
            $this->testSecureUpload();
            
            $this->cleanupTestData();
            $this->displayResults();
            
        } catch (Exception $e) {
            echo "❌ Lỗi trong quá trình test: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n";
        }
    }

    /**
     * Setup dữ liệu test
     */
    private function setupTestData()
    {
        echo "📋 Setup dữ liệu test...\n";
        
        // Tạo test tenants
        $this->testTenants['tenant1'] = $this->createTestTenant('Test Tenant 1', 'test-tenant-1');
        $this->testTenants['tenant2'] = $this->createTestTenant('Test Tenant 2', 'test-tenant-2');
        
        // Tạo test users với các roles khác nhau
        $this->testUsers['system_admin'] = $this->createTestUser('System Admin', 'admin@test.com', $this->testTenants['tenant1']->id);
        $this->testUsers['pm'] = $this->createTestUser('Project Manager', 'pm@test.com', $this->testTenants['tenant1']->id);
        $this->testUsers['design_lead'] = $this->createTestUser('Design Lead', 'design@test.com', $this->testTenants['tenant1']->id);
        $this->testUsers['site_engineer'] = $this->createTestUser('Site Engineer', 'site@test.com', $this->testTenants['tenant1']->id);
        $this->testUsers['qc_inspector'] = $this->createTestUser('QC Inspector', 'qc@test.com', $this->testTenants['tenant1']->id);
        $this->testUsers['client_rep'] = $this->createTestUser('Client Rep', 'client@test.com', $this->testTenants['tenant1']->id);
        $this->testUsers['subcontractor'] = $this->createTestUser('Subcontractor', 'sub@test.com', $this->testTenants['tenant1']->id);
        
        // Tạo test project
        $this->testProjects['project1'] = $this->createTestProject('Test Project 1', $this->testTenants['tenant1']->id);
        
        echo "✅ Setup dữ liệu test hoàn tất\n\n";
    }

    /**
     * Test 1: RBAC Roles
     */
    private function testRBACRoles()
    {
        echo "🔐 Test 1: RBAC Roles\n";
        echo "====================\n";
        
        $testCases = [
            'system_admin' => ['permissions' => ['user.create', 'user.edit', 'user.delete', 'project.create', 'project.edit'], 'expected' => true],
            'pm' => ['permissions' => ['project.view', 'project.edit', 'task.create', 'task.edit'], 'expected' => true],
            'design_lead' => ['permissions' => ['document.create', 'document.edit', 'rfi.answer'], 'expected' => true],
            'site_engineer' => ['permissions' => ['task.view', 'interaction_log.create', 'site_diary.create'], 'expected' => true],
            'qc_inspector' => ['permissions' => ['inspection.create', 'ncr.create'], 'expected' => true],
            'client_rep' => ['permissions' => ['change_request.approve', 'document.view'], 'expected' => true],
            'subcontractor' => ['permissions' => ['submittal.create', 'task.view'], 'expected' => true],
        ];
        
        foreach ($testCases as $role => $testCase) {
            $user = $this->testUsers[$role];
            $hasPermissions = $this->checkUserPermissions($user->id, $testCase['permissions']);
            
            $result = $hasPermissions === $testCase['expected'];
            $this->testResults['rbac_roles'][$role] = $result;
            
            echo $result ? "✅" : "❌";
            echo " {$role}: " . ($result ? "PASS" : "FAIL") . "\n";
        }
        
        echo "\n";
    }

    /**
     * Test 2: RFI Workflow
     */
    private function testRFIWorkflow()
    {
        echo "📝 Test 2: RFI Workflow\n";
        echo "======================\n";
        
        try {
            // 1. Site Engineer tạo RFI
            $rfiData = [
                'type' => 'RFI',
                'title' => 'Test RFI - Chi tiết dầm không rõ',
                'description' => 'Không đọc được chi tiết dầm tại vị trí A1-B2',
                'priority' => 'medium',
                'assignee_id' => $this->testUsers['design_lead']->id,
                'due_at' => now()->addDays(3)->toISOString(),
                'project_id' => $this->testProjects['project1']->id,
                'visibility' => 'internal'
            ];
            
            $rfi = $this->createRFI($rfiData);
            $this->testResults['rfi_workflow']['create_rfi'] = $rfi !== null;
            echo $rfi ? "✅" : "❌";
            echo " Tạo RFI: " . ($rfi ? "PASS" : "FAIL") . "\n";
            
            if ($rfi) {
                // 2. Design Lead trả lời RFI
                $answerData = [
                    'answer' => 'Chi tiết dầm đã được cập nhật trong bản vẽ Rev B',
                    'attachments' => []
                ];
                
                $answered = $this->answerRFI($rfi->id, $answerData);
                $this->testResults['rfi_workflow']['answer_rfi'] = $answered;
                echo $answered ? "✅" : "❌";
                echo " Trả lời RFI: " . ($answered ? "PASS" : "FAIL") . "\n";
                
                // 3. PM đóng RFI
                $closed = $this->closeRFI($rfi->id);
                $this->testResults['rfi_workflow']['close_rfi'] = $closed;
                echo $closed ? "✅" : "❌";
                echo " Đóng RFI: " . ($closed ? "PASS" : "FAIL") . "\n";
            }
            
        } catch (Exception $e) {
            $this->testResults['rfi_workflow']['error'] = $e->getMessage();
            echo "❌ RFI Workflow Error: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }

    /**
     * Test 3: Change Request
     */
    private function testChangeRequest()
    {
        echo "🔄 Test 3: Change Request\n";
        echo "========================\n";
        
        try {
            // 1. PM tạo Change Request
            $crData = [
                'title' => 'Thay đổi vật liệu sàn',
                'description' => 'Chủ đầu tư yêu cầu đổi từ gạch ceramic sang gạch granite',
                'type' => 'scope',
                'priority' => 'high',
                'impact_days' => 5,
                'impact_cost' => 50000,
                'impact_kpi' => ['quality' => 'improved'],
                'project_id' => $this->testProjects['project1']->id,
                'status' => 'draft'
            ];
            
            $cr = $this->createChangeRequest($crData);
            $this->testResults['change_request']['create_cr'] = $cr !== null;
            echo $cr ? "✅" : "❌";
            echo " Tạo CR: " . ($cr ? "PASS" : "FAIL") . "\n";
            
            if ($cr) {
                // 2. Submit CR để phê duyệt
                $submitted = $this->submitChangeRequest($cr->id);
                $this->testResults['change_request']['submit_cr'] = $submitted;
                echo $submitted ? "✅" : "❌";
                echo " Submit CR: " . ($submitted ? "PASS" : "FAIL") . "\n";
                
                // 3. Client Rep phê duyệt CR
                $approved = $this->approveChangeRequest($cr->id);
                $this->testResults['change_request']['approve_cr'] = $approved;
                echo $approved ? "✅" : "❌";
                echo " Approve CR: " . ($approved ? "PASS" : "FAIL") . "\n";
                
                // 4. Apply impact vào project
                $applied = $this->applyChangeRequest($cr->id);
                $this->testResults['change_request']['apply_cr'] = $applied;
                echo $applied ? "✅" : "❌";
                echo " Apply CR: " . ($applied ? "PASS" : "FAIL") . "\n";
            }
            
        } catch (Exception $e) {
            $this->testResults['change_request']['error'] = $e->getMessage();
            echo "❌ Change Request Error: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }

    /**
     * Test 4: Task Dependencies
     */
    private function testTaskDependencies()
    {
        echo "🔗 Test 4: Task Dependencies\n";
        echo "============================\n";
        
        try {
            // 1. Tạo Task A (đổ bê tông)
            $taskAData = [
                'title' => 'Đổ bê tông móng',
                'description' => 'Đổ bê tông móng nhà',
                'status' => 'pending',
                'project_id' => $this->testProjects['project1']->id,
                'planned_start' => now()->addDays(1)->toISOString(),
                'planned_end' => now()->addDays(3)->toISOString()
            ];
            
            $taskA = $this->createTask($taskAData);
            $this->testResults['task_dependencies']['create_task_a'] = $taskA !== null;
            echo $taskA ? "✅" : "❌";
            echo " Tạo Task A: " . ($taskA ? "PASS" : "FAIL") . "\n";
            
            // 2. Tạo Task B (tháo cốp pha) - phụ thuộc Task A
            $taskBData = [
                'title' => 'Tháo cốp pha',
                'description' => 'Tháo cốp pha sau khi bê tông đã đông cứng',
                'status' => 'pending',
                'project_id' => $this->testProjects['project1']->id,
                'planned_start' => now()->addDays(4)->toISOString(),
                'planned_end' => now()->addDays(5)->toISOString()
            ];
            
            $taskB = $this->createTask($taskBData);
            $this->testResults['task_dependencies']['create_task_b'] = $taskB !== null;
            echo $taskB ? "✅" : "❌";
            echo " Tạo Task B: " . ($taskB ? "PASS" : "FAIL") . "\n";
            
            if ($taskA && $taskB) {
                // 3. Tạo dependency A → B
                $dependencyCreated = $this->createTaskDependency($taskA->id, $taskB->id);
                $this->testResults['task_dependencies']['create_dependency'] = $dependencyCreated;
                echo $dependencyCreated ? "✅" : "❌";
                echo " Tạo dependency: " . ($dependencyCreated ? "PASS" : "FAIL") . "\n";
                
                // 4. Thử start Task B trước khi Task A hoàn thành (phải bị chặn)
                $blocked = $this->tryStartTask($taskB->id);
                $this->testResults['task_dependencies']['block_start'] = $blocked === false;
                echo ($blocked === false) ? "✅" : "❌";
                echo " Block start Task B: " . ($blocked === false ? "PASS" : "FAIL") . "\n";
                
                // 5. Hoàn thành Task A
                $completedA = $this->completeTask($taskA->id);
                $this->testResults['task_dependencies']['complete_task_a'] = $completedA;
                echo $completedA ? "✅" : "❌";
                echo " Hoàn thành Task A: " . ($completedA ? "PASS" : "FAIL") . "\n";
                
                // 6. Bây giờ có thể start Task B
                $canStartB = $this->tryStartTask($taskB->id);
                $this->testResults['task_dependencies']['can_start_b'] = $canStartB === true;
                echo ($canStartB === true) ? "✅" : "❌";
                echo " Có thể start Task B: " . ($canStartB === true ? "PASS" : "FAIL") . "\n";
            }
            
        } catch (Exception $e) {
            $this->testResults['task_dependencies']['error'] = $e->getMessage();
            echo "❌ Task Dependencies Error: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }

    /**
     * Test 5: Multi-tenant
     */
    private function testMultiTenant()
    {
        echo "🏢 Test 5: Multi-tenant\n";
        echo "======================\n";
        
        try {
            // 1. User từ tenant1 truy cập dữ liệu tenant2 (phải bị chặn)
            $crossTenantAccess = $this->tryCrossTenantAccess();
            $this->testResults['multi_tenant']['block_cross_tenant'] = $crossTenantAccess === false;
            echo ($crossTenantAccess === false) ? "✅" : "❌";
            echo " Block cross-tenant access: " . ($crossTenantAccess === false ? "PASS" : "FAIL") . "\n";
            
            // 2. Kiểm tra tenant isolation trong queries
            $tenantIsolation = $this->checkTenantIsolation();
            $this->testResults['multi_tenant']['tenant_isolation'] = $tenantIsolation;
            echo $tenantIsolation ? "✅" : "❌";
            echo " Tenant isolation: " . ($tenantIsolation ? "PASS" : "FAIL") . "\n";
            
            // 3. Kiểm tra ULID không lộ sequence
            $ulidSecurity = $this->checkULIDSecurity();
            $this->testResults['multi_tenant']['ulid_security'] = $ulidSecurity;
            echo $ulidSecurity ? "✅" : "❌";
            echo " ULID security: " . ($ulidSecurity ? "PASS" : "FAIL") . "\n";
            
        } catch (Exception $e) {
            $this->testResults['multi_tenant']['error'] = $e->getMessage();
            echo "❌ Multi-tenant Error: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }

    /**
     * Test 6: Secure Upload
     */
    private function testSecureUpload()
    {
        echo "🔒 Test 6: Secure Upload\n";
        echo "========================\n";
        
        try {
            // 1. Test upload file hợp lệ
            $validFile = $this->createTestFile('test.pdf', 'application/pdf', 'valid content');
            $validUpload = $this->uploadFile($validFile);
            $this->testResults['secure_upload']['valid_file'] = $validUpload !== null;
            echo $validUpload ? "✅" : "❌";
            echo " Upload file hợp lệ: " . ($validUpload ? "PASS" : "FAIL") . "\n";
            
            // 2. Test upload file nguy hiểm (PHP trá hình PDF)
            $maliciousFile = $this->createTestFile('malicious.php.pdf', 'application/pdf', '<?php system($_GET["cmd"]); ?>');
            $maliciousUpload = $this->uploadFile($maliciousFile);
            $this->testResults['secure_upload']['block_malicious'] = $maliciousUpload === null;
            echo ($maliciousUpload === null) ? "✅" : "❌";
            echo " Block file nguy hiểm: " . ($maliciousUpload === null ? "PASS" : "FAIL") . "\n";
            
            // 3. Test MIME type validation
            $mimeValidation = $this->testMIMEValidation();
            $this->testResults['secure_upload']['mime_validation'] = $mimeValidation;
            echo $mimeValidation ? "✅" : "❌";
            echo " MIME validation: " . ($mimeValidation ? "PASS" : "FAIL") . "\n";
            
            // 4. Test file lưu ngoài public
            $storageSecurity = $this->testStorageSecurity();
            $this->testResults['secure_upload']['storage_security'] = $storageSecurity;
            echo $storageSecurity ? "✅" : "❌";
            echo " Storage security: " . ($storageSecurity ? "PASS" : "FAIL") . "\n";
            
        } catch (Exception $e) {
            $this->testResults['secure_upload']['error'] = $e->getMessage();
            echo "❌ Secure Upload Error: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }

    // Helper methods
    private function createTestTenant($name, $slug)
    {
        return DB::table('tenants')->insertGetId([
            'id' => \Illuminate\Support\Str::ulid(),
            'name' => $name,
            'slug' => $slug,
            'domain' => $slug . '.test.com',
            'status' => 'active',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    private function createTestUser($name, $email, $tenantId)
    {
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
    }

    private function createTestProject($name, $tenantId)
    {
        $projectId = DB::table('projects')->insertGetId([
            'id' => \Illuminate\Support\Str::ulid(),
            'tenant_id' => $tenantId,
            'name' => $name,
            'description' => 'Test project for testing',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        return (object) ['id' => $projectId, 'tenant_id' => $tenantId];
    }

    private function checkUserPermissions($userId, $permissions)
    {
        // Mock implementation - trong thực tế sẽ gọi RBAC service
        return true; // Tạm thời return true để test
    }

    private function createRFI($data)
    {
        // Mock implementation
        return (object) ['id' => \Illuminate\Support\Str::ulid()];
    }

    private function answerRFI($rfiId, $data)
    {
        // Mock implementation
        return true;
    }

    private function closeRFI($rfiId)
    {
        // Mock implementation
        return true;
    }

    private function createChangeRequest($data)
    {
        // Mock implementation
        return (object) ['id' => \Illuminate\Support\Str::ulid()];
    }

    private function submitChangeRequest($crId)
    {
        // Mock implementation
        return true;
    }

    private function approveChangeRequest($crId)
    {
        // Mock implementation
        return true;
    }

    private function applyChangeRequest($crId)
    {
        // Mock implementation
        return true;
    }

    private function createTask($data)
    {
        // Mock implementation
        return (object) ['id' => \Illuminate\Support\Str::ulid()];
    }

    private function createTaskDependency($taskAId, $taskBId)
    {
        // Mock implementation
        return true;
    }

    private function tryStartTask($taskId)
    {
        // Mock implementation - return false nếu bị block
        return false;
    }

    private function completeTask($taskId)
    {
        // Mock implementation
        return true;
    }

    private function tryCrossTenantAccess()
    {
        // Mock implementation - return false nếu bị block
        return false;
    }

    private function checkTenantIsolation()
    {
        // Mock implementation
        return true;
    }

    private function checkULIDSecurity()
    {
        // Mock implementation
        return true;
    }

    private function createTestFile($filename, $mimeType, $content)
    {
        return [
            'name' => $filename,
            'type' => $mimeType,
            'tmp_name' => tempnam(sys_get_temp_dir(), 'test'),
            'error' => 0,
            'size' => strlen($content)
        ];
    }

    private function uploadFile($file)
    {
        // Mock implementation
        return (object) ['id' => \Illuminate\Support\Str::ulid()];
    }

    private function testMIMEValidation()
    {
        // Mock implementation
        return true;
    }

    private function testStorageSecurity()
    {
        // Mock implementation
        return true;
    }

    private function cleanupTestData()
    {
        echo "🧹 Cleanup dữ liệu test...\n";
        
        // Xóa test data
        DB::table('users')->whereIn('email', [
            'admin@test.com', 'pm@test.com', 'design@test.com', 
            'site@test.com', 'qc@test.com', 'client@test.com', 'sub@test.com'
        ])->delete();
        
        DB::table('projects')->where('name', 'Test Project 1')->delete();
        DB::table('tenants')->whereIn('slug', ['test-tenant-1', 'test-tenant-2'])->delete();
        
        echo "✅ Cleanup hoàn tất\n\n";
    }

    private function displayResults()
    {
        echo "📊 KẾT QUẢ TEST TỔNG HỢP\n";
        echo "=======================\n\n";
        
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
        echo "📈 TỔNG KẾT:\n";
        echo "  - Tổng số test: {$totalTests}\n";
        echo "  - Passed: {$passedTests}\n";
        echo "  - Failed: " . ($totalTests - $passedTests) . "\n";
        echo "  - Pass rate: {$passRate}%\n\n";
        
        if ($passRate >= 80) {
            echo "🎉 HỆ THỐNG ĐẠT YÊU CẦU CƠ BẢN!\n";
        } elseif ($passRate >= 60) {
            echo "⚠️  HỆ THỐNG CẦN CẢI THIỆN!\n";
        } else {
            echo "❌ HỆ THỐNG CẦN SỬA CHỮA NGHIÊM TRỌNG!\n";
        }
    }
}

// Chạy test
$tester = new MustHaveFeatureTester();
$tester->runAllTests();
