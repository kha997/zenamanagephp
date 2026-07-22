<?php declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ZenaPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Chống tái phát bug walkthrough 21/07: route store/update dự án từng gate
 * bằng `rbac:project.write` — mã quyền KHÔNG tồn tại trong taxonomy
 * (ZenaPermissionsSeeder chỉ có project.view/create/update/delete), nên
 * không ai — kể cả admin — tạo/sửa được dự án qua web UI, và browser nhận
 * JSON envelope 403 thô thay vì trang thân thiện.
 */
class ProjectStoreRbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->seed(RoleSeeder::class);
        $this->seed(ZenaPermissionsSeeder::class);
    }

    private function makeUserWithPermissions(Tenant $tenant, array $codes): User
    {
        $user = User::factory()->create(['tenant_id' => (string) $tenant->id, 'is_active' => true]);
        $role = Role::factory()->create(['name' => 'Test Role ' . uniqid()]);
        $role->permissions()->sync(Permission::whereIn('code', $codes)->pluck('id'));
        UserRole::query()->create(['user_id' => (string) $user->id, 'role_id' => (string) $role->id]);

        return $user;
    }

    public function test_user_with_project_create_can_store_project_via_web(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->makeUserWithPermissions($tenant, ['project.view', 'project.create']);

        $response = $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => (string) $tenant->id])
            ->post('/app/projects', [
                'name' => 'Dự án RBAC test',
                'description' => 'Tạo qua web form',
                'start_date' => '2026-08-01',
                'end_date' => '2026-12-31',
            ]);

        $response->assertRedirect(route('app.projects'));
        $this->assertTrue(
            Project::query()->where('tenant_id', (string) $tenant->id)->where('name', 'Dự án RBAC test')->exists(),
            'Project phải được tạo thật trong DB — nếu fail ở 403, route đang gate bằng mã quyền không tồn tại.'
        );
    }

    public function test_user_without_project_create_cannot_store_project(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->makeUserWithPermissions($tenant, ['project.view']);

        $this->actingAs($user)->post('/app/projects', [
            'name' => 'Dự án không được phép',
            'start_date' => '2026-08-01',
            'end_date' => '2026-12-31',
        ])->assertStatus(302);

        $this->assertFalse(
            Project::query()->where('name', 'Dự án không được phép')->exists()
        );
    }

    /**
     * Invariant cả lớp lỗi: mọi tham số `rbac:` trên route web phải trỏ tới
     * (a) một Permission code có thật sau khi seed, hoặc (b) một role mà
     * RoleBasedAccessControlMiddleware::isRole() nhận diện, hoặc (c) nằm
     * trong allowlist cố ý fail-closed. Nếu test này fail với mã mới: hoặc
     * sửa route dùng đúng code trong ZenaPermissionsSeeder, hoặc thêm code
     * mới vào ZenaPermissionsSeeder (KHÔNG phải PermissionSeeder — xem bài
     * học seeder-ordering 20/07).
     */
    public function test_web_rbac_params_reference_real_permissions_or_roles(): void
    {
        // Roles trong RoleBasedAccessControlMiddleware::isRole().
        $knownRoles = ['project_manager', 'team_member', 'client', 'viewer'];
        // 'admin' không phải role được isRole() nhận diện và không có Permission
        // code — hệ quả thực tế: chỉ super_admin qua được. Giữ nguyên ngữ nghĩa
        // fail-closed đó cho các route admin/performance hiện có.
        $intentionallySuperAdminOnly = ['admin'];

        $permissionCodes = Permission::pluck('code')->all();

        $violations = [];
        foreach (Route::getRoutes() as $route) {
            if (!in_array('web', $route->gatherMiddleware(), true)) {
                continue;
            }
            foreach ($route->gatherMiddleware() as $middleware) {
                if (!is_string($middleware) || !str_starts_with($middleware, 'rbac:')) {
                    continue;
                }
                $param = explode(',', substr($middleware, 5))[0];
                if (in_array($param, $knownRoles, true)
                    || in_array($param, $intentionallySuperAdminOnly, true)
                    || in_array($param, $permissionCodes, true)) {
                    continue;
                }
                $violations[] = ($route->getName() ?? $route->uri()) . " → rbac:{$param}";
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($violations)),
            "Route web gate bằng mã quyền không tồn tại (không ai truy cập được):\n" . implode("\n", array_unique($violations))
        );
    }

    public function test_dead_project_subpage_routes_are_removed(): void
    {
        // 2 route này từng trỏ tới ProjectController::documents()/history()
        // đã bị xoá từ lâu (BadMethodCallException 500) với view đích là mock
        // demo — đã gỡ hẳn nút + route + view trong hotfix 21/07.
        $this->assertFalse(Route::has('app.projects.documents'), 'Route app.projects.documents phải bị gỡ (method controller không tồn tại).');
        $this->assertFalse(Route::has('app.projects.history'), 'Route app.projects.history phải bị gỡ (method controller không tồn tại).');
        // Route render biểu mẫu (có thật, đang dùng) phải giữ nguyên.
        $this->assertTrue(Route::has('app.projects.documents.render'));
    }
}
