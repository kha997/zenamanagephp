<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class VendorApiTest extends TestCase
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
            'vendor.view',
            'vendor.create',
            'vendor.update',
            'vendor.delete',
        ]);
        $this->userB = $this->createTenantUser($this->tenantB, [], ['admin'], [
            'vendor.view',
            'vendor.create',
            'vendor.update',
            'vendor.delete',
        ]);
    }

    public function test_vendor_crud_round_trips_on_canonical_zena_path_for_vendor_slice(): void
    {
        $create = $this->postJson($this->route('store'), [
            'code' => 'ven-001',
            'name' => 'Concrete Supply Co',
            'contact_name' => 'Alice Nguyen',
            'email' => 'alice@example.com',
            'phone' => '0909000111',
            'address' => '123 Nguyen Hue',
        ], $this->headersFor($this->userA));

        $create->assertStatus(201)
            ->assertJsonPath('data.code', 'VEN-001')
            ->assertJsonPath('data.name', 'Concrete Supply Co')
            ->assertJsonPath('data.contact_name', 'Alice Nguyen');

        $vendorId = (string) $create->json('data.id');

        $this->getJson($this->route('index'), $this->headersFor($this->userA))
            ->assertOk()
            ->assertJsonPath('data.0.id', $vendorId);

        $this->getJson($this->route('show', ['id' => $vendorId]), $this->headersFor($this->userA))
            ->assertOk()
            ->assertJsonPath('data.email', 'alice@example.com');

        $this->putJson($this->route('update', ['id' => $vendorId]), [
            'contact_name' => 'Bao Tran',
            'is_active' => false,
        ], $this->headersFor($this->userA))
            ->assertOk()
            ->assertJsonPath('data.contact_name', 'Bao Tran')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('vendors', [
            'id' => $vendorId,
            'tenant_id' => (string) $this->tenantA->id,
            'contact_name' => 'Bao Tran',
            'is_active' => 0,
        ]);

        $this->deleteJson($this->route('destroy', ['id' => $vendorId]), [], $this->headersFor($this->userA))
            ->assertOk();

        $this->assertDatabaseMissing('vendors', [
            'id' => $vendorId,
            'tenant_id' => (string) $this->tenantA->id,
        ]);
    }

    public function test_vendor_show_and_update_return_404_cross_tenant_for_vendor_slice(): void
    {
        $vendor = Vendor::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'code' => 'VEN-CROSS-001',
            'name' => 'Hidden Vendor',
            'contact_name' => 'Secret Contact',
            'email' => 'secret@example.com',
        ]);

        $this->getJson($this->route('show', ['id' => $vendor->id]), $this->headersFor($this->userB))
            ->assertStatus(404);

        $this->putJson($this->route('update', ['id' => $vendor->id]), [
            'name' => 'Cross tenant edit',
        ], $this->headersFor($this->userB))
            ->assertStatus(404);

        $this->deleteJson($this->route('destroy', ['id' => $vendor->id]), [], $this->headersFor($this->userB))
            ->assertStatus(404);
    }

    public function test_vendor_rbac_denies_without_canonical_permissions_for_vendor_slice(): void
    {
        $restricted = $this->createTenantUser($this->tenantA, [], ['member'], []);
        $headers = $this->headersFor($restricted);

        $this->getJson($this->route('index'), $headers)->assertStatus(403);
        $this->postJson($this->route('store'), [
            'code' => 'VEN-NOPE-001',
            'name' => 'No Permission Vendor',
        ], $headers)->assertStatus(403);
    }

    private function route(string $name, array $parameters = []): string
    {
        return route('api.zena.vendors.' . $name, $parameters, false);
    }

    /**
     * @return array<string, string>
     */
    private function headersFor(User $user): array
    {
        $token = $user->createToken('vendor-api-test')->plainTextToken;

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Tenant-ID' => (string) $user->tenant_id,
            'Authorization' => 'Bearer ' . $token,
        ];
    }
}
