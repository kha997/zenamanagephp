<?php

namespace Tests\Feature\Policies;

use App\Models\File;
use App\Models\User;
use App\Models\Project;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $projectManager;
    protected User $member;
    protected User $otherTenantUser;
    protected Tenant $tenant;
    protected Tenant $otherTenant;
    protected File $file;
    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->otherTenant = Tenant::factory()->create();

        $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->admin->assignRole('admin');

        $this->projectManager = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->projectManager->assignRole('project_manager');

        $this->member = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->member->assignRole('member');

        $this->otherTenantUser = User::factory()->create(['tenant_id' => $this->otherTenant->id]);
        $this->otherTenantUser->assignRole('member');

        $this->project = Project::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->file = File::factory()->create([
            'user_id' => $this->member->id,
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'is_public' => false
        ]);
    }

    public function test_admin_can_view_any_files()
    {
        $this->assertTrue($this->admin->can('viewAny', File::class));
    }

    public function test_admin_can_view_any_file()
    {
        $this->assertTrue($this->admin->can('view', $this->file));
    }

    public function test_admin_can_view_other_tenant_files()
    {
        $otherTenantFile = File::factory()->create(['tenant_id' => $this->otherTenant->id]);
        $this->assertTrue($this->admin->can('view', $otherTenantFile));
    }

    public function test_project_manager_can_view_project_files()
    {
        $this->assertTrue($this->projectManager->can('view', $this->file));
    }

    public function test_member_can_view_own_files()
    {
        $this->assertTrue($this->member->can('view', $this->file));
    }

    public function test_member_can_view_public_files()
    {
        $publicFile = File::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_public' => true
        ]);
        $this->assertTrue($this->member->can('view', $publicFile));
    }

    public function test_user_cannot_view_other_tenant_files()
    {
        $otherTenantFile = File::factory()->create(['tenant_id' => $this->otherTenant->id]);
        $this->assertFalse($this->member->can('view', $otherTenantFile));
    }

    public function test_user_cannot_view_private_files_of_others()
    {
        $otherUserFile = File::factory()->create([
            'user_id' => $this->projectManager->id,
            'tenant_id' => $this->tenant->id,
            'is_public' => false
        ]);
        $this->assertFalse($this->member->can('view', $otherUserFile));
    }

    public function test_admin_can_create_files()
    {
        $this->assertTrue($this->admin->can('create', File::class));
    }

    public function test_project_manager_can_create_files()
    {
        $this->assertTrue($this->projectManager->can('create', File::class));
    }

    public function test_member_can_create_files()
    {
        $this->assertTrue($this->member->can('create', File::class));
    }

    public function test_admin_can_update_any_file()
    {
        $this->assertTrue($this->admin->can('update', $this->file));
    }

    public function test_project_manager_can_update_project_files()
    {
        $this->assertTrue($this->projectManager->can('update', $this->file));
    }

    public function test_member_can_update_own_files()
    {
        $this->assertTrue($this->member->can('update', $this->file));
    }

    public function test_member_cannot_update_others_files()
    {
        $otherUserFile = File::factory()->create([
            'user_id' => $this->projectManager->id,
            'tenant_id' => $this->tenant->id
        ]);
        $this->assertFalse($this->member->can('update', $otherUserFile));
    }

    public function test_admin_can_delete_any_file()
    {
        $this->assertTrue($this->admin->can('delete', $this->file));
    }

    public function test_project_manager_can_delete_project_files()
    {
        $this->assertTrue($this->projectManager->can('delete', $this->file));
    }

    public function test_member_can_delete_own_files()
    {
        $this->assertTrue($this->member->can('delete', $this->file));
    }

    public function test_member_cannot_delete_others_files()
    {
        $otherUserFile = File::factory()->create([
            'user_id' => $this->projectManager->id,
            'tenant_id' => $this->tenant->id
        ]);
        $this->assertFalse($this->member->can('delete', $otherUserFile));
    }

    public function test_only_admin_can_restore_files()
    {
        $this->assertTrue($this->admin->can('restore', $this->file));
        $this->assertFalse($this->projectManager->can('restore', $this->file));
        $this->assertFalse($this->member->can('restore', $this->file));
    }

    public function test_only_admin_can_force_delete_files()
    {
        $this->assertTrue($this->admin->can('forceDelete', $this->file));
        $this->assertFalse($this->projectManager->can('forceDelete', $this->file));
        $this->assertFalse($this->member->can('forceDelete', $this->file));
    }

    public function test_download_permission_matches_view_permission()
    {
        $this->assertTrue($this->admin->can('download', $this->file));
        $this->assertTrue($this->projectManager->can('download', $this->file));
        $this->assertTrue($this->member->can('download', $this->file));
        $this->assertFalse($this->otherTenantUser->can('download', $this->file));
    }

    public function test_upload_permission_matches_create_permission()
    {
        $this->assertTrue($this->admin->can('upload', File::class));
        $this->assertTrue($this->projectManager->can('upload', File::class));
        $this->assertTrue($this->member->can('upload', File::class));
    }
}
