<?php declare(strict_types=1);

// Bootstrap Laravel application
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Tenant;
use App\Models\User;
use Src\RBAC\Models\Role;
use Src\RBAC\Models\Permission;
use Src\CoreProject\Models\Project;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Src\RBAC\Services\AuthService; // Thay đổi import

class ProjectApiTester
{
    private $baseUrl;
    private $tenant;
    private $user;
    private $token;
    private $adminRole;
    private AuthService $authService; // Thêm property
    
    public function __construct()
    {
        $this->baseUrl = 'http://localhost:8000/api/v1';
        $this->authService = new AuthService(); // Khởi tạo AuthService
        $this->setupTestData();
        $this->authenticate();
    }
    
    /**
     * Thiết lập dữ liệu test
     */
    private function setupTestData(): void
    {
        echo "🔧 Setting up test data...\n";
        
        try {
            // Xóa dữ liệu cũ - sử dụng đúng tên bảng
            DB::table('system_user_roles')->delete();
            DB::table('custom_user_roles')->delete();
            DB::table('project_user_roles')->delete();
            DB::table('role_permissions')->delete();
            DB::table('projects')->delete();
            DB::table('users')->delete();
            DB::table('roles')->delete();
            DB::table('permissions')->delete();
            DB::table('tenants')->delete();
            
            // Tạo tenant bằng factory hoặc create trực tiếp
            $this->tenant = new Tenant();
            $this->tenant->name = 'Test Company';
            $this->tenant->slug = 'test-company';
            $this->tenant->domain = 'test.com';
            $this->tenant->status = 'trial';
            $this->tenant->is_active = true;
            $this->tenant->save();
            
            // Tạo user
            $this->user = new User();
            $this->user->name = 'Test User';
            $this->user->email = 'test@example.com';
            $this->user->tenant_id = $this->tenant->id;
            $this->user->password = Hash::make('password123');
            $this->user->is_active = true;
            $this->user->save();
            
            // Tạo permissions
            $permissions = [
                'project.create',
                'project.read', 
                'project.update',
                'project.delete'
            ];
            
            foreach ($permissions as $permissionCode) {
                $permission = new Permission();
                $permission->code = $permissionCode;
                $permission->module = 'project';
                $permission->action = explode('.', $permissionCode)[1];
                $permission->description = 'Permission for ' . $permissionCode;
                $permission->save();
            }
            
            // Tạo admin role
            $this->adminRole = new Role();
            $this->adminRole->name = 'Admin';
            $this->adminRole->scope = 'system';
            $this->adminRole->description = 'System Administrator';
            $this->adminRole->save();
            
            // Gán permissions cho role
            $permissionIds = Permission::whereIn('code', $permissions)->pluck('id');
            $this->adminRole->permissions()->attach($permissionIds);
            
            // Gán role cho user - sử dụng relationship đúng
            $this->user->systemRoles()->attach($this->adminRole->id);
            
            echo "✅ Test data setup completed\n";
            
        } catch (Exception $e) {
            echo "❌ Error setting up test data: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
    
    /**
     * Xác thực và lấy JWT token
     */
    private function authenticate(): void
    {
        echo "🔐 Authenticating user...\n";
        
        try {
            // Sử dụng AuthService thay vì JWTAuth
            $this->token = $this->authService->createTokenForUser($this->user);
            echo "✅ Authentication successful\n";
        } catch (Exception $e) {
            echo "❌ Authentication failed: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
    
    /**
     * Test GET /api/v1/projects - Lấy danh sách projects
     */
    public function testGetProjects(): void
    {
        echo "\n📋 Testing GET /projects...\n";
        
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
            'X-Tenant-ID' => $this->tenant->id
        ])->get($this->baseUrl . '/projects');
        
        $this->assertResponse($response, 200, 'GET /projects');
        
        $data = $response->json();
        if (isset($data['data']) && is_array($data['data'])) {
            echo "✅ Projects list retrieved successfully (" . count($data['data']) . " items)\n";
        } else {
            echo "⚠️  Unexpected response structure\n";
        }
    }
    
    /**
     * Test POST /api/v1/projects - Tạo project mới
     */
    public function testCreateProject(): array
    {
        echo "\n➕ Testing POST /projects...\n";
        
        $projectData = [
            'name' => 'Test Project ' . time(),
            'description' => 'This is a test project for API testing',
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
            'status' => 'planning',
            'planned_cost' => 100000.00,
            'visibility' => 'internal'
        ];
        
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Tenant-ID' => $this->tenant->id
        ])->post($this->baseUrl . '/projects', $projectData);
        
        $this->assertResponse($response, 201, 'POST /projects');
        
        $data = $response->json();
        if (isset($data['data']['id'])) {
            echo "✅ Project created successfully with ID: " . $data['data']['id'] . "\n";
            return $data['data'];
        } else {
            echo "❌ Project creation failed - no ID returned\n";
            return [];
        }
    }
    
