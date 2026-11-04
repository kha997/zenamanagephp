<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\AuditLog;
use App\Models\Payroll\StaffMember;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\User;
use App\Services\Payroll\PayrollRuleViolation;
use App\Services\Payroll\StaffService;
use Database\Seeders\PayrollRolePermissionSeeder;
use Database\Seeders\ZenaPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GAP-109 (Gate 2 Q-1) — company staff register: employees and collaborators, office (overhead) flag, optional login
 * and Treasury party links, standard-hours override, annual bonus estimate; payroll.manage only; audited; per tenant.
 */
class StaffRegisterTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private User $accountant;

    private User $engineer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPayrollPermissions();
        $this->tenant = Tenant::factory()->create();
        $this->owner = $this->payrollUser($this->tenant, 'Admin');
        $this->accountant = $this->payrollUser($this->tenant, 'Finance');
        $this->engineer = $this->payrollUser($this->tenant, 'site_engineer');
    }

    public function test_create_update_and_end_with_rules_and_audit(): void
    {
        $service = app(StaffService::class);
        $party = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'employee']);

        $staff = $service->create($this->accountant, [
            'code' => 'NV01', 'name' => 'Nguyễn Văn A', 'staff_type' => 'employee', 'is_overhead' => false,
            'user_id' => (string) $this->engineer->id, 'financial_party_id' => (string) $party->id,
            'annual_bonus_estimate' => '12000000', 'start_date' => '2026-01-01',
        ]);
        $this->assertSame('employee', $staff->staff_type);
        $this->assertSame('12000000.00', $staff->fresh()->annual_bonus_estimate);
        $this->assertTrue($staff->fresh()->is_active);

        $this->assertRule(fn () => $service->create($this->accountant, ['code' => 'NV01', 'name' => 'Trùng', 'staff_type' => 'employee', 'start_date' => '2026-01-01']), 'Mã nhân sự');
        $this->assertRule(fn () => $service->create($this->accountant, ['code' => 'X', 'name' => 'X', 'staff_type' => 'boss', 'start_date' => '2026-01-01']), 'Loại nhân sự');
        $this->assertRule(fn () => $service->create($this->accountant, ['code' => 'CTV1', 'name' => 'B', 'staff_type' => 'collaborator', 'start_date' => '2026-05-01', 'end_date' => '2026-04-30']), 'Ngày nghỉ');
        $this->assertRule(fn () => $service->create($this->accountant, ['code' => 'CTV2', 'name' => 'B', 'staff_type' => 'collaborator', 'start_date' => '2026-05-01', 'standard_hours_override' => '0']), 'Giờ chuẩn');
        $this->assertRule(fn () => $service->create($this->accountant, ['code' => 'CTV3', 'name' => 'B', 'staff_type' => 'collaborator', 'start_date' => '2026-05-01', 'annual_bonus_estimate' => '-1']), 'không được âm');
        $this->assertRule(fn () => $service->create($this->engineer, ['code' => 'NV02', 'name' => 'C', 'staff_type' => 'employee', 'start_date' => '2026-01-01']), 'quyền');

        $other = Tenant::factory()->create();
        $foreignUser = User::factory()->create(['tenant_id' => $other->id]);
        $foreignParty = TreasuryFinancialParty::factory()->create(['tenant_id' => $other->id, 'party_type' => 'employee']);
        $this->assertRule(fn () => $service->create($this->accountant, ['code' => 'NV03', 'name' => 'D', 'staff_type' => 'employee', 'start_date' => '2026-01-01', 'user_id' => (string) $foreignUser->id]), 'Tài khoản');
        $this->assertRule(fn () => $service->create($this->accountant, ['code' => 'NV04', 'name' => 'D', 'staff_type' => 'employee', 'start_date' => '2026-01-01', 'financial_party_id' => (string) $foreignParty->id]), 'Đối tác');

        $service->update($this->owner, $staff, [
            'code' => 'NV01', 'name' => 'Nguyễn Văn A', 'staff_type' => 'employee', 'is_overhead' => true,
            'standard_hours_override' => '120', 'annual_bonus_estimate' => '24000000', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
        ]);
        $fresh = $staff->fresh();
        $this->assertTrue($fresh->is_overhead);
        $this->assertSame('120.00', $fresh->standard_hours_override);
        $this->assertNull($fresh->user_id);
        $this->assertSame('2026-12-31', $fresh->end_date?->toDateString());

        $this->assertSame(1, AuditLog::query()->where('action', 'payroll.staff.created')->count());
        $updated = AuditLog::query()->where('action', 'payroll.staff.updated')->sole();
        $this->assertFalse($updated->old_data['is_overhead']);
        $this->assertTrue($updated->new_data['is_overhead']);
    }

    public function test_pages_menu_and_permissions(): void
    {
        $this->actingAs($this->owner)->get(route('operator.payroll.staff.index'))
            ->assertOk()->assertSee('payroll-staff-form', false);
        $this->actingAs($this->owner)->get(route('operator.payroll.index'))->assertOk()->assertSee('Nhân sự &amp; lương', false);

        $this->actingAs($this->accountant)->post(route('operator.payroll.staff.store'), [
            'code' => 'CTV01', 'name' => 'Trần Thị B', 'staff_type' => 'collaborator', 'is_overhead' => '0', 'start_date' => '2026-03-01',
        ])->assertRedirect(route('operator.payroll.staff.index'));
        $staff = StaffMember::query()->where('code', 'CTV01')->sole();
        $this->actingAs($this->accountant)->get(route('operator.payroll.staff.index'))->assertOk()->assertSee('Trần Thị B')->assertSee('Cộng tác viên');
        $this->actingAs($this->accountant)->get(route('operator.payroll.staff.edit', $staff->id))->assertOk()->assertSee('CTV01');
        $this->actingAs($this->accountant)->post(route('operator.payroll.staff.update', $staff->id), [
            'code' => 'CTV01', 'name' => 'Trần Thị B', 'staff_type' => 'collaborator', 'is_overhead' => '1', 'start_date' => '2026-03-01',
        ])->assertRedirect(route('operator.payroll.staff.index'));
        $this->assertTrue($staff->fresh()->is_overhead);
        $this->actingAs($this->accountant)->post(route('operator.payroll.staff.store'), [
            'code' => '', 'name' => '', 'staff_type' => 'employee', 'start_date' => '2026-03-01',
        ])->assertSessionHasErrors(['code', 'name']);

        // No payroll permission: no menu, no page, no write.
        $this->actingAs($this->engineer)->get(route('operator.payroll.staff.index'))->assertRedirect();
        $this->actingAs($this->engineer)->post(route('operator.payroll.staff.store'), [
            'code' => 'X1', 'name' => 'X', 'staff_type' => 'employee', 'start_date' => '2026-03-01',
        ])->assertRedirect();
        $this->assertSame(0, StaffMember::query()->where('code', 'X1')->count());
        $routes = static fn (array $sections): array => collect($sections)->flatten()->map(static fn ($item): string => $item->routeName)->all();
        $nav = app(\App\Support\Navigation\OperatorNavigationComposer::class);
        $this->assertContains('operator.payroll.index', $routes($nav->visibleFor($this->accountant)));
        $this->assertNotContains('operator.payroll.index', $routes($nav->visibleFor($this->engineer)));
    }

    public function test_tenant_isolation(): void
    {
        $other = Tenant::factory()->create();
        $foreign = StaffMember::query()->create([
            'tenant_id' => $other->id, 'code' => 'NV01', 'name' => 'Người công ty khác', 'staff_type' => 'employee',
            'is_overhead' => false, 'annual_bonus_estimate' => '0', 'start_date' => '2026-01-01', 'is_active' => true,
        ]);
        // Same code is fine in another tenant.
        app(StaffService::class)->create($this->owner, ['code' => 'NV01', 'name' => 'A', 'staff_type' => 'employee', 'start_date' => '2026-01-01']);

        $this->actingAs($this->owner)->get(route('operator.payroll.staff.edit', $foreign->id))->assertNotFound();
        $this->actingAs($this->owner)->get(route('operator.payroll.staff.index'))->assertOk()->assertDontSee('Người công ty khác');
    }

    private function seedPayrollPermissions(): void
    {
        $this->seed(ZenaPermissionsSeeder::class);
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
    }

    /** Admin holds payroll.manage and payroll.close, Finance payroll.manage only, any other role neither. */
    private function payrollUser(Tenant $tenant, string $roleName): User
    {
        $role = Role::query()->where('name', $roleName)->first() ?? Role::factory()->create(['name' => $roleName]);
        $this->seed(PayrollRolePermissionSeeder::class);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function assertRule(callable $action, string $message): void
    {
        try {
            $action();
            $this->fail('Expected a PayrollRuleViolation.');
        } catch (PayrollRuleViolation $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }
}
