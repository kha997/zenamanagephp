<?php

namespace Tests\Feature\Integration;

use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Services\TemplateService;
use App\Services\TemplateImportExportService;
use App\Services\TemplateSharingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Str;

class TemplateIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected TemplateService $templateService;
    protected TemplateImportExportService $importExportService;
    protected TemplateSharingService $sharingService;

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

        $this->templateService = new TemplateService();
        $this->importExportService = new TemplateImportExportService();
        $this->sharingService = new TemplateSharingService();
    }

    /** @test */
    public function it_can_create_and_apply_template_to_project()
    {
        // Create template
        $templateData = [
            'name' => 'Project Template',
            'description' => 'Complete project template',
            'category' => Template::CATEGORY_PROJECT,
            'template_data' => [
                'phases' => [
                    [
                        'name' => 'Planning',
                        'duration_days' => 5,
                        'tasks' => [
                            [
                                'name' => 'Project Setup',
                                'description' => 'Initialize project',
                                'duration_days' => 2,
                                'priority' => 'high',
                                'estimated_hours' => 16
                            ]
                        ]
                    ],
                    [
                        'name' => 'Development',
                        'duration_days' => 15,
                        'tasks' => [
                            [
                                'name' => 'Core Development',
                                'description' => 'Main development work',
                                'duration_days' => 10,
                                'priority' => 'high',
                                'estimated_hours' => 80
                            ]
                        ]
                    ]
                ],
                'milestones' => [
                    [
                        'name' => 'Project Kickoff',
                        'date_offset' => 0,
                        'description' => 'Project initiation'
                    ]
                ]
            ],
            'status' => Template::STATUS_PUBLISHED,
            'is_public' => false,
            'tags' => ['project', 'development'],
            'metadata' => ['complexity' => 'medium']
        ];

        $template = $this->templateService->createTemplate($templateData, $this->user->id, $this->tenant->id);

        // Verify template creation
        $this->assertInstanceOf(Template::class, $template);
        $this->assertEquals('Project Template', $template->name);
        $this->assertEquals(Template::STATUS_PUBLISHED, $template->status);
        $this->assertTrue($template->canBeUsed());

        // Create project
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_id' => $this->user->id,
            'name' => 'Test Project'
        ]);

        // Apply template to project
        $result = $this->templateService->applyTemplateToProject($template, $project, $this->user->id);

        // Verify template application
        $this->assertArrayHasKey('tasks', $result);
        $this->assertArrayHasKey('milestones', $result);
        $this->assertCount(2, $result['tasks']); // 2 tasks from template

        // Verify tasks were created correctly
        $task1 = $result['tasks'][0];
        $this->assertEquals('Project Setup', $task1->name);
        $this->assertEquals($project->id, $task1->project_id);
        $this->assertEquals($this->tenant->id, $task1->tenant_id);
        $this->assertEquals('high', $task1->priority);
        $this->assertEquals(16, $task1->estimated_hours);

        $task2 = $result['tasks'][1];
        $this->assertEquals('Core Development', $task2->name);
        $this->assertEquals($project->id, $task2->project_id);
        $this->assertEquals($this->tenant->id, $task2->tenant_id);
        $this->assertEquals('high', $task2->priority);
        $this->assertEquals(80, $task2->estimated_hours);

        // Verify template usage count was incremented
        $this->assertEquals(1, $template->fresh()->usage_count);

        // Verify template metadata was added to tasks
        $this->assertArrayHasKey('template_id', $task1->metadata);
        $this->assertArrayHasKey('template_version', $task1->metadata);
        $this->assertEquals($template->id, $task1->metadata['template_id']);
    }

    /** @test */
    public function it_can_duplicate_and_modify_template()
    {
        // Create original template
        $originalTemplate = Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Original Template',
            'usage_count' => 5,
            'template_data' => [
                'phases' => [
                    [
                        'name' => 'Phase 1',
                        'duration_days' => 3,
                        'tasks' => [
                            [
                                'name' => 'Task 1',
                                'duration_days' => 2,
                                'priority' => 'medium',
                                'estimated_hours' => 16
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        // Duplicate template
        $duplicate = $this->templateService->duplicateTemplate($originalTemplate, 'Duplicated Template', $this->user->id);

        // Verify duplication
        $this->assertInstanceOf(Template::class, $duplicate);
        $this->assertEquals('Duplicated Template', $duplicate->name);
        $this->assertEquals(0, $duplicate->usage_count);
        $this->assertEquals(Template::STATUS_DRAFT, $duplicate->status);
        $this->assertNotEquals($originalTemplate->id, $duplicate->id);

        // Modify duplicated template
        $updateData = [
            'name' => 'Modified Duplicate',
            'template_data' => [
                'phases' => [
                    [
                        'name' => 'Modified Phase',
                        'duration_days' => 5,
                        'tasks' => [
                            [
                                'name' => 'Modified Task',
                                'duration_days' => 3,
                                'priority' => 'high',
                                'estimated_hours' => 24
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $modifiedTemplate = $this->templateService->updateTemplate($duplicate, $updateData, $this->user->id);

        // Verify modification
        $this->assertEquals('Modified Duplicate', $modifiedTemplate->name);
        $this->assertEquals(2, $modifiedTemplate->version); // Version should increment
        $this->assertEquals('Modified Phase', $modifiedTemplate->template_data['phases'][0]['name']);
        $this->assertEquals('Modified Task', $modifiedTemplate->template_data['phases'][0]['tasks'][0]['name']);

        // Verify original template is unchanged
        $this->assertEquals('Original Template', $originalTemplate->fresh()->name);
        $this->assertEquals(5, $originalTemplate->fresh()->usage_count);
    }

    /** @test */
    public function it_can_manage_template_versions()
    {
        // Create template
        $template = Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'version' => 1,
            'template_data' => [
                'phases' => [
                    [
                        'name' => 'Version 1 Phase',
                        'duration_days' => 3
                    ]
                ]
            ]
        ]);

        // Create initial version
        $version1 = $this->templateService->createVersion($template, $this->user->id, 'Initial version');
        $this->assertEquals(1, $version1->version);
        $this->assertTrue($version1->is_active);

        // Update template
        $updateData = [
            'template_data' => [
                'phases' => [
                    [
                        'name' => 'Version 2 Phase',
                        'duration_days' => 5
                    ]
                ]
            ]
        ];

        $updatedTemplate = $this->templateService->updateTemplate($template, $updateData, $this->user->id);

        // Verify version increment
        $this->assertEquals(2, $updatedTemplate->version);

        // Verify new version was created
        $version2 = TemplateVersion::where('template_id', $template->id)
            ->where('version', 2)
            ->first();

        $this->assertNotNull($version2);
        $this->assertTrue($version2->is_active);
        $this->assertFalse($version1->fresh()->is_active);

        // Verify version history
        $versions = $template->versions;
        $this->assertCount(2, $versions);
        $this->assertEquals(2, $versions->first()->version); // Latest first
        $this->assertEquals(1, $versions->last()->version);
    }

    /** @test */
    public function it_can_import_and_export_templates()
    {
        // Create template
        $originalTemplate = Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Export Template',
            'template_data' => [
                'phases' => [
                    [
                        'name' => 'Export Phase',
                        'duration_days' => 7,
                        'tasks' => [
                            [
                                'name' => 'Export Task',
                                'duration_days' => 3,
                                'priority' => 'medium',
                                'estimated_hours' => 24
                            ]
                        ]
                    ]
                ]
            ],
            'tags' => ['export', 'test'],
            'metadata' => ['source' => 'manual']
        ]);

        // Export template
        $exportData = $this->importExportService->exportTemplate($originalTemplate);

        // Verify export structure
        $this->assertArrayHasKey('template_export', $exportData);
        $this->assertArrayHasKey('template', $exportData);
        $this->assertEquals('Export Template', $exportData['template']['name']);
        $this->assertEquals(['export', 'test'], $exportData['template']['tags']);
        $this->assertEquals(['source' => 'manual'], $exportData['template']['metadata']);

        // Create new tenant and user for import
        $newTenant = Tenant::factory()->create(['id' => 'new-tenant-1']);
        $newUser = User::factory()->create([
            'id' => 'new-user-1',
            'tenant_id' => $newTenant->id,
            'email' => 'new@example.com',
            'password' => bcrypt('password'),
        ]);

        // Import template
        $importedTemplate = $this->importExportService->importTemplate($exportData, $newUser->id, $newTenant->id);

        // Verify import
        $this->assertInstanceOf(Template::class, $importedTemplate);
        $this->assertEquals('Export Template (Imported)', $importedTemplate->name);
        $this->assertEquals($newTenant->id, $importedTemplate->tenant_id);
        $this->assertEquals($newUser->id, $importedTemplate->created_by);
        $this->assertEquals(Template::STATUS_DRAFT, $importedTemplate->status);
        $this->assertFalse($importedTemplate->is_public);
        $this->assertEquals(1, $importedTemplate->version);

        // Verify template data
        $this->assertEquals('Export Phase', $importedTemplate->template_data['phases'][0]['name']);
        $this->assertEquals('Export Task', $importedTemplate->template_data['phases'][0]['tasks'][0]['name']);

        // Verify metadata includes import information
        $this->assertArrayHasKey('imported_at', $importedTemplate->metadata);
        $this->assertArrayHasKey('imported_from', $importedTemplate->metadata);
        $this->assertArrayHasKey('original_name', $importedTemplate->metadata);
        $this->assertEquals('Export Template', $importedTemplate->metadata['original_name']);
    }

    /** @test */
    public function it_can_share_templates()
    {
        // Create template
        $template = Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'is_public' => false
        ]);

        // Create another user
        $otherUser = User::factory()->create([
            'id' => 'other-user-1',
            'tenant_id' => $this->tenant->id,
            'email' => 'other@example.com',
            'password' => bcrypt('password'),
        ]);

        // Make template public
        $publicTemplate = $this->sharingService->makePublic($template, $this->user->id);

        $this->assertTrue($publicTemplate->is_public);
        $this->assertEquals(Template::STATUS_PUBLISHED, $publicTemplate->status);

        // Share template with specific user
        $sharedTemplates = $this->sharingService->shareWithUsers($template, [$otherUser->id], $this->user->id);

        $this->assertCount(1, $sharedTemplates);
        $this->assertEquals('other-user-1', $sharedTemplates[0]->created_by);
        $this->assertStringContainsString('(Shared)', $sharedTemplates[0]->name);
        $this->assertArrayHasKey('shared_by', $sharedTemplates[0]->metadata);
        $this->assertEquals($this->user->id, $sharedTemplates[0]->metadata['shared_by']);

        // Get shared templates for user
        $userSharedTemplates = $this->sharingService->getSharedTemplates($otherUser->id, $this->tenant->id);
        $this->assertCount(1, $userSharedTemplates);
        $this->assertEquals($sharedTemplates[0]->id, $userSharedTemplates->first()->id);

        // Get sharing analytics
        $analytics = $this->sharingService->getTemplateSharingAnalytics($template);
        $this->assertArrayHasKey('total_shares', $analytics);
        $this->assertArrayHasKey('unique_recipients', $analytics);
        $this->assertEquals(1, $analytics['total_shares']);
        $this->assertEquals(1, $analytics['unique_recipients']);
    }

    /** @test */
    public function it_can_get_comprehensive_analytics()
    {
        // Create templates with different characteristics
        Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'category' => Template::CATEGORY_PROJECT,
            'status' => Template::STATUS_PUBLISHED,
            'is_public' => true,
            'usage_count' => 15,
            'created_at' => now()->subDays(5)
        ]);

        Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'category' => Template::CATEGORY_TASK,
            'status' => Template::STATUS_DRAFT,
            'is_public' => false,
            'usage_count' => 5,
            'created_at' => now()->subDays(2)
        ]);

        Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'category' => Template::CATEGORY_WORKFLOW,
            'status' => Template::STATUS_ARCHIVED,
            'is_public' => false,
            'usage_count' => 0,
            'created_at' => now()->subDays(10)
        ]);

        // Get analytics
        $analytics = $this->templateService->getTemplateAnalytics($this->tenant->id);

        // Verify analytics structure
        $this->assertArrayHasKey('total_templates', $analytics);
        $this->assertArrayHasKey('published_templates', $analytics);
        $this->assertArrayHasKey('draft_templates', $analytics);
        $this->assertArrayHasKey('archived_templates', $analytics);
        $this->assertArrayHasKey('public_templates', $analytics);
        $this->assertArrayHasKey('total_usage', $analytics);
        $this->assertArrayHasKey('most_used_template', $analytics);
        $this->assertArrayHasKey('categories', $analytics);
        $this->assertArrayHasKey('recent_templates', $analytics);
        $this->assertArrayHasKey('popular_templates', $analytics);

        // Verify analytics values
        $this->assertEquals(3, $analytics['total_templates']);
        $this->assertEquals(1, $analytics['published_templates']);
        $this->assertEquals(1, $analytics['draft_templates']);
        $this->assertEquals(1, $analytics['archived_templates']);
        $this->assertEquals(1, $analytics['public_templates']);
        $this->assertEquals(20, $analytics['total_usage']);

        // Verify categories breakdown
        $this->assertArrayHasKey(Template::CATEGORY_PROJECT, $analytics['categories']);
        $this->assertArrayHasKey(Template::CATEGORY_TASK, $analytics['categories']);
        $this->assertArrayHasKey(Template::CATEGORY_WORKFLOW, $analytics['categories']);
        $this->assertEquals(1, $analytics['categories'][Template::CATEGORY_PROJECT]);
        $this->assertEquals(1, $analytics['categories'][Template::CATEGORY_TASK]);
        $this->assertEquals(1, $analytics['categories'][Template::CATEGORY_WORKFLOW]);

        // Verify most used template
        $this->assertNotNull($analytics['most_used_template']);
        $this->assertEquals(15, $analytics['most_used_template']->usage_count);

        // Verify recent templates (should be ordered by created_at desc)
        $this->assertCount(3, $analytics['recent_templates']);
        $this->assertEquals(Template::CATEGORY_TASK, $analytics['recent_templates']->first()->category);

        // Verify popular templates (should be ordered by usage_count desc)
        $this->assertCount(3, $analytics['popular_templates']);
        $this->assertEquals(15, $analytics['popular_templates']->first()->usage_count);
    }

    /** @test */
    public function it_respects_tenant_isolation_across_all_operations()
    {
        $otherTenant = Tenant::factory()->create(['id' => 'other-tenant-1']);
        $otherUser = User::factory()->create([
            'id' => 'other-user-1',
            'tenant_id' => $otherTenant->id,
            'email' => 'other@example.com',
            'password' => bcrypt('password'),
        ]);

        // Create templates for both tenants
        $template1 = Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Tenant 1 Template'
        ]);

        $template2 = Template::factory()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Tenant 2 Template'
        ]);

        // Verify tenant isolation in search
        $tenant1Templates = $this->templateService->searchTemplates($this->tenant->id);
        $tenant2Templates = $this->templateService->searchTemplates($otherTenant->id);

        $this->assertCount(1, $tenant1Templates);
        $this->assertCount(1, $tenant2Templates);
        $this->assertEquals($this->tenant->id, $tenant1Templates->first()->tenant_id);
        $this->assertEquals($otherTenant->id, $tenant2Templates->first()->tenant_id);

        // Verify tenant isolation in analytics
        $tenant1Analytics = $this->templateService->getTemplateAnalytics($this->tenant->id);
        $tenant2Analytics = $this->templateService->getTemplateAnalytics($otherTenant->id);

        $this->assertEquals(1, $tenant1Analytics['total_templates']);
        $this->assertEquals(1, $tenant2Analytics['total_templates']);

        // Verify tenant isolation in sharing
        $tenant1SharedTemplates = $this->sharingService->getSharedTemplates($this->user->id, $this->tenant->id);
        $tenant2SharedTemplates = $this->sharingService->getSharedTemplates($otherUser->id, $otherTenant->id);

        $this->assertCount(0, $tenant1SharedTemplates);
        $this->assertCount(0, $tenant2SharedTemplates);
    }
}
