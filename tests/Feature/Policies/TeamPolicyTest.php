<?php

namespace Tests\Feature\Policies;

use Tests\TestCase;
use App\Models\User;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TeamPolicyTest extends TestCase
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
        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'tenant_id' => 1]);
        $this->admin = User::factory()->create(['role' => 'admin', 'tenant_id' => 1]);
        $this->pm = User::factory()->create(['role' => 'pm', 'tenant_id' => 1]);
        $this->designer = User::factory()->create(['role' => 'designer', 'tenant_id' => 1]);
        $this->engineer = User::factory()->create(['role' => 'engineer', 'tenant_id' => 1]);
        $this->regularUser = User::factory()->create(['role' => 'user', 'tenant_id' => 1]);
    }

    /** @test */
    public function team_policy_allows_proper_access()
    {
        $team = Team::factory()->create(['tenant_id' => 1]);
        
        // Super admin can do everything
        $this->assertTrue($this->superAdmin->can('view', $team));
        $this->assertTrue($this->superAdmin->can('create', Team::class));
        $this->assertTrue($this->superAdmin->can('update', $team));
        $this->assertTrue($this->superAdmin->can('delete', $team));
        
        // Admin can do most things
        $this->assertTrue($this->admin->can('view', $team));
        $this->assertTrue($this->admin->can('create', Team::class));
        $this->assertTrue($this->admin->can('update', $team));
        $this->assertTrue($this->admin->can('delete', $team));
        
        // PM can view and create
        $this->assertTrue($this->pm->can('view', $team));
        $this->assertTrue($this->pm->can('create', Team::class));
        $this->assertTrue($this->pm->can('update', $team));
        $this->assertFalse($this->pm->can('delete', $team));
        
        // Designer cannot access teams
        $this->assertFalse($this->designer->can('view', $team));
        $this->assertFalse($this->designer->can('create', Team::class));
        $this->assertFalse($this->designer->can('update', $team));
        $this->assertFalse($this->designer->can('delete', $team));
        
        // Engineer cannot access teams
        $this->assertFalse($this->engineer->can('view', $team));
        $this->assertFalse($this->engineer->can('create', Team::class));
        $this->assertFalse($this->engineer->can('update', $team));
        $this->assertFalse($this->engineer->can('delete', $team));
        
        // Regular user cannot access
        $this->assertFalse($this->regularUser->can('view', $team));
        $this->assertFalse($this->regularUser->can('create', Team::class));
        $this->assertFalse($this->regularUser->can('update', $team));
        $this->assertFalse($this->regularUser->can('delete', $team));
    }

    /** @test */
    public function team_member_can_view_their_team()
    {
        $team = Team::factory()->create(['tenant_id' => 1]);
        
        // Add user as team member
        TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $this->designer->id,
            'role' => 'member'
        ]);
        
        // Team member can view their team
        $this->assertTrue($this->designer->can('view', $team));
        
        // But cannot update or delete
        $this->assertFalse($this->designer->can('update', $team));
        $this->assertFalse($this->designer->can('delete', $team));
    }

    /** @test */
    public function team_leader_can_manage_their_team()
    {
        $team = Team::factory()->create(['tenant_id' => 1]);
        
        // Add user as team leader
        TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $this->designer->id,
            'role' => 'leader'
        ]);
        
        // Team leader can view and update their team
        $this->assertTrue($this->designer->can('view', $team));
        $this->assertTrue($this->designer->can('update', $team));
        $this->assertTrue($this->designer->can('invite', $team));
        $this->assertTrue($this->designer->can('removeMember', $team));
        $this->assertTrue($this->designer->can('assignRole', $team));
        
        // But cannot delete
        $this->assertFalse($this->designer->can('delete', $team));
    }

    /** @test */
    public function tenant_isolation_prevents_cross_tenant_team_access()
    {
        $team1 = Team::factory()->create(['tenant_id' => 1]);
        $team2 = Team::factory()->create(['tenant_id' => 2]);
        
        $user1 = User::factory()->create(['tenant_id' => 1, 'role' => 'admin']);
        $user2 = User::factory()->create(['tenant_id' => 2, 'role' => 'admin']);
        
        // User 1 can access tenant 1 teams
        $this->assertTrue($user1->can('view', $team1));
        $this->assertTrue($user1->can('update', $team1));
        
        // User 1 cannot access tenant 2 teams
        $this->assertFalse($user1->can('view', $team2));
        $this->assertFalse($user1->can('update', $team2));
        
        // User 2 can access tenant 2 teams
        $this->assertTrue($user2->can('view', $team2));
        $this->assertTrue($user2->can('update', $team2));
        
        // User 2 cannot access tenant 1 teams
        $this->assertFalse($user2->can('view', $team1));
        $this->assertFalse($user2->can('update', $team1));
    }

    /** @test */
    public function team_member_can_leave_their_team()
    {
        $team = Team::factory()->create(['tenant_id' => 1]);
        
        // Add user as team member
        TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $this->designer->id,
            'role' => 'member'
        ]);
        
        // Team member can leave their team
        $this->assertTrue($this->designer->can('leave', $team));
    }
}
