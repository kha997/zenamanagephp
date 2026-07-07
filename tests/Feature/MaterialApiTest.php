<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Material;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class MaterialApiTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();
        $this->userA = $this->createTenantUser($this->tenantA, [], ['admin'], [
            'material.view',
            'material.create',
            'material.update',
            'material.delete',
        ]);
        $this->userB = $this->createTenantUser($this->tenantB, [], ['admin'], [
            'material.view',
            'material.create',
            'material.update',
            'material.delete',
        ]);
    }

    public function test_material_crud_round_trips_on_canonical_zena_path_for_material_slice(): void
    {
        $create = $this->postJson($this->route('store'), [
            'code' => 'mat-001',
            'name' => 'Cement Type I',
            'category' => 'cement',
            'unit' => 'bag',
            'description' => 'Owner-side catalog entry',
        ], $this->headersFor($this->userA));

        $create->assertStatus(201)
            ->assertJsonPath('data.code', 'MAT-001')
            ->assertJsonPath('data.name', 'Cement Type I')
            ->assertJsonPath('data.category', 'cement');

        $materialId = (string) $create->json('data.id');

        $this->getJson($this->route('index'), $this->headersFor($this->userA))
            ->assertOk()
            ->assertJsonPath('data.0.id', $materialId);

        $this->getJson($this->route('show', ['id' => $materialId]), $this->headersFor($this->userA))
            ->assertOk()
            ->assertJsonPath('data.unit', 'bag');

        $this->putJson($this->route('update', ['id' => $materialId]), [
            'name' => 'Cement Type I Updated',
            'unit' => 'kg',
            'is_active' => false,
        ], $this->headersFor($this->userA))
            ->assertOk()
            ->assertJsonPath('data.name', 'Cement Type I Updated')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('materials', [
            'id' => $materialId,
            'tenant_id' => (string) $this->tenantA->id,
            'name' => 'Cement Type I Updated',
            'unit' => 'kg',
            'is_active' => 0,
        ]);

        $this->deleteJson($this->route('destroy', ['id' => $materialId]), [], $this->headersFor($this->userA))
            ->assertOk();

        $this->assertDatabaseMissing('materials', [
            'id' => $materialId,
            'tenant_id' => (string) $this->tenantA->id,
        ]);
    }

    public function test_material_show_and_update_return_404_cross_tenant_for_material_slice(): void
    {
        $material = Material::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'code' => 'MAT-CROSS-001',
            'name' => 'Hidden Material',
            'category' => 'steel',
            'unit' => 'piece',
        ]);

        $this->getJson($this->route('show', ['id' => $material->id]), $this->headersFor($this->userB))
            ->assertStatus(404);

        $this->putJson($this->route('update', ['id' => $material->id]), [
            'name' => 'Cross tenant edit',
        ], $this->headersFor($this->userB))
            ->assertStatus(404);

        $this->deleteJson($this->route('destroy', ['id' => $material->id]), [], $this->headersFor($this->userB))
            ->assertStatus(404);
    }

    public function test_material_rbac_denies_without_canonical_permissions_for_material_slice(): void
    {
        $restricted = $this->createTenantUser($this->tenantA, [], ['member'], []);
        $headers = $this->headersFor($restricted);

        $this->getJson($this->route('index'), $headers)->assertStatus(403);
        $this->postJson($this->route('store'), [
            'code' => 'MAT-NOPE-001',
            'name' => 'No Permission',
        ], $headers)->assertStatus(403);
    }

    private function route(string $name, array $parameters = []): string
    {
        return route('api.zena.materials.' . $name, $parameters, false);
    }

    /**
     * @return array<string, string>
     */
    private function headersFor(User $user): array
    {
        $token = $user->createToken('material-api-test')->plainTextToken;

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Tenant-ID' => (string) $user->tenant_id,
            'Authorization' => 'Bearer ' . $token,
        ];
    }
}
