<?php

namespace Tests\Feature\Unit\Policies;

use App\Models\User;
use App\Models\ChangeRequest;
use App\Models\Tenant;
use App\Policies\ChangeRequestPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChangeRequestPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected $policy;
    protected $tenant;
    protected $user;
    protected $changeRequest;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->policy = new ChangeRequestPolicy();
        
        $this->tenant = Tenant::factory()->create([
            'slug' => 'test-tenant-' . uniqid(),
            'name' => 'Test Tenant'
        ]);
        
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'test@example-' . uniqid() . '.com'
        ]);
        
        $this->changeRequest = ChangeRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'title' => 'Test Change Request',
            'project_id' => \App\Models\Project::factory()->create(['tenant_id' => $this->tenant->id])->id
        ]);
    }

    public function test_user_can_view_change_request_with_proper_role()
    {
        $this->user->assignRole('pm');
        $this->assertTrue($this->policy->view($this->user, $this->changeRequest));
    }

    public function test_user_cannot_view_change_request_in_different_tenant()
    {
        $otherTenant = Tenant::factory()->create(['slug' => 'other-tenant-' . uniqid()]);
        $otherChangeRequest = ChangeRequest::factory()->create([
            'tenant_id' => $otherTenant->id,
            'created_by' => $this->user->id
        ]);
        
        $this->user->assignRole('pm');
        $this->assertFalse($this->policy->view($this->user, $otherChangeRequest));
    }

    public function test_user_can_create_change_request_with_proper_role()
    {
        $this->user->assignRole('pm');
        $this->assertTrue($this->policy->create($this->user));
    }

    public function test_user_can_update_own_change_request()
    {
        // Create role if it doesn't exist
        $role = \App\Models\Role::firstOrCreate(
            ['name' => 'project_manager'],
            [
                'scope' => 'project',
                'allow_override' => false,
                'description' => 'Project Manager - Project management',
            ]
        );
        
        // Manually insert role assignment
        \DB::table('user_roles')->insert([
            'user_id' => $this->user->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        $this->assertTrue($this->policy->update($this->user, $this->changeRequest));
    }

    public function test_user_can_approve_change_request_with_management_role()
    {
        // Create role if it doesn't exist
        $role = \App\Models\Role::firstOrCreate(
            ['name' => 'project_manager'],
            [
                'scope' => 'project',
                'allow_override' => false,
                'description' => 'Project Manager - Project management',
            ]
        );
        
        // Manually insert role assignment
        \DB::table('user_roles')->insert([
            'user_id' => $this->user->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        $this->assertTrue($this->policy->approve($this->user, $this->changeRequest));
    }

    public function test_user_can_comment_change_request_with_proper_role()
    {
        $this->user->assignRole('engineer');
        $this->assertTrue($this->policy->comment($this->user, $this->changeRequest));
    }
}