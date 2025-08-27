<?php declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\WorkTemplate\Models\Template;
use Src\WorkTemplate\Models\TemplateVersion;
use Database\Factories\TemplateFactory;

class TemplateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test template creation với valid data
     */
    public function test_can_create_template_with_valid_data(): void
    {
        $templateData = [
            'template_name' => 'Test Template',
            'category' => 'Design',
            'json_body' => [
                'template_name' => 'Test Template',
                'phases' => [
                    [
                        'name' => 'Phase 1',
                        'tasks' => [
                            [
                                'name' => 'Task 1',
                                'duration_days' => 5,
                                'role' => 'Developer',
                                'contract_value_percent' => 10.0,
                            ],
                        ],
                    ],
                ],
            ],
            'version' => 1,
            'is_active' => true,
        ];

        $template = Template::create($templateData);

        $this->assertInstanceOf(Template::class, $template);
        $this->assertEquals('Test Template', $template->template_name);
        $this->assertEquals('Design', $template->category);
        $this->assertTrue($template->is_active);
        $this->assertEquals(1, $template->version);
        $this->assertIsArray($template->json_body);
    }

    /**
     * Test validateJsonStructure method với valid JSON
     */
    public function test_validate_json_structure_with_valid_data(): void
    {
        $template = new Template();
        
        $validJson = [
            'template_name' => 'Valid Template',
            'phases' => [
                [
                    'name' => 'Phase 1',
                    'tasks' => [
                        [
                            'name' => 'Task 1',
                            'duration_days' => 5,
                            'role' => 'Developer',
                            'contract_value_percent' => 10.0,
                        ],
                    ],
                ],
            ],
        ];

        $this->assertTrue($template->validateJsonStructure($validJson));
    }

    /**
     * Test validateJsonStructure method với invalid JSON
     */
    public function test_validate_json_structure_with_invalid_data(): void
    {
        $template = new Template();
        
        // Test thiếu template_name
        $invalidJson1 = [
            'phases' => [
                [
                    'name' => 'Phase 1',
                    'tasks' => [],
                ],
            ],
        ];
        $this->assertFalse($template->validateJsonStructure($invalidJson1));

        // Test thiếu phases
        $invalidJson2 = [
            'template_name' => 'Invalid Template',
        ];
        $this->assertFalse($template->validateJsonStructure($invalidJson2));

        // Test phases không phải array
        $invalidJson3 = [
            'template_name' => 'Invalid Template',
            'phases' => 'not an array',
        ];
        $this->assertFalse($template->validateJsonStructure($invalidJson3));

        // Test phase thiếu name
        $invalidJson4 = [
            'template_name' => 'Invalid Template',
            'phases' => [
                [
                    'tasks' => [],
                ],
            ],
        ];
        $this->assertFalse($template->validateJsonStructure($invalidJson4));

        // Test task thiếu required fields
        $invalidJson5 = [
            'template_name' => 'Invalid Template',
            'phases' => [
                [
                    'name' => 'Phase 1',
                    'tasks' => [
                        [
                            'name' => 'Task 1',
                            // Thiếu duration_days, role, contract_value_percent
                        ],
                    ],
                ],
            ],
        ];
        $this->assertFalse($template->validateJsonStructure($invalidJson5));
    }

    /**
     * Test createNewVersion method
     */
    public function test_create_new_version(): void
    {
        $template = Template::factory()->create([
            'version' => 1,
        ]);

        $newJsonBody = [
            'template_name' => 'Updated Template',
            'phases' => [
                [
                    'name' => 'Updated Phase',
                    'tasks' => [
                        [
                            'name' => 'Updated Task',
                            'duration_days' => 10,
                            'role' => 'Senior Developer',
                            'contract_value_percent' => 15.0,
                        ],
                    ],
                ],
            ],
        ];

        $templateVersion = $template->createNewVersion(
            $newJsonBody,
            'Updated template with new requirements',
            'user123'
        );

        // Kiểm tra template version được tạo
        $this->assertInstanceOf(TemplateVersion::class, $templateVersion);
        $this->assertEquals(2, $templateVersion->version);
        $this->assertEquals($newJsonBody, $templateVersion->json_body);
        $this->assertEquals('Updated template with new requirements', $templateVersion->note);
        $this->assertEquals('user123', $templateVersion->created_by);

        // Kiểm tra template được cập nhật
        $template->refresh();
        $this->assertEquals(2, $template->version);
        $this->assertEquals($newJsonBody, $template->json_body);
        $this->assertEquals('user123', $template->updated_by);
    }

    /**
     * Test getTotalTasksAttribute accessor
     */
    public function test_get_total_tasks_attribute(): void
    {
        $template = Template::factory()->withComplexStructure()->create();
        
        $expectedTotal = 0;
        foreach ($template->json_body['phases'] as $phase) {
            $expectedTotal += count($phase['tasks']);
        }

        $this->assertEquals($expectedTotal, $template->total_tasks);
    }

    /**
     * Test getEstimatedDurationAttribute accessor
     */
    public function test_get_estimated_duration_attribute(): void
    {
        $jsonBody = [
            'template_name' => 'Duration Test Template',
            'phases' => [
                [
                    'name' => 'Phase 1',
                    'tasks' => [
                        ['name' => 'Task 1', 'duration_days' => 5, 'role' => 'Dev', 'contract_value_percent' => 10],
                        ['name' => 'Task 2', 'duration_days' => 3, 'role' => 'Dev', 'contract_value_percent' => 10],
                    ],
                ],
                [
                    'name' => 'Phase 2',
                    'tasks' => [
                        ['name' => 'Task 3', 'duration_days' => 8, 'role' => 'Dev', 'contract_value_percent' => 10],
                        ['name' => 'Task 4', 'duration_days' => 2, 'role' => 'Dev', 'contract_value_percent' => 10],
                    ],
                ],
            ],
        ];

        $template = Template::factory()->create(['json_body' => $jsonBody]);
        
        // Phase 1: max(5, 3) = 5 days
        // Phase 2: max(8, 2) = 8 days
        // Total: 5 + 8 = 13 days
        $this->assertEquals(13, $template->estimated_duration);
    }

    /**
     * Test active scope
     */
    public function test_active_scope(): void
    {
        Template::factory()->create(['is_active' => true]);
        Template::factory()->create(['is_active' => true]);
        Template::factory()->inactive()->create();

        $activeTemplates = Template::active()->get();
        
        $this->assertCount(2, $activeTemplates);
        $this->assertTrue($activeTemplates->every(fn($template) => $template->is_active));
    }

    /**
     * Test byCategory scope
     */
    public function test_by_category_scope(): void
    {
        Template::factory()->withCategory('Design')->create();
        Template::factory()->withCategory('Design')->create();
        Template::factory()->withCategory('Construction')->create();

        $designTemplates = Template::byCategory('Design')->get();
        
        $this->assertCount(2, $designTemplates);
        $this->assertTrue($designTemplates->every(fn($template) => $template->category === 'Design'));
    }

    /**
     * Test relationships
     */
    public function test_template_relationships(): void
    {
        $template = Template::factory()->create();

        // Test versions relationship
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $template->versions());
        
        // Test projectPhases relationship
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $template->projectPhases());
        
        // Test projectTasks relationship
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $template->projectTasks());
    }

    /**
     * Test template với empty phases
     */
    public function test_template_with_empty_phases(): void
    {
        $jsonBody = [
            'template_name' => 'Empty Template',
            'phases' => [],
        ];

        $template = Template::factory()->create(['json_body' => $jsonBody]);
        
        $this->assertEquals(0, $template->total_tasks);
        $this->assertEquals(0, $template->estimated_duration);
    }

    /**
     * Test template constants
     */
    public function test_template_categories_constant(): void
    {
        $expectedCategories = ['Design', 'Construction', 'QC', 'Inspection'];
        
        $this->assertEquals($expectedCategories, Template::CATEGORIES);
    }
}