<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Str;

class TemplateApiTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['id' => 'test-tenant-1']);
        $this->user = User::factory()->create([
            'id' => 'test-user-1',
            'tenant_id' => $this->tenant->id,
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    /** @test */
    public function it_can_get_templates_list()
    {
        Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Template 1',
            'category' => Template::CATEGORY_PROJECT
        ]);

        Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Template 2',
            'category' => Template::CATEGORY_TASK
        ]);

        $this->actingAs($this->user, 'sanctum');

        $response = $this->getJson('/api/v1/app/templates');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'templates',
                        'filters',
                        'total'
                    ]
                ])
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'total' => 2
                    ]
                ]);
    }

    /** @test */
    public function it_can_filter_templates_by_category()
    {
        Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'category' => Template::CATEGORY_PROJECT
        ]);

        Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'category' => Template::CATEGORY_TASK
        ]);

        $this->actingAs($this->user, 'sanctum');

        $response = $this->getJson('/api/v1/app/templates?category=project');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'total' => 1
                    ]
                ]);

        $templates = $response->json('data.templates');
        $this->assertEquals(Template::CATEGORY_PROJECT, $templates[0]['category']);
    }

    /** @test */
    public function it_can_search_templates_by_name()
    {
        Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Project Management Template'
        ]);

        Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Task Tracking Template'
        ]);

        $this->actingAs($this->user, 'sanctum');

        $response = $this->getJson('/api/v1/app/templates?search=Project');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'total' => 1
                    ]
                ]);

        $templates = $response->json('data.templates');
        $this->assertStringContainsString('Project', $templates[0]['name']);
    }

    /** @test */
    public function it_can_get_specific_template()
    {
        $template = Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Template',
            'description' => 'Test description'
        ]);

        $this->actingAs($this->user, 'sanctum');

        $response = $this->getJson("/api/v1/app/templates/{$template->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'template' => [
                            'id',
                            'name',
                            'description',
                            'category',
                            'status',
                            'version',
                            'usage_count',
                            'created_at',
                            'updated_at'
                        ]
                    ]
                ])
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'template' => [
                            'id' => $template->id,
                            'name' => 'Test Template',
                            'description' => 'Test description'
                        ]
                    ]
                ]);
    }

    /** @test */
    public function it_can_create_template()
    {
        $templateData = [
            'name' => 'New Template',
            'description' => 'New template description',
            'category' => Template::CATEGORY_PROJECT,
            'template_data' => [
                'phases' => [
                    [
                        'name' => 'Phase 1',
                        'duration_days' => 5,
                        'tasks' => []
                    ]
                ]
            ],
            'status' => Template::STATUS_DRAFT,
            'is_public' => false,
            'tags' => ['test', 'template'],
            'metadata' => ['source' => 'api']
        ];

        $this->actingAs($this->user, 'sanctum');

        $response = $this->postJson('/api/v1/app/templates', $templateData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'template' => [
                            'id',
                            'name',
                            'description',
                            'category',
                            'status',
                            'version',
                            'created_by'
                        ]
                    ]
                ])
                ->assertJson([
                    'success' => true,
                    'message' => 'Template created successfully',
                    'data' => [
                        'template' => [
                            'name' => 'New Template',
                            'description' => 'New template description',
                            'category' => Template::CATEGORY_PROJECT,
                            'status' => Template::STATUS_DRAFT
                        ]
                    ]
                ]);

        $this->assertDatabaseHas('templates', [
            'name' => 'New Template',
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id
        ]);
    }

    /** @test */
    public function it_can_update_template()
    {
        $template = Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Original Template',
            'version' => 1
        ]);

        $updateData = [
            'name' => 'Updated Template',
            'description' => 'Updated description',
            'template_data' => [
                'phases' => [
                    [
                        'name' => 'Updated Phase',
                        'duration_days' => 10,
                        'tasks' => []
                    ]
                ]
            ]
        ];

        $this->actingAs($this->user, 'sanctum');

        $response = $this->putJson("/api/v1/app/templates/{$template->id}", $updateData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Template updated successfully',
                    'data' => [
                        'template' => [
                            'name' => 'Updated Template',
                            'description' => 'Updated description'
                        ]
                    ]
                ]);

        $this->assertDatabaseHas('templates', [
            'id' => $template->id,
            'name' => 'Updated Template',
            'description' => 'Updated description',
            'version' => 2 // Version should increment
        ]);
    }

    /** @test */
    public function it_can_delete_template()
    {
        $template = Template::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);

        $this->actingAs($this->user, 'sanctum');

        $response = $this->deleteJson("/api/v1/app/templates/{$template->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Template deleted successfully'
                ]);

        $this->assertSoftDeleted('templates', [
            'id' => $template->id
        ]);
    }

    /** @test */
    public function it_can_apply_template_to_project()
    {
        $template = Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => Template::STATUS_PUBLISHED,
            'is_active' => true,
            'template_data' => [
                'tasks' => [
                    [
                        'name' => 'Template Task',
                        'description' => 'Task from template',
                        'duration_days' => 3,
                        'priority' => 'medium',
                        'estimated_hours' => 24
                    ]
                ]
            ]
        ]);

        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_id' => $this->user->id
        ]);

        $this->actingAs($this->user, 'sanctum');

        $response = $this->postJson("/api/v1/app/templates/{$template->id}/apply-to-project", [
            'project_id' => $project->id
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'template',
                        'project',
                        'tasks',
                        'milestones'
                    ]
                ])
                ->assertJson([
                    'success' => true,
                    'message' => 'Template applied to project successfully'
                ]);

        $this->assertDatabaseHas('tasks', [
            'name' => 'Template Task',
            'project_id' => $project->id,
            'tenant_id' => $this->tenant->id
        ]);

        $this->assertEquals(1, $template->fresh()->usage_count);
    }

    /** @test */
    public function it_can_duplicate_template()
    {
        $template = Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Original Template',
            'usage_count' => 5
        ]);

        $this->actingAs($this->user, 'sanctum');

        $response = $this->postJson("/api/v1/app/templates/{$template->id}/duplicate", [
            'name' => 'Duplicated Template'
        ]);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'template' => [
                            'id',
                            'name',
                            'created_by'
                        ]
                    ]
                ])
                ->assertJson([
                    'success' => true,
                    'message' => 'Template duplicated successfully',
                    'data' => [
                        'template' => [
                            'name' => 'Duplicated Template'
                        ]
                    ]
                ]);

        $this->assertDatabaseHas('templates', [
            'name' => 'Duplicated Template',
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'usage_count' => 0
        ]);
    }

    /** @test */
    public function it_can_get_template_analytics()
    {
        Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => Template::STATUS_PUBLISHED,
            'is_public' => true,
            'usage_count' => 10
        ]);

        Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => Template::STATUS_DRAFT,
            'is_public' => false,
            'usage_count' => 5
        ]);

        $this->actingAs($this->user, 'sanctum');

        $response = $this->getJson('/api/v1/app/templates/analytics');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'total_templates',
                        'published_templates',
                        'draft_templates',
                        'archived_templates',
                        'public_templates',
                        'total_usage',
                        'most_used_template',
                        'categories',
                        'recent_templates',
                        'popular_templates'
                    ]
                ])
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'total_templates' => 2,
                        'published_templates' => 1,
                        'draft_templates' => 1,
                        'total_usage' => 15
                    ]
                ]);
    }

    /** @test */
    public function it_can_export_template()
    {
        $template = Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Export Template',
            'template_data' => [
                'phases' => [
                    [
                        'name' => 'Export Phase',
                        'duration_days' => 5
                    ]
                ]
            ]
        ]);

        $this->actingAs($this->user, 'sanctum');

        $response = $this->getJson("/api/v1/app/templates/{$template->id}/export");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'template_export',
                        'template' => [
                            'name',
                            'description',
                            'category',
                            'template_data'
                        ]
                    ]
                ])
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'template' => [
                            'name' => 'Export Template'
                        ]
                    ]
                ]);
    }

    /** @test */
    public function it_can_import_template()
    {
        $importData = [
            'template_data' => [
                'template' => [
                    'name' => 'Imported Template',
                    'description' => 'Imported template description',
                    'category' => Template::CATEGORY_PROJECT,
                    'template_data' => [
                        'phases' => [
                            [
                                'name' => 'Imported Phase',
                                'duration_days' => 7
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $this->actingAs($this->user, 'sanctum');

        $response = $this->postJson('/api/v1/app/templates/import', $importData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'template' => [
                            'id',
                            'name',
                            'created_by'
                        ]
                    ]
                ])
                ->assertJson([
                    'success' => true,
                    'message' => 'Template imported successfully',
                    'data' => [
                        'template' => [
                            'name' => 'Imported Template (Imported)'
                        ]
                    ]
                ]);

        $this->assertDatabaseHas('templates', [
            'name' => 'Imported Template (Imported)',
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'status' => Template::STATUS_DRAFT
        ]);
    }

    /** @test */
    public function it_respects_tenant_isolation()
    {
        $otherTenant = Tenant::factory()->create(['id' => 'other-tenant-1']);
        $otherUser = User::factory()->create([
            'id' => 'other-user-1',
            'tenant_id' => $otherTenant->id,
            'email' => 'other@example.com',
            'password' => bcrypt('password'),
        ]);

        $template1 = Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Tenant 1 Template'
        ]);

        $template2 = Template::factory()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Tenant 2 Template'
        ]);

        $this->actingAs($this->user, 'sanctum');

        // User should only see templates from their tenant
        $response = $this->getJson('/api/v1/app/templates');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'total' => 1
                    ]
                ]);

        $templates = $response->json('data.templates');
        $this->assertEquals($this->tenant->id, $templates[0]['tenant_id']);

        // User should not be able to access template from other tenant
        $response = $this->getJson("/api/v1/app/templates/{$template2->id}");
        $response->assertStatus(404);
    }

    /** @test */
    public function it_requires_authentication()
    {
        $template = Template::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);

        // Test without authentication
        $response = $this->getJson('/api/v1/app/templates');
        $response->assertStatus(401);

        $response = $this->getJson("/api/v1/app/templates/{$template->id}");
        $response->assertStatus(401);

        $response = $this->postJson('/api/v1/app/templates', []);
        $response->assertStatus(401);
    }

    /** @test */
    public function it_validates_template_creation_data()
    {
        $this->actingAs($this->user, 'sanctum');

        // Test with missing required fields
        $response = $this->postJson('/api/v1/app/templates', [
            'description' => 'Missing name and category'
        ]);

        $response->assertStatus(422)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'errors'
                ]);

        // Test with invalid category
        $response = $this->postJson('/api/v1/app/templates', [
            'name' => 'Test Template',
            'category' => 'invalid_category',
            'template_data' => []
        ]);

        $response->assertStatus(422);

        // Test with invalid status
        $response = $this->postJson('/api/v1/app/templates', [
            'name' => 'Test Template',
            'category' => Template::CATEGORY_PROJECT,
            'status' => 'invalid_status',
            'template_data' => []
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function it_can_export_analytics()
    {
        Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Analytics Template',
            'category' => Template::CATEGORY_PROJECT,
            'usage_count' => 10
        ]);

        $this->actingAs($this->user, 'sanctum');

        $response = $this->getJson('/api/v1/app/templates/analytics/export?period=30');

        $response->assertStatus(200)
                ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
                ->assertHeader('Content-Disposition', 'attachment; filename="template-analytics-30days.csv"');

        $content = $response->getContent();
        $this->assertStringContainsString('Template Analytics Report (30 days)', $content);
        $this->assertStringContainsString('Analytics Template', $content);
    }
}
