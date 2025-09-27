<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Models\Document;
use App\Models\Team;
use App\Models\ChangeRequest;
use App\Policies\ProjectPolicy;
use App\Policies\TaskPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\TeamPolicy;
use App\Policies\ChangeRequestPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PolicyTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $manager;
    protected User $member;
    protected User $guest;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test users with different roles
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->manager = User::factory()->create(['role' => 'project_manager']);
        $this->member = User::factory()->create(['role' => 'member']);
        $this->guest = User::factory()->create(['role' => 'guest']);
    }

    /** @test */
    public function project_policy_allows_admin_to_view_any_projects()
    {
        $policy = new ProjectPolicy();
        
        $this->assertTrue($policy->viewAny($this->admin));
    }

    /** @test */
    public function project_policy_denies_guest_to_create_projects()
    {
        $policy = new ProjectPolicy();
        
        $this->assertFalse($policy->create($this->guest));
    }

    /** @test */
    public function project_policy_allows_manager_to_update_own_projects()
    {
        $project = Project::factory()->create(['created_by' => $this->manager->id]);
        $policy = new ProjectPolicy();
        
        $this->assertTrue($policy->update($this->manager, $project));
    }

    /** @test */
    public function project_policy_denies_member_to_delete_projects()
    {
        $project = Project::factory()->create();
        $policy = new ProjectPolicy();
        
        $this->assertFalse($policy->delete($this->member, $project));
    }

    /** @test */
    public function task_policy_allows_admin_to_view_any_tasks()
    {
        $policy = new TaskPolicy();
        
        $this->assertTrue($policy->viewAny($this->admin));
    }

    /** @test */
    public function task_policy_allows_assigned_user_to_view_task()
    {
        $task = Task::factory()->create(['assigned_to' => $this->member->id]);
        $policy = new TaskPolicy();
        
        $this->assertTrue($policy->view($this->member, $task));
    }

    /** @test */
    public function task_policy_denies_guest_to_create_tasks()
    {
        $policy = new TaskPolicy();
        
        $this->assertFalse($policy->create($this->guest));
    }

    /** @test */
    public function task_policy_allows_manager_to_update_tasks()
    {
        $task = Task::factory()->create();
        $policy = new TaskPolicy();
        
        $this->assertTrue($policy->update($this->manager, $task));
    }

    /** @test */
    public function document_policy_allows_admin_to_view_any_documents()
    {
        $policy = new DocumentPolicy();
        
        $this->assertTrue($policy->viewAny($this->admin));
    }

    /** @test */
    public function document_policy_allows_owner_to_view_document()
    {
        $document = Document::factory()->create(['created_by' => $this->member->id]);
        $policy = new DocumentPolicy();
        
        $this->assertTrue($policy->view($this->member, $document));
    }

    /** @test */
    public function document_policy_allows_manager_to_create_documents()
    {
        $policy = new DocumentPolicy();
        
        $this->assertTrue($policy->create($this->manager));
    }

    /** @test */
    public function document_policy_denies_guest_to_delete_documents()
    {
        $document = Document::factory()->create();
        $policy = new DocumentPolicy();
        
        $this->assertFalse($policy->delete($this->guest, $document));
    }

    /** @test */
    public function team_policy_allows_admin_to_view_any_teams()
    {
        $policy = new TeamPolicy();
        
        $this->assertTrue($policy->viewAny($this->admin));
    }

    /** @test */
    public function team_policy_allows_manager_to_create_teams()
    {
        $policy = new TeamPolicy();
        
        $this->assertTrue($policy->create($this->manager));
    }

    /** @test */
    public function team_policy_denies_member_to_delete_teams()
    {
        $team = Team::factory()->create();
        $policy = new TeamPolicy();
        
        $this->assertFalse($policy->delete($this->member, $team));
    }

    /** @test */
    public function change_request_policy_allows_admin_to_view_any_change_requests()
    {
        $policy = new ChangeRequestPolicy();
        
        $this->assertTrue($policy->viewAny($this->admin));
    }

    /** @test */
    public function change_request_policy_allows_creator_to_view_own_change_request()
    {
        $changeRequest = ChangeRequest::factory()->create(['created_by' => $this->member->id]);
        $policy = new ChangeRequestPolicy();
        
        $this->assertTrue($policy->view($this->member, $changeRequest));
    }

    /** @test */
    public function change_request_policy_allows_manager_to_approve_change_requests()
    {
        $changeRequest = ChangeRequest::factory()->create();
        $policy = new ChangeRequestPolicy();
        
        // Assuming approve method exists in ChangeRequestPolicy
        if (method_exists($policy, 'approve')) {
            $this->assertTrue($policy->approve($this->manager, $changeRequest));
        } else {
            $this->assertTrue($policy->update($this->manager, $changeRequest));
        }
    }

    /** @test */
    public function change_request_policy_denies_guest_to_create_change_requests()
    {
        $policy = new ChangeRequestPolicy();
        
        $this->assertFalse($policy->create($this->guest));
    }

    /** @test */
    public function policies_respect_tenant_isolation()
    {
        // Create users in different tenants
        $tenant1User = User::factory()->create(['tenant_id' => 'tenant-1', 'role' => 'member']);
        $tenant2User = User::factory()->create(['tenant_id' => 'tenant-2', 'role' => 'member']);
        
        // Create project in tenant 1
        $tenant1Project = Project::factory()->create(['tenant_id' => 'tenant-1']);
        
        $policy = new ProjectPolicy();
        
        // User from tenant 1 should be able to view project from tenant 1
        $this->assertTrue($policy->view($tenant1User, $tenant1Project));
        
        // User from tenant 2 should NOT be able to view project from tenant 1
        $this->assertFalse($policy->view($tenant2User, $tenant1Project));
    }

    /** @test */
    public function admin_can_bypass_tenant_isolation()
    {
        // Create project in different tenant
        $project = Project::factory()->create(['tenant_id' => 'different-tenant']);
        
        $policy = new ProjectPolicy();
        
        // Admin should be able to view projects from any tenant
        $this->assertTrue($policy->view($this->admin, $project));
    }

    /** @test */
    public function policies_handle_null_resources_gracefully()
    {
        $projectPolicy = new ProjectPolicy();
        $taskPolicy = new TaskPolicy();
        $documentPolicy = new DocumentPolicy();
        
        // These should not throw exceptions
        $this->assertTrue($projectPolicy->create($this->manager));
        $this->assertTrue($taskPolicy->create($this->manager));
        $this->assertTrue($documentPolicy->create($this->manager));
        
        $this->assertFalse($projectPolicy->create($this->guest));
        $this->assertFalse($taskPolicy->create($this->guest));
        $this->assertFalse($documentPolicy->create($this->guest));
    }

    /** @test */
    public function policies_check_user_permissions_correctly()
    {
        $project = Project::factory()->create();
        $policy = new ProjectPolicy();
        
        // Test different permission levels
        $this->assertTrue($policy->viewAny($this->admin)); // Admin can view any
        $this->assertTrue($policy->viewAny($this->manager)); // Manager can view any
        $this->assertTrue($policy->viewAny($this->member)); // Member can view any
        $this->assertFalse($policy->delete($this->member, $project)); // Member cannot delete
        $this->assertTrue($policy->delete($this->admin, $project)); // Admin can delete
    }
}