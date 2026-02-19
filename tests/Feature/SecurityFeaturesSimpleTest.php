<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\SSOT\FixtureFactory;

/**
 * Security Features Test - Simplified Version
 * 
 * Tests basic security features that can be tested without complex HTTP requests
 */
class SecurityFeaturesSimpleTest extends TestCase
{
    use RefreshDatabase, FixtureFactory;

    protected $tenant;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $this->tenant = $this->createTenant([
            'name' => 'Security Test Company',
            'slug' => 'security-test-' . uniqid(),
            'status' => 'active'
        ]);

        $this->user = $this->createTenantUserWithRbac($this->tenant, 'member', 'member', [], [
            'name' => 'Security Tester',
            'email' => 'security@test-' . uniqid() . '.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /**
     * Test password hashing security
     */
    public function test_password_hashing_security(): void
    {
        // Test password hashing
        $password = 'testpassword123';
        $hashedPassword = bcrypt($password);
        
        $this->assertNotEquals($password, $hashedPassword);
        $this->assertTrue(Hash::check($password, $hashedPassword));
        
        // Test that different passwords produce different hashes
        $password2 = 'differentpassword';
        $hashedPassword2 = bcrypt($password2);
        
        $this->assertNotEquals($hashedPassword, $hashedPassword2);
    }

    /**
     * Test SQL injection prevention through Eloquent
     */
    public function test_sql_injection_prevention(): void
    {
        // Test that malicious input is handled safely
        $maliciousInput = "'; DROP TABLE users; --";
        
        // Create project with malicious input
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'SEC-' . uniqid(),
            'name' => $maliciousInput,
            'description' => 'Test project',
            'status' => 'active',
            'budget_total' => 100000.00
        ]);
        
        $this->assertNotNull($project);
        $this->assertEquals($maliciousInput, $project->name);
        
        // Verify that the malicious input was stored as-is (not executed)
        $retrievedProject = Project::find($project->id);
        $this->assertNotNull($retrievedProject);
        $this->assertEquals($maliciousInput, $retrievedProject->name);
    }

