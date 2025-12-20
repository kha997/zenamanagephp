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
use Tests\TestCase;
use Tests\Traits\SeedsAdminRolesTrait;

/**
 * AdminTemplateImportTest
 * 
 * Feature tests for admin template import functionality.
 * Tests CSV/JSON import, validation, tenant isolation.
 */
class AdminTemplateImportTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAdminRolesTrait;

    protected User $admin;
    protected User $tenantUser;
    protected Tenant $tenantA;
    protected Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable feature flag
        config(['features.tasks.enable_wbs_templates' => true]);

        // Ensure admin roles/permissions exist
        $this->seedAdminRolesAndPermissions();

        // Create tenants
        $this->tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
        $this->tenantB = Tenant::factory()->create(['name' => 'Tenant B']);

        // Create super-admin user
        $this->admin = User::factory()->create([
            'tenant_id' => null,
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
        ]);
        $this->admin->assignRole('super_admin');

        // Create tenant user
        $this->tenantUser = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'email' => 'user@tenant-a.com',
            'password' => Hash::make('password'),
            'role' => 'member',
        ]);
    }

    /** @test */
    public function feature_flag_disabled_blocks_admin_template_import(): void
    {
        config(['features.tasks.enable_wbs_templates' => false]);

        $this->actingAs($this->admin);

        $this->get('/admin/templates/import')->assertForbidden();

        $this->post('/admin/templates/import', [])->assertForbidden();
    }

    /** @test */
    public function feature_flag_enabled_import_endpoints_validate_file_uploads(): void
    {
        config(['features.tasks.enable_wbs_templates' => true]);

        $this->actingAs($this->admin);

        $this->get('/admin/templates/import')->assertOk();

        $this->post('/admin/templates/import', [])
            ->assertSessionHasErrors('file');
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

        $response = $this->postImport([
            'file' => $file,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('template_sets', [
            'code' => 'TEST_TEMPLATE',
            'name' => 'Test Template',
        ]);

        $set = TemplateSet::where('code', 'TEST_TEMPLATE')->first();
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

        $response = $this->postImport([
            'file' => $file,
        ]);

        $response->assertRedirect();

        $expectedCsvCode = str_replace('-', '_', 'WBS-IMPORT-' . date('YmdHis'));
        $this->assertDatabaseHas('template_sets', [
            'code' => $expectedCsvCode,
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

        $response = $this->postImport([
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
        $sanitizedCodes = array_map([$this, 'normalizeSanitizedCode'], $codes);
        $this->assertContains($this->normalizeSanitizedCode('TENANT-A-TEMPLATE'), $sanitizedCodes);
        $this->assertNotContains($this->normalizeSanitizedCode('TENANT-B-TEMPLATE'), $sanitizedCodes);
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
        $sanitizedCodes = array_map([$this, 'normalizeSanitizedCode'], $codes);
        $this->assertContains($this->normalizeSanitizedCode('GLOBAL-TEMPLATE'), $sanitizedCodes);
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

        $response = $this->postImport([
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

        $response = $this->postImport([
            'file' => $file,
        ]);

        $response->assertSessionHasErrors();
    }

    /** @test */
    public function feature_flag_disabled_blocks_import_routes()
    {
        config(['features.tasks.enable_wbs_templates' => false]);

        $this->actingAs($this->admin);

        $this->get('/admin/templates/import')
            ->assertStatus(403);

        session()->start();
        session()->put('_token', 'feature-flag-token');

        $response = $this->post('/admin/templates/import', [
            '_token' => 'feature-flag-token',
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function feature_flag_enabled_allows_import_form_and_validation()
    {
        $this->actingAs($this->admin);

        $this->get('/admin/templates/import')
            ->assertStatus(200);

        $response = $this->postImport([]);

        $response->assertSessionHasErrors('file');
    }

    private function postImport(array $payload)
    {
        $this->get('/admin/templates/import');

        return $this->post('/admin/templates/import', array_merge($payload, [
            '_token' => session()->token(),
        ]));
    }

    private function normalizeCode(string $code): string
    {
        return trim(strtoupper(str_replace('-', '_', $code)), '_');
    }

    private function normalizeSanitizedCode(string $code): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper($code));
    }
}
