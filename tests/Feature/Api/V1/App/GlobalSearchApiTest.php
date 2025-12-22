<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\ChangeOrder;
use App\Models\Contract;
use App\Models\ContractActualPayment;
use App\Models\ContractPaymentCertificate;
use App\Models\Document;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GlobalSearchApiTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Global Search User',
            'email' => 'search@example.com',
        ]);

        $this->user->tenants()->attach($this->tenant->id, [
            'role' => 'pm',
            'is_default' => true,
        ]);

        $role = Role::factory()->create([
            'name' => 'Search Role',
            'scope' => 'tenant',
        ]);

        $permissions = [
            Permission::firstOrCreate([
                'code' => 'projects.cost.view',
            ], [
                'module' => 'projects',
                'action' => 'cost.view',
                'description' => 'Allow viewing cost entities',
            ]),
            Permission::firstOrCreate([
                'code' => 'users.view',
            ], [
                'module' => 'users',
                'action' => 'view',
                'description' => 'Allow viewing users',
            ]),
        ];

        foreach ($permissions as $permission) {
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $this->user->roles()->attach($role->id);
    }

    public function test_search_requires_query_param(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/app/search');

        $response->assertStatus(422);
        $validation = (array) $response->json('details.validation');
        $this->assertArrayHasKey('q', $validation);
    }

    public function test_search_returns_results_across_modules(): void
    {
        Sanctum::actingAs($this->user);
        $entities = $this->seedSearchEntities('Riviera');

        $response = $this->getJson('/api/v1/app/search?q=Riviera');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'results',
                    'pagination',
                ],
            ]);

        $results = $response->json('data.results');
        $this->assertNotEmpty($results);

        $modules = collect($results)->pluck('module')->unique();
        $this->assertTrue($modules->contains('projects'));
        $this->assertTrue($modules->contains('tasks'));
        $this->assertTrue($modules->contains('documents'));
        $this->assertTrue($modules->contains('cost'));
        $this->assertTrue($modules->contains('users'));

        $costTypes = collect($results)
            ->where('module', 'cost')
            ->pluck('type')
            ->unique();

        $this->assertTrue($costTypes->contains('change_order'));
        $this->assertTrue($costTypes->contains('certificate'));
        $this->assertTrue($costTypes->contains('payment'));

        $userResults = collect($results)->where('module', 'users');
        $this->assertNotEmpty($userResults);
        $this->assertStringContainsString('Riviera', $userResults->first()['title']);
    }

    public function test_search_can_filter_by_module(): void
    {
        Sanctum::actingAs($this->user);
        $this->seedSearchEntities('Riviera');

        $response = $this->getJson('/api/v1/app/search?q=Riviera&modules[]=projects');

        $response->assertStatus(200);
        $results = $response->json('data.results');
        $modules = collect($results)->pluck('module')->unique();
        $this->assertCount(1, $modules);
        $this->assertEquals('projects', $modules->first());
    }

    public function test_search_can_filter_by_project(): void
    {
        Sanctum::actingAs($this->user);
        $entities = $this->seedSearchEntities('Riviera');
        $projectA = $entities['project'];

        $projectB = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Riviera II',
            'code' => 'RV-B',
        ]);

        $contractB = Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $projectB->id,
        ]);

        Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $projectB->id,
            'name' => 'Riviera B Task',
            'description' => 'Should be filtered out',
            'created_by' => $this->user->id,
            'assignee_id' => $this->user->id,
        ]);

        ChangeOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $projectB->id,
            'contract_id' => $contractB->id,
            'title' => 'Riviera B Change',
        ]);

        $response = $this->getJson("/api/v1/app/search?q=Riviera&project_id={$projectA->id}");

        $response->assertStatus(200);
        $results = $response->json('data.results');
        $this->assertNotEmpty($results);

        $projectIds = collect($results)
            ->map(fn (array $result) => $result['project_id'])
            ->filter();

        $this->assertFalse($projectIds->contains($projectB->id));
    }

    public function test_search_respects_tenant_isolation(): void
    {
        Sanctum::actingAs($this->user);
        $entities = $this->seedSearchEntities('Riviera');
        $projectA = $entities['project'];

        $tenantB = Tenant::factory()->create();
        $projectB = Project::factory()->create([
            'tenant_id' => $tenantB->id,
            'name' => 'Riviera Other Tenant',
        ]);

        Task::factory()->create([
            'tenant_id' => $tenantB->id,
            'project_id' => $projectB->id,
            'name' => 'Riviera Tenant Task',
            'description' => 'Should not appear',
            'created_by' => $this->user->id,
            'assignee_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/app/search?q=Riviera');

        $response->assertStatus(200);
        $results = $response->json('data.results');
        $projectModules = collect($results)
            ->where('module', 'projects')
            ->pluck('id');

        $this->assertFalse($projectModules->contains($projectB->id));
        $this->assertTrue($projectModules->contains($projectA->id));
    }

    private function seedSearchEntities(string $keyword): array
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => "{$keyword} Tower",
            'code' => "{$keyword}-PRJ",
        ]);

        Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'name' => "{$keyword} Task",
            'description' => "{$keyword} work package",
            'created_by' => $this->user->id,
            'assignee_id' => $this->user->id,
        ]);

        $documentUploader = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => "{$keyword} Uploader",
            'email' => "uploader+{$keyword}@example.com",
        ]);

        $documentUploader->tenants()->attach($this->tenant->id, [
            'role' => 'member',
            'is_default' => false,
        ]);


        $documentId = (string) Str::ulid();
        DB::statement('PRAGMA foreign_keys = OFF');
        DB::table('documents')->insert([
            'id' => $documentId,
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'name' => "{$keyword} Specification",
            'original_name' => "{$keyword} Specification.pdf",
            'file_path' => "/documents/{$documentId}.pdf",
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 123456,
            'file_hash' => Str::uuid()->toString(),
            'category' => 'general',
            'description' => "{$keyword} specs for the tower",
            'metadata' => json_encode(['source' => 'search']),
            'status' => 'approved',
            'version' => 1,
            'is_current_version' => true,
            'parent_document_id' => null,
            'uploaded_by' => $documentUploader->id,
            'created_by' => $documentUploader->id,
            'updated_by' => $documentUploader->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::statement('PRAGMA foreign_keys = ON');

        $contract = Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'code' => "{$keyword}-CT",
        ]);

        ChangeOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'contract_id' => $contract->id,
            'code' => "{$keyword}-CO",
            'title' => "{$keyword} Change",
        ]);

        ContractPaymentCertificate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'contract_id' => $contract->id,
            'code' => "{$keyword}-CERT",
            'title' => "{$keyword} Payment Cert",
        ]);

        ContractActualPayment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'contract_id' => $contract->id,
            'reference_no' => "{$keyword}-PAY",
        ]);

        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => "{$keyword} Colleague",
            'email' => "colleague+{$keyword}@example.com",
        ]);

        $user->tenants()->attach($this->tenant->id, [
            'role' => 'member',
            'is_default' => false,
        ]);

        return compact('project');
    }
}
