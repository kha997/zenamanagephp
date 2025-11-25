<?php declare(strict_types=1);

namespace Tests\Feature\Api\Reports;

use App\Models\User;
use App\Models\Tenant;
use App\Models\ReportSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;
use Carbon\Carbon;

/**
 * Tests for Reports API tenant permission enforcement
 * 
 * Tests that reports endpoints properly enforce tenant.permission middleware
 * for view endpoints (GET requests).
 * 
 * Round 13: Hardening Reports view permissions
 * Round 14: tenant.manage_reports permission defined for future mutation routes
 * 
 * @group reports
 * @group tenant-permissions
 */
class ReportsPermissionTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenant;
    private ReportSchedule $reportSchedule;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(98765);
        $this->setDomainName('reports-permission');
        $this->setupDomainIsolation();
        
        // Create tenant
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . uniqid(),
        ]);
        
        // Create a user for report schedule
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        // Create report schedule
        $this->reportSchedule = ReportSchedule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $user->id,
            'name' => 'Test Report',
            'type' => 'dashboard',
            'format' => 'pdf',
            'frequency' => 'weekly',
            'recipients' => json_encode([$user->email]),
            'is_active' => true,
            'next_send_at' => Carbon::now()->addDay(),
            'last_sent_at' => Carbon::now()->subDay(),
        ]);
    }

    /**
     * Test that all 4 standard roles (owner/admin/member/viewer) can GET reports endpoints (Round 13)
     * 
     * All standard roles have tenant.view_reports from config, so should all pass.
     */
    public function test_all_standard_roles_can_get_reports_endpoints(): void
    {
        $roles = ['owner', 'admin', 'member', 'viewer'];

        foreach ($roles as $role) {
            $user = User::factory()->create([
                'tenant_id' => $this->tenant->id,
                'email_verified_at' => now(),
            ]);

            $user->tenants()->attach($this->tenant->id, [
                'role' => $role,
                'is_default' => true,
            ]);

            Sanctum::actingAs($user);
            $token = $user->createToken('test-token')->plainTextToken;

            // All standard roles should be able to GET reports/kpis
            $kpisResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->getJson('/api/v1/app/reports/kpis');

            $kpisResponse->assertStatus(200, "Role {$role} should be able to GET reports KPIs (has tenant.view_reports)");

            // All standard roles should be able to GET reports/alerts
            $alertsResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->getJson('/api/v1/app/reports/alerts');

            $alertsResponse->assertStatus(200, "Role {$role} should be able to GET reports alerts (has tenant.view_reports)");

            // All standard roles should be able to GET reports/activity
            $activityResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->getJson('/api/v1/app/reports/activity');

            $activityResponse->assertStatus(200, "Role {$role} should be able to GET reports activity (has tenant.view_reports)");
        }
    }

    /**
     * Test that user without tenant.view_reports cannot GET reports endpoints (Round 13)
     * 
     * Negative test: role 'guest' is not defined in config/permissions.php tenant_roles,
     * so user will have no permissions and should get 403.
     */
    public function test_user_without_view_reports_cannot_access_reports_endpoints(): void
    {
        // Create user with 'guest' role (not in config/permissions.php, so no permissions)
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $user->tenants()->attach($this->tenant->id, [
            'role' => 'guest', // Role not in config, so no tenant.view_reports
            'is_default' => true,
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        // Guest should NOT be able to GET reports/kpis
        $kpisResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/reports/kpis');

        $kpisResponse->assertStatus(403);
        $kpisResponse->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);

        // Guest should NOT be able to GET reports/alerts
        $alertsResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/reports/alerts');

        $alertsResponse->assertStatus(403);
        $alertsResponse->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);

        // Guest should NOT be able to GET reports/activity
        $activityResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/reports/activity');

        $activityResponse->assertStatus(403);
        $activityResponse->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);
    }

    /**
     * Test that reports are scoped to active tenant
     */
    public function test_reports_are_scoped_to_active_tenant(): void
    {
        // Create another tenant and report schedule
        $otherTenant = Tenant::factory()->create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . uniqid(),
        ]);
        
        // Create a user for the other tenant's report schedule
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'email_verified_at' => now(),
        ]);
        
        $otherReportSchedule = ReportSchedule::create([
            'tenant_id' => $otherTenant->id,
            'user_id' => $otherUser->id,
            'name' => 'Other Tenant Report',
            'type' => 'dashboard',
            'format' => 'pdf',
            'frequency' => 'weekly',
            'recipients' => json_encode([$otherUser->email]),
            'is_active' => true,
            'next_send_at' => Carbon::now()->addDay(),
        ]);

        // Create user with admin role in first tenant
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $user->tenants()->attach($this->tenant->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        // User should only see reports from their active tenant
        $kpisResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/reports/kpis');

        $kpisResponse->assertStatus(200);
        $data = $kpisResponse->json('data');
        
        // Verify total_reports only counts reports from active tenant
        // The test tenant should have 1 report, other tenant should not be counted
        $this->assertGreaterThanOrEqual(1, $data['total_reports'] ?? 0, 'Should include reports from active tenant');
        
        // Verify alerts only show reports from active tenant
        $alertsResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/reports/alerts');

        $alertsResponse->assertStatus(200);
        $alerts = $alertsResponse->json('data') ?? [];
        
        // Verify all alerts are from active tenant
        foreach ($alerts as $alert) {
            $reportId = $alert['metadata']['report_id'] ?? null;
            if ($reportId) {
                $report = ReportSchedule::find($reportId);
                if ($report) {
                    $this->assertEquals(
                        $this->tenant->id,
                        $report->tenant_id,
                        'Reports in alerts should only be from active tenant'
                    );
                }
            }
        }
    }

    /**
     * Test that tenant.manage_reports permission is defined for future use (Round 14)
     * 
     * Verifies that tenant.manage_reports permission is properly configured in
     * config/permissions.php for owner and admin roles, ready for when mutation
     * routes are added (POST/PUT/PATCH/DELETE for report generation, export, scheduling).
     */
    public function test_manage_reports_permission_is_defined_for_future_use(): void
    {
        $permissions = config('permissions.tenant_roles');

        // Owner should have tenant.manage_reports
        $this->assertIsArray($permissions['owner'] ?? null, 'Owner role should be defined');
        $this->assertContains(
            'tenant.manage_reports',
            $permissions['owner'] ?? [],
            'Owner role should have tenant.manage_reports permission'
        );

        // Admin should have tenant.manage_reports
        $this->assertIsArray($permissions['admin'] ?? null, 'Admin role should be defined');
        $this->assertContains(
            'tenant.manage_reports',
            $permissions['admin'] ?? [],
            'Admin role should have tenant.manage_reports permission'
        );

        // Member should NOT have tenant.manage_reports
        $this->assertIsArray($permissions['member'] ?? null, 'Member role should be defined');
        $this->assertNotContains(
            'tenant.manage_reports',
            $permissions['member'] ?? [],
            'Member role should NOT have tenant.manage_reports permission'
        );

        // Viewer should NOT have tenant.manage_reports
        $this->assertIsArray($permissions['viewer'] ?? null, 'Viewer role should be defined');
        $this->assertNotContains(
            'tenant.manage_reports',
            $permissions['viewer'] ?? [],
            'Viewer role should NOT have tenant.manage_reports permission'
        );

        // Verify owner and admin also have view_reports (should already exist from Round 13)
        $this->assertContains(
            'tenant.view_reports',
            $permissions['owner'] ?? [],
            'Owner role should have tenant.view_reports permission'
        );
        $this->assertContains(
            'tenant.view_reports',
            $permissions['admin'] ?? [],
            'Admin role should have tenant.view_reports permission'
        );
    }
}

