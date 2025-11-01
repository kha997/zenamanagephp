<?php

namespace Tests\Feature\Policies;

use Tests\TestCase;
use App\Models\User;
use App\Models\Document;
use App\Models\Component;
use App\Models\Project;
use App\Models\Task;
use App\Models\Rfi;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PolicyTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $admin;
    protected $pm;
    protected $designer;
    protected $engineer;
    protected $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test users with different roles
        $this->superAdmin = User::factory()->create(['role' => 'super_admin']);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->pm = User::factory()->create(['role' => 'pm']);
        $this->designer = User::factory()->create(['role' => 'designer']);
        $this->engineer = User::factory()->create(['role' => 'engineer']);
        $this->regularUser = User::factory()->create(['role' => 'user']);
    }

    /** @test */
    public function document_policy_allows_proper_access()
    {
        $document = Document::factory()->create(['tenant_id' => 1]);
        
        // Super admin can do everything
        $this->assertTrue($this->superAdmin->can('view', $document));
        $this->assertTrue($this->superAdmin->can('create', Document::class));
        $this->assertTrue($this->superAdmin->can('update', $document));
        $this->assertTrue($this->superAdmin->can('delete', $document));
        
        // Admin can do most things
        $this->assertTrue($this->admin->can('view', $document));
        $this->assertTrue($this->admin->can('create', Document::class));
        $this->assertTrue($this->admin->can('update', $document));
        $this->assertTrue($this->admin->can('delete', $document));
        
        // PM can view and create
        $this->assertTrue($this->pm->can('view', $document));
        $this->assertTrue($this->pm->can('create', Document::class));
        $this->assertTrue($this->pm->can('update', $document));
        $this->assertFalse($this->pm->can('delete', $document));
        
        // Designer can view and create
        $this->assertTrue($this->designer->can('view', $document));
        $this->assertTrue($this->designer->can('create', Document::class));
        $this->assertTrue($this->designer->can('update', $document));
        $this->assertFalse($this->designer->can('delete', $document));
        
        // Engineer can only view
        $this->assertTrue($this->engineer->can('view', $document));
        $this->assertFalse($this->engineer->can('create', Document::class));
        $this->assertFalse($this->engineer->can('update', $document));
        $this->assertFalse($this->engineer->can('delete', $document));
        
        // Regular user cannot access
        $this->assertFalse($this->regularUser->can('view', $document));
        $this->assertFalse($this->regularUser->can('create', Document::class));
        $this->assertFalse($this->regularUser->can('update', $document));
        $this->assertFalse($this->regularUser->can('delete', $document));
    }

    /** @test */
    public function component_policy_allows_proper_access()
    {
        $component = Component::factory()->create(['tenant_id' => 1]);
        
        // Super admin can do everything
        $this->assertTrue($this->superAdmin->can('view', $component));
        $this->assertTrue($this->superAdmin->can('create', Component::class));
        $this->assertTrue($this->superAdmin->can('update', $component));
        $this->assertTrue($this->superAdmin->can('delete', $component));
        
        // Admin can do most things
        $this->assertTrue($this->admin->can('view', $component));
        $this->assertTrue($this->admin->can('create', Component::class));
        $this->assertTrue($this->admin->can('update', $component));
        $this->assertTrue($this->admin->can('delete', $component));
        
        // PM can view and create
        $this->assertTrue($this->pm->can('view', $component));
        $this->assertTrue($this->pm->can('create', Component::class));
        $this->assertTrue($this->pm->can('update', $component));
        $this->assertFalse($this->pm->can('delete', $component));
        
        // Designer can view and create
        $this->assertTrue($this->designer->can('view', $component));
        $this->assertTrue($this->designer->can('create', Component::class));
        $this->assertTrue($this->designer->can('update', $component));
        $this->assertFalse($this->designer->can('delete', $component));
        
        // Engineer can only view
        $this->assertTrue($this->engineer->can('view', $component));
        $this->assertFalse($this->engineer->can('create', Component::class));
        $this->assertFalse($this->engineer->can('update', $component));
        $this->assertFalse($this->engineer->can('delete', $component));
    }

    /** @test */
    public function user_policy_allows_proper_access()
    {
        $targetUser = User::factory()->create();
        
        // Super admin can do everything
        $this->assertTrue($this->superAdmin->can('view', $targetUser));
        $this->assertTrue($this->superAdmin->can('create', User::class));
        $this->assertTrue($this->superAdmin->can('update', $targetUser));
        $this->assertTrue($this->superAdmin->can('delete', $targetUser));
        
        // Admin can do most things
        $this->assertTrue($this->admin->can('view', $targetUser));
        $this->assertTrue($this->admin->can('create', User::class));
        $this->assertTrue($this->admin->can('update', $targetUser));
        $this->assertTrue($this->admin->can('delete', $targetUser));
        
        // PM can view and create
        $this->assertTrue($this->pm->can('view', $targetUser));
        $this->assertTrue($this->pm->can('create', User::class));
        $this->assertTrue($this->pm->can('update', $targetUser));
        $this->assertFalse($this->pm->can('delete', $targetUser));
        
        // Users can view and update their own profile
        $this->assertTrue($this->regularUser->can('view', $this->regularUser));
        $this->assertTrue($this->regularUser->can('update', $this->regularUser));
        $this->assertFalse($this->regularUser->can('view', $targetUser));
        $this->assertFalse($this->regularUser->can('update', $targetUser));
        $this->assertFalse($this->regularUser->can('create', User::class));
        $this->assertFalse($this->regularUser->can('delete', $targetUser));
    }

    /** @test */
    public function tenant_isolation_prevents_cross_tenant_access()
    {
        $document1 = Document::factory()->create(['tenant_id' => 1]);
        $document2 = Document::factory()->create(['tenant_id' => 2]);
        
        $user1 = User::factory()->create(['tenant_id' => 1, 'role' => 'admin']);
        $user2 = User::factory()->create(['tenant_id' => 2, 'role' => 'admin']);
        
        // User 1 can access tenant 1 documents
        $this->assertTrue($user1->can('view', $document1));
        $this->assertTrue($user1->can('update', $document1));
        
        // User 1 cannot access tenant 2 documents
        $this->assertFalse($user1->can('view', $document2));
        $this->assertFalse($user1->can('update', $document2));
        
        // User 2 can access tenant 2 documents
        $this->assertTrue($user2->can('view', $document2));
        $this->assertTrue($user2->can('update', $document2));
        
        // User 2 cannot access tenant 1 documents
        $this->assertFalse($user2->can('view', $document1));
        $this->assertFalse($user2->can('update', $document1));
    }

    /** @test */
    public function rfi_policy_allows_proper_access()
    {
        $rfi = Rfi::factory()->create(['tenant_id' => 1, 'created_by' => $this->pm->id]);
        
        // Creator can update and delete
        $this->assertTrue($this->pm->can('update', $rfi));
        $this->assertTrue($this->pm->can('delete', $rfi));
        
        // Super admin can do everything
        $this->assertTrue($this->superAdmin->can('view', $rfi));
        $this->assertTrue($this->superAdmin->can('create', Rfi::class));
        $this->assertTrue($this->superAdmin->can('update', $rfi));
        $this->assertTrue($this->superAdmin->can('delete', $rfi));
        
        // Admin can do most things
        $this->assertTrue($this->admin->can('view', $rfi));
        $this->assertTrue($this->admin->can('create', Rfi::class));
        $this->assertTrue($this->admin->can('update', $rfi));
        $this->assertFalse($this->admin->can('delete', $rfi));
        
        // Designer can view and create
        $this->assertTrue($this->designer->can('view', $rfi));
        $this->assertTrue($this->designer->can('create', Rfi::class));
        $this->assertFalse($this->designer->can('update', $rfi));
        $this->assertFalse($this->designer->can('delete', $rfi));
    }
}
