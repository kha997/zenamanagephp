<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tests\Traits\AuthenticationTestTrait;

/**
 * GAP-099 — /api/v1 project update works; the never-working /api/v1/change-requests
 * routes are retired (the canonical API is /api/zena/change-requests), creation stays.
 */
class V1ProjectAndChangeRequestRoutesTest extends TestCase
{
    use AuthenticationTestTrait;
    use RefreshDatabase;

    /** Names of the retired /api/v1/change-requests routes (store stays). */
    private const RETIRED_CHANGE_REQUEST_ROUTE_NAMES = [
        'change-requests.index',
        'change-requests.show',
        'change-requests.update',
        'change-requests.destroy',
        'change-requests.submit',
        'change-requests.approve',
        'change-requests.reject',
        'change-requests.statistics',
        'change-requests.by-project',
        'change-requests.pending-approval',
    ];

    private Tenant $tenant;

    private User $user;

    /** @var array<string, string> */
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->user = $this->createTenantUser($this->tenant);
        $this->headers = array_merge(
            $this->apiHeadersForTenant((string) $this->tenant->id),
            ['Authorization' => 'Bearer ' . $this->apiLoginToken($this->user, $this->tenant)]
        );
    }

    public function test_v1_project_update_succeeds(): void
    {
        $project = $this->project();

        $this->apiPatch("/api/v1/projects/{$project->id}", ['name' => 'Renamed through v1'], $this->headers) // ssot-allow-hardcode (unnamed v1 compatibility route)
            ->assertOk();

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'Renamed through v1']);
    }

    public function test_v1_project_status_change_and_delete_succeed(): void
    {
        $project = $this->project();

        $this->apiPatch("/api/v1/projects/{$project->id}", ['status' => 'on_hold'], $this->headers) // ssot-allow-hardcode (unnamed v1 compatibility route)
            ->assertOk();
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'status' => 'on_hold']);

        $this->apiDelete("/api/v1/projects/{$project->id}", $this->headers) // ssot-allow-hardcode (unnamed v1 compatibility route)
            ->assertOk();
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_never_working_v1_change_request_routes_are_retired(): void
    {
        foreach (self::RETIRED_CHANGE_REQUEST_ROUTE_NAMES as $name) {
            $this->assertFalse(Route::has($name), "{$name} should be retired");
        }
        $this->assertTrue(Route::has('change-requests.store'));
        $this->assertSame(['POST'], Route::getRoutes()->getByName('change-requests.store')?->methods());
    }

    public function test_v1_change_request_creation_still_works(): void
    {
        $project = $this->project();

        $this->apiPost('/api/v1/change-requests', [ // ssot-allow-hardcode (unnamed v1 compatibility route)
            'project_id' => $project->id,
            'title' => 'Đổi vật liệu',
            'description' => 'Thay gạch ốp',
        ], $this->headers)->assertCreated();

        $this->assertDatabaseHas('change_requests', ['project_id' => $project->id, 'title' => 'Đổi vật liệu']);
    }

    private function project(): Project
    {
        return Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pm_id' => $this->user->id,
            'created_by' => $this->user->id,
            'status' => 'active',
        ]);
    }
}
