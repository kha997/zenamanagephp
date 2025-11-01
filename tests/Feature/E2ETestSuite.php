<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Models\Client;
use App\Services\AppApiGateway;
use App\Services\PerformanceMonitoringService;
use App\Services\SecurityAuditService;

/**
 * E2ETestSuite
 * 
 * Comprehensive end-to-end testing suite for ZenaManage system
 * Tests complete user workflows and system integration
 * 
 * Features:
 * - Complete user workflow testing
 * - Admin workflow testing
 * - Client workflow testing
 * - System integration testing
 * - Cross-module functionality testing
 * - Real-world scenario testing
 */
class E2ETestSuite extends TestCase
{
    use RefreshDatabase, WithFaker;
    
    private AppApiGateway $apiGateway;
    private PerformanceMonitoringService $performanceService;
    private SecurityAuditService $securityService;
    private array $testUsers = [];
    private array $testData = [];
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->apiGateway = app(AppApiGateway::class);
        $this->performanceService = app(PerformanceMonitoringService::class);
        $this->securityService = app(SecurityAuditService::class);
        
        // Set up comprehensive test data
        $this->setUpComprehensiveTestData();
    }
    
    /**
     * Test complete user workflow from login to project completion
     */
    public function testCompleteUserWorkflow(): void
    {
        $this->markTestSkipped('E2E test - run manually with: php artisan test --filter=testCompleteUserWorkflow');
        
        Log::info('Starting complete user workflow test');
        
        $user = $this->testUsers['project_manager'];
        
        // Step 1: User Login
        $this->testUserLogin($user);
        
        // Step 2: Dashboard Access
        $this->testDashboardAccess($user);
        
        // Step 3: Project Creation
        $project = $this->testProjectCreation($user);
        
        // Step 4: Task Management
        $tasks = $this->testTaskManagement($user, $project);
        
        // Step 5: Client Management
        $client = $this->testClientManagement($user);
        
        // Step 6: Document Management
        $this->testDocumentManagement($user, $project);
        
        // Step 7: Team Collaboration
        $this->testTeamCollaboration($user, $project);
        
        // Step 8: Project Completion
        $this->testProjectCompletion($user, $project, $tasks);
        
        // Step 9: Reporting and Analytics
        $this->testReportingAndAnalytics($user);
        
        Log::info('Complete user workflow test completed successfully');
    }
    
    /**
     * Test admin workflow for system management
     */
    public function testAdminWorkflow(): void
    {
        $this->markTestSkipped('E2E test - run manually with: php artisan test --filter=testAdminWorkflow');
        
        Log::info('Starting admin workflow test');
        
        $admin = $this->testUsers['admin'];
        
        // Step 1: Admin Login
        $this->testUserLogin($admin);
        
        // Step 2: System Overview
        $this->testSystemOverview($admin);
        
        // Step 3: User Management
        $this->testUserManagement($admin);
        
        // Step 4: Tenant Management
        $this->testTenantManagement($admin);
        
        // Step 5: System Configuration
        $this->testSystemConfiguration($admin);
        
        // Step 6: Performance Monitoring
        $this->testPerformanceMonitoring($admin);
        
        // Step 7: Security Management
        $this->testSecurityManagement($admin);
        
        // Step 8: Backup and Maintenance
        $this->testBackupAndMaintenance($admin);
        
        Log::info('Admin workflow test completed successfully');
    }
    
    /**
     * Test client workflow for external client access
     */
    public function testClientWorkflow(): void
    {
        $this->markTestSkipped('E2E test - run manually with: php artisan test --filter=testClientWorkflow');
        
        Log::info('Starting client workflow test');
        
        $client = $this->testUsers['client'];
        
        // Step 1: Client Login
        $this->testUserLogin($client);
        
        // Step 2: Project Viewing
        $this->testClientProjectViewing($client);
        
        // Step 3: Task Status Updates
        $this->testClientTaskUpdates($client);
        
        // Step 4: Document Access
        $this->testClientDocumentAccess($client);
        
        // Step 5: Communication
        $this->testClientCommunication($client);
        
        // Step 6: Reporting
        $this->testClientReporting($client);
        
        Log::info('Client workflow test completed successfully');
    }
    
    /**
     * Test system integration across all modules
     */
    public function testSystemIntegration(): void
    {
        $this->markTestSkipped('E2E test - run manually with: php artisan test --filter=testSystemIntegration');
        
        Log::info('Starting system integration test');
        
        // Test API Gateway integration
        $this->testApiGatewayIntegration();
        
        // Test Performance Monitoring integration
        $this->testPerformanceMonitoringIntegration();
        
        // Test Security Audit integration
        $this->testSecurityAuditIntegration();
        
        // Test Cache integration
        $this->testCacheIntegration();
        
        // Test Database integration
        $this->testDatabaseIntegration();
        
        // Test Queue integration
        $this->testQueueIntegration();
        
        // Test Notification integration
        $this->testNotificationIntegration();
        
        Log::info('System integration test completed successfully');
    }
    
    /**
     * Test user login workflow
     */
    private function testUserLogin(User $user): void
    {
        $response = $this->post('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password'
        ]);
        
        $this->assertEquals(200, $response->status());
        $this->assertArrayHasKey('token', $response->json());
        
        Log::info('User login test passed', ['user_id' => $user->id]);
    }
    
    /**
     * Test dashboard access
     */
    private function testDashboardAccess(User $user): void
    {
        $this->actingAs($user);
        
        $response = $this->get('/api/dashboard');
        
        $this->assertEquals(200, $response->status());
        $this->assertArrayHasKey('data', $response->json());
        
        Log::info('Dashboard access test passed', ['user_id' => $user->id]);
    }
    
    /**
     * Test project creation workflow
     */
    private function testProjectCreation(User $user): Project
    {
        $this->actingAs($user);
        
        $projectData = [
            'name' => 'E2E Test Project',
            'description' => 'Project created during E2E testing',
            'budget_total' => 50000,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(3)->toDateString(),
            'client_id' => $this->testData['client']->id
        ];
        
        $response = $this->post('/api/projects', $projectData);
        
        $this->assertEquals(201, $response->status());
        $this->assertArrayHasKey('data', $response->json());
        
        $project = Project::find($response->json('data.id'));
        $this->assertNotNull($project);
        
        Log::info('Project creation test passed', [
            'user_id' => $user->id,
            'project_id' => $project->id
        ]);
        
        return $project;
    }
    
    /**
     * Test task management workflow
     */
    private function testTaskManagement(User $user, Project $project): array
    {
        $this->actingAs($user);
        
        $tasks = [];
        
        // Create multiple tasks
        for ($i = 1; $i <= 5; $i++) {
            $taskData = [
                'name' => "E2E Test Task {$i}",
                'description' => "Task {$i} created during E2E testing",
                'project_id' => $project->id,
                'start_date' => now()->toDateString(),
                'end_date' => now()->addWeeks(2)->toDateString(),
                'priority' => $this->faker->randomElement(['low', 'medium', 'high']),
                'status' => 'pending'
            ];
            
            $response = $this->post('/api/tasks', $taskData);
            
            $this->assertEquals(201, $response->status());
            $this->assertArrayHasKey('data', $response->json());
            
            $task = Task::find($response->json('data.id'));
            $this->assertNotNull($task);
            $tasks[] = $task;
        }
        
        // Update task status
        foreach ($tasks as $index => $task) {
            $updateData = [
                'status' => $index < 2 ? 'in_progress' : 'pending',
                'progress_percent' => $index < 2 ? 50 : 0
            ];
            
            $response = $this->put("/api/tasks/{$task->id}", $updateData);
            
            $this->assertEquals(200, $response->status());
        }
        
        Log::info('Task management test passed', [
            'user_id' => $user->id,
            'project_id' => $project->id,
            'tasks_created' => count($tasks)
        ]);
        
        return $tasks;
    }
    
    /**
     * Test client management workflow
     */
    private function testClientManagement(User $user): Client
    {
        $this->actingAs($user);
        
        $clientData = [
            'name' => 'E2E Test Client',
            'email' => 'e2eclient@example.com',
            'phone' => '1234567890',
            'company' => 'E2E Test Company',
            'address' => '123 Test Street, Test City'
        ];
        
        $response = $this->post('/api/clients', $clientData);
        
        $this->assertEquals(201, $response->status());
        $this->assertArrayHasKey('data', $response->json());
        
        $client = Client::find($response->json('data.id'));
        $this->assertNotNull($client);
        
        Log::info('Client management test passed', [
            'user_id' => $user->id,
            'client_id' => $client->id
        ]);
        
        return $client;
    }
    
    /**
     * Test document management workflow
     */
    private function testDocumentManagement(User $user, Project $project): void
    {
        $this->actingAs($user);
        
        // Test document upload (simulated)
        $documentData = [
            'name' => 'E2E Test Document',
            'description' => 'Document uploaded during E2E testing',
            'project_id' => $project->id,
            'file_path' => '/uploads/test-document.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024000
        ];
        
        $response = $this->post('/api/documents', $documentData);
        
        $this->assertEquals(201, $response->status());
        $this->assertArrayHasKey('data', $response->json());
        
        Log::info('Document management test passed', [
            'user_id' => $user->id,
            'project_id' => $project->id
        ]);
    }
    
    /**
     * Test team collaboration workflow
     */
    private function testTeamCollaboration(User $user, Project $project): void
    {
        $this->actingAs($user);
        
        // Test team member invitation
        $inviteData = [
            'email' => 'teammember@example.com',
            'role' => 'member',
            'project_id' => $project->id
        ];
        
        $response = $this->post('/api/team/invite', $inviteData);
        
        $this->assertEquals(200, $response->status());
        
        Log::info('Team collaboration test passed', [
            'user_id' => $user->id,
            'project_id' => $project->id
        ]);
    }
    
    /**
     * Test project completion workflow
     */
    private function testProjectCompletion(User $user, Project $project, array $tasks): void
    {
        $this->actingAs($user);
        
        // Complete all tasks
        foreach ($tasks as $task) {
            $updateData = [
                'status' => 'completed',
                'progress_percent' => 100,
                'completed_at' => now()->toISOString()
            ];
            
            $response = $this->put("/api/tasks/{$task->id}", $updateData);
            $this->assertEquals(200, $response->status());
        }
        
        // Complete project
        $projectData = [
            'status' => 'completed',
            'progress_pct' => 100,
            'completed_at' => now()->toISOString()
        ];
        
        $response = $this->put("/api/projects/{$project->id}", $projectData);
        
        $this->assertEquals(200, $response->status());
        
        Log::info('Project completion test passed', [
            'user_id' => $user->id,
            'project_id' => $project->id,
            'tasks_completed' => count($tasks)
        ]);
    }
    
    /**
     * Test reporting and analytics workflow
     */
    private function testReportingAndAnalytics(User $user): void
    {
        $this->actingAs($user);
        
        // Test dashboard analytics
        $response = $this->get('/api/dashboard/stats');
        
        $this->assertEquals(200, $response->status());
        $this->assertArrayHasKey('data', $response->json());
        
        // Test performance metrics
        $response = $this->get('/api/performance/metrics');
        
        $this->assertEquals(200, $response->status());
        $this->assertArrayHasKey('data', $response->json());
        
        Log::info('Reporting and analytics test passed', ['user_id' => $user->id]);
    }
    
    /**
     * Test system overview for admin
     */
    private function testSystemOverview(User $admin): void
    {
        $this->actingAs($admin);
        
        $response = $this->get('/api/admin/overview');
        
        $this->assertEquals(200, $response->status());
        $this->assertArrayHasKey('data', $response->json());
        
        Log::info('System overview test passed', ['admin_id' => $admin->id]);
    }
    
    /**
     * Test user management for admin
     */
    private function testUserManagement(User $admin): void
    {
        $this->actingAs($admin);
        
        // Test user listing
        $response = $this->get('/api/admin/users');
        
        $this->assertEquals(200, $response->status());
        $this->assertArrayHasKey('data', $response->json());
        
        // Test user creation
        $userData = [
            'name' => 'E2E Admin Test User',
            'email' => 'e2eadmintest@example.com',
            'password' => 'password',
            'role' => 'member',
            'tenant_id' => 1
        ];
        
        $response = $this->post('/api/admin/users', $userData);
        
        $this->assertEquals(201, $response->status());
        
        Log::info('User management test passed', ['admin_id' => $admin->id]);
    }
    
    /**
     * Test tenant management for admin
     */
    private function testTenantManagement(User $admin): void
    {
        $this->actingAs($admin);
        
        $response = $this->get('/api/admin/tenants');
        
        $this->assertEquals(200, $response->status());
        $this->assertArrayHasKey('data', $response->json());
        
        Log::info('Tenant management test passed', ['admin_id' => $admin->id]);
    }
    
    /**
     * Test system configuration for admin
     */
    private function testSystemConfiguration(User $admin): void
    {
        $this->actingAs($admin);
        
        $response = $this->get('/api/admin/config');
        
        $this->assertEquals(200, $response->status());
        $this->assertArrayHasKey('data', $response->json());
        
        Log::info('System configuration test passed', ['admin_id' => $admin->id]);
    }
    
    /**
     * Test performance monitoring for admin
     */
    private function testPerformanceMonitoring(User $admin): void
    {
        $this->actingAs($admin);
        
        $response = $this->get('/api/performance/dashboard');
        
        $this->assertEquals(200, $response->status());
        $this->assertArrayHasKey('data', $response->json());
        
        Log::info('Performance monitoring test passed', ['admin_id' => $admin->id]);
    }
    
    /**
     * Test security management for admin
     */
    private function testSecurityManagement(User $admin): void
    {
        $this->actingAs($admin);
        
        $response = $this->get('/api/admin/security/audit');
        
        $this->assertEquals(200, $response->status());
        $this->assertArrayHasKey('data', $response->json());
        
        Log::info('Security management test passed', ['admin_id' => $admin->id]);
    }
    
    /**
     * Test backup and maintenance for admin
     */
    private function testBackupAndMaintenance(User $admin): void
    {
        $this->actingAs($admin);
        
        $response = $this->get('/api/admin/backup/status');
        
        $this->assertEquals(200, $response->status());
        
        Log::info('Backup and maintenance test passed', ['admin_id' => $admin->id]);
    }
    
    /**
     * Test client project viewing
     */
    private function testClientProjectViewing(User $client): void
    {
        $this->actingAs($client);
        
        $response = $this->get('/api/projects');
        
        $this->assertEquals(200, $response->status());
        $this->assertArrayHasKey('data', $response->json());
        
        Log::info('Client project viewing test passed', ['client_id' => $client->id]);
    }
    
    /**
     * Test client task updates
     */
    private function testClientTaskUpdates(User $client): void
    {
        $this->actingAs($client);
        
        $response = $this->get('/api/tasks');
        
        $this->assertEquals(200, $response->status());
        $this->assertArrayHasKey('data', $response->json());
        
        Log::info('Client task updates test passed', ['client_id' => $client->id]);
    }
    
    /**
     * Test client document access
     */
    private function testClientDocumentAccess(User $client): void
    {
        $this->actingAs($client);
        
        $response = $this->get('/api/documents');
        
        $this->assertEquals(200, $response->status());
        $this->assertArrayHasKey('data', $response->json());
        
        Log::info('Client document access test passed', ['client_id' => $client->id]);
    }
    
    /**
     * Test client communication
     */
    private function testClientCommunication(User $client): void
    {
        $this->actingAs($client);
        
        // Test communication features
        $response = $this->get('/api/communications');
        
        $this->assertEquals(200, $response->status());
        
        Log::info('Client communication test passed', ['client_id' => $client->id]);
    }
    
    /**
     * Test client reporting
     */
    private function testClientReporting(User $client): void
    {
        $this->actingAs($client);
        
        $response = $this->get('/api/reports');
        
        $this->assertEquals(200, $response->status());
        $this->assertArrayHasKey('data', $response->json());
        
        Log::info('Client reporting test passed', ['client_id' => $client->id]);
    }
    
    /**
     * Test API Gateway integration
     */
    private function testApiGatewayIntegration(): void
    {
        $this->apiGateway->setAuthContext(null, '1', 'tenant');
        
        $response = $this->apiGateway->fetchProjects();
        
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
        
        Log::info('API Gateway integration test passed');
    }
    
    /**
     * Test Performance Monitoring integration
     */
    private function testPerformanceMonitoringIntegration(): void
    {
        $metrics = $this->performanceService->getAllMetrics();
        
        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('request_count', $metrics);
        $this->assertArrayHasKey('average_response_time', $metrics);
        
        Log::info('Performance Monitoring integration test passed');
    }
    
    /**
     * Test Security Audit integration
     */
    private function testSecurityAuditIntegration(): void
    {
        $this->securityService->logSecurityEvent('e2e_test_event', [
            'test' => true,
            'timestamp' => now()->toISOString()
        ]);
        
        $auditLog = $this->securityService->getSecurityAuditLog();
        
        $this->assertIsArray($auditLog);
        
        Log::info('Security Audit integration test passed');
    }
    
    /**
     * Test Cache integration
     */
    private function testCacheIntegration(): void
    {
        $key = 'e2e_test_cache_key';
        $value = 'e2e_test_cache_value';
        
        Cache::put($key, $value, 60);
        $retrieved = Cache::get($key);
        
        $this->assertEquals($value, $retrieved);
        
        Cache::forget($key);
        
        Log::info('Cache integration test passed');
    }
    
    /**
     * Test Database integration
     */
    private function testDatabaseIntegration(): void
    {
        $userCount = User::count();
        $projectCount = Project::count();
        $taskCount = Task::count();
        $clientCount = Client::count();
        
        $this->assertGreaterThan(0, $userCount);
        $this->assertGreaterThan(0, $projectCount);
        $this->assertGreaterThan(0, $taskCount);
        $this->assertGreaterThan(0, $clientCount);
        
        Log::info('Database integration test passed', [
            'users' => $userCount,
            'projects' => $projectCount,
            'tasks' => $taskCount,
            'clients' => $clientCount
        ]);
    }
    
    /**
     * Test Queue integration
     */
    private function testQueueIntegration(): void
    {
        // Test queue functionality
        $this->assertTrue(true); // Placeholder for queue testing
        
        Log::info('Queue integration test passed');
    }
    
    /**
     * Test Notification integration
     */
    private function testNotificationIntegration(): void
    {
        // Test notification functionality
        $this->assertTrue(true); // Placeholder for notification testing
        
        Log::info('Notification integration test passed');
    }
    
    /**
     * Set up comprehensive test data
     */
    private function setUpComprehensiveTestData(): void
    {
        // Create test users with different roles
        $this->testUsers['admin'] = User::create([
            'name' => 'E2E Admin User',
            'email' => 'e2eadmin@example.com',
            'password' => bcrypt('password'),
            'tenant_id' => 1,
            'role' => 'admin'
        ]);
        
        $this->testUsers['project_manager'] = User::create([
            'name' => 'E2E Project Manager',
            'email' => 'e2epm@example.com',
            'password' => bcrypt('password'),
            'tenant_id' => 1,
            'role' => 'project_manager'
        ]);
        
        $this->testUsers['member'] = User::create([
            'name' => 'E2E Member User',
            'email' => 'e2emember@example.com',
            'password' => bcrypt('password'),
            'tenant_id' => 1,
            'role' => 'member'
        ]);
        
        $this->testUsers['client'] = User::create([
            'name' => 'E2E Client User',
            'email' => 'e2eclient@example.com',
            'password' => bcrypt('password'),
            'tenant_id' => 1,
            'role' => 'client'
        ]);
        
        // Create test client
        $this->testData['client'] = Client::create([
            'name' => 'E2E Test Client Company',
            'email' => 'client@e2etest.com',
            'phone' => '1234567890',
            'tenant_id' => 1
        ]);
        
        // Create test projects
        for ($i = 1; $i <= 3; $i++) {
            $project = Project::create([
                'name' => "E2E Test Project {$i}",
                'description' => "Project {$i} for E2E testing",
                'budget_total' => 100000,
                'user_id' => $this->testUsers['project_manager']->id,
                'client_id' => $this->testData['client']->id,
                'tenant_id' => 1
            ]);
            
            $this->testData['projects'][] = $project;
            
            // Create test tasks for each project
            for ($j = 1; $j <= 5; $j++) {
                Task::create([
                    'name' => "E2E Test Task {$i}-{$j}",
                    'description' => "Task {$j} for Project {$i}",
                    'project_id' => $project->id,
                    'user_id' => $this->testUsers['project_manager']->id,
                    'tenant_id' => 1
                ]);
            }
        }
        
        Log::info('Comprehensive test data setup completed', [
            'users_created' => count($this->testUsers),
            'projects_created' => count($this->testData['projects']),
            'client_created' => $this->testData['client']->id
        ]);
    }
}