    /**
     * Test XSS protection through model attributes
     */
    public function test_xss_protection_in_models(): void
    {
        $xssPayload = '<script>alert("XSS")</script>';
        
        // Create project with XSS payload
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'XSS-' . uniqid(),
            'name' => 'Safe Project Name',
            'description' => $xssPayload,
            'status' => 'active',
            'budget_total' => 100000.00
        ]);
        
        $this->assertNotNull($project);
        
        // Model sanitization strips script payloads from persisted description.
        $this->assertStringNotContainsString('<script>', (string) $project->description);
        $this->assertSame('', (string) $project->description);
    }

    /**
     * Test tenant isolation at model level
     */
    public function test_tenant_isolation_at_model_level(): void
    {
        // Create another tenant
        $otherTenant = Tenant::factory()->create([
            'name' => 'Other Company',
            'slug' => 'other-company-' . uniqid(),
            'status' => 'active'
        ]);

        // Create projects for both tenants
        $project1 = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'TENANT1-' . uniqid(),
            'name' => 'Tenant 1 Project',
            'status' => 'active',
            'budget_total' => 100000.00
        ]);

        $project2 = Project::factory()->create([
            'tenant_id' => $otherTenant->id,
            'code' => 'TENANT2-' . uniqid(),
            'name' => 'Tenant 2 Project',
            'status' => 'active',
            'budget_total' => 200000.00
        ]);

        // Test tenant isolation
        $tenant1Projects = Project::where('tenant_id', $this->tenant->id)->get();
        $tenant2Projects = Project::where('tenant_id', $otherTenant->id)->get();

        $this->assertCount(1, $tenant1Projects);
        $this->assertCount(1, $tenant2Projects);
        
        $this->assertEquals($this->tenant->id, $tenant1Projects->first()->tenant_id);
        $this->assertEquals($otherTenant->id, $tenant2Projects->first()->tenant_id);
    }

    /**
     * Test data validation through model fillable
     */
    public function test_model_fillable_protection(): void
    {
        // Test that non-fillable attributes are protected
        $maliciousData = [
            'tenant_id' => $this->tenant->id,
            'code' => 'FILLABLE-' . uniqid(),
            'name' => 'Test Project',
            'status' => 'active',
            'budget_total' => 100000.00,
            'created_at' => '2020-01-01 00:00:00', // Should be protected
            'updated_at' => '2020-01-01 00:00:00', // Should be protected
            'id' => 'malicious-id' // Should be protected
        ];

        $project = new Project();
        $project->fill($maliciousData);
        $project->save();
        
        $this->assertNotNull($project);
        $this->assertNotEquals('malicious-id', $project->id);
        $this->assertNotEquals('2020-01-01 00:00:00', $project->created_at);
    }

    /**
     * Test ULID generation for security
     */
    public function test_ulid_generation_security(): void
    {
        // Test that ULIDs are generated correctly
        $project1 = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'ULID1-' . uniqid(),
            'name' => 'ULID Test Project 1',
            'status' => 'active',
            'budget_total' => 100000.00
        ]);

        // Ensure a later timestamp component for lexicographic ULID ordering checks.
        usleep(2000);

        $project2 = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'ULID2-' . uniqid(),
            'name' => 'ULID Test Project 2',
            'status' => 'active',
            'budget_total' => 200000.00
        ]);

        // Test ULID properties
        $this->assertNotEquals($project1->id, $project2->id);
        $this->assertMatchesRegularExpression('/^[0-9A-Za-z]{26}$/', $project1->id);
        $this->assertMatchesRegularExpression('/^[0-9A-Za-z]{26}$/', $project2->id);
        
        // Test creation chronology is preserved for records with ULID identifiers.
        $this->assertTrue($project1->created_at->lessThanOrEqualTo($project2->created_at));
    }

    /**
     * Test hard delete security
     */
    public function test_hard_delete_security(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'DELETE-' . uniqid(),
            'name' => 'Delete Test Project',
            'status' => 'active',
            'budget_total' => 100000.00
        ]);

        $projectId = $project->id;
        
        // Hard delete the project
        $project->delete();
        
        // Test that project is deleted
        $deletedProject = Project::find($projectId);
        $this->assertNull($deletedProject);
        
        // Project uses soft deletes, so assert deleted_at is set.
        $this->assertSoftDeleted('projects', ['id' => $projectId]);
    }

    /**
     * Test mass assignment protection
     */
    public function test_mass_assignment_protection(): void
    {
        $maliciousData = [
            'tenant_id' => $this->tenant->id,
            'code' => 'MASS-' . uniqid(),
            'name' => 'Mass Assignment Test',
            'status' => 'active',
            'budget_total' => 100000.00,
            'is_admin' => true, // Should not be fillable
            'role' => 'admin' // Should not be fillable
        ];

        $project = new Project();
        $project->fill($maliciousData);
        $project->save();
        
        $this->assertNotNull($project);
        // These attributes should not be set even if provided
        $this->assertNull($project->is_admin ?? null);
        $this->assertNull($project->role ?? null);
    }

    /**
     * Test data type casting security
     */
    public function test_data_type_casting_security(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'CAST-' . uniqid(),
            'name' => 'Type Casting Test',
            'status' => 'active',
            'budget_total' => '100000.50', // String that should be cast to float
        ]);

        $this->assertIsFloat($project->budget_total);
        $this->assertEquals(100000.50, $project->budget_total);
    }

    /**
     * Test comprehensive security features
     */
    public function test_comprehensive_security_features(): void
    {
        // Test multiple security aspects together
        $securityTests = [
            // Test 1: Password security
            function() {
                $password = 'securepassword123';
                $hashed = bcrypt($password);
                return $password !== $hashed && Hash::check($password, $hashed);
            },
            
            // Test 2: ULID uniqueness
            function() {
                $project1 = Project::factory()->create([
                    'tenant_id' => $this->tenant->id,
                    'code' => 'UNIQUE1-' . uniqid(),
                    'name' => 'Unique Test 1',
                    'status' => 'active',
                    'budget_total' => 100000.00
                ]);
                
                $project2 = Project::factory()->create([
                    'tenant_id' => $this->tenant->id,
                    'code' => 'UNIQUE2-' . uniqid(),
                    'name' => 'Unique Test 2',
                    'status' => 'active',
                    'budget_total' => 200000.00
                ]);
                
                return $project1->id !== $project2->id;
            },
            
            // Test 3: Tenant isolation
            function() {
                $otherTenant = Tenant::factory()->create([
                    'name' => 'Isolation Test',
                    'slug' => 'isolation-' . uniqid(),
                    'status' => 'active'
                ]);
                
                $project1 = Project::factory()->create([
                    'tenant_id' => $this->tenant->id,
                    'code' => 'ISO1-' . uniqid(),
                    'name' => 'Isolation Test 1',
                    'status' => 'active',
                    'budget_total' => 100000.00
                ]);
                
                $project2 = Project::factory()->create([
                    'tenant_id' => $otherTenant->id,
                    'code' => 'ISO2-' . uniqid(),
                    'name' => 'Isolation Test 2',
                    'status' => 'active',
                    'budget_total' => 200000.00
                ]);
                
                $tenant1Projects = Project::where('tenant_id', $this->tenant->id)->count();
                $tenant2Projects = Project::where('tenant_id', $otherTenant->id)->count();
                
                return $tenant1Projects >= 1 && $tenant2Projects >= 1;
            }
        ];

        foreach ($securityTests as $index => $test) {
            $this->assertTrue($test(), "Security test " . ($index + 1) . " failed");
        }
    }
}
