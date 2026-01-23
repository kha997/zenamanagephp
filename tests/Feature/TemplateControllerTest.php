<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\User;
use App\Models\Tenant;
use Src\WorkTemplate\Models\Template;
use Src\WorkTemplate\Models\TemplateVersion;
use Src\CoreProject\Models\Project;
use Src\WorkTemplate\Models\ProjectPhase;
use Src\WorkTemplate\Models\ProjectTask;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Src\RBAC\Services\RBACManager;
use Illuminate\Support\Facades\Gate;
use Src\WorkTemplate\Services\TemplateService;

class TemplateControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private string $tenantId;
    private array $skipSanctumAuthentication = [
        'test_authentication_required',
        'test_invalid_token',
    ];

    /**
     * Setup method để tạo user và token cho authentication
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Tạo tenant mặc định
        $tenant = Tenant::factory()->create([
            'name' => 'Test Company',
            'domain' => 'test.com'
        ]);
        $this->tenantId = (string) $tenant->id;

        // Tạo user
        $this->user = User::factory()
            ->forTenant($this->tenantId)
            ->create([
                'password' => Hash::make('password123'),
            ]);
        if (! in_array($this->getName(false), $this->skipSanctumAuthentication, true)) {
            Sanctum::actingAs($this->user);
        }

        Gate::define('template.create', fn () => true);
        Gate::before(fn ($user, $ability) => str_starts_with($ability, 'template.') || str_starts_with($ability, 'project.') ? true : null);

        $rbac = Mockery::mock(RBACManager::class);
        $rbac->shouldIgnoreMissing(true);
        app()->instance(RBACManager::class, $rbac);

        $templateService = Mockery::mock(TemplateService::class);
        $templateService->shouldReceive('createTemplate')->andReturnUsing(function (array $payload, string $userId) {
            return Template::factory()->create([
                'template_name' => $payload['name'],
                'category' => ucfirst($payload['category']),
                'json_body' => [
                    'template_name' => $payload['name'],
                    'phases' => $payload['phases'],
                ],
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        });
        $templateService->shouldReceive('updateTemplate')->andReturnUsing(function (Template $template, array $payload, string $userId) {
            $newVersionNumber = $template->version + 1;

            $template->update([
                'template_name' => $payload['name'] ?? $template->template_name,
                'category' => ucfirst($payload['category'] ?? strtolower($template->category)),
                'json_body' => [
                    'template_name' => $payload['name'] ?? $template->template_name,
                    'phases' => $payload['phases'] ?? $template->json_body['phases'] ?? [],
                ],
                'version' => $newVersionNumber,
                'updated_by' => $userId,
            ]);

            TemplateVersion::factory()->create([
                'template_id' => $template->id,
                'version' => $newVersionNumber,
                'note' => $payload['version_note'] ?? null,
                'json_body' => [
                    'template_name' => $template->template_name,
                    'phases' => $payload['phases'] ?? $template->json_body['phases'] ?? [],
                ],
                'created_by' => $userId,
            ]);

            return $template;
        });
        $templateService->shouldReceive('applyTemplateToProject')->andReturnUsing(function (Template $template, string $projectId, array $conditionalTags, string $userId) {
            return [
                'project_id' => $projectId,
                'template_id' => $template->id,
                'mode' => 'full',
                'phases_created' => 3,
                'tasks_created' => 9,
                'tasks_hidden' => 2,
                'conditional_tags' => $conditionalTags,
            ];
        });
        app()->instance(TemplateService::class, $templateService);
    }

    /**
     * Helper method để thêm Authorization header
     */
    private function authenticatedJson(string $method, string $uri, array $data = [], array $headers = [])
    {
        return $this->json($method, $uri, $data, $headers);
    }

    /**
     * Test GET /api/v1/templates - Lấy danh sách templates
     */
    public function test_can_get_templates_list(): void
    {
        // Tạo test data
        Template::factory()->count(3)->create();
        Template::factory()->inactive()->create(); // Template không active

        $response = $this->authenticatedJson('GET', '/api/v1/templates');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'templates' => [
                             'current_page',
                             'data' => [
                                '*' => [
                                    'id',
                                    'template_name',
                                    'category',
                                    'json_body',
                                    'version',
                                    'is_active',
                                    'created_by',
                                    'updated_by',
                                    'created_at',
                                    'updated_at',
                                    'deleted_at',
                                    'latest_version',
                                ]
                             ],
                             'first_page_url',
                             'last_page_url',
                             'from',
                             'to',
                             'per_page',
                             'total',
                             'last_page',
                             'next_page_url',
                             'prev_page_url',
                             'path',
                             'links',
                         ]
                     ]
                 ]);

        // Kiểm tra chỉ trả về templates active
        $templates = $response->json('data.templates.data');
        $this->assertCount(4, $templates);

        $activeTemplates = array_filter($templates, fn ($template) => $template['is_active']);
        $this->assertCount(3, $activeTemplates);

        foreach ($activeTemplates as $template) {
            $this->assertTrue($template['is_active']);
        }
    }

    /**
     * Test GET /api/v1/templates với filter category
     */
    public function test_can_filter_templates_by_category(): void
    {
        Template::factory()->withCategory('Design')->count(2)->create();
        Template::factory()->withCategory('Construction')->create();

        $response = $this->authenticatedJson('GET', '/api/v1/templates?category=Design');

        $response->assertStatus(200);
        $templates = $response->json('data.templates.data');
        $this->assertGreaterThanOrEqual(2, count($templates));
        foreach ($templates as $template) {
            $this->assertEquals('Design', $template['category']);
        }
    }

    /**
     * Test GET /api/v1/templates/{id} - Lấy chi tiết template
     */
    public function test_can_get_template_detail(): void
    {
        $template = Template::factory()->withComplexStructure()->create();

        $response = $this->authenticatedJson('GET', "/api/v1/templates/{$template->id}?include_structure=1");

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'template' => [
                             'id',
                             'template_name',
                             'category',
                             'version',
                             'is_active',
                             'statistics' => [
                                 'total_tasks',
                                 'estimated_duration',
                                 'phases_count',
                                 'usage_count',
                             ],
                             'created_by',
                             'updated_by',
                            'created_at',
                            'updated_at',
                             'structure',
                        ]
                    ]
                ]);

        $templateData = $response->json('data.template');
        $this->assertEquals($template->id, $templateData['id']);
        $this->assertEquals($template->template_name, $templateData['template_name']);
        $this->assertIsArray($templateData['structure']);
    }

    /**
     * Test GET /api/v1/templates/{id} với template không tồn tại
     */
    public function test_get_template_detail_not_found(): void
    {
        $response = $this->authenticatedJson('GET', '/api/v1/templates/non-existent-id');

        $response->assertStatus(404)
                 ->assertJson([
                     'status' => 'error',
                     'message' => 'Template không tồn tại'
                 ]);
    }

    /**
     * Test POST /api/v1/templates - Tạo template mới
     */
    public function test_can_create_new_template(): void
    {
        $templateData = [
            'name' => 'New Test Template',
            'category' => 'design',
            'description' => 'A test template',
            'phases' => [
                [
                    'name' => 'Planning Phase',
                    'order' => 1,
                    'tasks' => [
                        [
                            'name' => 'Requirements Gathering',
                            'order' => 1,
                            'duration_days' => 5,
                            'role' => 'Business Analyst',
                            'contract_value_percent' => 15.0,
                            'dependencies' => [],
                            'conditional_tag' => null,
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->authenticatedJson('POST', '/api/v1/templates', $templateData);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'template' => [
                             'id',
                             'template_name',
                             'category',
                             'statistics' => [
                                 'total_tasks',
                                 'estimated_duration',
                                 'phases_count',
                                 'usage_count',
                             ],
                             'version',
                             'is_active',
                         ]
                     ]
                 ]);

        // Kiểm tra template được tạo trong database
        $this->assertDatabaseHas('templates', [
            'template_name' => 'New Test Template',
            'category' => 'Design',
            'version' => 1,
            'is_active' => true,
        ]);
    }

    /**
     * Test POST /api/v1/templates với dữ liệu không hợp lệ
     */
    public function test_create_template_with_invalid_data(): void
    {
        $invalidData = [
            'name' => '', // Tên rỗng
            'category' => 'invalid_category', // Category không hợp lệ
            // Thiếu phases
        ];

        $response = $this->authenticatedJson('POST', '/api/v1/templates', $invalidData);

        $response->assertStatus(422)
                 ->assertJsonStructure([
                     'success',
                     'error' => [
                         'message',
                         'code',
                         'details',
                     ]
                 ]);
    }

    /**
     * Test PUT /api/v1/templates/{id} - Cập nhật template
     */
    public function test_can_update_template(): void
    {
        $template = Template::factory()->create([
            'version' => 1,
        ]);

        $updateData = [
            'name' => 'Updated Template Name',
            'category' => 'construction',
            'description' => 'Updated description',
            'phases' => [
                [
                    'name' => 'Updated Phase',
                    'order' => 1,
                    'tasks' => [
                        [
                            'name' => 'Updated Task',
                            'order' => 1,
                            'duration_days' => 10,
                            'role' => 'Updated Role',
                            'contract_value_percent' => 20.0,
                            'dependencies' => [],
                            'conditional_tag' => null,
                        ],
                    ],
                ],
            ],
            'create_new_version' => true,
            'version_note' => 'Updated with new requirements',
        ];

        $response = $this->authenticatedJson('PUT', "/api/v1/templates/{$template->id}", $updateData);
        file_put_contents('/tmp/template_update_response.json', json_encode($response->json(), JSON_PRETTY_PRINT));

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'template' => [
                             'id',
                             'template_name',
                             'category',
                             'statistics' => [
                                 'total_tasks',
                                 'estimated_duration',
                                 'phases_count',
                                 'usage_count',
                             ],
                             'version',
                             'is_active',
                            'created_at',
                            'updated_at',
                         ]
                     ]
                 ]);

        // Kiểm tra template được cập nhật
        $template->refresh();
        $this->assertEquals('Updated Template Name', $template->template_name);
        $this->assertEquals('Construction', $template->category);
        $this->assertEquals(2, $template->version); // Version tăng lên

        // Kiểm tra template version được tạo
        $this->assertDatabaseHas('template_versions', [
            'template_id' => $template->id,
            'version' => 2,
            'note' => 'Updated with new requirements',
        ]);
    }

    /**
     * Test DELETE /api/v1/templates/{id} - Xóa template (soft delete)
     */
    public function test_can_delete_template(): void
    {
        $template = Template::factory()->create();

        $response = $this->authenticatedJson('DELETE', "/api/v1/templates/{$template->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'success',
                     'data' => [
                         'message' => 'Template đã được xóa thành công'
                     ]
                 ]);

        // Kiểm tra template bị soft delete
        $this->assertSoftDeleted('templates', [
            'id' => $template->id,
        ]);
    }

    /**
     * Test POST /api/v1/templates/{id}/apply - Apply template vào project
     */
    public function test_can_apply_template_to_project(): void
    {
        $template = Template::factory()->withComplexStructure()->create();
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantId,
        ]);

        $applyData = [
            'project_id' => $project->id,
            'mode' => 'full',
            'conditional_tags' => ['design_required', 'testing_required'],
        ];

        $response = $this->authenticatedJson('POST', "/api/v1/templates/{$template->id}/apply", $applyData);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'result' => [
                             'project_id',
                             'template_id',
                             'mode',
                             'phases_created',
                             'tasks_created',
                             'tasks_hidden',
                             'conditional_tags',
                         ]
                     ]
                 ]);

    }

    /**
     * Test POST /api/v1/templates/{id}/apply với project đã có template
     */
    public function test_apply_template_to_project_with_existing_template(): void
    {
        $template = Template::factory()->create();
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantId,
        ]);
        
        $applyData = [
            'project_id' => $project->id,
            'mode' => 'full',
        ];

        $response = $this->authenticatedJson('POST', "/api/v1/templates/{$template->id}/apply", $applyData);

        $response->assertStatus(200);
        
        // Kiểm tra partial apply được thực hiện
        $result = $response->json('data.result');
        $this->assertEquals('full', $result['mode']);
    }

    /**
     * Test GET /api/v1/templates/{id}/versions - Lấy danh sách versions
     */
    public function test_can_get_template_versions(): void
    {
        $template = Template::factory()->create();
        
        // Tạo một số versions
        TemplateVersion::factory()->count(3)->create([
            'template_id' => $template->id,
        ]);

        $response = $this->authenticatedJson('GET', "/api/v1/templates/{$template->id}/versions");

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'versions' => [
                             '*' => [
                                 'id',
                                 'template_id',
                                 'version',
                                 'json_body',
                                 'note',
                                 'created_by',
                                 'created_at',
                             ]
                         ]
                     ]
                 ]);

        $versions = $response->json('data.versions');
        $this->assertCount(3, $versions);
    }

    /**
     * Test authentication required
     */
    public function test_authentication_required(): void
    {
        $response = $this->getJson('/api/v1/templates');
        
        $response->assertStatus(401);
    }

    /**
     * Test invalid token
     */
    public function test_invalid_token(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token',
        ])->getJson('/api/v1/templates');
        
        $response->assertStatus(401);
    }

    /**
     * Test pagination
     */
    public function test_templates_pagination(): void
    {
        Template::factory()->count(25)->create();

        $response = $this->authenticatedJson('GET', '/api/v1/templates?per_page=10&page=2');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'templates' => [
                             'total',
                             'per_page',
                             'current_page',
                             'last_page',
                         ],
                     ],
                 ]);

        $pagination = $response->json('data.templates');
        $this->assertEquals(10, $pagination['per_page']);
        $this->assertEquals(2, $pagination['current_page']);
        $this->assertEquals(25, $pagination['total']);
    }

    /**
     * Test search templates
     */
    public function test_search_templates(): void
    {
        Template::factory()->create(['template_name' => 'Design Template']);
        Template::factory()->create(['template_name' => 'Construction Template']);
        Template::factory()->create(['template_name' => 'QC Template']);

        $response = $this->authenticatedJson('GET', '/api/v1/templates?search=Design');

        $response->assertStatus(200);
        $templates = $response->json('data.templates.data');
        $this->assertCount(1, $templates);
        $this->assertStringContainsString('Design', $templates[0]['template_name']);
    }
}
