<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Tests\Helpers\TestDataSeeder;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;

/**
 * Integration tests for Project Assignments API Controller (V1)
 * 
 * Tests the new Api/V1/App/ProjectAssignmentsController that replaced Unified/ProjectAssignmentController
 * 
 * @group project-assignments
 */
class ProjectAssignmentsControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker, DomainTestIsolation;

    protected User $user;
    protected Tenant $tenant;
    protected Project $project;
    protected string $authToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setDomainSeed(23456);
        $this->setDomainName('project-assignments');
        $this->setupDomainIsolation();

        $this->tenant = TestDataSeeder::createTenant();
        $this->storeTestData('tenant', $this->tenant);

        $this->user = TestDataSeeder::createUser($this->tenant, [
            'name' => 'Project Manager',
            'email' => 'pm@assignments-test.test',
            'role' => 'pm',
            'password' => Hash::make('password123'),
        ]);

        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        Sanctum::actingAs($this->user);
        $this->authToken = $this->user->createToken('test-token')->plainTextToken;

        Cache::flush();
    }

    /**
     * Test assign users to project
     */
    public function test_assign_users_to_project(): void
    {
        $users = User::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'member',
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/v1/app/projects/{$this->project->id}/assignments/users", [
            'users' => $users->pluck('id')->toArray(),
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'success' => [],
                'failed' => [],
                'skipped' => [],
            ],
        ]);
    }

    /**
     * Test assign users respects tenant isolation
     */
    public function test_assign_users_respects_tenant_isolation(): void
    {
        $otherTenant = TestDataSeeder::createTenant();
        $otherUser = TestDataSeeder::createUser($otherTenant, ['role' => 'member']);

        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/v1/app/projects/{$this->project->id}/assignments/users", [
            'users' => [$otherUser->id],
        ]);

        // Should fail because user is from different tenant
        $response->assertStatus(422);
    }

    /**
     * Test sync users to project
     */
    public function test_sync_users_to_project(): void
    {
        $users = User::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'member',
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/v1/app/projects/{$this->project->id}/assignments/users/sync", [
            'users' => $users->pluck('id')->toArray(),
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data',
        ]);
    }

    /**
     * Test get users assigned to project
     */
    public function test_get_users_assigned_to_project(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/v1/app/projects/{$this->project->id}/assignments/users");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [],
        ]);
    }

    /**
     * Test remove user from project
     */
    public function test_remove_user_from_project(): void
    {
        $userToRemove = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'member',
        ]);

        // First assign the user
        $this->project->users()->attach($userToRemove->id);

        Sanctum::actingAs($this->user);

        $response = $this->deleteJson("/api/v1/app/projects/{$this->project->id}/assignments/users/{$userToRemove->id}");

        $response->assertStatus(200);
        $this->assertFalse($this->project->users()->where('users.id', $userToRemove->id)->exists());
    }
}