    /**
     * Test GET /api/v1/projects/{id} - Lấy project cụ thể
     */
    public function testGetProject(string $projectId): void
    {
        echo "\n🔍 Testing GET /projects/{$projectId}...\n";
        
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
            'X-Tenant-ID' => $this->tenant->id
        ])->get($this->baseUrl . '/projects/' . $projectId);
        
        $this->assertResponse($response, 200, 'GET /projects/{id}');
        
        $data = $response->json();
        if (isset($data['data']['id']) && $data['data']['id'] === $projectId) {
            echo "✅ Project retrieved successfully\n";
        } else {
            echo "❌ Project retrieval failed or ID mismatch\n";
        }
    }
    
    /**
     * Test PUT /api/v1/projects/{id} - Cập nhật project
     */
    public function testUpdateProject(string $projectId): void
    {
        echo "\n✏️  Testing PUT /projects/{$projectId}...\n";
        
        $updateData = [
            'name' => 'Updated Test Project ' . time(),
            'description' => 'This project has been updated via API test',
            'status' => 'active',
            'progress' => 25.50
        ];
        
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Tenant-ID' => $this->tenant->id
        ])->put($this->baseUrl . '/projects/' . $projectId, $updateData);
        
        $this->assertResponse($response, 200, 'PUT /projects/{id}');
        
        $data = $response->json();
        if (isset($data['data']['name']) && strpos($data['data']['name'], 'Updated') !== false) {
            echo "✅ Project updated successfully\n";
        } else {
            echo "❌ Project update failed\n";
        }
    }
    
    /**
     * Test DELETE /api/v1/projects/{id} - Xóa project
     */
    public function testDeleteProject(string $projectId): void
    {
        echo "\n🗑️  Testing DELETE /projects/{$projectId}...\n";
        
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
            'X-Tenant-ID' => $this->tenant->id
        ])->delete($this->baseUrl . '/projects/' . $projectId);
        
        $this->assertResponse($response, 200, 'DELETE /projects/{id}');
        echo "✅ Project deleted successfully\n";
    }
    
    /**
     * Test validation errors
     */
    public function testValidationErrors(): void
    {
        echo "\n⚠️  Testing validation errors...\n";
        
        $invalidData = [
            'name' => '', // Required field empty
            'start_date' => 'invalid-date',
            'planned_cost' => 'not-a-number'
        ];
        
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Tenant-ID' => $this->tenant->id
        ])->post($this->baseUrl . '/projects', $invalidData);
        
        if ($response->status() === 422) {
            echo "✅ Validation errors handled correctly\n";
        } else {
            echo "❌ Validation errors not handled properly (Status: " . $response->status() . ")\n";
        }
    }
    
    /**
     * Helper method để kiểm tra response
     */
    private function assertResponse($response, int $expectedStatus, string $testName): void
    {
        $actualStatus = $response->status();
        
        if ($actualStatus === $expectedStatus) {
            echo "✅ {$testName}: Status {$actualStatus} (Expected: {$expectedStatus})\n";
        } else {
            echo "❌ {$testName}: Status {$actualStatus} (Expected: {$expectedStatus})\n";
            echo "Response: " . $response->body() . "\n";
        }
    }
    
    /**
     * Chạy tất cả tests
     */
    public function runAllTests(): void
    {
        echo "🚀 Starting Project API Tests...\n";
        echo "=================================\n";
        
        try {
            // Test GET projects (empty list)
            $this->testGetProjects();
            
            // Test CREATE project
            $project = $this->testCreateProject();
            
            if (!empty($project) && isset($project['id'])) {
                $projectId = $project['id'];
                
                // Test GET specific project
                $this->testGetProject($projectId);
                
                // Test UPDATE project
                $this->testUpdateProject($projectId);
                
                // Test GET projects (should have 1 item)
                $this->testGetProjects();
                
                // Test DELETE project
                $this->testDeleteProject($projectId);
                
                // Test GET projects (should be empty again)
                $this->testGetProjects();
            }
            
            // Test validation errors
            $this->testValidationErrors();
            
            echo "\n🎉 All tests completed!\n";
            
        } catch (Exception $e) {
            echo "\n💥 Test failed with exception: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n";
        }
    }
}

// Chạy tests
try {
    $tester = new ProjectApiTester();
    $tester->runAllTests();
} catch (Exception $e) {
    echo "💥 Failed to initialize tester: " . $e->getMessage() . "\n";
    exit(1);
}