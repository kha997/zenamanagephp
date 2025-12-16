<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tenant;
use App\Models\TemplateSet;
use App\Models\TemplatePhase;
use App\Models\TemplateDiscipline;
use App\Models\TemplateTask;
use App\Models\TemplateTaskDependency;
use App\Models\TemplatePreset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Helpers\AuthHelper;

/**
 * AdminTemplateImportTest
 * 
 * Feature tests for admin template import functionality.
 * Tests CSV/JSON import, validation, tenant isolation.
 */
class AdminTemplateImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $tenantUser;
    protected Tenant $tenantA;
    protected Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable feature flag
        config(['features.tasks.enable_wbs_templates' => true]);

        // Create tenants
        $this->tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
        $this->tenantB = Tenant::factory()->create(['name' => 'Tenant B']);

        // Create super-admin user
        $this->admin = User::factory()->create([
            'tenant_id' => null,
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->admin->assignRole('super_admin');

        // Create tenant user
        $this->tenantUser = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'email' => 'user@tenant-a.com',
            'password' => Hash::make('password'),
        ]);
    }

    /** @test */
    public function admin_can_import_template_from_json()
    {
        $this->actingAs($this->admin);

        $jsonContent = json_encode([
            'set' => [
                'code' => 'TEST-TEMPLATE',
                'name' => 'Test Template',
                'version' => '1.0',
            ],
            'phases' => [
                ['code' => 'PHASE1', 'name' => 'Phase 1', 'order' => 1],
            ],
            'disciplines' => [
                ['code' => 'DISC1', 'name' => 'Discipline 1', 'order' => 1],
            ],
            'tasks' => [
                [
                    'code' => 'TASK1',
                    'name' => 'Task 1',
                    'phase' => 'PHASE1',
                    'discipline' => 'DISC1',
                    'order' => 1,
                    'depends_on' => [],
                ],
            ],
            'presets' => [],
        ]);

        $file = UploadedFile::fake()->createWithContent('template.json', $jsonContent);

        $response = $this->post('/admin/templates/import', [
            'file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('template_sets', [
            'code' => 'TEST-TEMPLATE',
            'name' => 'Test Template',
        ]);

        $set = TemplateSet::where('code', 'TEST-TEMPLATE')->first();
        $this->assertNotNull($set);
        $this->assertEquals(1, $set->phases()->count());
        $this->assertEquals(1, $set->disciplines()->count());
        $this->assertEquals(1, $set->tasks()->count());
    }

    /** @test */
    public function admin_can_import_template_from_csv()
    {
        $this->actingAs($this->admin);

        $csvContent = "phase_code,phase_name,discipline_code,discipline_name,color_hex,task_code,task_name,description,est_duration_days,role_key,deliverable_type,order_index,is_optional,depends_on_codes\n";
        $csvContent .= "PHASE1,Phase 1,DISC1,Discipline 1,#1E88E5,TASK1,Task 1,Test task,3,lead_architect,layout_dwg,1,false,\n";

        $file = UploadedFile::fake()->createWithContent('template.csv', $csvContent);

        $response = $this->post('/admin/templates/import', [
            'file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('template_sets', [
            'code' => 'WBS-IMPORT-' . date('YmdHis'),
        ]);
    }

    /** @test */
    public function tenant_user_cannot_import_templates()
    {
        $this->actingAs($this->tenantUser);

        $jsonContent = json_encode([
            'set' => ['code' => 'TEST', 'name' => 'Test'],
            'phases' => [],
            'disciplines' => [],
            'tasks' => [],
            'presets' => [],
        ]);

        $file = UploadedFile::fake()->createWithContent('template.json', $jsonContent);

        $response = $this->post('/admin/templates/import', [
            'file' => $file,
        ]);

        $response->assertForbidden();
    }

    /** @test */
    public function tenant_isolation_prevents_access_to_other_tenant_templates()
    {
        $this->actingAs($this->admin);

        // Create template for tenant A
        $setA = TemplateSet::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'TENANT-A-TEMPLATE',
            'created_by' => $this->admin->id,
        ]);

        // Create template for tenant B
        $setB = TemplateSet::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'code' => 'TENANT-B-TEMPLATE',
            'created_by' => $this->admin->id,
        ]);

        // Tenant A user should only see their template and global templates
        $this->actingAs($this->tenantUser);

        $response = $this->getJson('/api/v1/app/template-sets');

        $response->assertOk();
        $data = $response->json('data');
        
        $codes = collect($data)->pluck('code')->toArray();
        $this->assertContains('TENANT-A-TEMPLATE', $codes);
        $this->assertNotContains('TENANT-B-TEMPLATE', $codes);
    }

    /** @test */
    public function global_templates_are_accessible_to_all_tenants()
    {
        $this->actingAs($this->admin);

        // Create global template
        $globalSet = TemplateSet::factory()->create([
            'tenant_id' => null,
            'code' => 'GLOBAL-TEMPLATE',
            'is_global' => true,
            'created_by' => $this->admin->id,
        ]);

        // Tenant A user should see global template
        $this->actingAs($this->tenantUser);

        $response = $this->getJson('/api/v1/app/template-sets');

        $response->assertOk();
        $data = $response->json('data');
        
        $codes = collect($data)->pluck('code')->toArray();
        $this->assertContains('GLOBAL-TEMPLATE', $codes);
    }

    /** @test */
    public function import_validates_required_fields()
    {
        $this->actingAs($this->admin);

        $jsonContent = json_encode([
            'set' => [
                // Missing code and name
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('template.json', $jsonContent);

        $response = $this->post('/admin/templates/import', [
            'file' => $file,
        ]);

        $response->assertSessionHasErrors();
    }

    /** @test */
    public function import_handles_duplicate_task_codes()
    {
        $this->actingAs($this->admin);

        $jsonContent = json_encode([
            'set' => [
                'code' => 'TEST',
                'name' => 'Test',
                'version' => '1.0',
            ],
            'phases' => [
                ['code' => 'PHASE1', 'name' => 'Phase 1', 'order' => 1],
            ],
            'disciplines' => [
                ['code' => 'DISC1', 'name' => 'Discipline 1', 'order' => 1],
            ],
            'tasks' => [
                [
                    'code' => 'TASK1',
                    'name' => 'Task 1',
                    'phase' => 'PHASE1',
                    'discipline' => 'DISC1',
                ],
                [
                    'code' => 'TASK1', // Duplicate
                    'name' => 'Task 1 Duplicate',
                    'phase' => 'PHASE1',
                    'discipline' => 'DISC1',
                ],
            ],
            'presets' => [],
        ]);

        $file = UploadedFile::fake()->createWithContent('template.json', $jsonContent);

        $response = $this->post('/admin/templates/import', [
            'file' => $file,
        ]);

        $response->assertSessionHasErrors();
    }
}

