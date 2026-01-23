<?php declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Http\Middleware\TenantScopeMiddleware;
use App\Models\Project;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tests\Traits\RbacTestTrait;

class RoleBasedAccessControlMiddlewareTest extends TestCase
{
    use RefreshDatabase, RbacTestTrait;

    private string|false $originalRbacBypassEnv = false;
    private bool $originalRbacBypassConfig = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalRbacBypassEnv = getenv('RBAC_BYPASS_TESTING');
        $this->originalRbacBypassConfig = config('rbac.bypass_testing');

        $this->registerTestRoutes();
    }

    protected function tearDown(): void
    {
        $this->restoreRbacBypassSetting();

        parent::tearDown();
    }

    public function test_allows_requests_when_rbac_bypass_is_enabled(): void
    {
        $this->setRbacBypass(true);

        $context = $this->actingAsWithPermissions([], [
            'attributes' => ['role' => 'project_manager'],
        ]);

        $project = Project::factory()->create([
            'tenant_id' => $context['user']->tenant_id,
        ]);

        $response = $this->withHeaders($this->authHeaders($context))
            ->getJson("/_test/projects/{$project->id}/read");

        $response->assertStatus(200);
    }

    public function test_enforces_project_read_permission_when_bypass_is_disabled(): void
    {
        $this->setRbacBypass(false);

        $context = $this->actingAsWithPermissions([], [
            'attributes' => ['role' => 'project_manager'],
        ]);

        $project = Project::factory()->create([
            'tenant_id' => $context['user']->tenant_id,
        ]);

        $response = $this->withHeaders($this->authHeaders($context))
            ->getJson("/_test/projects/{$project->id}/read");

        $response->assertStatus(403);
    }

    public function test_project_write_alias_allows_create_or_update_permissions(): void
    {
        $this->setRbacBypass(false);

        $context = $this->actingAsWithPermissions(['project.create'], [
            'attributes' => ['role' => 'project_manager'],
        ]);

        $project = Project::factory()->create([
            'tenant_id' => $context['user']->tenant_id,
        ]);

        $response = $this->withHeaders($this->authHeaders($context))
            ->postJson("/_test/projects/{$project->id}/write");

        $response->assertStatus(200);
    }

    public function test_legacy_aliases_resolve_before_permission_check(): void
    {
        $this->setRbacBypass(false);

        $context = $this->actingAsWithPermissions([
            'project.read',
            'task.update',
        ], [
            'attributes' => ['role' => 'project_manager'],
        ]);

        $project = Project::factory()->create([
            'tenant_id' => $context['user']->tenant_id,
        ]);

        $projectResponse = $this->withHeaders($this->authHeaders($context))
            ->getJson("/_test/projects/{$project->id}/view");

        $taskResponse = $this->withHeaders($this->authHeaders($context))
            ->patchJson('/_test/tasks/edit');

        $projectResponse->assertStatus(200);
        $taskResponse->assertStatus(200);
    }

    public function test_project_scoped_permission_returns_forbidden_if_project_not_in_tenant(): void
    {
        $this->setRbacBypass(false);

        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $authA = $this->actingAsWithPermissions(['project.read'], [
            'attributes' => ['tenant_id' => (string) $tenantA->id],
        ]);

        $authB = $this->actingAsWithPermissions(['project.read'], [
            'attributes' => ['tenant_id' => (string) $tenantB->id],
        ]);

        $ownProject = Project::factory()->create([
            'tenant_id' => $tenantA->id,
        ]);

        $responseAllowed = $this->withHeaders($this->authHeaders($authA))
            ->getJson("/_test/projects/{$ownProject->id}/read");

        $responseAllowed->assertStatus(200);

        $responseForbidden = $this->withHeaders($this->authHeaders($authB))
            ->getJson("/_test/projects/{$ownProject->id}/read");

        $responseForbidden->assertStatus(403);
    }

    private function registerTestRoutes(): void
    {
        if (Route::has('tests.rbac.projects.read')) {
            return;
        }

        Route::middleware(['auth:sanctum', TenantScopeMiddleware::class, 'rbac:project.read,project'])
            ->get('/_test/projects/{project}', fn () => response()->json(['ok' => true]))
            ->name('tests.rbac.projects.show');

        Route::middleware(['api', 'auth.api', 'tenant.isolation', 'rbac:project.read,project'])
            ->get('/_test/projects/{project}/read', fn () => response()->json(['ok' => true]))
            ->name('tests.rbac.projects.read');

        Route::middleware(['api', 'auth.api', 'tenant.isolation', 'rbac:project.write,project'])
            ->post('/_test/projects/{project}/write', fn () => response()->json(['ok' => true]))
            ->name('tests.rbac.projects.write');

        Route::middleware(['api', 'auth.api', 'tenant.isolation', 'rbac:project.view,project'])
            ->get('/_test/projects/{project}/view', fn () => response()->json(['ok' => true]))
            ->name('tests.rbac.projects.view');

        Route::middleware(['api', 'auth.api', 'rbac:task.edit'])
            ->patch('/_test/tasks/edit', fn () => response()->json(['ok' => true]))
            ->name('tests.rbac.tasks.edit');
    }

    private function authHeaders(array $context, ?string $tenantId = null): array
    {
        return [
            'Authorization' => 'Bearer ' . $context['sanctum_token'],
            'Accept' => 'application/json',
            'X-Tenant-ID' => $tenantId ?? (string) $context['user']->tenant_id,
        ];
    }

    private function setRbacBypass(bool $enabled): void
    {
        config(['rbac.bypass_testing' => $enabled]);
        $value = $enabled ? '1' : '0';
        putenv("RBAC_BYPASS_TESTING={$value}");
        $_ENV['RBAC_BYPASS_TESTING'] = $value;
        $_SERVER['RBAC_BYPASS_TESTING'] = $value;
    }

    private function restoreRbacBypassSetting(): void
    {
        config(['rbac.bypass_testing' => $this->originalRbacBypassConfig]);

        if ($this->originalRbacBypassEnv === false) {
            putenv('RBAC_BYPASS_TESTING');
            unset($_ENV['RBAC_BYPASS_TESTING'], $_SERVER['RBAC_BYPASS_TESTING']);
            return;
        }

        putenv("RBAC_BYPASS_TESTING={$this->originalRbacBypassEnv}");
        $_ENV['RBAC_BYPASS_TESTING'] = $this->originalRbacBypassEnv;
        $_SERVER['RBAC_BYPASS_TESTING'] = $this->originalRbacBypassEnv;
    }
}
