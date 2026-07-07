<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\ZenaRbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProcurementReceivingContractCostPilotRbacAlignmentTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $superAdmin;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->seed(ZenaRbacSeeder::class);

        $this->tenant = Tenant::factory()->create();
        $this->superAdmin = User::query()->where('email', 'superadmin@zena.com')->firstOrFail();
        $this->superAdmin->forceFill(['tenant_id' => (string) $this->tenant->id])->save();

        $this->project = $this->createProjectPair($this->tenant, 'Seeded Super Admin Pilot Project');
    }

    public function test_seeded_super_admin_can_access_canonical_pilot_owner_routes_without_authorization_403s(): void
    {
        $headers = $this->headersFor($this->superAdmin);

        $this->getJson(route('api.zena.materials.index', [], false), $headers)
            ->assertOk();

        $this->getJson(route('api.zena.vendors.index', [], false), $headers)
            ->assertOk();

        $this->getJson(route('api.zena.projects.contracts.index', ['project' => $this->project->id], false), $headers)
            ->assertOk();

        $this->getJson(route('api.zena.material-requests.index', [], false), $headers)
            ->assertOk();
    }

    private function createProjectPair(Tenant $tenant, string $name): Project
    {
        $project = Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'name' => $name,
        ]);

        DB::table('zena_projects')->insert([
            'id' => (string) $project->id,
            'tenant_id' => (string) $tenant->id,
            'code' => (string) $project->code,
            'name' => (string) $project->name,
            'description' => $project->description,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $project;
    }

    /**
     * @return array<string, string>
     */
    private function headersFor(User $user): array
    {
        $token = $user->createToken('seeded-super-admin-pilot-rbac-alignment-test')->plainTextToken;

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Tenant-ID' => (string) $user->tenant_id,
            'Authorization' => 'Bearer ' . $token,
        ];
    }
}
