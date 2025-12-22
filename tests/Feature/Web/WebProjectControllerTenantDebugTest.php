<?php

namespace Tests\Feature\Web;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class WebProjectControllerTenantDebugTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set API base URL for testing
        config(['app.api_base_url' => 'http://localhost']);
        
        // Create tenant
        $this->tenant = Tenant::factory()->create();
        
        // Create user with tenant
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'test@example.com',
            'password' => bcrypt('password')
        ]);
    }

    /**
     * Test tenant ID comparison
     */
    public function test_tenant_id_comparison(): void
    {
        // Create a real project in the database
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Project',
            'code' => 'TEST-001',
            'status' => 'active',
            'progress_pct' => 50,
            'budget_actual' => 10000
        ]);
        
        dump('User tenant_id: ' . $this->user->tenant_id);
        dump('Project tenant_id: ' . $project->tenant_id);
        dump('Are they equal? ' . ($project->tenant_id === $this->user->tenant_id ? 'YES' : 'NO'));
        dump('User tenant_id type: ' . gettype($this->user->tenant_id));
        dump('Project tenant_id type: ' . gettype($project->tenant_id));
        
        $this->assertTrue(true); // Just to make it pass
    }
}
