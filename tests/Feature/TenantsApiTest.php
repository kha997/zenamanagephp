<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TenantsApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test user
        $this->user = User::factory()->create([
            'role' => 'super_admin'
        ]);
    }

    /** @test */
    public function it_can_list_tenants_with_etag_support()
    {
        // Create test tenants
        $tenants = Tenant::factory()->count(5)->create();

        $response = $this->actingAs($this->user)
            ->getJson('/api/admin/tenants');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'domain',
                        'status',
                        'users_count',
                        'projects_count'
                    ]
                ],
                'meta' => [
                    'total',
                    'page',
                    'per_page',
                    'last_page',
                    'generatedAt'
                ]
            ])
            ->assertHeader('ETag')
            ->assertHeader('Cache-Control');

        // Test ETag caching
        $etag = $response->headers->get('ETag');
        $cachedResponse = $this->actingAs($this->user)
            ->getJson('/api/admin/tenants', [
                'If-None-Match' => $etag
            ]);

        $cachedResponse->assertStatus(304);
    }

    /** @test */
    public function it_can_filter_tenants_by_status()
    {
        Tenant::factory()->create(['status' => 'active']);
        Tenant::factory()->create(['status' => 'suspended']);

        $response = $this->actingAs($this->user)
            ->getJson('/api/admin/tenants?status=active');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('meta.total'));
    }

    /** @test */
    public function it_can_search_tenants()
    {
        Tenant::factory()->create(['name' => 'TechCorp']);
        Tenant::factory()->create(['name' => 'DesignStudio']);

        $response = $this->actingAs($this->user)
            ->getJson('/api/admin/tenants?q=Tech');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals('TechCorp', $response->json('data.0.name'));
    }

    /** @test */
    public function it_can_sort_tenants()
    {
        Tenant::factory()->create(['name' => 'Alpha Corp']);
        Tenant::factory()->create(['name' => 'Beta Corp']);

        $response = $this->actingAs($this->user)
            ->getJson('/api/admin/tenants?sort=name');

        $response->assertStatus(200);
        $this->assertEquals('Alpha Corp', $response->json('data.0.name'));

        $response = $this->actingAs($this->user)
            ->getJson('/api/admin/tenants?sort=-name');

        $response->assertStatus(200);
        $this->assertEquals('Beta Corp', $response->json('data.0.name'));
    }

    /** @test */
    public function it_rejects_invalid_sort_parameters()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/admin/tenants?sort=invalid_field');

        $response->assertStatus(422);
    }

    /** @test */
    public function it_can_create_tenant()
    {
        $tenantData = [
            'name' => 'Test Corp',
            'domain' => 'testcorp.com',
            'ownerName' => 'John Doe',
            'ownerEmail' => 'john@testcorp.com',
            'plan' => 'Basic'
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/admin/tenants', $tenantData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'domain',
                    'status'
                ]
            ]);

        $this->assertDatabaseHas('tenants', [
            'name' => 'Test Corp',
            'domain' => 'testcorp.com'
        ]);
    }

    /** @test */
    public function it_validates_tenant_creation_data()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/admin/tenants', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'domain', 'ownerName', 'ownerEmail', 'plan']);
    }

    /** @test */
    public function it_can_show_tenant()
    {
        $tenant = Tenant::factory()->create();

        $response = $this->actingAs($this->user)
            ->getJson("/api/admin/tenants/{$tenant->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'domain',
                    'status',
                    'users_count',
                    'projects_count'
                ]
            ]);
    }

    /** @test */
    public function it_can_update_tenant()
    {
        $tenant = Tenant::factory()->create();

        $updateData = [
            'name' => 'Updated Corp',
            'status' => 'suspended'
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/admin/tenants/{$tenant->id}", $updateData);

        $response->assertStatus(200);
        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'name' => 'Updated Corp',
            'status' => 'suspended'
        ]);
    }

    /** @test */
    public function it_can_delete_tenant()
    {
        $tenant = Tenant::factory()->create();

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/admin/tenants/{$tenant->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('tenants', ['id' => $tenant->id]);
    }

    /** @test */
    public function it_can_export_tenants()
    {
        Tenant::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->getJson('/api/admin/tenants/export.csv');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('Content-Disposition');

        $csvContent = $response->getContent();
        $this->assertStringContainsString('id,name,domain,status', $csvContent);
    }

    /** @test */
    public function it_rate_limits_export_requests()
    {
        // Make multiple export requests to trigger rate limiting
        for ($i = 0; $i < 12; $i++) {
            $response = $this->actingAs($this->user)
                ->getJson('/api/admin/tenants/export.csv');
        }

        $response->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    /** @test */
    public function it_requires_authentication()
    {
        $response = $this->getJson('/api/admin/tenants');
        $response->assertStatus(401);
    }

    /** @test */
    public function it_requires_admin_role()
    {
        $regularUser = User::factory()->create(['role' => 'user']);
        
        $response = $this->actingAs($regularUser)
            ->getJson('/api/admin/tenants');
            
        $response->assertStatus(403);
    }
}
