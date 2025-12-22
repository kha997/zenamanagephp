<?php declare(strict_types=1);

namespace Tests\Unit\Policies;

use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Tests\Helpers\TestDataSeeder;
use Tests\Helpers\PolicyTestHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Template;
use App\Policies\TemplatePolicy;

/**
 * Unit tests for TemplatePolicy
 * 
 * Tests tenant isolation, role-based access, and creator permissions
 * 
 * @group templates
 * @group policies
 */
class TemplatePolicyTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    private Tenant $tenant1;
    private Tenant $tenant2;
    private User $user1; // Creator
    private User $user2; // Other user
    private User $user3; // Different tenant
    private Template $template1; // Private
    private Template $template2; // Public
    private Template $template3; // Different tenant
    private TemplatePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(101010);
        $this->setDomainName('templates');
        $this->setupDomainIsolation();
        
        // Create tenants
        $this->tenant1 = TestDataSeeder::createTenant(['name' => 'Tenant 1']);
        $this->tenant2 = TestDataSeeder::createTenant(['name' => 'Tenant 2']);
        
        // Create users
        $this->user1 = PolicyTestHelper::createUserWithRole($this->tenant1, 'member', [
            'name' => 'User 1',
            'email' => 'user1@test.com',
        ]);
        
        $this->user2 = PolicyTestHelper::createUserWithRole($this->tenant1, 'member', [
            'name' => 'User 2',
            'email' => 'user2@test.com',
        ]);
        
        $this->user3 = PolicyTestHelper::createUserWithRole($this->tenant2, 'member', [
            'name' => 'User 3',
            'email' => 'user3@test.com',
        ]);
        
        // Create templates
        $this->template1 = Template::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'created_by' => $this->user1->id,
            'is_public' => false,
            'name' => 'Template 1',
        ]);
        
        $this->template2 = Template::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'created_by' => $this->user1->id,
            'is_public' => true,
            'name' => 'Template 2',
        ]);
        
        $this->template3 = Template::factory()->create([
            'tenant_id' => $this->tenant2->id,
            'created_by' => $this->user3->id,
            'is_public' => false,
            'name' => 'Template 3',
        ]);
        
        // Refresh to ensure all relationships are loaded
        $this->template1->refresh();
        $this->template2->refresh();
        $this->template3->refresh();
        $this->user1->refresh();
        $this->user2->refresh();
        
        $this->policy = new TemplatePolicy();
    }

    /**
     * Test viewAny policy - user with tenant_id can view
     */
    public function test_view_any_policy_with_tenant(): void
    {
        $this->assertTrue($this->policy->viewAny($this->user1));
        $this->assertTrue($this->policy->viewAny($this->user2));
    }

    /**
     * Test view policy - creator can view
     */
    public function test_view_policy_creator_can_view(): void
    {
        $this->assertTrue($this->policy->view($this->user1, $this->template1));
    }

    /**
     * Test view policy - public templates can be viewed by anyone in tenant
     */
    public function test_view_policy_public_templates_can_be_viewed(): void
    {
        $this->assertTrue($this->policy->view($this->user2, $this->template2));
    }

    /**
     * Test view policy - private templates cannot be viewed by others
     */
    public function test_view_policy_private_templates_cannot_be_viewed_by_others(): void
    {
        $this->assertFalse($this->policy->view($this->user2, $this->template1));
    }

    /**
     * Test view policy - different tenant cannot view
     */
    public function test_view_policy_different_tenant(): void
    {
        $this->assertFalse($this->policy->view($this->user1, $this->template3));
        $this->assertFalse($this->policy->view($this->user3, $this->template1));
    }

    /**
     * Test create policy - user with tenant_id can create
     */
    public function test_create_policy_with_tenant(): void
    {
        $this->assertTrue($this->policy->create($this->user1));
        $this->assertTrue($this->policy->create($this->user2));
    }

    /**
     * Test update policy - creator can update
     */
    public function test_update_policy_creator_can_update(): void
    {
        $this->assertTrue($this->policy->update($this->user1, $this->template1));
    }

    /**
     * Test update policy - other user cannot update
     */
    public function test_update_policy_other_user_cannot_update(): void
    {
        $this->assertFalse($this->policy->update($this->user2, $this->template1));
    }

    /**
     * Test delete policy - creator can delete
     */
    public function test_delete_policy_creator_can_delete(): void
    {
        $this->assertTrue($this->policy->delete($this->user1, $this->template1));
    }

    /**
     * Test delete policy - other user cannot delete
     */
    public function test_delete_policy_other_user_cannot_delete(): void
    {
        $this->assertFalse($this->policy->delete($this->user2, $this->template1));
    }

    /**
     * Test tenant isolation
     */
    public function test_tenant_isolation(): void
    {
        $this->assertFalse($this->policy->view($this->user1, $this->template3));
        $this->assertFalse($this->policy->update($this->user1, $this->template3));
        $this->assertFalse($this->policy->delete($this->user1, $this->template3));
    }
}

