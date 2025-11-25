<?php declare(strict_types=1);

namespace Tests\Feature\Api\Reports;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Contract;
use App\Models\ContractBudgetLine;
use App\Models\ContractExpense;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;
use Carbon\Carbon;

/**
 * Tests for Project Health Portfolio Export API
 * 
 * Round 79: Project Health Portfolio Export
 * 
 * Tests that project health portfolio export endpoint returns CSV file with proper
 * tenant isolation and permission checks.
 * 
 * @group reports
 * @group projects
 * @group health
 * @group export
 */
class ProjectHealthPortfolioExportTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;
    private string $tokenA;
    private string $tokenB;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(78902);
        
        // Create tenants
        $this->tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
        $this->tenantB = Tenant::factory()->create(['name' => 'Tenant B']);
        
        // Create users
        $this->userA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'email' => 'userA@test.com',
        ]);
        $this->userB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'email' => 'userB@test.com',
        ]);
        
        // Attach users to tenants with 'admin' role (which has tenant.view_reports in config)
        $this->userA->tenants()->attach($this->tenantA->id, ['role' => 'admin']);
        $this->userB->tenants()->attach($this->tenantB->id, ['role' => 'admin']);
        
        // Create tokens
        $this->tokenA = $this->userA->createToken('test-token')->plainTextToken;
        $this->tokenB = $this->userB->createToken('test-token')->plainTextToken;
    }

    /**
     * Test that export endpoint returns CSV with expected columns
     */
    public function test_export_endpoint_returns_csv_with_expected_columns(): void
    {
        $client = Client::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Test Client',
        ]);
        
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
            'client_id' => $client->id,
            'status' => 'active',
        ]);
        
        // Create contract for financial data
        $contract = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'code' => 'CT-001',
            'total_value' => 10000.00,
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        ContractBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contract->id,
            'total_amount' => 12000.00,
            'status' => 'active',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        ContractExpense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contract->id,
            'amount' => 11000.00,
            'status' => 'recorded',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        // Create tasks for health data
        Task::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'status' => 'done',
        ]);
        Task::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'status' => 'in_progress',
        ]);
        
        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->get('/api/v1/app/reports/projects/health/export');
        
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $contentDisposition = $response->headers->get('Content-Disposition');
        $this->assertNotNull($contentDisposition);
        $this->assertStringContainsString('attachment', $contentDisposition);
        $this->assertStringContainsString('.csv', $contentDisposition);
        
        // For streamed responses, use streamedContent() instead of getContent()
        $content = method_exists($response, 'streamedContent') ? $response->streamedContent() : $response->getContent();
        $this->assertIsString($content);
        $this->assertNotEmpty($content);
        
        // Check for BOM (UTF-8)
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        
        // Check headers
        $this->assertStringContainsString('ProjectCode', $content);
        $this->assertStringContainsString('ProjectName', $content);
        $this->assertStringContainsString('ClientName', $content);
        $this->assertStringContainsString('ProjectStatus', $content);
        $this->assertStringContainsString('ScheduleStatus', $content);
        $this->assertStringContainsString('CostStatus', $content);
        $this->assertStringContainsString('OverallStatus', $content);
        $this->assertStringContainsString('TasksCompletionRate', $content);
        $this->assertStringContainsString('BlockedTasksRatio', $content);
        $this->assertStringContainsString('OverdueTasks', $content);
        $this->assertStringContainsString('CostOverrunPercent', $content);
        
        // Check data row
        $this->assertStringContainsString('PRJ-001', $content);
        $this->assertStringContainsString('Test Project', $content);
        $this->assertStringContainsString('Test Client', $content);
        
        // Check that overall_status contains one of good, warning, critical
        // Remove BOM if present at the start
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = explode("\n", trim($content));
        
        // Find header line and get data lines after it
        $headerFound = false;
        $dataLines = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            if (!$headerFound && str_starts_with($line, 'ProjectCode')) {
                $headerFound = true;
                continue;
            }
            
            if ($headerFound) {
                $dataLines[] = $line;
            }
        }
        
        if (!empty($dataLines)) {
            $firstDataLine = $dataLines[0];
            $csvData = str_getcsv($firstDataLine);
            // OverallStatus is at index 6 (0-indexed: ProjectCode=0, ProjectName=1, ClientName=2, ProjectStatus=3, ScheduleStatus=4, CostStatus=5, OverallStatus=6)
            if (isset($csvData[6])) {
                $overallStatus = trim($csvData[6]);
                $this->assertContains($overallStatus, ['good', 'warning', 'critical'], 
                    "Overall status should be one of good, warning, critical, got: '{$overallStatus}'");
            }
        }
    }

    /**
     * Test that export endpoint is tenant isolated
     */
    public function test_export_endpoint_is_tenant_isolated(): void
    {
        $clientA = Client::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Client A',
        ]);
        
        $clientB = Client::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Client B',
        ]);
        
        // Create project in tenant A
        $projectA = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-A-001',
            'name' => 'Tenant A Project',
            'client_id' => $clientA->id,
        ]);
        
        // Create project in tenant B
        $projectB = Project::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'code' => 'PRJ-B-001',
            'name' => 'Tenant B Project',
            'client_id' => $clientB->id,
        ]);
        
        // User A calls endpoint
        Sanctum::actingAs($this->userA);
        $responseA = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->get('/api/v1/app/reports/projects/health/export');
        
        $responseA->assertStatus(200);
        $contentA = method_exists($responseA, 'streamedContent') ? $responseA->streamedContent() : $responseA->getContent();
        $this->assertIsString($contentA);
        $this->assertNotEmpty($contentA);
        
        // User A should only see tenant A project
        $this->assertStringContainsString('PRJ-A-001', $contentA);
        $this->assertStringNotContainsString('PRJ-B-001', $contentA);
        
        // User B calls endpoint
        Sanctum::actingAs($this->userB);
        $responseB = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenB}",
        ])->get('/api/v1/app/reports/projects/health/export');
        
        $responseB->assertStatus(200);
        $contentB = method_exists($responseB, 'streamedContent') ? $responseB->streamedContent() : $responseB->getContent();
        $this->assertIsString($contentB);
        $this->assertNotEmpty($contentB);
        
        // User B should only see tenant B project
        $this->assertStringContainsString('PRJ-B-001', $contentB);
        $this->assertStringNotContainsString('PRJ-A-001', $contentB);
    }

    /**
     * Test that export endpoint requires view_reports permission
     */
    public function test_export_endpoint_requires_view_reports_permission(): void
    {
        $userWithoutPermission = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'email' => 'noperm@test.com',
        ]);
        
        Sanctum::actingAs($userWithoutPermission);
        $token = $userWithoutPermission->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->get('/api/v1/app/reports/projects/health/export');
        
        $response->assertStatus(403);
    }

    /**
     * Test that export CSV has correct format with multiple projects
     */
    public function test_export_csv_has_correct_format_with_multiple_projects(): void
    {
        $client = Client::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Test Client',
        ]);
        
        // Project 1: active with tasks
        $project1 = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Project 1',
            'client_id' => $client->id,
            'status' => 'active',
        ]);
        
        Task::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project1->id,
            'status' => 'done',
        ]);
        
        // Project 2: planning without tasks
        $project2 = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-002',
            'name' => 'Project 2',
            'client_id' => $client->id,
            'status' => 'planning',
        ]);
        
        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->get('/api/v1/app/reports/projects/health/export');
        
        $response->assertStatus(200);
        // For streamed responses, use streamedContent() instead of getContent()
        $content = method_exists($response, 'streamedContent') ? $response->streamedContent() : $response->getContent();
        $this->assertIsString($content);
        $this->assertNotEmpty($content);
        
        // Check for BOM (UTF-8)
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        
        // Check headers
        $this->assertStringContainsString('ProjectCode', $content);
        $this->assertStringContainsString('ProjectName', $content);
        
        // Check both projects are in CSV
        $this->assertStringContainsString('PRJ-001', $content);
        $this->assertStringContainsString('PRJ-002', $content);
        $this->assertStringContainsString('Project 1', $content);
        $this->assertStringContainsString('Project 2', $content);
    }
}

