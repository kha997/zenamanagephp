<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

/**
 * Data-entry #4 (2026-07-22): các form tạo mới có field `code` bắt gõ tay
 * giờ prefill sẵn mã theo pattern của receipts (`GRN-YYYYMMDD-XXXX`,
 * PREFIX-date-random, user vẫn sửa được). Test chốt từng trang create
 * hiển thị value đã prefill đúng prefix + ngày hiện tại.
 */
class OperatorCodePrefillTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenant = Tenant::factory()->create();
        $this->user = $this->createTenantUser(
            $this->tenant,
            [],
            ['admin'],
            ['material.create', 'material.view', 'vendor.create', 'vendor.view', 'contract.create', 'contract.view', 'boq.create', 'boq.view']
        );
    }

    public static function prefillPages(): array
    {
        return [
            'materials' => ['operator.materials.create', 'MAT-'],
            'vendors' => ['operator.vendors.create', 'VENDOR-'],
            'contracts' => ['operator.contracts.create', 'CTR-'],
            'boqs' => ['operator.boqs.create', 'BOQ-'],
        ];
    }

    /**
     * @dataProvider prefillPages
     */
    public function test_create_page_prefills_code_field(string $routeName, string $prefix): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->get(route($routeName), $headers)
            ->assertOk()
            ->assertSee('value="' . $prefix . now()->format('Ymd') . '-', false);
    }
}
