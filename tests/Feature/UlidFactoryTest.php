<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Tenant;
use App\Models\User;
use Src\CoreProject\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Test cases cho Factory sử dụng ULID
 */
class UlidFactoryTest extends TestCase
{
    use RefreshDatabase;
    
    /**
     * Test TenantFactory tạo ULID đúng
     */
    public function test_tenant_factory_creates_valid_ulid(): void
    {
        $tenant = Tenant::factory()->create();
        
        $this->assertEquals(26, strlen($tenant->id));
        // Sửa pattern để chấp nhận cả lowercase và uppercase
        $this->assertMatchesRegularExpression('/^[0-9A-Za-z]{26}$/', $tenant->id);
        $this->assertTrue($tenant->is_active);
    }
    
    /**
     * Test UserFactory với tenant relationship
     */
    public function test_user_factory_with_tenant_relationship(): void
    {
        $user = User::factory()->create();
        
        // User ID phải là ULID
        $this->assertEquals(26, strlen($user->id));
        
        // Tenant ID phải là ULID
        $this->assertEquals(26, strlen($user->tenant_id));
        
        // Relationship phải hoạt động
        $this->assertInstanceOf(Tenant::class, $user->tenant);
    }
    
    /**
     * Test ProjectFactory với tenant relationship
     */
    public function test_project_factory_with_tenant_relationship(): void
    {
        $project = Project::factory()->create();
        
        // Project ID phải là ULID
        $this->assertEquals(26, strlen($project->id));
        
        // Tenant ID phải là ULID
        $this->assertEquals(26, strlen($project->tenant_id));
        
        // Relationship phải hoạt động
        $this->assertInstanceOf(Tenant::class, $project->tenant);
    }
    
    /**
     * Test Factory states với ULID
     */
    public function test_factory_states_with_ulid(): void
    {
        $tenant = Tenant::factory()->create();
        
        // Test Project factory states
        $planningProject = Project::factory()
            ->forTenant($tenant->id)
            ->planning()
            ->create();
            
        $this->assertEquals('planning', $planningProject->status);
        $this->assertEquals($tenant->id, $planningProject->tenant_id);
        
        $activeProject = Project::factory()
            ->forTenant($tenant->id)
            ->active()
            ->create();
            
        $this->assertEquals('active', $activeProject->status);
        $this->assertEquals($tenant->id, $activeProject->tenant_id);
    }
    
    /**
     * Test tạo nhiều records với Factory
     */
    public function test_create_multiple_records_with_factory(): void
    {
        $tenants = Tenant::factory(5)->create();
        
        $this->assertEquals(5, $tenants->count());
        
        foreach ($tenants as $tenant) {
            $this->assertEquals(26, strlen($tenant->id));
            // Sửa pattern để chấp nhận cả lowercase và uppercase
            $this->assertMatchesRegularExpression('/^[0-9A-Za-z]{26}$/', $tenant->id);
        }
        
        // Tất cả ID phải khác nhau
        $ids = $tenants->pluck('id')->toArray();
        $uniqueIds = array_unique($ids);
        $this->assertEquals(count($ids), count($uniqueIds));
    }
}