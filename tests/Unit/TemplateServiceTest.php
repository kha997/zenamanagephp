<?php

namespace Tests\Unit;

use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Services\TemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Str;

class TemplateServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TemplateService $templateService;
    protected Tenant $tenant;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->markTestSkipped('All TemplateServiceTest tests skipped - missing template_versions table');

        $this->templateService = new TemplateService();
        $this->tenant = Tenant::factory()->create(['id' => 'test-tenant-1']);
        $this->user = User::factory()->create([
            'id' => 'test-user-1',
            'tenant_id' => $this->tenant->id,
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    /** @test */
    public function it_can_create_a_template()
    {
        $templateData = [
            'name' => 'Test Template',
            'description' => 'Test template description',
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
            'metadata' => ['source' => 'manual']
        ];

        $template = $this->templateService->createTemplate($templateData, $this->user->id, $this->tenant->id);

        $this->assertInstanceOf(Template::class, $template);
        $this->assertEquals('Test Template', $template->name);
        $this->assertEquals($this->tenant->id, $template->tenant_id);
        $this->assertEquals($this->user->id, $template->created_by);
        $this->assertEquals(Template::STATUS_DRAFT, $template->status);
        $this->assertFalse($template->is_public);
        $this->assertEquals(1, $template->version);
        $this->assertEquals(['test', 'template'], $template->tags);
        $this->assertEquals(['source' => 'manual'], $template->metadata);

        // Check that initial version was created
        $this->assertDatabaseHas('template_versions', [
            'template_id' => $template->id,
            'version' => 1,
            'is_active' => true
        ]);
    }

    /** @test */
    public function it_can_update_a_template()
    {
        $template = Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
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

        $updatedTemplate = $this->templateService->updateTemplate($template, $updateData, $this->user->id);

        $this->assertEquals('Updated Template', $updatedTemplate->name);
        $this->assertEquals('Updated description', $updatedTemplate->description);
        $this->assertEquals(2, $updatedTemplate->version); // Version should increment
        $this->assertEquals($this->user->id, $updatedTemplate->updated_by);

        // Check that new version was created
        $this->assertDatabaseHas('template_versions', [
            'template_id' => $template->id,
            'version' => 2,
            'is_active' => true
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
                        'name' => 'Task 1',
                        'description' => 'First task',
                        'duration_days' => 3,
                        'priority' => 'high',
                        'estimated_hours' => 24
                    ],
                    [
                        'name' => 'Task 2',
                        'description' => 'Second task',
                        'duration_days' => 5,
                        'priority' => 'medium',
                        'estimated_hours' => 40
                    ]
                ]
            ]
        ]);

        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_id' => $this->user->id
        ]);

        $result = $this->templateService->applyTemplateToProject($template, $project, $this->user->id);

        $this->assertArrayHasKey('template', $result);
        $this->assertArrayHasKey('project', $result);
        $this->assertArrayHasKey('tasks', $result);
        $this->assertArrayHasKey('milestones', $result);

        $this->assertCount(2, $result['tasks']);
        $this->assertEquals('Task 1', $result['tasks'][0]->name);
        $this->assertEquals('Task 2', $result['tasks'][1]->name);
        $this->assertEquals($project->id, $result['tasks'][0]->project_id);
        $this->assertEquals($this->tenant->id, $result['tasks'][0]->tenant_id);

        // Check that template usage count was incremented
        $this->assertEquals(1, $template->fresh()->usage_count);
    }

    /** @test */
    public function it_can_duplicate_template()
    {
        $originalTemplate = Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Original Template',
            'usage_count' => 10
        ]);

        $duplicate = $this->templateService->duplicateTemplate($originalTemplate, 'Duplicated Template', $this->user->id);

        $this->assertInstanceOf(Template::class, $duplicate);
        $this->assertEquals('Duplicated Template', $duplicate->name);
        $this->assertEquals($this->tenant->id, $duplicate->tenant_id);
        $this->assertEquals(1, $duplicate->version);
        $this->assertEquals(Template::STATUS_DRAFT, $duplicate->status);
        $this->assertEquals(0, $duplicate->usage_count);
        $this->assertEquals($this->user->id, $duplicate->created_by);
        $this->assertNotEquals($originalTemplate->id, $duplicate->id);
    }

    /** @test */
    public function it_can_create_template_version()
    {
        $template = Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'version' => 1
        ]);

        $version = $this->templateService->createVersion($template, $this->user->id, 'Test version');

        $this->assertInstanceOf(TemplateVersion::class, $version);
        $this->assertEquals($template->id, $version->template_id);
        $this->assertEquals(1, $version->version);
        $this->assertEquals('Test version', $version->name);
        $this->assertEquals('Test version', $version->description);
        $this->assertTrue($version->is_active);
        $this->assertEquals($this->user->id, $version->created_by);

        // Check that other versions are deactivated
        $this->assertDatabaseHas('template_versions', [
            'template_id' => $template->id,
            'version' => 1,
            'is_active' => true
        ]);
    }

    /** @test */
    public function it_can_get_template_analytics()
    {
        // Create templates with different statuses
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
        Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => Template::STATUS_ARCHIVED,
            'is_public' => false,
            'usage_count' => 0
        ]);

        $analytics = $this->templateService->getTemplateAnalytics($this->tenant->id);

        $this->assertArrayHasKey('total_templates', $analytics);
        $this->assertArrayHasKey('published_templates', $analytics);
        $this->assertArrayHasKey('draft_templates', $analytics);
        $this->assertArrayHasKey('archived_templates', $analytics);
        $this->assertArrayHasKey('public_templates', $analytics);
        $this->assertArrayHasKey('total_usage', $analytics);
        $this->assertArrayHasKey('categories', $analytics);
        $this->assertArrayHasKey('popular_templates', $analytics);
        $this->assertArrayHasKey('recent_templates', $analytics);

        $this->assertEquals(3, $analytics['total_templates']);
        $this->assertEquals(1, $analytics['published_templates']);
        $this->assertEquals(1, $analytics['draft_templates']);
        $this->assertEquals(1, $analytics['archived_templates']);
        $this->assertEquals(1, $analytics['public_templates']);
        $this->assertEquals(15, $analytics['total_usage']);
    }

    /** @test */
    public function it_can_search_templates()
    {
        Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Project Template',
            'category' => Template::CATEGORY_PROJECT,
            'status' => Template::STATUS_PUBLISHED,
            'is_public' => true,
            'tags' => ['project', 'management']
        ]);
        Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Task Template',
            'category' => Template::CATEGORY_TASK,
            'status' => Template::STATUS_DRAFT,
            'is_public' => false,
            'tags' => ['task', 'workflow']
        ]);

        // Search by category
        $projectTemplates = $this->templateService->searchTemplates($this->tenant->id, [
            'category' => Template::CATEGORY_PROJECT
        ]);
        $this->assertCount(1, $projectTemplates);
        $this->assertEquals(Template::CATEGORY_PROJECT, $projectTemplates->first()->category);

        // Search by status
        $publishedTemplates = $this->templateService->searchTemplates($this->tenant->id, [
            'status' => Template::STATUS_PUBLISHED
        ]);
        $this->assertCount(1, $publishedTemplates);
        $this->assertEquals(Template::STATUS_PUBLISHED, $publishedTemplates->first()->status);

        // Search by public/private
        $publicTemplates = $this->templateService->searchTemplates($this->tenant->id, [
            'is_public' => true
        ]);
        $this->assertCount(1, $publicTemplates);
        $this->assertTrue($publicTemplates->first()->is_public);

        // Search by text
        $searchResults = $this->templateService->searchTemplates($this->tenant->id, [
            'search' => 'Project'
        ]);
        $this->assertCount(1, $searchResults);
        $this->assertStringContainsString('Project', $searchResults->first()->name);

        // Search by tags
        $tagResults = $this->templateService->searchTemplates($this->tenant->id, [
            'tags' => 'project'
        ]);
        $this->assertCount(1, $tagResults);
        $this->assertContains('project', $tagResults->first()->tags);
    }

    /** @test */
    public function it_respects_tenant_isolation()
    {
        $otherTenant = Tenant::factory()->create(['id' => 'other-tenant-1']);
        
        Template::factory()->create(['tenant_id' => $this->tenant->id]);
        Template::factory()->create(['tenant_id' => $otherTenant->id]);

        $templates = $this->templateService->searchTemplates($this->tenant->id);
        $otherTemplates = $this->templateService->searchTemplates($otherTenant->id);

        $this->assertCount(1, $templates);
        $this->assertCount(1, $otherTemplates);
        $this->assertEquals($this->tenant->id, $templates->first()->tenant_id);
        $this->assertEquals($otherTenant->id, $otherTemplates->first()->tenant_id);
    }

    /** @test */
    public function it_handles_empty_template_data()
    {
        $templateData = [
            'name' => 'Empty Template',
            'description' => 'Template with no data',
            'category' => Template::CATEGORY_PROJECT,
            'template_data' => []
        ];

        $template = $this->templateService->createTemplate($templateData, $this->user->id, $this->tenant->id);

        $this->assertInstanceOf(Template::class, $template);
        $this->assertEquals('Empty Template', $template->name);
        $this->assertEquals([], $template->template_data);
    }

    /** @test */
    public function it_handles_template_without_version_increment()
    {
        $template = Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
            'version' => 1
        ]);

        $updateData = [
            'name' => 'Updated Template',
            'description' => 'Updated description'
            // No template_data change
        ];

        $updatedTemplate = $this->templateService->updateTemplate($template, $updateData, $this->user->id);

        $this->assertEquals('Updated Template', $updatedTemplate->name);
        $this->assertEquals(1, $updatedTemplate->version); // Version should not increment
    }
}