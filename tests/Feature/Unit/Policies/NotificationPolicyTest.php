<?php

namespace Tests\Feature\Unit\Policies;

use App\Models\User;
use App\Models\Notification;
use App\Models\Tenant;
use App\Policies\NotificationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected $policy;
    protected $tenant;
    protected $user;
    protected $notification;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->policy = new NotificationPolicy();
        
        $this->tenant = Tenant::factory()->create([
            'slug' => 'test-tenant-' . uniqid(),
            'name' => 'Test Tenant'
        ]);
        
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'test@example-' . uniqid() . '.com'
        ]);
        
        $this->notification = Notification::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'title' => 'Test Notification'
        ]);
    }

    public function test_user_can_view_own_notification()
    {
        $this->assertTrue($this->policy->view($this->user, $this->notification));
    }

    public function test_user_cannot_view_notification_in_different_tenant()
    {
        $otherTenant = Tenant::factory()->create(['slug' => 'other-tenant-' . uniqid()]);
        $otherNotification = Notification::factory()->create([
            'tenant_id' => $otherTenant->id,
            'user_id' => $this->user->id
        ]);
        
        $this->assertFalse($this->policy->view($this->user, $otherNotification));
    }

    public function test_user_can_mark_own_notification_as_read()
    {
        $this->assertTrue($this->policy->markAsRead($this->user, $this->notification));
    }

    public function test_user_can_create_notification_with_proper_role()
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

        $this->assertTrue($this->policy->create($this->user));
    }

    public function test_user_can_send_notification_with_management_role()
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

        $this->assertTrue($this->policy->send($this->user));
    }
}