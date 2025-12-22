<?php declare(strict_types=1);

namespace Tests\Feature\Api\Templates;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Tests for Templates API tenant permission enforcement
 * 
 * Tests that templates endpoints properly enforce tenant.permission middleware
 * for mutation endpoints (POST, PUT, PATCH, DELETE).
 * 
 * @group templates
 * @group tenant-permissions
 */
class TemplatesPermissionTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenant;
    private Template $template;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(54321);
        $this->setDomainName('templates-permission');
        $this->setupDomainIsolation();
        
        // Create tenant
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . uniqid(),
        ]);
        
        // Create template
        $this->template = Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Template',
            'category' => 'project',
            'status' => 'published',
        ]);
    }

    /**
     * Test that GET /api/v1/app/templates requires tenant.view_templates permission (Round 11)
     * 
     * Viewer role has tenant.view_templates from config, so should be able to GET templates.
     */
    public function test_get_templates_requires_view_permission(): void
    {
        // Create user with viewer role (has tenant.view_templates from config)
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $user->tenants()->attach($this->tenant->id, [
            'role' => 'viewer',
            'is_default' => true,
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        // Viewer should be able to view templates (has tenant.view_templates)
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/templates');

        $response->assertStatus(200);
    }

    /**
     * Test that POST /api/v1/app/templates requires tenant.manage_templates permission
     */
    public function test_create_template_requires_manage_permission(): void
    {
        // Create user with admin role (has tenant.manage_templates)
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $user->tenants()->attach($this->tenant->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        // Admin should be able to create templates
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-create-template-' . uniqid(),
        ])->postJson('/api/v1/app/templates', [
            'name' => 'New Template',
            'description' => 'Test template description',
            'category' => 'project',
            'template_data' => [
                'tasks' => [],
                'phases' => [],
            ],
            'is_public' => false,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('templates', [
            'name' => 'New Template',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /**
     * Test that POST /api/v1/app/templates returns 403 without tenant.manage_templates permission
     */
    public function test_create_template_returns_403_without_permission(): void
    {
        // Create user with viewer role (only has view permissions, not manage)
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $user->tenants()->attach($this->tenant->id, [
            'role' => 'viewer',
            'is_default' => true,
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        // Viewer should NOT be able to create templates
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-create-template-viewer-' . uniqid(),
        ])->postJson('/api/v1/app/templates', [
            'name' => 'New Template',
            'category' => 'project',
            'template_data' => [],
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);
    }

    /**
     * Test that PUT /api/v1/app/templates/{template} requires tenant.manage_templates permission
     */
    public function test_update_template_requires_manage_permission(): void
    {
        // Create user with admin role
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $user->tenants()->attach($this->tenant->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        // Admin should be able to update templates
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-update-template-' . uniqid(),
        ])->putJson("/api/v1/app/templates/{$this->template->id}", [
            'name' => 'Updated Template Name',
            'description' => 'Updated description',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('templates', [
            'id' => $this->template->id,
            'name' => 'Updated Template Name',
        ]);
    }

    /**
     * Test that PUT /api/v1/app/templates/{template} returns 403 without permission
     */
    public function test_update_template_returns_403_without_permission(): void
    {
        // Create user with member role (no tenant.manage_templates)
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $user->tenants()->attach($this->tenant->id, [
            'role' => 'member',
            'is_default' => true,
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        // Member should NOT be able to update templates
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-update-template-member-' . uniqid(),
        ])->putJson("/api/v1/app/templates/{$this->template->id}", [
            'name' => 'Updated Template Name',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);
    }

    /**
     * Test that DELETE /api/v1/app/templates/{template} requires tenant.manage_templates permission
     */
    public function test_delete_template_requires_manage_permission(): void
    {
        // Create user with admin role
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $user->tenants()->attach($this->tenant->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        // Create a template to delete
        $templateToDelete = Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Template to Delete',
        ]);

        // Admin should be able to delete templates
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->deleteJson("/api/v1/app/templates/{$templateToDelete->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('templates', [
            'id' => $templateToDelete->id,
        ]);
    }

    /**
     * Test that DELETE /api/v1/app/templates/{template} returns 403 without permission
     */
    public function test_delete_template_returns_403_without_permission(): void
    {
        // Create user with viewer role
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $user->tenants()->attach($this->tenant->id, [
            'role' => 'viewer',
            'is_default' => true,
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        // Viewer should NOT be able to delete templates
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->deleteJson("/api/v1/app/templates/{$this->template->id}");

        $response->assertStatus(403);
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);
    }

    /**
     * Test that member role can view templates but not manage them
     */
    public function test_member_role_can_view_but_not_manage_templates(): void
    {
        // Create user with member role
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $user->tenants()->attach($this->tenant->id, [
            'role' => 'member',
            'is_default' => true,
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        // Member should be able to view templates
        $viewResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/templates');

        $this->assertContains($viewResponse->status(), [200, 404]);

        // Member should NOT be able to delete templates (requires tenant.manage_templates)
        $deleteResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->deleteJson("/api/v1/app/templates/{$this->template->id}");

        $deleteResponse->assertStatus(403);
        $deleteResponse->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);
    }

    /**
     * Test that templates are scoped to active tenant
     */
    public function test_templates_are_scoped_to_active_tenant(): void
    {
        // Create another tenant and template
        $otherTenant = Tenant::factory()->create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . uniqid(),
        ]);
        
        $otherTemplate = Template::factory()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Tenant Template',
        ]);

        // Create user with admin role in first tenant
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $user->tenants()->attach($this->tenant->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        // User should only see templates from their active tenant
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/templates');

        $response->assertStatus(200);
        $templates = $response->json('data') ?? [];
        
        // Verify all returned templates belong to the active tenant
        foreach ($templates as $template) {
            $templateTenantId = $template['tenant_id'] ?? null;
            $this->assertEquals(
                $this->tenant->id,
                $templateTenantId,
                'Templates should only be from active tenant'
            );
        }
        
        // Verify other tenant's template is not included
        $templateIds = array_column($templates, 'id');
        $this->assertNotContains($otherTemplate->id, $templateIds, 'Other tenant template should not be visible');
    }

    /**
     * Test Templates view vs manage permissions (Round 9)
     * 
     * Confirm that member/viewer:
     * - GET /api/v1/app/templates and GET /templates/{id} → 200 OK (no tenant.permission required, tenant-scoped only)
     * - POST/PUT/DELETE /templates* → 403 + TENANT_PERMISSION_DENIED
     */
    public function test_member_viewer_can_view_but_not_manage_templates(): void
    {
        // Create user with member role (has tenant.view_templates, not tenant.manage_templates)
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $user->tenants()->attach($this->tenant->id, [
            'role' => 'member',
            'is_default' => true,
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        // Member should be able to GET templates list
        $listResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/templates');

        $listResponse->assertStatus(200);

        // Member should be able to GET specific template
        $showResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson("/api/v1/app/templates/{$this->template->id}");

        $showResponse->assertStatus(200);

        // Member should NOT be able to POST (create) templates
        $createResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-create-member-' . uniqid(),
        ])->postJson('/api/v1/app/templates', [
            'name' => 'New Template',
            'category' => 'project',
            'template_data' => [],
        ]);

        $createResponse->assertStatus(403);
        $createResponse->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);

        // Member should NOT be able to PUT (update) templates
        $updateResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->putJson("/api/v1/app/templates/{$this->template->id}", [
            'name' => 'Updated Name',
        ]);

        $updateResponse->assertStatus(403);
        $updateResponse->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);

        // Member should NOT be able to DELETE templates
        $deleteResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->deleteJson("/api/v1/app/templates/{$this->template->id}");

        $deleteResponse->assertStatus(403);
        $deleteResponse->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);
    }

    /**
     * Test that admin/owner can manage templates (has both view & manage)
     */
    public function test_admin_owner_can_manage_templates(): void
    {
        // Create user with admin role (has both tenant.view_templates and tenant.manage_templates)
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $user->tenants()->attach($this->tenant->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        // Admin should be able to GET templates
        $getResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/templates');

        $getResponse->assertStatus(200);

        // Admin should be able to POST (create) templates
        $createResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-create-admin-' . uniqid(),
        ])->postJson('/api/v1/app/templates', [
            'name' => 'Admin Template',
            'category' => 'project',
            'template_data' => [],
        ]);

        $createResponse->assertStatus(201);

        // Admin should be able to PUT (update) templates
        $updateResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->putJson("/api/v1/app/templates/{$this->template->id}", [
            'name' => 'Updated by Admin',
        ]);

        $updateResponse->assertStatus(200);
    }

    /**
     * Test that user without tenant.view_templates cannot GET templates (Round 11)
     * 
     * Negative test: role 'guest' is not defined in config/permissions.php tenant_roles,
     * so user will have no permissions and should get 403.
     */
    public function test_user_without_view_templates_cannot_get_templates(): void
    {
        // Create user with 'guest' role (not in config/permissions.php, so no permissions)
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $user->tenants()->attach($this->tenant->id, [
            'role' => 'guest', // Role not in config, so no tenant.view_templates
            'is_default' => true,
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        // Guest should NOT be able to GET templates list
        $listResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/templates');

        $listResponse->assertStatus(403);
        $listResponse->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);

        // Guest should NOT be able to GET specific template
        $showResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson("/api/v1/app/templates/{$this->template->id}");

        $showResponse->assertStatus(403);
        $showResponse->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);

        // Guest should NOT be able to GET templates/kpis
        $kpisResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/templates/kpis');

        $kpisResponse->assertStatus(403);
        $kpisResponse->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);

        // Guest should NOT be able to GET templates/library
        $libraryResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/templates/library');

        $libraryResponse->assertStatus(403);
        $libraryResponse->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);
    }

    /**
     * Test that all 4 standard roles (owner/admin/member/viewer) can GET templates (Round 11)
     * 
     * All standard roles have tenant.view_templates from config, so should all pass.
     */
    public function test_all_standard_roles_can_get_templates(): void
    {
        $roles = ['owner', 'admin', 'member', 'viewer'];

        foreach ($roles as $role) {
            $user = User::factory()->create([
                'tenant_id' => $this->tenant->id,
                'email_verified_at' => now(),
            ]);

            $user->tenants()->attach($this->tenant->id, [
                'role' => $role,
                'is_default' => true,
            ]);

            Sanctum::actingAs($user);
            $token = $user->createToken('test-token')->plainTextToken;

            // All standard roles should be able to GET templates
            $response = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->getJson('/api/v1/app/templates');

            $response->assertStatus(200, "Role {$role} should be able to GET templates (has tenant.view_templates)");

            // All standard roles should be able to GET specific template
            $showResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->getJson("/api/v1/app/templates/{$this->template->id}");

            $showResponse->assertStatus(200, "Role {$role} should be able to GET template detail (has tenant.view_templates)");
        }
    }
}

