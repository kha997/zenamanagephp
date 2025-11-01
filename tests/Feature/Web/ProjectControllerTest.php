<?php

namespace Tests\Feature\Web;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use App\Models\Project;

class ProjectControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test user
        $this->user = User::factory()->create([
            'tenant_id' => 'test-tenant-1',
            'email' => 'test@example.com',
            'password' => bcrypt('password')
        ]);

        // Mock API responses
        Http::fake([
            '*/api/projects*' => Http::response([
                'success' => true,
                'data' => [
                    'id' => 1,
                    'name' => 'Test Project',
                    'code' => 'TEST-001',
                    'description' => 'Test description',
                    'status' => 'planning',
                    'priority' => 'medium',
                    'start_date' => '2025-01-01',
                    'end_date' => '2025-12-31',
                    'client_id' => 1,
                    'client_name' => 'Test Client',
                    'project_manager_id' => 1,
                    'project_manager_name' => 'Test Manager'
                ]
            ], 200),
            '*/api/tasks*' => Http::response([
                'success' => true,
                'data' => [
                    ['id' => 1, 'name' => 'Test Task', 'status' => 'pending']
                ]
            ], 200),
            '*/api/documents*' => Http::response([
                'success' => true,
                'data' => [
                    ['id' => 1, 'name' => 'Test Document']
                ]
            ], 200),
            '*/api/clients*' => Http::response([
                'success' => true,
                'data' => [
                    ['id' => 1, 'name' => 'Test Client']
                ]
            ], 200),
            '*/api/team*' => Http::response([
                'success' => true,
                'data' => [
                    ['id' => 1, 'name' => 'Test Manager']
                ]
            ], 200)
        ]);
    }

    /** @test */
    public function it_can_display_projects_index()
    {
        $this->actingAs($this->user)
            ->get('/app/projects')
            ->assertStatus(200)
            ->assertViewIs('app.projects.index');
    }

    /** @test */
    public function it_can_display_create_project_form()
    {
        $this->actingAs($this->user)
            ->get('/app/projects/create')
            ->assertStatus(200)
            ->assertViewIs('app.projects.create');
    }

    /** @test */
    public function it_can_create_project_with_valid_data()
    {
        $projectData = [
            'name' => 'Test Project',
            'code' => 'TEST-001',
            'description' => 'Test project description',
            'status' => 'planning',
            'priority' => 'medium',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31'
        ];

        $this->actingAs($this->user)
            ->post('/app/projects', $projectData)
            ->assertRedirect(route('app.projects.index'))
            ->assertSessionHas('success', 'Project created successfully!');

        // Verify API was called
        Http::assertSent(function ($request) {
            return $request->url() === config('app.url') . '/api/projects' &&
                   $request->method() === 'POST';
        });
    }

    /** @test */
    public function it_validates_required_fields_when_creating_project()
    {
        $this->actingAs($this->user)
            ->post('/app/projects', [])
            ->assertSessionHasErrors(['name', 'code']);
    }

    /** @test */
    public function it_can_display_project_details()
    {
        $this->actingAs($this->user)
            ->get('/app/projects/1')
            ->assertStatus(200)
            ->assertViewIs('app.projects.show')
            ->assertViewHas('project');

        // Verify API was called
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/projects/1') &&
                   $request->method() === 'GET';
        });
    }

    /** @test */
    public function it_can_display_edit_project_form()
    {
        $this->actingAs($this->user)
            ->get('/app/projects/1/edit')
            ->assertStatus(200)
            ->assertViewIs('app.projects.edit')
            ->assertViewHas('project');
    }

    /** @test */
    public function it_can_update_project_with_valid_data()
    {
        $updateData = [
            'name' => 'Updated Project Name',
            'code' => 'UPD-001',
            'description' => 'Updated description',
            'status' => 'active'
        ];

        $this->actingAs($this->user)
            ->put('/app/projects/1', $updateData)
            ->assertRedirect(route('app.projects.index'))
            ->assertSessionHas('success', 'Project updated successfully!');

        // Verify API was called
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/projects/1') &&
                   $request->method() === 'PUT';
        });
    }

    /** @test */
    public function it_can_delete_project()
    {
        $this->actingAs($this->user)
            ->delete('/app/projects/1')
            ->assertRedirect(route('app.projects.index'))
            ->assertSessionHas('success', 'Project deleted successfully!');

        // Verify API was called
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/projects/1') &&
                   $request->method() === 'DELETE';
        });
    }

    /** @test */
    public function it_can_set_view_mode()
    {
        $this->actingAs($this->user)
            ->post('/app/projects/view-mode', ['view_mode' => 'card'])
            ->assertRedirect();

        $this->assertEquals('card', session('projects_view_mode'));
    }

    /** @test */
    public function it_rejects_invalid_view_mode()
    {
        $this->actingAs($this->user)
            ->post('/app/projects/view-mode', ['view_mode' => 'invalid'])
            ->assertRedirect();

        $this->assertNotEquals('invalid', session('projects_view_mode'));
    }

    /** @test */
    public function it_redirects_unauthenticated_users_to_login()
    {
        $this->get('/app/projects')
            ->assertRedirect('/login');
    }

    /** @test */
    public function it_handles_project_not_found()
    {
        Http::fake([
            '*/api/projects/999999*' => Http::response([
                'success' => false,
                'error' => ['message' => 'Project not found']
            ], 404)
        ]);

        $this->actingAs($this->user)
            ->get('/app/projects/999999')
            ->assertStatus(404);
    }

    /** @test */
    public function it_maps_api_response_to_object_correctly()
    {
        $response = $this->actingAs($this->user)
            ->get('/app/projects/1')
            ->assertStatus(200);

        // Get the view data from the response
        $viewData = $response->viewData('project');
        
        // Verify the mapper created proper object structure
        $this->assertNotNull($viewData);
        $this->assertEquals('Test Project', $viewData->name);
        $this->assertEquals('TEST-001', $viewData->code);
        
        // Verify fake relationships were created
        $this->assertNotNull($viewData->client);
        $this->assertEquals('Test Client', $viewData->client->name);
        
        $this->assertNotNull($viewData->projectManager);
        $this->assertEquals('Test Manager', $viewData->projectManager->name);
        
        // Verify date objects were created
        $this->assertInstanceOf(\Carbon\Carbon::class, $viewData->start_date);
        $this->assertInstanceOf(\Carbon\Carbon::class, $viewData->end_date);
    }
}
