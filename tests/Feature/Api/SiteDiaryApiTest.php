<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Project;
use App\Models\SiteDiary;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class SiteDiaryApiTest extends TestCase
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

        $this->userA = $this->createTenantUser($this->tenantA, [], ['admin'], ['site_diary.view', 'site_diary.create', 'site_diary.approve']);
        $this->userB = $this->createTenantUser($this->tenantB, [], ['admin'], ['site_diary.view', 'site_diary.create', 'site_diary.approve']);
    }

    public function test_index_is_tenant_scoped(): void
    {
        $projectA = $this->createProject($this->tenantA);
        $projectB = $this->createProject($this->tenantB);

        $diaryA = $this->createDiary($this->tenantA, $projectA, $this->userA, '2026-07-01');
        $this->createDiary($this->tenantB, $projectB, $this->userB, '2026-07-01');

        $response = $this->getJson($this->route('index'), $this->headersFor($this->userA));

        $response->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $diaryA->id);
    }

    public function test_index_supports_project_status_and_date_filters(): void
    {
        $project = $this->createProject($this->tenantA);
        $otherProject = $this->createProject($this->tenantA);

        $match = $this->createDiary($this->tenantA, $project, $this->userA, '2026-07-02');
        $this->createDiary($this->tenantA, $otherProject, $this->userA, '2026-07-02');
        $outOfRange = $this->createDiary($this->tenantA, $project, $this->userA, '2026-06-01');

        $response = $this->getJson($this->route('index', [], [
            'project_id' => (string) $project->id,
            'status' => 'draft',
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-31',
        ]), $this->headersFor($this->userA));

        $response->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $match->id);

        $this->assertNotSame((string) $outOfRange->id, $response->json('data.0.id'));
    }

    public function test_store_creates_draft_diary(): void
    {
        $project = $this->createProject($this->tenantA);

        $response = $this->postJson($this->route('store'), [
            'project_id' => (string) $project->id,
            'diary_date' => '2026-07-08',
            'weather' => 'Nắng',
            'temperature' => '30-35°C',
            'manpower_count' => 42,
            'work_performed' => 'Đổ bê tông sàn tầng 3',
            'equipment_used' => 'Cẩu tháp, máy bơm bê tông',
            'safety_notes' => 'Không có sự cố',
        ], $this->headersFor($this->userA));

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.manpower_count', 42)
            ->assertJsonPath('data.created_by', (string) $this->userA->id);

        $this->assertStringStartsWith('SD-', (string) $response->json('data.diary_number'));
    }

    public function test_store_rejects_duplicate_date_per_project(): void
    {
        $project = $this->createProject($this->tenantA);
        $this->createDiary($this->tenantA, $project, $this->userA, '2026-07-08');

        $response = $this->postJson($this->route('store'), [
            'project_id' => (string) $project->id,
            'diary_date' => '2026-07-08',
            'work_performed' => 'Trùng ngày',
        ], $this->headersFor($this->userA));

        $response->assertStatus(422);
    }

    public function test_store_rejects_foreign_tenant_project(): void
    {
        $projectB = $this->createProject($this->tenantB);

        $response = $this->postJson($this->route('store'), [
            'project_id' => (string) $projectB->id,
            'diary_date' => '2026-07-08',
            'work_performed' => 'Không được phép',
        ], $this->headersFor($this->userA));

        $response->assertStatus(422);
    }

    public function test_submit_and_approve_workflow(): void
    {
        $project = $this->createProject($this->tenantA);
        $diary = $this->createDiary($this->tenantA, $project, $this->userA, '2026-07-08');

        $submit = $this->postJson($this->route('submit', ['id' => (string) $diary->id]), [], $this->headersFor($this->userA));
        $submit->assertOk()->assertJsonPath('data.status', 'submitted');

        $approve = $this->postJson($this->route('approve', ['id' => (string) $diary->id]), [], $this->headersFor($this->userA));
        $approve->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_by', (string) $this->userA->id);

        $this->assertNotNull($approve->json('data.approved_at'));
    }

    public function test_approve_requires_submitted_status(): void
    {
        $project = $this->createProject($this->tenantA);
        $diary = $this->createDiary($this->tenantA, $project, $this->userA, '2026-07-08');

        $this->postJson($this->route('approve', ['id' => (string) $diary->id]), [], $this->headersFor($this->userA))
            ->assertStatus(422);
    }

    public function test_update_only_allowed_on_draft(): void
    {
        $project = $this->createProject($this->tenantA);
        $diary = $this->createDiary($this->tenantA, $project, $this->userA, '2026-07-08', SiteDiary::STATUS_SUBMITTED);

        $this->putJson($this->route('update', ['id' => (string) $diary->id]), [
            'work_performed' => 'Sửa nội dung',
        ], $this->headersFor($this->userA))
            ->assertStatus(422);
    }

    public function test_show_hides_foreign_tenant_diaries(): void
    {
        $projectB = $this->createProject($this->tenantB);
        $diaryB = $this->createDiary($this->tenantB, $projectB, $this->userB, '2026-07-08');

        $this->getJson($this->route('show', ['id' => (string) $diaryB->id]), $this->headersFor($this->userA))
            ->assertStatus(404);
    }

    public function test_index_requires_view_permission(): void
    {
        $restricted = $this->createTenantUser($this->tenantA, [], ['member'], []);

        $this->getJson($this->route('index'), $this->headersFor($restricted))
            ->assertStatus(403);
    }

    private function createProject(Tenant $tenant): Project
    {
        return Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
        ]);
    }

    private function createDiary(Tenant $tenant, Project $project, User $creator, string $date, string $status = SiteDiary::STATUS_DRAFT): SiteDiary
    {
        return SiteDiary::query()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'diary_number' => 'SD-' . strtoupper(uniqid()),
            'diary_date' => $date,
            'manpower_count' => 10,
            'work_performed' => 'Công việc thử nghiệm',
            'status' => $status,
            'created_by' => (string) $creator->id,
        ]);
    }

    private function route(string $name, array $parameters = [], array $query = []): string
    {
        $url = route('api.zena.site-diaries.' . $name, $parameters, false);

        if ($query === []) {
            return $url;
        }

        return $url . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return array<string, string>
     */
    private function headersFor(User $user): array
    {
        $token = $user->createToken('site-diary-api-test')->plainTextToken;

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Tenant-ID' => (string) $user->tenant_id,
            'Authorization' => 'Bearer ' . $token,
        ];
    }
}
