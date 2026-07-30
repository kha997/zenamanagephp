# Kéo-thả pipeline CRM — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cho phép kéo-thả (và click "Chuyển giai đoạn" làm fallback) để đổi `pipeline_stage` của một Opportunity giữa các cột trên board CRM (`crm.index`), dùng route/permission/validation hiện có.

**Architecture:** Trích logic đổi stage từ `ApiOpportunityController::updateStage` sang `App\Services\Crm\OpportunityStageTransitionService` dùng chung bởi cả `ApiOpportunityController` và `CrmPageController`. `BOARD_GROUPS` đổi từ mảng liên kết theo label sang mảng có stable key + `default_entry_stage` + `choice_options` tường minh. Frontend là 1 module vanilla JS mới (`resources/js/crm-pipeline-drag.js`) dùng HTML5 Drag & Drop API gốc, pessimistic DOM update, và 1 hàm orchestration `requestStageTransition()` dùng chung cho cả kéo-thả và click.

**Tech Stack:** Laravel (PHP 8.2+, `declare(strict_types=1)`), Blade, Vite, PHPUnit (Feature/Unit), Laravel Dusk. Không thêm thư viện mới (không Alpine, không Sortable.js, không Jest/Vitest).

**Nguồn sự thật:** `docs/superpowers/specs/2026-07-30-pipeline-drag-drop-design.md` (rev 3, commit `ec3deffb`, đã duyệt). Kế hoạch này **không** thay đổi lại bất kỳ quyết định kiến trúc nào đã khóa trong spec. Nếu một task phát hiện code thực tế mâu thuẫn với giả định trong spec, task đó dừng lại ở bước đối chiếu và ghi rõ xung đột — không tự sửa thiết kế.

## Global Constraints

- Dùng `App\Services\Crm\OpportunityStageTransitionService` — **không** tạo namespace `App\Actions\*`.
- `BOARD_GROUPS` dùng stable string key (`new`, `consulting_survey`, `quote`, `negotiation_contract`, `won`, `lost_nurture`) tách biệt khỏi label hiển thị tiếng Việt.
- Nhóm `lost_nurture` có `requires_choice: true`, `default_entry_stage: null`, và `choice_options` (`lost`/`no_bid`/`nurture` với `requires_reason`/`terminal`).
- Thả/chuyển vào đúng cột nguồn là no-op tuyệt đối — không gọi API, không đổi DOM.
- DOM chỉ đổi **sau** khi backend xác nhận thành công (pessimistic update) — không rollback vì không có gì để rollback.
- Drag handle (`<button class="crm-drag-handle" draggable="true">`) tách biệt khỏi toàn bộ `<li>` card — không đặt `draggable` lên `<li>` (nó còn chứa `<a>` link).
- Click "Chuyển giai đoạn" và kéo-thả đi qua **cùng một** hàm `requestStageTransition()` — không tách 2 luồng logic.
- JSON success của route web (`operator.crm.opportunities.stage` khi `Accept: application/json`) dùng `response()->json([...], 200)` tường minh với shape khóa cứng: `{"message": "...", "data": {"id", "pipeline_stage", "is_terminal"}}`. **Không** thêm `ZenaContractResponseTrait` vào `CrmPageController`.
- Lỗi JS đọc qua đúng 1 hàm `parseErrorResponse()` — không tự viết `if (status === ...)` rải rác ở nơi khác.
- Không thêm Alpine.js, Sortable.js, Jest, Vitest, hay bất kỳ thư viện JS/test-runner mới nào.
- Không hỗ trợ sắp xếp thứ tự trong cùng cột, không thêm `board_position`.
- Không đổi API JSON shape hiện có của `ApiOpportunityController::updateStage` (route `api.zena.crm.opportunities.stage`) — contract cũ phải giữ nguyên sau refactor.
- Không đổi hành vi route form cũ (không có `Accept: application/json`) — vẫn `redirect()->with('success'/'error')` như hiện tại.

---

### Task 1: Baseline — xác nhận contract hiện tại trước khi đổi bất cứ gì

**Files:** không tạo/sửa file nào — chỉ chạy và ghi lại kết quả.

**Interfaces:**
- Consumes: không có.
- Produces: baseline pass/fail counts cho các test task sau phải giữ nguyên xanh (không đổi hành vi ngoài ý muốn). Task 2 phải đối chiếu lại các số này sau khi đổi `BOARD_GROUPS`; Task 3-4 đối chiếu sau khi đổi controller.

- [ ] **Step 1: Xác nhận các test hiện hành liên quan còn tồn tại đúng vị trí đã khảo sát**

Chạy:

```bash
grep -n "function test_can_create_update_and_move_opportunity_stage\|function test_lost_stage_requires_reason\|function test_terminal_opportunity_cannot_change_stage_again" tests/Feature/Api/CrmApiTest.php
grep -n "function test_crm_ui_full_flow_lead_to_project\|function test_crm_actions_denied_without_manage_permission" tests/Feature/Zena/OperatorCrmUiTest.php
grep -n "private const BOARD_GROUPS\|public function index\|public function updateStage" app/Http/Controllers/Web/CrmPageController.php
```

Expected: mỗi grep trả về đúng 1 dòng cho mỗi tên hàm/thuộc tính, số dòng khớp với khảo sát trong spec (`BOARD_GROUPS` dòng 41, `index()` dòng 60, `updateStage()` dòng 444 tại thời điểm viết spec). Nếu số dòng lệch nhiều (code đã trôi), ghi chú lại số dòng mới — không phải lỗi, chỉ là mốc tham chiếu cho các task sau.

- [ ] **Step 2: Baseline — API test cho update stage**

Chạy:

```bash
./vendor/bin/phpunit tests/Feature/Api/CrmApiTest.php --filter "test_can_create_update_and_move_opportunity_stage|test_lost_stage_requires_reason|test_terminal_opportunity_cannot_change_stage_again"
```

Expected: `OK (3 tests, N assertions)` — ghi lại số assertion chính xác vào báo cáo cuối task (dùng làm mốc so sánh cho Task 3-4).

- [ ] **Step 3: Baseline — Web UI flow test (board render + redirect legacy)**

Chạy:

```bash
./vendor/bin/phpunit tests/Feature/Zena/OperatorCrmUiTest.php --filter "test_crm_ui_full_flow_lead_to_project|test_crm_actions_denied_without_manage_permission"
```

Expected: `OK (2 tests, N assertions)`. Test đầu tiên (`test_crm_ui_full_flow_lead_to_project`) là bài kiểm chứng chính cho việc board vẫn hiện đúng ("Pipeline kinh doanh", "Hộp lead") và route `operator.crm.opportunities.stage` vẫn redirect — phải PASS xuyên suốt toàn bộ Task 2-4.

- [ ] **Step 4: Baseline — Vite build**

Chạy:

```bash
npm run build
```

Expected: build thành công (exit code 0), không có warning về module bị thiếu. Đây là baseline để Task 7 đối chiếu sau khi thêm `crm-pipeline-drag.js` vào `vite.config.js`.

- [ ] **Step 5: Ghi lại baseline vào báo cáo**

Không có commit cho task này (không có file thay đổi). Trong báo cáo trả về controller: liệt kê đủ 4 kết quả trên (số dòng, số test pass, số assertion, kết quả build) để Task 2 có mốc đối chiếu.

---

### Task 2: `BOARD_GROUPS` — stable key + 5 invariant test

**Files:**
- Modify: `app/Http/Controllers/Web/CrmPageController.php:41-79` (constant `BOARD_GROUPS` + method `index()`)
- Modify: `resources/views/crm/index.blade.php` (đổi biến vòng lặp từ label sang group key + column label)
- Test: `tests/Unit/Http/Controllers/Web/CrmPageControllerBoardGroupsTest.php` (mới)
- Test: `tests/Feature/Zena/OperatorCrmUiTest.php` (thêm 1 test mới cho board render, không sửa test cũ)

**Interfaces:**
- Consumes: `Opportunity::STAGE_*` constants, `Opportunity::VALID_STAGES` (đã có sẵn, `app/Models/Opportunity.php:22-69`).
- Produces: `CrmPageController::BOARD_GROUPS` cấu trúc mới — mọi task sau (3-8) đọc cấu trúc này qua key ổn định: `$group['label']`, `$group['stages']`, `$group['default_entry_stage']` (`string|null`), `$group['requires_choice']` (`bool`, mặc định `false` nếu không set), `$group['choice_options']` (`list<array{stage:string,label:string,requires_reason:bool,terminal:bool}>|null`). `index()` trả `$board` keyed theo group key (`'new'`, `'consulting_survey'`, ...), mỗi phần tử có thêm `'label'`, `'default_entry_stage'`, `'requires_choice'`, `'choice_options'` bên cạnh `'items'`, `'count'`, `'total_fee'` đã có.

- [ ] **Step 1: Viết test thất bại cho 5 invariant**

Tạo `tests/Unit/Http/Controllers/Web/CrmPageControllerBoardGroupsTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Web;

use App\Http\Controllers\Web\CrmPageController;
use App\Models\Opportunity;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class CrmPageControllerBoardGroupsTest extends TestCase
{
    /** @return array<string, array{label:string,stages:list<string>,default_entry_stage:?string,requires_choice?:bool,choice_options?:list<array{stage:string,label:string,requires_reason:bool,terminal:bool}>}> */
    private function boardGroups(): array
    {
        return (new ReflectionClass(CrmPageController::class))->getConstant('BOARD_GROUPS');
    }

    public function test_group_keys_are_unique(): void
    {
        $keys = array_keys($this->boardGroups());
        $this->assertSame($keys, array_unique($keys));
    }

    public function test_no_stage_belongs_to_more_than_one_group_and_union_matches_valid_stages(): void
    {
        $groups = $this->boardGroups();
        $seen = [];

        foreach ($groups as $groupKey => $group) {
            foreach ($group['stages'] as $stage) {
                $this->assertArrayNotHasKey(
                    $stage,
                    $seen,
                    "Stage '{$stage}' xuất hiện ở cả group '{$seen[$stage] ?? ''}' và '{$groupKey}'."
                );
                $seen[$stage] = $groupKey;
            }
        }

        $union = array_keys($seen);
        sort($union);
        $validStages = Opportunity::VALID_STAGES;
        sort($validStages);
        $this->assertSame($validStages, $union);
    }

    public function test_default_entry_stage_belongs_to_its_own_group_stages(): void
    {
        foreach ($this->boardGroups() as $groupKey => $group) {
            if ($group['default_entry_stage'] === null) {
                continue;
            }
            $this->assertContains(
                $group['default_entry_stage'],
                $group['stages'],
                "Group '{$groupKey}': default_entry_stage không thuộc stages của chính nó."
            );
        }
    }

    public function test_requires_choice_group_has_no_default_entry_stage(): void
    {
        foreach ($this->boardGroups() as $groupKey => $group) {
            if (!empty($group['requires_choice'])) {
                $this->assertNull(
                    $group['default_entry_stage'],
                    "Group '{$groupKey}' có requires_choice=true nhưng vẫn có default_entry_stage — gây mơ hồ target stage."
                );
            }
        }
    }

    public function test_choice_options_stages_belong_to_their_own_group_stages(): void
    {
        foreach ($this->boardGroups() as $groupKey => $group) {
            if (empty($group['choice_options'])) {
                continue;
            }
            foreach ($group['choice_options'] as $option) {
                $this->assertContains(
                    $option['stage'],
                    $group['stages'],
                    "Group '{$groupKey}': choice_option stage '{$option['stage']}' không thuộc stages."
                );
            }
        }
    }
}
```

- [ ] **Step 2: Chạy test, xác nhận thất bại đúng lý do**

Chạy:

```bash
./vendor/bin/phpunit tests/Unit/Http/Controllers/Web/CrmPageControllerBoardGroupsTest.php
```

Expected: FAIL — `test_group_keys_are_unique` thất bại vì `BOARD_GROUPS` hiện tại key theo label tiếng Việt (`'Mới'`, `'Tư vấn / Khảo sát'`, ...) nên `array_keys()` không có `'new'`/`'consulting_survey'`/... , và `getConstant('BOARD_GROUPS')` trả về `array<string,list<string>>` (không có `stages`/`default_entry_stage` con) → các test khác lỗi `Undefined array key "stages"` (TypeError/ArrayAccess lỗi). Đây là thất bại đúng lý do: cấu trúc cũ chưa khớp shape mới.

- [ ] **Step 3: Đổi `BOARD_GROUPS` sang cấu trúc mới**

Trong `app/Http/Controllers/Web/CrmPageController.php`, thay thế toàn bộ khối (dòng 41-47 hiện tại):

```php
    /** Nhóm 14 stage thành cột hiển thị board. */
    private const BOARD_GROUPS = [
        'Mới' => [Opportunity::STAGE_NEW_LEAD, Opportunity::STAGE_QUALIFIED, Opportunity::STAGE_CONTACTED],
        'Tư vấn / Khảo sát' => [Opportunity::STAGE_BRIEF_DISCOVERY, Opportunity::STAGE_SURVEY_OR_INPUTS_RECEIVED, Opportunity::STAGE_SCOPE_DEFINED],
        'Báo giá' => [Opportunity::STAGE_PROPOSAL_DRAFT, Opportunity::STAGE_PROPOSAL_SENT],
        'Đàm phán / Hợp đồng' => [Opportunity::STAGE_NEGOTIATION, Opportunity::STAGE_CONTRACTING],
        'Thắng' => [Opportunity::STAGE_WON],
        'Thua / Nurture' => [Opportunity::STAGE_LOST, Opportunity::STAGE_NO_BID, Opportunity::STAGE_NURTURE],
    ];
```

bằng:

```php
    /**
     * Nhóm 14 stage thành cột hiển thị board. Key là định danh ổn định dùng
     * trong data-board-group / JS — KHÔNG đổi key này, chỉ 'label' mới là
     * chuỗi hiển thị được phép đổi.
     */
    private const BOARD_GROUPS = [
        'new' => [
            'label' => 'Mới',
            'stages' => [Opportunity::STAGE_NEW_LEAD, Opportunity::STAGE_QUALIFIED, Opportunity::STAGE_CONTACTED],
            'default_entry_stage' => Opportunity::STAGE_NEW_LEAD,
        ],
        'consulting_survey' => [
            'label' => 'Tư vấn / Khảo sát',
            'stages' => [Opportunity::STAGE_BRIEF_DISCOVERY, Opportunity::STAGE_SURVEY_OR_INPUTS_RECEIVED, Opportunity::STAGE_SCOPE_DEFINED],
            'default_entry_stage' => Opportunity::STAGE_BRIEF_DISCOVERY,
        ],
        'quote' => [
            'label' => 'Báo giá',
            'stages' => [Opportunity::STAGE_PROPOSAL_DRAFT, Opportunity::STAGE_PROPOSAL_SENT],
            'default_entry_stage' => Opportunity::STAGE_PROPOSAL_DRAFT,
        ],
        'negotiation_contract' => [
            'label' => 'Đàm phán / Hợp đồng',
            'stages' => [Opportunity::STAGE_NEGOTIATION, Opportunity::STAGE_CONTRACTING],
            'default_entry_stage' => Opportunity::STAGE_NEGOTIATION,
        ],
        'won' => [
            'label' => 'Thắng',
            'stages' => [Opportunity::STAGE_WON],
            'default_entry_stage' => Opportunity::STAGE_WON,
        ],
        'lost_nurture' => [
            'label' => 'Thua / Nurture',
            'stages' => [Opportunity::STAGE_LOST, Opportunity::STAGE_NO_BID, Opportunity::STAGE_NURTURE],
            'default_entry_stage' => null,
            'requires_choice' => true,
            'choice_options' => [
                ['stage' => Opportunity::STAGE_LOST, 'label' => 'Thua', 'requires_reason' => true, 'terminal' => true],
                ['stage' => Opportunity::STAGE_NO_BID, 'label' => 'Không tham gia', 'requires_reason' => false, 'terminal' => true],
                ['stage' => Opportunity::STAGE_NURTURE, 'label' => 'Nuôi dưỡng', 'requires_reason' => false, 'terminal' => false],
            ],
        ],
    ];
```

Trong cùng file, method `index()` (dòng ~66-75 hiện tại), thay:

```php
        $board = [];
        foreach (self::BOARD_GROUPS as $label => $stages) {
            $items = $opportunities->whereIn('pipeline_stage', $stages)->values();
            $board[$label] = [
                'items' => $items,
                'count' => $items->count(),
                'total_fee' => (float) $items->sum('estimated_fee'),
            ];
        }
```

bằng:

```php
        $board = [];
        foreach (self::BOARD_GROUPS as $groupKey => $group) {
            $items = $opportunities->whereIn('pipeline_stage', $group['stages'])->values();
            $board[$groupKey] = [
                'label' => $group['label'],
                'items' => $items,
                'count' => $items->count(),
                'total_fee' => (float) $items->sum('estimated_fee'),
                'default_entry_stage' => $group['default_entry_stage'],
                'requires_choice' => $group['requires_choice'] ?? false,
                'choice_options' => $group['choice_options'] ?? null,
            ];
        }
```

- [ ] **Step 4: Chạy lại test invariant, xác nhận pass**

Chạy:

```bash
./vendor/bin/phpunit tests/Unit/Http/Controllers/Web/CrmPageControllerBoardGroupsTest.php
```

Expected: `OK (5 tests, N assertions)`.

- [ ] **Step 5: Cập nhật Blade để không vỡ (view vẫn dùng biến vòng lặp cũ)**

`resources/views/crm/index.blade.php` hiện có:

```blade
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($board as $label => $column)
            <x-ui.card>
                <div class="mb-3 flex items-center justify-between">
                    <span class="font-semibold text-slate-900">{{ $label }}</span>
                    <span class="text-sm text-slate-500">{{ $column['count'] }} · {{ number_format($column['total_fee'], 0, ',', '.') }}₫</span>
                </div>
```

Đổi thành (chỉ đổi biến vòng lặp `$label` → `$groupKey`, và nguồn label hiển thị từ `$column['label']` — **chưa** thêm `data-board-group`/`data-*` khác, việc đó thuộc Task 5):

```blade
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($board as $groupKey => $column)
            <x-ui.card>
                <div class="mb-3 flex items-center justify-between">
                    <span class="font-semibold text-slate-900">{{ $column['label'] }}</span>
                    <span class="text-sm text-slate-500">{{ $column['count'] }} · {{ number_format($column['total_fee'], 0, ',', '.') }}₫</span>
                </div>
```

Phần còn lại của file (danh sách `<li>` cơ hội bên trong) giữ nguyên y hệt — Task 5 mới sửa phần đó.

- [ ] **Step 6: Viết test render mới xác nhận board vẫn hiện đúng 6 label**

Thêm vào `tests/Feature/Zena/OperatorCrmUiTest.php` (không sửa test hiện có), đặt sau `test_crm_ui_full_flow_lead_to_project`:

```php
    public function test_crm_index_renders_all_six_board_group_labels(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->get(route('operator.crm.index'), $headers)
            ->assertOk()
            ->assertSee('Mới')
            ->assertSee('Tư vấn / Khảo sát')
            ->assertSee('Báo giá')
            ->assertSee('Đàm phán / Hợp đồng')
            ->assertSee('Thắng')
            ->assertSee('Thua / Nurture');
    }
```

- [ ] **Step 7: Chạy toàn bộ test liên quan, xác nhận không vỡ baseline Task 1**

Chạy:

```bash
./vendor/bin/phpunit tests/Unit/Http/Controllers/Web/CrmPageControllerBoardGroupsTest.php tests/Feature/Zena/OperatorCrmUiTest.php --filter "test_crm_ui_full_flow_lead_to_project|test_crm_actions_denied_without_manage_permission|test_crm_index_renders_all_six_board_group_labels"
```

Expected: `OK` — đặc biệt `test_crm_ui_full_flow_lead_to_project` (bài kiểm chứng board + redirect legacy) phải vẫn pass y hệt baseline Task 1, không đổi số assertion.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Web/CrmPageController.php resources/views/crm/index.blade.php tests/Unit/Http/Controllers/Web/CrmPageControllerBoardGroupsTest.php tests/Feature/Zena/OperatorCrmUiTest.php
git commit -m "refactor(crm): stable board group keys with default_entry_stage + choice_options"
```

---

### Task 3: `OpportunityStageTransitionService` — trích logic, không đổi hành vi

**Files:**
- Create: `app/Services/Crm/OpportunityStageTransitionService.php`
- Test: `tests/Unit/Services/Crm/OpportunityStageTransitionServiceTest.php` (mới)

**Interfaces:**
- Consumes: `App\Models\Opportunity` (`VALID_STAGES`, `isTerminal()`, `pipeline_stage`, `lost_reason`, `forecast_category`), `App\Models\User`, `App\Models\EventRecord`, `App\Policies\OpportunityPolicy::update()` (đã có, không đổi).
- Produces: `OpportunityStageTransitionService::transition(User $actor, Opportunity $opportunity, string $toStage, ?string $lostReason): Opportunity` — ném `\Illuminate\Auth\Access\AuthorizationException` nếu actor không có quyền, ném `\Illuminate\Validation\ValidationException` nếu stage không hợp lệ / opportunity terminal / thiếu `lost_reason` khi chuyển `lost`. Task 4 gọi method này từ cả 2 controller.

- [ ] **Step 1: Viết test thất bại cho service (class chưa tồn tại)**

Tạo `tests/Unit/Services/Crm/OpportunityStageTransitionServiceTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Services\Crm;

use App\Models\Account;
use App\Models\EventRecord;
use App\Models\Opportunity;
use App\Models\Tenant;
use App\Services\Crm\OpportunityStageTransitionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class OpportunityStageTransitionServiceTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private function makeOpportunity(Tenant $tenant, array $overrides = []): Opportunity
    {
        $manager = $this->createTenantUser($tenant, [], ['admin'], ['crm.manage']);

        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khách hàng test',
            'status' => Account::STATUS_ACTIVE,
        ]);

        return Opportunity::query()->create(array_merge([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Cơ hội test',
            'service_category' => 'architecture',
            'pipeline_stage' => Opportunity::STAGE_NEW_LEAD,
            'sales_owner_id' => (string) $manager->id,
            'created_by' => (string) $manager->id,
        ], $overrides));
    }

    public function test_transition_updates_stage_and_records_event(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->createTenantUser($tenant, [], ['admin'], ['crm.manage']);
        $opportunity = $this->makeOpportunity($tenant);

        $updated = (new OpportunityStageTransitionService())->transition(
            $actor,
            $opportunity,
            Opportunity::STAGE_QUALIFIED,
            null
        );

        $this->assertSame(Opportunity::STAGE_QUALIFIED, $updated->pipeline_stage);
        $this->assertDatabaseHas('event_records', [
            'aggregate_id' => (string) $opportunity->id,
            'event_key' => 'crm.opportunity.stage_changed',
        ]);
    }

    public function test_transition_rejects_actor_without_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->createTenantUser($tenant, [], ['team_member'], ['crm.view']);
        $opportunity = $this->makeOpportunity($tenant);

        $this->expectException(AuthorizationException::class);

        (new OpportunityStageTransitionService())->transition(
            $actor,
            $opportunity,
            Opportunity::STAGE_QUALIFIED,
            null
        );
    }

    public function test_transition_rejects_actor_from_another_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $actorFromB = $this->createTenantUser($tenantB, [], ['admin'], ['crm.manage']);
        $opportunity = $this->makeOpportunity($tenantA);

        $this->expectException(AuthorizationException::class);

        (new OpportunityStageTransitionService())->transition(
            $actorFromB,
            $opportunity,
            Opportunity::STAGE_QUALIFIED,
            null
        );
    }

    public function test_transition_rejects_invalid_stage(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->createTenantUser($tenant, [], ['admin'], ['crm.manage']);
        $opportunity = $this->makeOpportunity($tenant);

        $this->expectException(ValidationException::class);

        (new OpportunityStageTransitionService())->transition(
            $actor,
            $opportunity,
            'not_a_real_stage',
            null
        );
    }

    public function test_transition_blocks_terminal_opportunity(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->createTenantUser($tenant, [], ['admin'], ['crm.manage']);
        $opportunity = $this->makeOpportunity($tenant, ['pipeline_stage' => Opportunity::STAGE_WON]);

        $this->expectException(ValidationException::class);

        (new OpportunityStageTransitionService())->transition(
            $actor,
            $opportunity,
            Opportunity::STAGE_QUALIFIED,
            null
        );
    }

    public function test_transition_to_lost_requires_reason(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->createTenantUser($tenant, [], ['admin'], ['crm.manage']);
        $opportunity = $this->makeOpportunity($tenant);

        $this->expectException(ValidationException::class);

        (new OpportunityStageTransitionService())->transition(
            $actor,
            $opportunity,
            Opportunity::STAGE_LOST,
            null
        );
    }

    public function test_transition_to_lost_with_reason_sets_lost_reason_and_forecast_category(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->createTenantUser($tenant, [], ['admin'], ['crm.manage']);
        $opportunity = $this->makeOpportunity($tenant);

        $updated = (new OpportunityStageTransitionService())->transition(
            $actor,
            $opportunity,
            Opportunity::STAGE_LOST,
            'Khách chọn đối thủ khác'
        );

        $this->assertSame(Opportunity::STAGE_LOST, $updated->pipeline_stage);
        $this->assertSame('Khách chọn đối thủ khác', $updated->lost_reason);
        $this->assertSame('closed_lost', $updated->forecast_category);
    }

    public function test_transition_to_nurture_is_not_terminal(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->createTenantUser($tenant, [], ['admin'], ['crm.manage']);
        $opportunity = $this->makeOpportunity($tenant);

        $updated = (new OpportunityStageTransitionService())->transition(
            $actor,
            $opportunity,
            Opportunity::STAGE_NURTURE,
            null
        );

        $this->assertSame(Opportunity::STAGE_NURTURE, $updated->pipeline_stage);
        $this->assertFalse($updated->isTerminal());
    }
}
```

- [ ] **Step 2: Chạy test, xác nhận thất bại đúng lý do**

Chạy:

```bash
./vendor/bin/phpunit tests/Unit/Services/Crm/OpportunityStageTransitionServiceTest.php
```

Expected: FAIL — `Class "App\Services\Crm\OpportunityStageTransitionService" not found`.

- [ ] **Step 3: Tạo service — bê nguyên logic từ `ApiOpportunityController::updateStage` (dòng 306-345 hiện tại), không đổi hành vi**

Tạo `app/Services/Crm/OpportunityStageTransitionService.php`:

```php
<?php declare(strict_types=1);

namespace App\Services\Crm;

use App\Models\EventRecord;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class OpportunityStageTransitionService
{
    /**
     * @throws \Illuminate\Auth\Access\AuthorizationException nếu $actor không có quyền update $opportunity
     * @throws ValidationException nếu $toStage không hợp lệ, opportunity đã terminal, hoặc thiếu lost_reason khi chuyển sang lost
     */
    public function transition(User $actor, Opportunity $opportunity, string $toStage, ?string $lostReason): Opportunity
    {
        Gate::forUser($actor)->authorize('update', $opportunity);

        if (!in_array($toStage, Opportunity::VALID_STAGES, true)) {
            throw ValidationException::withMessages(['pipeline_stage' => ['Giai đoạn không hợp lệ.']]);
        }

        if ($opportunity->isTerminal()) {
            throw ValidationException::withMessages([
                'pipeline_stage' => ['Won/lost/no-bid opportunities can no longer change stage.'],
            ]);
        }

        if ($toStage === Opportunity::STAGE_LOST && trim((string) $lostReason) === '') {
            throw ValidationException::withMessages(['lost_reason' => ['Vui lòng nhập lý do khi chuyển sang Thua.']]);
        }

        $from = (string) $opportunity->pipeline_stage;
        $opportunity->pipeline_stage = $toStage;
        $opportunity->lost_reason = $toStage === Opportunity::STAGE_LOST ? (string) $lostReason : null;

        if ($toStage === Opportunity::STAGE_WON) {
            $opportunity->forecast_category = 'closed_won';
        } elseif (in_array($toStage, [Opportunity::STAGE_LOST, Opportunity::STAGE_NO_BID], true)) {
            $opportunity->forecast_category = 'closed_lost';
        }

        $opportunity->save();

        EventRecord::query()->create([
            'tenant_id' => (string) $opportunity->tenant_id,
            'project_id' => $opportunity->converted_project_id,
            'aggregate_type' => 'opportunity',
            'aggregate_id' => (string) $opportunity->id,
            'event_key' => 'crm.opportunity.stage_changed',
            'actor_user_id' => (string) $actor->id,
            'payload' => ['from' => $from, 'to' => $toStage],
            'occurred_at' => now(),
        ]);

        return $opportunity->fresh() ?? $opportunity;
    }
}
```

- [ ] **Step 4: Chạy lại test, xác nhận pass**

Chạy:

```bash
./vendor/bin/phpunit tests/Unit/Services/Crm/OpportunityStageTransitionServiceTest.php
```

Expected: `OK (8 tests, N assertions)`.

- [ ] **Step 5: Chạy lại toàn bộ baseline API test từ Task 1 — service CHƯA được gọi bởi controller nào, chỉ xác nhận chưa vỡ gì**

Chạy:

```bash
./vendor/bin/phpunit tests/Feature/Api/CrmApiTest.php --filter "test_can_create_update_and_move_opportunity_stage|test_lost_stage_requires_reason|test_terminal_opportunity_cannot_change_stage_again"
```

Expected: `OK` — số assertion phải khớp y hệt baseline Task 1 (service mới hoàn toàn cô lập, `ApiOpportunityController` chưa đổi ở task này).

- [ ] **Step 6: Commit**

```bash
git add app/Services/Crm/OpportunityStageTransitionService.php tests/Unit/Services/Crm/OpportunityStageTransitionServiceTest.php
git commit -m "feat(crm): extract OpportunityStageTransitionService (not yet wired into controllers)"
```

---

### Task 4: Controller integration — content negotiation, JSON shape khóa cứng

**Files:**
- Modify: `app/Http/Controllers/Api/OpportunityController.php:287-346` (`updateStage`)
- Modify: `app/Http/Controllers/Web/CrmPageController.php:444-459` (`updateStage`)
- Test: `tests/Feature/Api/CrmApiTest.php` (không sửa test cũ — 3 test dòng 181/228/239 phải vẫn pass nguyên trạng để chứng minh contract không đổi)
- Test: `tests/Feature/Zena/OperatorCrmUiTest.php` (thêm test JSON path mới, không sửa `test_crm_ui_full_flow_lead_to_project`)

**Interfaces:**
- Consumes: `OpportunityStageTransitionService::transition()` (Task 3).
- Produces: `CrmPageController::updateStage` trả JSON `{"message": string, "data": {"id": string, "pipeline_stage": string, "is_terminal": bool}}` khi `$request->wantsJson()`, redirect như cũ khi không. Task 5 (Blade) và Task 6 (JS) đều phụ thuộc đúng shape này.

- [ ] **Step 1: Viết test thất bại cho JSON path mới (web route)**

Thêm vào `tests/Feature/Zena/OperatorCrmUiTest.php`, sau `test_crm_index_renders_all_six_board_group_labels` (thêm ở Task 2):

```php
    public function test_update_stage_returns_json_shape_when_ajax(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];
        $account = \App\Models\Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'display_name' => 'Khách hàng AJAX test',
        ]);
        $opportunity = Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Cơ hội AJAX test',
            'pipeline_stage' => Opportunity::STAGE_NEW_LEAD,
            'sales_owner_id' => (string) $this->user->id,
            'created_by' => (string) $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('operator.crm.opportunities.stage', $opportunity->id), [
                'pipeline_stage' => Opportunity::STAGE_QUALIFIED,
            ], $headers);

        $response->assertOk();
        $response->assertJson([
            'message' => 'Đã cập nhật giai đoạn.',
            'data' => [
                'id' => (string) $opportunity->id,
                'pipeline_stage' => Opportunity::STAGE_QUALIFIED,
                'is_terminal' => false,
            ],
        ]);
        $this->assertArrayNotHasKey('success', $response->json());
        $this->assertArrayNotHasKey('status', $response->json());
    }

    public function test_update_stage_json_returns_403_when_permission_missing(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];
        $viewer = $this->createTenantUser($this->tenant, [], ['crm_viewer'], ['crm.view']);
        $account = \App\Models\Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'display_name' => 'Khách hàng 403 test',
        ]);
        $opportunity = Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Cơ hội 403 test',
            'pipeline_stage' => Opportunity::STAGE_NEW_LEAD,
            'sales_owner_id' => (string) $viewer->id,
            'created_by' => (string) $viewer->id,
        ]);

        $this->actingAs($viewer)
            ->postJson(route('operator.crm.opportunities.stage', $opportunity->id), [
                'pipeline_stage' => Opportunity::STAGE_QUALIFIED,
            ], $headers)
            ->assertStatus(403);
    }

    public function test_update_stage_json_returns_422_when_lost_reason_missing(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];
        $account = \App\Models\Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'display_name' => 'Khách hàng 422 test',
        ]);
        $opportunity = Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Cơ hội 422 test',
            'pipeline_stage' => Opportunity::STAGE_NEW_LEAD,
            'sales_owner_id' => (string) $this->user->id,
            'created_by' => (string) $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->postJson(route('operator.crm.opportunities.stage', $opportunity->id), [
                'pipeline_stage' => Opportunity::STAGE_LOST,
            ], $headers)
            ->assertStatus(422);
    }

    public function test_update_stage_json_blocks_terminal_transition(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];
        $account = \App\Models\Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'display_name' => 'Khách hàng terminal test',
        ]);
        $opportunity = Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Cơ hội terminal test',
            'pipeline_stage' => Opportunity::STAGE_WON,
            'sales_owner_id' => (string) $this->user->id,
            'created_by' => (string) $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->postJson(route('operator.crm.opportunities.stage', $opportunity->id), [
                'pipeline_stage' => Opportunity::STAGE_QUALIFIED,
            ], $headers)
            ->assertStatus(422);

        $opportunity->refresh();
        $this->assertSame(Opportunity::STAGE_WON, $opportunity->pipeline_stage);
    }
```

- [ ] **Step 2: Chạy test, xác nhận thất bại đúng lý do**

Chạy:

```bash
./vendor/bin/phpunit tests/Feature/Zena/OperatorCrmUiTest.php --filter "test_update_stage_returns_json_shape_when_ajax|test_update_stage_json_returns_403_when_permission_missing|test_update_stage_json_returns_422_when_lost_reason_missing|test_update_stage_json_blocks_terminal_transition"
```

Expected: FAIL trên `test_update_stage_returns_json_shape_when_ajax` — `CrmPageController::updateStage` hiện luôn trả `RedirectResponse` (không có nhánh JSON), `postJson()` của test nhận về HTML redirect thay vì JSON → `assertOk()`/`assertJson()` thất bại. 3 test còn lại (403/422/terminal) có thể vẫn "pass tình cờ" theo status code cũ (proxy hiện tại vẫn trả JSON qua `ApiOpportunityController::updateStage` được gọi nội bộ) — ghi chú rõ trong báo cáo nếu bất kỳ test nào trong 3 test đó pass ngay từ bước RED, đó không phải lỗi kế hoạch, chỉ là hành vi cũ (proxy) tình cờ đã đúng status code; bước GREEN vẫn cần thiết vì luồng nội bộ đổi hẳn cách proxy hoạt động.

- [ ] **Step 3: Đổi `ApiOpportunityController::updateStage` để dùng service (giữ nguyên response shape hiện tại)**

Trong `app/Http/Controllers/Api/OpportunityController.php`, thêm import ở đầu file (sau `use App\Models\Project;` dòng ~11):

```php
use App\Services\Crm\OpportunityStageTransitionService;
```

Thay toàn bộ thân method `updateStage` (dòng 287-346 hiện tại) — từ đoạn validate trở đi (giữ nguyên phần fetch-scoped + authorize + not-found ở đầu method, dòng 287-304):

```php
        $validator = Validator::make($request->all(), [
            'pipeline_stage' => ['required', Rule::in(Opportunity::VALID_STAGES)],
            'lost_reason' => [
                'required_if:pipeline_stage,' . Opportunity::STAGE_LOST,
                'nullable', 'string', 'max:500',
            ],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        if ($opportunity->isTerminal()) {
            return $this->validationError([
                'pipeline_stage' => ['Won/lost/no-bid opportunities can no longer change stage.'],
            ]);
        }

        $from = (string) $opportunity->pipeline_stage;
        $to = (string) $request->input('pipeline_stage');

        $opportunity->pipeline_stage = $to;
        $opportunity->lost_reason = $to === Opportunity::STAGE_LOST
            ? (string) $request->input('lost_reason')
            : null;

        if ($to === Opportunity::STAGE_WON) {
            $opportunity->forecast_category = 'closed_won';
        } elseif (in_array($to, [Opportunity::STAGE_LOST, Opportunity::STAGE_NO_BID], true)) {
            $opportunity->forecast_category = 'closed_lost';
        }

        $opportunity->save();

        $this->recordEvent($opportunity, 'crm.opportunity.stage_changed', ['from' => $from, 'to' => $to]);

        return $this->zenaSuccessResponse(
            $this->serialize($opportunity->fresh() ?? $opportunity),
            'Opportunity stage updated successfully'
        );
```

bằng:

```php
        $validator = Validator::make($request->all(), [
            'pipeline_stage' => ['required', 'string'],
            'lost_reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            $opportunity = app(OpportunityStageTransitionService::class)->transition(
                $request->user(),
                $opportunity,
                (string) $request->input('pipeline_stage'),
                $request->input('lost_reason')
            );
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return $this->validationError($exception->errors());
        }

        return $this->zenaSuccessResponse(
            $this->serialize($opportunity),
            'Opportunity stage updated successfully'
        );
```

Lưu ý: validation `pipeline_stage` không còn `Rule::in(Opportunity::VALID_STAGES)` ở tầng controller — service tự kiểm tra và ném `ValidationException` với message tương đương (`'Giai đoạn không hợp lệ.'` thay vì message mặc định của `Rule::in`). Đây là thay đổi message lỗi (không phải status code) cho trường hợp stage rác — chấp nhận được vì không có test nào ở baseline Task 1 assert message cụ thể cho case này (chỉ assert status 422). Nếu Step 4 phát hiện có test khác assert message cụ thể, dừng lại và báo cáo xung đột thay vì tự đổi thêm.

- [ ] **Step 4: Chạy lại baseline API test — xác nhận contract không đổi**

Chạy:

```bash
./vendor/bin/phpunit tests/Feature/Api/CrmApiTest.php
```

Expected: `OK` — toàn bộ file, không riêng 3 test liên quan (chạy cả file vì đổi 1 method trong `ApiOpportunityController` có thể ảnh hưởng import/autoload của file). Số assertion của 3 test `test_can_create_update_and_move_opportunity_stage`/`test_lost_stage_requires_reason`/`test_terminal_opportunity_cannot_change_stage_again` phải khớp y hệt baseline Task 1.

- [ ] **Step 5: Đổi `CrmPageController::updateStage` — bỏ proxy, tự fetch + gọi service + content-negotiate**

Trong `app/Http/Controllers/Web/CrmPageController.php`, thêm import (cạnh các `use App\Services\...` hiện có, ví dụ sau `use App\Services\AiAssistService;`):

```php
use App\Services\Crm\OpportunityStageTransitionService;
```

Thay toàn bộ method `updateStage` (dòng 444-459 hiện tại):

```php
    public function updateStage(Request $request, string $id, ApiOpportunityController $apiController): RedirectResponse
    {
        $validated = $request->validate([
            'pipeline_stage' => ['required', 'string'],
            'lost_reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $response = $apiController->updateStage($this->buildApiRequest($request, array_filter($validated, fn ($value) => $value !== null)), $id);
        } catch (AuthorizationException) {
            return back()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->with('error', 'Không thể xử lý yêu cầu.');
        }

        return $this->handleMutationResponse($response, url()->previous(), 'Đã chuyển giai đoạn');
    }
```

bằng (đổi kiểu trả về sang `RedirectResponse|JsonResponse` vì giờ có 2 nhánh; `JsonResponse` đã có sẵn trong `use` list của file — dòng `use Illuminate\Http\JsonResponse;`):

```php
    public function updateStage(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'pipeline_stage' => ['required', 'string'],
            'lost_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $tenantId = $this->tenantId();
        $opportunity = Opportunity::query()->forTenant($tenantId)->findOrFail($id);

        try {
            $opportunity = app(OpportunityStageTransitionService::class)->transition(
                Auth::user(),
                $opportunity,
                (string) $validated['pipeline_stage'],
                $validated['lost_reason'] ?? null
            );
        } catch (AuthorizationException) {
            if ($request->wantsJson()) {
                throw new AuthorizationException('Bạn không có quyền thực hiện thao tác này.');
            }
            return back()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            if ($request->wantsJson()) {
                throw $exception;
            }
            return back()->withErrors($exception->errors())->withInput();
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Đã cập nhật giai đoạn.',
                'data' => [
                    'id' => $opportunity->id,
                    'pipeline_stage' => $opportunity->pipeline_stage,
                    'is_terminal' => $opportunity->isTerminal(),
                ],
            ], 200);
        }

        return back()->with('success', 'Đã chuyển giai đoạn');
    }
```

`throw new AuthorizationException(...)`/`throw $exception` (thay vì tự `response()->json(...)`) để tận dụng đúng cơ chế Laravel mặc định đã khảo sát trong spec (JSON tự động khi `expectsJson()`, status 403/422 đúng chuẩn) — nhất quán với ghi chú "không giả định shape cố định" ở spec, không tự chế thêm 1 shape lỗi thứ 3.

Route `routes/web.php:1016` hiện truyền `ApiOpportunityController $apiController` làm tham số thứ 3 cho `updateStage` qua route-model-binding tự động của Laravel container — kiểm tra route definition **không** cần đổi (`Route::post(...)` không khai tham số cụ thể, Laravel tự inject theo type-hint của method), nhưng vì method không còn nhận `ApiOpportunityController $apiController` nữa, Laravel container đơn giản là không inject gì thêm — không cần sửa `routes/web.php:1016`.

- [ ] **Step 6: Chạy lại test JSON path mới + toàn bộ Web UI test**

Chạy:

```bash
./vendor/bin/phpunit tests/Feature/Zena/OperatorCrmUiTest.php
```

Expected: `OK` toàn bộ file — bao gồm `test_crm_ui_full_flow_lead_to_project` (redirect legacy, phải còn `assertRedirect()` đúng như cũ) và 4 test JSON mới ở Step 1.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/OpportunityController.php app/Http/Controllers/Web/CrmPageController.php tests/Feature/Zena/OperatorCrmUiTest.php
git commit -m "refactor(crm): wire both controllers to OpportunityStageTransitionService, add JSON path to web route"
```

---

### Task 5: Blade render contract — metadata cho card/column, dialog

**Files:**
- Modify: `resources/views/crm/index.blade.php` (toàn bộ khối card + thêm dialog)
- Test: `tests/Feature/Zena/OperatorCrmUiTest.php` (thêm test render contract, không sửa test cũ)

**Interfaces:**
- Consumes: `$board` (Task 2 — mỗi phần tử có `label`, `items`, `count`, `total_fee`, `default_entry_stage`, `requires_choice`, `choice_options`), `Opportunity::isTerminal()`, `$opportunity->estimated_fee`.
- Produces: DOM contract mà `crm-pipeline-drag.js` (Task 6) phụ thuộc: `data-board-group`, `data-column-label`, `data-requires-choice`, `data-default-entry-stage`, `data-choice-options`, `data-column-count`, `data-column-total`, `data-opportunity-id`, `data-current-stage`, `data-terminal`, `data-amount`, class `.crm-drag-handle`, `.crm-stage-transition-btn`, `[data-crm-stage-dialog]`, `.crm-dialog-group-option`.

- [ ] **Step 1: Viết test thất bại cho render contract**

Thêm vào `tests/Feature/Zena/OperatorCrmUiTest.php`, sau `test_update_stage_json_blocks_terminal_transition` (Task 4):

```php
    public function test_crm_index_renders_drag_drop_dom_contract(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];
        $account = \App\Models\Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'display_name' => 'Khách hàng contract test',
        ]);
        $normalOpportunity = Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Cơ hội thường',
            'pipeline_stage' => Opportunity::STAGE_NEW_LEAD,
            'estimated_fee' => 1250000000,
            'sales_owner_id' => (string) $this->user->id,
            'created_by' => (string) $this->user->id,
        ]);
        $terminalOpportunity = Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Cơ hội đã thắng',
            'pipeline_stage' => Opportunity::STAGE_WON,
            'estimated_fee' => 500000000,
            'sales_owner_id' => (string) $this->user->id,
            'created_by' => (string) $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('operator.crm.index'), $headers);

        $response->assertOk();
        $html = $response->getContent();

        // Card thường: đủ data attribute, có handle + nút chuyển giai đoạn
        $this->assertStringContainsString('data-opportunity-id="' . $normalOpportunity->id . '"', $html);
        $this->assertStringContainsString('data-current-stage="' . Opportunity::STAGE_NEW_LEAD . '"', $html);
        $this->assertStringContainsString('data-terminal="0"', $html);
        $this->assertStringContainsString('data-amount="1250000000"', $html);
        $this->assertStringContainsString('crm-drag-handle', $html);
        $this->assertStringContainsString('crm-stage-transition-btn', $html);

        // Card terminal: data-terminal=1, KHÔNG có handle/nút trong phạm vi thẻ đó
        $this->assertStringContainsString('data-opportunity-id="' . $terminalOpportunity->id . '"', $html);
        $this->assertStringContainsString('data-terminal="1"', $html);

        // Cột dùng stable key, không phải label
        $this->assertStringContainsString('data-board-group="new"', $html);
        $this->assertStringContainsString('data-board-group="lost_nurture"', $html);
        $this->assertStringContainsString('data-default-entry-stage="' . Opportunity::STAGE_NEW_LEAD . '"', $html);
        $this->assertStringContainsString('data-requires-choice="1"', $html);
        $this->assertStringContainsString('data-choice-options="', $html);

        // Dialog dùng chung
        $this->assertStringContainsString('data-crm-stage-dialog', $html);
        $this->assertStringContainsString('crm-dialog-group-option', $html);
    }

    public function test_crm_index_terminal_card_has_no_drag_handle_or_transition_button(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];
        $account = \App\Models\Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'display_name' => 'Khách hàng terminal-only test',
        ]);
        $terminalOpportunity = Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Cơ hội đã thua',
            'pipeline_stage' => Opportunity::STAGE_LOST,
            'lost_reason' => 'Test',
            'sales_owner_id' => (string) $this->user->id,
            'created_by' => (string) $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('operator.crm.index'), $headers);

        $html = $response->getContent();
        $cardStart = strpos($html, 'data-opportunity-id="' . $terminalOpportunity->id . '"');
        $this->assertNotFalse($cardStart);

        // Cắt riêng đoạn HTML của thẻ này (đến </li> gần nhất) để không lẫn với thẻ khác
        $liEnd = strpos($html, '</li>', $cardStart);
        $cardHtml = substr($html, $cardStart, $liEnd - $cardStart);

        $this->assertStringNotContainsString('crm-drag-handle', $cardHtml);
        $this->assertStringNotContainsString('crm-stage-transition-btn', $cardHtml);
    }
```

- [ ] **Step 2: Chạy test, xác nhận thất bại đúng lý do**

Chạy:

```bash
./vendor/bin/phpunit tests/Feature/Zena/OperatorCrmUiTest.php --filter "test_crm_index_renders_drag_drop_dom_contract|test_crm_index_terminal_card_has_no_drag_handle_or_transition_button"
```

Expected: FAIL — không có `data-opportunity-id`, `data-board-group`, `crm-drag-handle`, `data-crm-stage-dialog` nào trong HTML hiện tại (view Task 2 chỉ đổi label, chưa thêm metadata này).

- [ ] **Step 3: Viết lại toàn bộ khối card + column trong `resources/views/crm/index.blade.php`**

Thay toàn bộ file (nội dung sau `@section('content')` trở đi, giữ nguyên `<x-ui.page-header>` ở đầu):

```blade
@extends('layouts.operator')

@section('title', 'CRM — Pipeline')
@section('page_title', 'CRM — Pipeline')

@section('content')
    <x-ui.page-header
        title="Pipeline kinh doanh"
        description="Cơ hội theo giai đoạn — từ lead mới đến ký hợp đồng."
    >
        <x-ui.button-link :href="route('operator.crm.leads')" variant="secondary">
            Hộp lead @if($newLeadCount > 0)({{ $newLeadCount }} mới)@endif
        </x-ui.button-link>
        <x-ui.button-link :href="route('operator.crm.accounts')" variant="secondary">Khách hàng</x-ui.button-link>
    </x-ui.page-header>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($board as $groupKey => $column)
            <x-ui.card
                data-board-group="{{ $groupKey }}"
                data-column-label="{{ $column['label'] }}"
                data-requires-choice="{{ $column['requires_choice'] ? '1' : '0' }}"
                data-default-entry-stage="{{ $column['default_entry_stage'] ?? '' }}"
                @if ($column['choice_options'])
                    data-choice-options="{{ json_encode($column['choice_options']) }}"
                @endif
            >
                <div class="mb-3 flex items-center justify-between">
                    <span class="font-semibold text-slate-900">{{ $column['label'] }}</span>
                    <span class="text-sm text-slate-500">
                        <span data-column-count>{{ $column['count'] }}</span> ·
                        <span data-column-total>{{ number_format($column['total_fee'], 0, ',', '.') }}₫</span>
                    </span>
                </div>

                @if ($column['items']->isEmpty())
                    <p class="text-sm text-slate-400" data-column-empty>Trống</p>
                @else
                    <ul class="space-y-2">
                        @foreach ($column['items'] as $opportunity)
                            <li class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2"
                                data-opportunity-id="{{ $opportunity->id }}"
                                data-current-stage="{{ $opportunity->pipeline_stage }}"
                                data-terminal="{{ $opportunity->isTerminal() ? '1' : '0' }}"
                                data-amount="{{ (int) ($opportunity->estimated_fee ?? 0) }}"
                            >
                                <div class="flex items-start gap-2">
                                    @if (!$opportunity->isTerminal())
                                        <button type="button" class="crm-drag-handle" draggable="true" aria-label="Kéo để chuyển giai đoạn">⋮⋮</button>
                                    @endif
                                    <div class="flex-1">
                                        <a href="{{ route('operator.crm.opportunities.show', $opportunity->id) }}" class="operator-link font-medium">
                                            {{ $opportunity->opportunity_name }}
                                        </a>
                                        <div class="text-xs text-slate-500">
                                            {{ $opportunity->account?->display_name ?? '—' }}
                                            · {{ $opportunity->salesOwner?->name ?? 'Chưa gán' }}
                                            @if ($opportunity->estimated_fee)
                                                · {{ number_format((float) $opportunity->estimated_fee, 0, ',', '.') }}₫
                                            @endif
                                        </div>
                                        @if (!$opportunity->isTerminal())
                                            <button type="button" class="crm-stage-transition-btn text-xs operator-link">Chuyển giai đoạn</button>
                                        @endif
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        @endforeach
    </div>

    <dialog data-crm-stage-dialog>
        <form method="dialog">
            <p>Chuyển "<span data-dialog-opportunity-name></span>" sang:</p>

            <div data-dialog-group-picker>
                @foreach ($board as $groupKey => $column)
                    <button type="button" class="crm-dialog-group-option" data-group="{{ $groupKey }}">
                        {{ $column['label'] }}
                    </button>
                @endforeach
            </div>

            <div data-dialog-choice-picker class="hidden"></div>

            <textarea data-dialog-reason placeholder="Lý do (bắt buộc nếu chọn Thua)" class="hidden"></textarea>
            <button type="button" data-dialog-cancel>Hủy</button>
            <button type="button" data-dialog-confirm disabled>Xác nhận</button>
        </form>
    </dialog>
@endsection
```

- [ ] **Step 4: Chạy lại test render contract, xác nhận pass**

Chạy:

```bash
./vendor/bin/phpunit tests/Feature/Zena/OperatorCrmUiTest.php --filter "test_crm_index_renders_drag_drop_dom_contract|test_crm_index_terminal_card_has_no_drag_handle_or_transition_button"
```

Expected: `OK (2 tests, N assertions)`.

- [ ] **Step 5: Chạy toàn bộ `OperatorCrmUiTest` — xác nhận không vỡ các test trước đó trong file (đặc biệt các test dùng `assertSee` trên nội dung card)**

Chạy:

```bash
./vendor/bin/phpunit tests/Feature/Zena/OperatorCrmUiTest.php
```

Expected: `OK` toàn bộ file.

- [ ] **Step 6: Commit**

```bash
git add resources/views/crm/index.blade.php tests/Feature/Zena/OperatorCrmUiTest.php
git commit -m "feat(crm): render drag-drop DOM contract (data attributes, drag handle, shared dialog)"
```

---

### Task 6: Vanilla JS module — `crm-pipeline-drag.js`

**Files:**
- Create: `resources/js/crm-pipeline-drag.js`

**Interfaces:**
- Consumes: DOM contract từ Task 5 (`data-board-group`, `data-opportunity-id`, v.v.), route `POST /crm/opportunities/{id}/stage` (Task 4), `<meta name="csrf-token">` (có sẵn ở `layouts/operator.blade.php:6`).
- Produces: hàm `initializePipelineDragDrop()` tự chạy khi `DOMContentLoaded` (theo pattern IIFE của `work-template-apply.js`). Task 7 chỉ cần import file này qua Vite, không cần biết chi tiết bên trong.

Vì đây là JS thuần không có bộ test runner (đã khảo sát và chốt ở spec — không thêm Jest/Vitest), task này **không theo chu trình RED/GREEN bằng PHPUnit**. Thay vào đó: viết Dusk test trước (Task 8) rồi tới lượt viết module này để làm Dusk pass là cách "test-first" khả thi duy nhất với constraint hiện tại — nhưng vì Dusk cần cả JS module lẫn build asset (Task 7) mới chạy được, thứ tự thực dụng là: viết đủ toàn bộ 10 hàm ở task này trước (dựa 100% trên spec đã duyệt, không có logic mới phát sinh), sau đó Task 7 build asset, Task 8 mới viết Dusk test thật để xác nhận toàn bộ chuỗi hoạt động — vi phạm "test trước" ở cấp module JS đơn lẻ, nhưng tuân thủ ở cấp tính năng (Dusk test là bài kiểm tra thật duy nhất có thể chạy được cho JS trong repo này, và Task 8 viết trước khi Task 6-7 được coi là "hoàn tất" — xem tiêu chí hoàn thành cuối task 8).

- [ ] **Step 1: Tạo file, chia theo đúng ranh giới trách nhiệm**

Tạo `resources/js/crm-pipeline-drag.js`:

```js
/**
 * Kéo-thả (và click "Chuyển giai đoạn" làm fallback) để đổi pipeline_stage
 * trên board CRM. Vanilla JS — layout operator không có Alpine. Copy pattern
 * từ work-template-apply.js.
 */
(function () {
    'use strict';

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function postJson(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
            body: JSON.stringify(body),
        });
    }

    // ---- Error normalization (1 nguồn duy nhất, Quyết định 6 của spec) ----

    function parseErrorResponse(response) {
        if (!response) {
            return Promise.resolve({ userMessage: 'Có lỗi xảy ra, vui lòng thử lại.' });
        }
        if (response.status === 401) {
            return Promise.resolve({ userMessage: 'Phiên đăng nhập không còn hợp lệ, vui lòng đăng nhập lại.' });
        }
        if (response.status === 403) {
            return Promise.resolve({ userMessage: 'Bạn không có quyền thực hiện thao tác này.' });
        }
        if (response.status === 419) {
            return Promise.resolve({ userMessage: 'Phiên làm việc đã hết hạn, vui lòng tải lại trang.', reload: true });
        }
        return response.json()
            .then(function (body) {
                if (response.status === 422) {
                    var firstFieldError = body && body.errors && Object.values(body.errors)[0];
                    var firstMessage = Array.isArray(firstFieldError) ? firstFieldError[0] : null;
                    return { userMessage: firstMessage || (body && body.message) || 'Dữ liệu không hợp lệ.' };
                }
                return { userMessage: (body && body.message) || 'Có lỗi xảy ra, vui lòng thử lại.' };
            })
            .catch(function () {
                return { userMessage: response.status >= 500
                    ? 'Có lỗi xảy ra, vui lòng thử lại.'
                    : 'Có lỗi xảy ra, vui lòng thử lại (mã lỗi ' + response.status + ').' };
            });
    }

    // ---- Toast lỗi (utility cục bộ cho tính năng này, không phải framework toàn repo) ----

    function showToast(message, options) {
        var el = document.createElement('div');
        el.className = 'fixed bottom-4 right-4 z-50 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800 shadow-lg';
        el.textContent = message;
        if (options && options.reload) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = 'Tải lại trang';
            btn.className = 'ml-3 underline';
            btn.addEventListener('click', function () { window.location.reload(); });
            el.appendChild(btn);
        }
        document.body.appendChild(el);
        if (!(options && options.reload)) {
            setTimeout(function () { el.remove(); }, 5000);
        }
    }

    // ---- Pending state ----

    function setCardPending(card, pending) {
        card.setAttribute('aria-busy', pending ? 'true' : 'false');
        var handle = card.querySelector('.crm-drag-handle');
        if (handle) {
            handle.draggable = !pending;
            handle.disabled = pending;
        }
        var transitionBtn = card.querySelector('.crm-stage-transition-btn');
        if (transitionBtn) {
            transitionBtn.disabled = pending;
        }
    }

    // ---- Aggregate cột (số thô, Quyết định aggregate của spec) ----

    function formatVnd(amount) {
        return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(amount);
    }

    function recalculateColumnSummary(columnEl) {
        var cards = columnEl.querySelectorAll('[data-opportunity-id]');
        var total = 0;
        cards.forEach(function (card) { total += parseInt(card.dataset.amount, 10) || 0; });
        var countEl = columnEl.querySelector('[data-column-count]');
        var totalEl = columnEl.querySelector('[data-column-total]');
        if (countEl) countEl.textContent = String(cards.length);
        if (totalEl) totalEl.textContent = formatVnd(total);
        var emptyEl = columnEl.querySelector('[data-column-empty]');
        if (emptyEl) emptyEl.classList.toggle('hidden', cards.length > 0);
    }

    // ---- DOM commit sau thành công (Quyết định 4 của spec — đủ 8 mục) ----

    function stageLabelFor(groupKey) {
        var groupEl = document.querySelector('[data-board-group="' + groupKey + '"]');
        return groupEl ? groupEl.dataset.columnLabel : groupKey;
    }

    function commitCardTransition(card, targetGroupKey, responseData) {
        var sourceGroupEl = card.closest('[data-board-group]');
        var targetGroupEl = document.querySelector('[data-board-group="' + targetGroupKey + '"]');
        var targetList = targetGroupEl.querySelector('ul');

        if (!targetList) {
            targetList = document.createElement('ul');
            targetList.className = 'space-y-2';
            targetGroupEl.appendChild(targetList);
        }

        // 1. vị trí card
        targetList.appendChild(card);

        // 2. data-current-stage
        card.dataset.currentStage = responseData.pipeline_stage;

        // 3. stage badge hiển thị (nếu card có phần tử badge riêng)
        var badgeEl = card.querySelector('[data-stage-badge]');
        if (badgeEl) badgeEl.textContent = stageLabelFor(targetGroupKey);

        // 4 + 5 + 6. data-terminal + xóa drag handle + nút "Chuyển giai đoạn" nếu terminal
        card.dataset.terminal = responseData.is_terminal ? '1' : '0';
        if (responseData.is_terminal) {
            var handle = card.querySelector('.crm-drag-handle');
            if (handle) handle.remove();
            var transitionBtn = card.querySelector('.crm-stage-transition-btn');
            if (transitionBtn) transitionBtn.remove();
        }

        // 7 + 8. count và tổng estimated_fee của cột nguồn và cột đích
        if (sourceGroupEl) recalculateColumnSummary(sourceGroupEl);
        recalculateColumnSummary(targetGroupEl);

        setCardPending(card, false);
    }

    // ---- Submit + luồng pessimistic update ----

    function submitStageTransition(card, targetGroupKey, toStage, reason) {
        setCardPending(card, true);

        return postStageUpdate(card.dataset.opportunityId, toStage, reason)
            .then(function (response) {
                if (response.ok) {
                    return response.json().then(function (body) {
                        commitCardTransition(card, targetGroupKey, body.data);
                    });
                }
                return parseErrorResponse(response).then(function (error) {
                    setCardPending(card, false);
                    showToast(error.userMessage, error);
                });
            })
            .catch(function () {
                return parseErrorResponse(null).then(function (error) {
                    setCardPending(card, false);
                    showToast(error.userMessage, error);
                });
            });
    }

    function postStageUpdate(opportunityId, toStage, reason) {
        var url = '/crm/opportunities/' + encodeURIComponent(opportunityId) + '/stage';
        var body = { pipeline_stage: toStage };
        if (reason) body.lost_reason = reason;
        return postJson(url, body);
    }

    // ---- Dialog (Quyết định 3 của spec — 1 dialog dùng chung) ----

    var activeDialogContext = null; // { card, targetGroupKey }

    function openStageDialog(card, targetGroupKey) {
        var dialog = document.querySelector('[data-crm-stage-dialog]');
        var nameEl = dialog.querySelector('[data-dialog-opportunity-name]');
        var groupPicker = dialog.querySelector('[data-dialog-group-picker]');
        var choicePicker = dialog.querySelector('[data-dialog-choice-picker]');
        var reasonEl = dialog.querySelector('[data-dialog-reason]');
        var confirmBtn = dialog.querySelector('[data-dialog-confirm]');

        var opportunityNameEl = card.querySelector('.operator-link');
        nameEl.textContent = opportunityNameEl ? opportunityNameEl.textContent.trim() : '';

        reasonEl.classList.add('hidden');
        reasonEl.value = '';
        confirmBtn.disabled = true;
        choicePicker.classList.add('hidden');
        choicePicker.innerHTML = '';

        if (targetGroupKey) {
            // Kéo-thả vào group requires_choice: bỏ qua bước chọn cột, mở thẳng bước choice_options
            groupPicker.classList.add('hidden');
            activeDialogContext = { card: card, targetGroupKey: targetGroupKey };
            renderChoiceOptions(targetGroupKey, choicePicker, reasonEl, confirmBtn);
        } else {
            // Click "Chuyển giai đoạn": hiện bước chọn cột đích trước
            groupPicker.classList.remove('hidden');
            var currentGroupKey = card.closest('[data-board-group]').dataset.boardGroup;
            groupPicker.querySelectorAll('.crm-dialog-group-option').forEach(function (btn) {
                btn.classList.toggle('hidden', btn.dataset.group === currentGroupKey);
            });
            activeDialogContext = { card: card, targetGroupKey: null };
        }

        dialog.showModal();
    }

    function renderChoiceOptions(groupKey, choicePicker, reasonEl, confirmBtn) {
        var groupEl = document.querySelector('[data-board-group="' + groupKey + '"]');
        var options = JSON.parse(groupEl.dataset.choiceOptions || '[]');

        choicePicker.classList.remove('hidden');
        options.forEach(function (option) {
            var label = document.createElement('label');
            var radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = 'stage_choice';
            radio.value = option.stage;
            radio.dataset.requiresReason = option.requires_reason ? '1' : '0';
            label.appendChild(radio);
            label.appendChild(document.createTextNode(' ' + option.label));
            choicePicker.appendChild(label);

            radio.addEventListener('change', function () {
                var requiresReason = radio.dataset.requiresReason === '1';
                reasonEl.classList.toggle('hidden', !requiresReason);
                confirmBtn.disabled = requiresReason && reasonEl.value.trim() === '';
            });
        });

        reasonEl.addEventListener('input', function () {
            var checked = choicePicker.querySelector('input[name="stage_choice"]:checked');
            var requiresReason = checked && checked.dataset.requiresReason === '1';
            confirmBtn.disabled = requiresReason && reasonEl.value.trim() === '';
        });
    }

    function initStageDialog() {
        var dialog = document.querySelector('[data-crm-stage-dialog]');
        if (!dialog) return;

        var groupPicker = dialog.querySelector('[data-dialog-group-picker]');
        var choicePicker = dialog.querySelector('[data-dialog-choice-picker]');
        var reasonEl = dialog.querySelector('[data-dialog-reason]');
        var confirmBtn = dialog.querySelector('[data-dialog-confirm]');
        var cancelBtn = dialog.querySelector('[data-dialog-cancel]');

        groupPicker.querySelectorAll('.crm-dialog-group-option').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var chosenGroupKey = btn.dataset.group;
                var groupEl = document.querySelector('[data-board-group="' + chosenGroupKey + '"]');
                activeDialogContext.targetGroupKey = chosenGroupKey;

                if (groupEl.dataset.requiresChoice === '1') {
                    groupPicker.classList.add('hidden');
                    renderChoiceOptions(chosenGroupKey, choicePicker, reasonEl, confirmBtn);
                } else {
                    confirmBtn.disabled = false;
                }
            });
        });

        cancelBtn.addEventListener('click', function () {
            activeDialogContext = null;
            dialog.close();
        });

        confirmBtn.addEventListener('click', function () {
            if (confirmBtn.disabled || !activeDialogContext) return;

            var card = activeDialogContext.card;
            var targetGroupKey = activeDialogContext.targetGroupKey;
            var groupEl = document.querySelector('[data-board-group="' + targetGroupKey + '"]');
            var checked = choicePicker.querySelector('input[name="stage_choice"]:checked');

            var toStage = checked ? checked.value : groupEl.dataset.defaultEntryStage;
            var reason = checked && checked.dataset.requiresReason === '1' ? reasonEl.value.trim() : null;

            dialog.close();
            submitStageTransition(card, targetGroupKey, toStage, reason);
            activeDialogContext = null;
        });
    }

    // ---- Orchestration dùng chung cho kéo-thả VÀ click (Quyết định 3 của spec) ----

    function requestStageTransition(card, targetGroupKey) {
        var targetGroupEl = document.querySelector('[data-board-group="' + targetGroupKey + '"]');
        var requiresChoice = targetGroupEl.dataset.requiresChoice === '1';

        if (requiresChoice) {
            openStageDialog(card, targetGroupKey);
            return;
        }

        var toStage = targetGroupEl.dataset.defaultEntryStage;
        submitStageTransition(card, targetGroupKey, toStage, null);
    }

    // ---- Kéo-thả (HTML5 Drag & Drop API gốc) ----

    var draggedCard = null;

    function initDragDrop() {
        document.querySelectorAll('.crm-drag-handle').forEach(function (handle) {
            handle.addEventListener('dragstart', function (event) {
                var card = event.currentTarget.closest('[data-opportunity-id]');
                if (card.getAttribute('aria-busy') === 'true') {
                    event.preventDefault();
                    return;
                }
                draggedCard = card;
                event.dataTransfer.effectAllowed = 'move';
            });
        });

        document.querySelectorAll('[data-board-group]').forEach(function (columnEl) {
            columnEl.addEventListener('dragover', function (event) {
                event.preventDefault();
            });

            columnEl.addEventListener('drop', function (event) {
                event.preventDefault();
                if (!draggedCard) return;

                var sourceGroupKey = draggedCard.closest('[data-board-group]').dataset.boardGroup;
                var targetGroupKey = columnEl.dataset.boardGroup;

                if (sourceGroupKey === targetGroupKey) {
                    draggedCard = null;
                    return; // no-op tuyệt đối — không gọi API, không đổi DOM
                }

                requestStageTransition(draggedCard, targetGroupKey);
                draggedCard = null;
            });
        });
    }

    // ---- Click fallback "Chuyển giai đoạn" ----

    function initClickFallback() {
        document.querySelectorAll('.crm-stage-transition-btn').forEach(function (btn) {
            btn.addEventListener('click', function (event) {
                var card = event.currentTarget.closest('[data-opportunity-id]');
                if (card.getAttribute('aria-busy') === 'true') return;
                openStageDialog(card, null);
            });
        });
    }

    // ---- Entry point ----

    function initializePipelineDragDrop() {
        if (!document.querySelector('[data-board-group]')) return; // không phải trang crm.index
        initStageDialog();
        initDragDrop();
        initClickFallback();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializePipelineDragDrop);
    } else {
        initializePipelineDragDrop();
    }
})();
```

Ranh giới trách nhiệm (đối chiếu với 10 hàm yêu cầu — 2 hàm được gộp/đổi tên có lý do ghi rõ):
- `initializePipelineDragDrop()` — entry point, gọi 3 hàm init con.
- `requestStageTransition()` — orchestration dùng chung drag + click, đúng tên yêu cầu.
- `openStageDialog()` — mở dialog, đúng tên yêu cầu.
- `submitStageTransition()` — luồng pessimistic update (đặt pending → gọi `postStageUpdate` → `commitCardTransition` hoặc lỗi). Đổi tên từ `submitStageChange` gợi ý trong spec sang khớp đúng tên `submitStageTransition` mà task này yêu cầu.
- `postStageUpdate()` — gọi `fetch`, đúng tên yêu cầu.
- `parseErrorResponse()` — đúng tên yêu cầu, đúng spec Quyết định 6.
- `setCardPending()` — đúng tên yêu cầu.
- `commitCardTransition()` — đúng tên yêu cầu, thực hiện đủ 8 mục DOM commit.
- `recalculateColumnSummary()` — đúng tên yêu cầu.
- `showToast()` — đúng tên yêu cầu.
- (Thêm, không có trong danh sách 10 hàm nhưng cần thiết để tránh 1 hàm phình to): `csrfToken()`, `postJson()`, `formatVnd()`, `stageLabelFor()`, `renderChoiceOptions()`, `initStageDialog()`, `initDragDrop()`, `initClickFallback()` — mỗi hàm 1 trách nhiệm hẹp, không hàm nào vượt quá ~30 dòng.

- [ ] **Step 2: Kiểm tra cú pháp tĩnh (không chạy được test JS thật ở bước này — xem ghi chú đầu task)**

Chạy:

```bash
node --check resources/js/crm-pipeline-drag.js
```

Expected: không có output (exit code 0) — xác nhận cú pháp JS hợp lệ trước khi build ở Task 7.

- [ ] **Step 3: Commit**

```bash
git add resources/js/crm-pipeline-drag.js
git commit -m "feat(crm): add crm-pipeline-drag.js (vanilla JS, drag+click share requestStageTransition)"
```

---

### Task 7: Asset integration + CSS tối thiểu

**Files:**
- Modify: `vite.config.js`
- Modify: `resources/views/layouts/operator.blade.php:10`
- Modify: `resources/css/operator.css` (thêm rule tối thiểu cho handle/dialog/toast — không redesign)

**Interfaces:**
- Consumes: `resources/js/crm-pipeline-drag.js` (Task 6).
- Produces: bundle build ra `public/build/` chứa module mới; Task 8 (Dusk) phụ thuộc bundle này đã build đúng để trang `crm.index` load được JS.

- [ ] **Step 1: Thêm entry vào Vite config**

Trong `vite.config.js`, tìm dòng:

```js
            input: ['resources/css/app.css', 'resources/css/operator.css', 'resources/js/app.js', 'resources/js/money-format.js', 'resources/js/ai-lead-suggest.js', 'resources/js/ai-design-item-suggest.js', 'resources/js/ai-opportunity-summary.js', 'resources/js/work-template-apply.js'],
```

Đổi thành (thêm `crm-pipeline-drag.js` vào cuối mảng):

```js
            input: ['resources/css/app.css', 'resources/css/operator.css', 'resources/js/app.js', 'resources/js/money-format.js', 'resources/js/ai-lead-suggest.js', 'resources/js/ai-design-item-suggest.js', 'resources/js/ai-opportunity-summary.js', 'resources/js/work-template-apply.js', 'resources/js/crm-pipeline-drag.js'],
```

- [ ] **Step 2: Thêm `@vite(...)` trong layout**

Trong `resources/views/layouts/operator.blade.php:10`, tìm:

```blade
    @vite(['resources/css/operator.css', 'resources/js/money-format.js', 'resources/js/ai-lead-suggest.js', 'resources/js/ai-design-item-suggest.js', 'resources/js/ai-opportunity-summary.js', 'resources/js/work-template-apply.js'])
```

Đổi thành:

```blade
    @vite(['resources/css/operator.css', 'resources/js/money-format.js', 'resources/js/ai-lead-suggest.js', 'resources/js/ai-design-item-suggest.js', 'resources/js/ai-opportunity-summary.js', 'resources/js/work-template-apply.js', 'resources/js/crm-pipeline-drag.js'])
```

- [ ] **Step 3: Build, xác nhận không lỗi và bundle chứa module mới**

Chạy:

```bash
npm run build
```

Expected: exit code 0, output liệt kê `crm-pipeline-drag` trong danh sách asset được build (Vite in tên file `.js` build ra kèm hash, ví dụ `public/build/assets/crm-pipeline-drag-XXXXXXXX.js`). Đối chiếu với baseline Task 1 (build cũ không có file này) để xác nhận module mới thực sự được đưa vào bundle.

- [ ] **Step 4: CSS tối thiểu — `<dialog>`, drag handle, pending state, toast**

Thêm vào cuối `resources/css/operator.css`:

```css
/* Kéo-thả pipeline CRM — tối thiểu, không redesign board */
.crm-drag-handle {
    cursor: grab;
    background: transparent;
    border: none;
    padding: 0.125rem 0.375rem;
    color: theme('colors.slate.400');
}
.crm-drag-handle:active {
    cursor: grabbing;
}
.crm-drag-handle:disabled {
    cursor: not-allowed;
    opacity: 0.4;
}

[data-board-group][data-dragover="true"] {
    outline: 2px dashed theme('colors.emerald.400');
    outline-offset: -4px;
}

[aria-busy="true"] {
    opacity: 0.6;
    pointer-events: none;
}

[data-crm-stage-dialog] {
    border: none;
    border-radius: 1rem;
    padding: 1.5rem;
    max-width: 24rem;
    width: 90vw;
}
[data-crm-stage-dialog]::backdrop {
    background: rgb(15 23 42 / 0.4);
}
```

Lưu ý: rule `[data-board-group][data-dragover="true"]` là CSS tối thiểu cho phản hồi thị giác khi kéo qua cột — nếu Task 6 không set `data-dragover` (module ở Task 6 hiện chưa set attribute này, chỉ dùng `preventDefault()` trong `dragover`), CSS này không kích hoạt được gì — không phải lỗi chặn, chỉ là hiệu ứng thị giác tùy chọn bị bỏ qua; nếu muốn hiệu ứng này hoạt động, cần bổ sung 2 dòng vào `initDragDrop()` ở Task 6 (`columnEl.addEventListener('dragenter', ...)`/`dragleave`) — **không bắt buộc theo spec**, ghi chú ở đây để không bị hiểu nhầm là thiếu sót nếu bỏ qua.

- [ ] **Step 5: Build lại, xác nhận CSS không lỗi cú pháp**

Chạy:

```bash
npm run build
```

Expected: exit code 0.

- [ ] **Step 6: Commit**

```bash
git add vite.config.js resources/views/layouts/operator.blade.php resources/css/operator.css
git commit -m "chore(crm): register crm-pipeline-drag.js as Vite entry, add minimal CSS"
```

---

### Task 8: Dusk browser tests

**Files:**
- Create: `tests/Browser/Crm/PipelineDragDropTest.php`

**Interfaces:**
- Consumes: toàn bộ Task 2-7 (route, Service, Blade contract, JS module, build asset).
- Produces: bằng chứng end-to-end rằng luồng click "Chuyển giai đoạn" (fallback không cần drag thật) hoạt động đúng theo spec — đây cũng là bài kiểm tra thực tế duy nhất cho `crm-pipeline-drag.js` (không có Jest/Vitest, xem Task 6).

- [ ] **Step 1: Tạo file test, seed dữ liệu theo pattern `ProjectCreateTest.php`**

Tạo `tests/Browser/Crm/PipelineDragDropTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Browser\Crm;

use App\Models\Account;
use App\Models\Opportunity;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Hash;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class PipelineDragDropTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected Tenant $tenant;
    protected User $user;
    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Company',
            'slug' => 'test-company-' . uniqid(),
            'status' => 'active',
        ]);

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test+' . uniqid() . '@example.com',
            'password' => Hash::make('password'),
            'tenant_id' => $this->tenant->id,
        ]);

        $role = Role::factory()->create(['name' => 'Dusk CRM Manager ' . uniqid()]);
        $permission = Permission::firstOrCreate(
            ['code' => 'crm.manage'],
            ['name' => 'crm.manage', 'module' => 'crm', 'action' => 'manage']
        );
        $viewPermission = Permission::firstOrCreate(
            ['code' => 'crm.view'],
            ['name' => 'crm.view', 'module' => 'crm', 'action' => 'view']
        );
        $role->permissions()->sync([$permission->id, $viewPermission->id]);
        UserRole::create(['user_id' => (string) $this->user->id, 'role_id' => (string) $role->id]);

        $this->account = Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'display_name' => 'Khách hàng Dusk test',
        ]);
    }

    private function makeOpportunity(array $overrides = []): Opportunity
    {
        return Opportunity::query()->create(array_merge([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $this->account->id,
            'opportunity_name' => 'Cơ hội Dusk ' . uniqid(),
            'pipeline_stage' => Opportunity::STAGE_NEW_LEAD,
            'estimated_fee' => 100000000,
            'sales_owner_id' => (string) $this->user->id,
            'created_by' => (string) $this->user->id,
        ], $overrides));
    }

    public function test_terminal_card_has_no_stage_transition_control(): void
    {
        $opportunity = $this->makeOpportunity(['pipeline_stage' => Opportunity::STAGE_WON]);

        $this->browse(function (Browser $browser) use ($opportunity) {
            $browser->loginAs($this->user)
                ->visit('/app/crm')
                ->waitFor('[data-opportunity-id="' . $opportunity->id . '"]', 10)
                ->assertMissing('[data-opportunity-id="' . $opportunity->id . '"] .crm-drag-handle')
                ->assertMissing('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn');
        });
    }

    public function test_click_transition_opens_dialog_excluding_current_group(): void
    {
        $opportunity = $this->makeOpportunity();

        $this->browse(function (Browser $browser) use ($opportunity) {
            $browser->loginAs($this->user)
                ->visit('/app/crm')
                ->waitFor('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn', 10)
                ->click('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn')
                ->waitFor('[data-crm-stage-dialog][open]', 10)
                ->assertVisible('[data-crm-stage-dialog] .crm-dialog-group-option[data-group="consulting_survey"]')
                ->assertMissing('[data-crm-stage-dialog] .crm-dialog-group-option[data-group="new"]:not(.hidden)');
        });
    }

    public function test_lost_choice_requires_reason_before_submit(): void
    {
        $opportunity = $this->makeOpportunity();

        $this->browse(function (Browser $browser) use ($opportunity) {
            $browser->loginAs($this->user)
                ->visit('/app/crm')
                ->waitFor('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn', 10)
                ->click('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn')
                ->waitFor('[data-crm-stage-dialog][open]', 10)
                ->click('[data-crm-stage-dialog] .crm-dialog-group-option[data-group="lost_nurture"]')
                ->waitFor('[data-dialog-choice-picker] input[value="lost"]', 10)
                ->click('[data-dialog-choice-picker] input[value="lost"]')
                ->assertAttribute('[data-dialog-confirm]', 'disabled', 'true');
        });
    }

    public function test_lost_choice_with_reason_submits_and_moves_card(): void
    {
        $opportunity = $this->makeOpportunity();

        $this->browse(function (Browser $browser) use ($opportunity) {
            $browser->loginAs($this->user)
                ->visit('/app/crm')
                ->waitFor('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn', 10)
                ->click('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn')
                ->waitFor('[data-crm-stage-dialog][open]', 10)
                ->click('[data-crm-stage-dialog] .crm-dialog-group-option[data-group="lost_nurture"]')
                ->waitFor('[data-dialog-choice-picker] input[value="lost"]', 10)
                ->click('[data-dialog-choice-picker] input[value="lost"]')
                ->type('[data-dialog-reason]', 'Khách chọn đối thủ khác')
                ->click('[data-dialog-confirm]')
                ->waitUntilMissing('[data-crm-stage-dialog][open]', 10)
                ->waitFor('[data-board-group="lost_nurture"] [data-opportunity-id="' . $opportunity->id . '"]', 10)
                ->assertMissing('[data-board-group="new"] [data-opportunity-id="' . $opportunity->id . '"]')
                ->assertMissing('[data-opportunity-id="' . $opportunity->id . '"] .crm-drag-handle')
                ->assertMissing('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn');
        });

        $opportunity->refresh();
        $this->assertSame(Opportunity::STAGE_LOST, $opportunity->pipeline_stage);
        $this->assertSame('Khách chọn đối thủ khác', $opportunity->lost_reason);
    }

    public function test_normal_group_transition_keeps_controls_because_not_terminal(): void
    {
        $opportunity = $this->makeOpportunity();

        $this->browse(function (Browser $browser) use ($opportunity) {
            $browser->loginAs($this->user)
                ->visit('/app/crm')
                ->waitFor('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn', 10)
                ->click('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn')
                ->waitFor('[data-crm-stage-dialog][open]', 10)
                ->click('[data-crm-stage-dialog] .crm-dialog-group-option[data-group="consulting_survey"]')
                ->waitFor('[data-dialog-confirm]:not([disabled])', 10)
                ->click('[data-dialog-confirm]')
                ->waitUntilMissing('[data-crm-stage-dialog][open]', 10)
                ->waitFor('[data-board-group="consulting_survey"] [data-opportunity-id="' . $opportunity->id . '"]', 10)
                ->assertPresent('[data-opportunity-id="' . $opportunity->id . '"] .crm-drag-handle')
                ->assertPresent('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn');
        });

        $opportunity->refresh();
        $this->assertSame(Opportunity::STAGE_BRIEF_DISCOVERY, $opportunity->pipeline_stage);
    }

    public function test_cancel_dialog_leaves_card_unchanged(): void
    {
        $opportunity = $this->makeOpportunity();

        $this->browse(function (Browser $browser) use ($opportunity) {
            $browser->loginAs($this->user)
                ->visit('/app/crm')
                ->waitFor('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn', 10)
                ->click('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn')
                ->waitFor('[data-crm-stage-dialog][open]', 10)
                ->click('[data-dialog-cancel]')
                ->waitUntilMissing('[data-crm-stage-dialog][open]', 10)
                ->assertPresent('[data-board-group="new"] [data-opportunity-id="' . $opportunity->id . '"]');
        });

        $opportunity->refresh();
        $this->assertSame(Opportunity::STAGE_NEW_LEAD, $opportunity->pipeline_stage);
    }

    public function test_backend_error_keeps_dom_and_shows_toast(): void
    {
        // Route mutation bị chặn ở tầng middleware rbac:crm.manage (routes/web.php:1016)
        // độc lập với frontend — dùng user chỉ có crm.view để đi qua ĐÚNG luồng UI thật
        // (click → dialog → confirm) và nhận 403 thật, thay vì giả lập lỗi bằng cách khác.
        // Board (route operator.crm.index) chỉ yêu cầu rbac:crm.view nên user này vẫn thấy
        // được nút "Chuyển giai đoạn" — xác nhận backend là ranh giới bảo mật thật sự,
        // không phải chỉ ẩn nút ở frontend (đúng Quyết định 7 của spec).
        $viewerRole = Role::factory()->create(['name' => 'Dusk CRM Viewer ' . uniqid()]);
        $viewPermission = Permission::firstOrCreate(
            ['code' => 'crm.view'],
            ['name' => 'crm.view', 'module' => 'crm', 'action' => 'view']
        );
        $viewerRole->permissions()->sync([$viewPermission->id]);

        $viewer = User::create([
            'name' => 'Dusk Viewer',
            'email' => 'viewer+' . uniqid() . '@example.com',
            'password' => Hash::make('password'),
            'tenant_id' => $this->tenant->id,
        ]);
        UserRole::create(['user_id' => (string) $viewer->id, 'role_id' => (string) $viewerRole->id]);

        $opportunity = $this->makeOpportunity();

        $this->browse(function (Browser $browser) use ($opportunity, $viewer) {
            $browser->loginAs($viewer)
                ->visit('/app/crm')
                ->waitFor('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn', 10)
                ->click('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn')
                ->waitFor('[data-crm-stage-dialog][open]', 10)
                ->click('[data-crm-stage-dialog] .crm-dialog-group-option[data-group="consulting_survey"]')
                ->waitFor('[data-dialog-confirm]:not([disabled])', 10)
                ->click('[data-dialog-confirm]')
                ->waitForText('Bạn không có quyền thực hiện thao tác này.', 10)
                ->assertPresent('[data-board-group="new"] [data-opportunity-id="' . $opportunity->id . '"]')
                ->assertMissing('[data-board-group="consulting_survey"] [data-opportunity-id="' . $opportunity->id . '"]');
        });

        $opportunity->refresh();
        $this->assertSame(Opportunity::STAGE_NEW_LEAD, $opportunity->pipeline_stage);
    }

    public function test_pending_state_blocks_duplicate_submit(): void
    {
        $opportunity = $this->makeOpportunity();

        $this->browse(function (Browser $browser) use ($opportunity) {
            $browser->loginAs($this->user)
                ->visit('/app/crm')
                ->waitFor('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn', 10)
                ->script([
                    "window.__pendingCount = 0;"
                    . "window.fetch = function() { window.__pendingCount++; return new Promise(function(){}); };",
                ])
                ->click('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn')
                ->waitFor('[data-crm-stage-dialog][open]', 10)
                ->click('[data-crm-stage-dialog] .crm-dialog-group-option[data-group="consulting_survey"]')
                ->waitFor('[data-dialog-confirm]:not([disabled])', 10)
                ->click('[data-dialog-confirm]')
                ->pause(300)
                ->assertAttribute('[data-opportunity-id="' . $opportunity->id . '"]', 'aria-busy', 'true');

            $pendingAfterFirstClick = $browser->script("return window.__pendingCount;")[0];
            $this->assertSame(1, $pendingAfterFirstClick);

            // Card đang pending, không còn nút/handle khả dụng để double-submit qua UI —
            // xác nhận lại bằng script rằng __pendingCount không tăng thêm sau 500ms nữa
            $browser->pause(500);
            $pendingAfterWait = $browser->script("return window.__pendingCount;")[0];
            $this->assertSame(1, $pendingAfterWait);
        });
    }
}
```

- [ ] **Step 2: Chạy toàn bộ Dusk test mới**

Chạy:

```bash
php artisan dusk tests/Browser/Crm/ --env=testing --without-tty
```

Expected (RED trước khi Task 6-7 tồn tại — nhưng vì kế hoạch đã yêu cầu Task 6-7 hoàn tất trước Task 8, đây thực chất là bước GREEN đầu tiên cho toàn chuỗi tính năng): `OK (8 tests, N assertions)`. Nếu bất kỳ test nào FAIL, đối chiếu lỗi cụ thể với đúng Task (2-7) chịu trách nhiệm phần đó thay vì sửa lan man nhiều nơi cùng lúc.

- [ ] **Step 3: Nếu FAIL, sửa đúng phạm vi rồi chạy lại**

Không có code mẫu cho bước này vì nó phụ thuộc lỗi thực tế phát sinh — nguyên tắc: lỗi selector/DOM → sửa Task 5 (Blade); lỗi logic JS → sửa Task 6; lỗi build/asset không load → sửa Task 7; lỗi status code/JSON shape → sửa Task 4. Không sửa Service (Task 3) trừ khi lỗi thực sự nằm ở business rule (rất khó xảy ra vì Task 3 đã có unit test riêng).

- [ ] **Step 4: Commit**

```bash
git add tests/Browser/Crm/PipelineDragDropTest.php
git commit -m "test(crm): add Dusk coverage for pipeline drag-drop click fallback"
```

---

### Task 9: Verification cuối — chạy toàn bộ suite liên quan, ghi lại kết quả

**Files:** không tạo/sửa file (trừ khi Step nào đó phát hiện lỗi cần fix — nếu có, quay lại đúng Task gây lỗi, không vá tại Task 9).

**Interfaces:**
- Consumes: toàn bộ Task 1-8.
- Produces: bằng chứng cuối cùng cho việc tính năng sẵn sàng review — không tuyên bố hoàn thành nếu bất kỳ command nào dưới đây chưa chạy và ghi lại kết quả thật.

- [ ] **Step 1: Targeted feature tests**

```bash
./vendor/bin/phpunit tests/Feature/Api/CrmApiTest.php tests/Feature/Zena/OperatorCrmUiTest.php
```

Expected: `OK` toàn bộ 2 file — bao gồm mọi test cũ (baseline Task 1) lẫn test mới (Task 2, 4, 5).

- [ ] **Step 2: Service unit tests**

```bash
./vendor/bin/phpunit tests/Unit/Services/Crm/OpportunityStageTransitionServiceTest.php tests/Unit/Http/Controllers/Web/CrmPageControllerBoardGroupsTest.php
```

Expected: `OK` cả 2 file (8 + 5 test).

- [ ] **Step 3: Dusk tests**

```bash
php artisan dusk tests/Browser/Crm/ --env=testing --without-tty
```

Expected: `OK (8 tests, N assertions)`.

- [ ] **Step 4: Static analysis (PHPStan) trên các file đã đổi/tạo**

```bash
./vendor/bin/phpstan analyse app/Services/Crm/OpportunityStageTransitionService.php app/Http/Controllers/Api/OpportunityController.php app/Http/Controllers/Web/CrmPageController.php --level=6
```

Expected: `[OK] No errors`. Nếu môi trường làm việc là worktree copy+symlink (đã ghi nhận là gotcha đã biết trong dự án — PHPStan local có thể lỗi fatal do autoload trùng), ghi rõ trong báo cáo và không coi đó là lỗi thật; đối chiếu bằng CI thật khi mở PR.

- [ ] **Step 5: Code style (Pint)**

```bash
./vendor/bin/pint --test app/Services/Crm/OpportunityStageTransitionService.php app/Http/Controllers/Api/OpportunityController.php app/Http/Controllers/Web/CrmPageController.php
```

Expected: `PASS`, không có file nào cần format lại. Nếu có, chạy `./vendor/bin/pint app/Services/Crm/OpportunityStageTransitionService.php app/Http/Controllers/Api/OpportunityController.php app/Http/Controllers/Web/CrmPageController.php` rồi commit riêng "style: pint format".

- [ ] **Step 6: Frontend build**

```bash
npm run build
```

Expected: exit code 0.

- [ ] **Step 7: Route/security invariant tests đã có sẵn trong repo (không tạo mới — chạy để xác nhận không phá vỡ)**

```bash
composer ssot:lint
```

Expected: exit code 0, không lỗi. Script này đã có sẵn trong `composer.json` (`scripts.ssot:lint`), kiểm route mồ côi + domain ownership. Task 4 không thêm route mới (chỉ đổi tham số của `updateStage()`), nên không có route mới cần khai báo — chạy lệnh này để xác nhận thay đổi chữ ký method không phá vỡ guard nào đang tồn tại.

- [ ] **Step 8: Broader regression — toàn bộ CRM-liên quan**

```bash
./vendor/bin/phpunit --filter Crm
```

Expected: `OK` — bắt được bất kỳ test nào khác trong repo có chữ "Crm" trong tên class/method mà các bước trước chưa liệt kê tường minh (lưới an toàn cuối).

- [ ] **Step 9: Tổng hợp báo cáo cuối**

Không có lệnh — tổng hợp kết quả 8 bước trên thành báo cáo cuối gửi cho người review, kèm số test/assertion mỗi bước, để đối chiếu với baseline Task 1. Không tuyên bố "hoàn thành" nếu bất kỳ bước nào ở trên chưa chạy hoặc chưa ghi lại kết quả thật (không đoán, không giả định "chắc sẽ pass").

---

## Đề xuất chia commit (đối chiếu với Task ở trên)

1. **Board config + invariants** — Task 2 (`refactor(crm): stable board group keys...`).
2. **Shared transition service refactor** — Task 3 (`feat(crm): extract OpportunityStageTransitionService...`) + Task 4 (`refactor(crm): wire both controllers...`) — 2 commit riêng vì Task 3 chưa nối controller, Task 4 mới nối.
3. **Web JSON content negotiation** — đã gộp trong Task 4 (cùng 1 thay đổi controller, tách thêm sẽ vụn — validation route web và JSON shape gắn liền với việc bỏ proxy sang API controller trong cùng method, tách ra sẽ tạo trạng thái trung gian vô nghĩa).
4. **Blade drag/drop contract** — Task 5 (`feat(crm): render drag-drop DOM contract...`).
5. **Vanilla JS + CSS** — Task 6 (`feat(crm): add crm-pipeline-drag.js...`) + Task 7 (`chore(crm): register crm-pipeline-drag.js as Vite entry...`) — 2 commit riêng vì JS module và asset-registration là 2 mối quan tâm khác nhau (logic vs build config).
6. **Browser tests và verification fixes** — Task 8 (`test(crm): add Dusk coverage...`); Task 9 không tạo commit riêng trừ khi phát sinh fix (nếu có, commit theo đúng Task gây lỗi, không gộp vào 1 commit "fix everything").

Không commit nào trộn backend refactor + Blade + JS + Dusk trong cùng 1 lần — mỗi task ở trên đã tách theo đúng layer.

## Kiểm soát phạm vi — không làm gì ngoài danh sách sau

- Không reorder trong cùng cột, không `board_position`.
- Không đổi business rule pipeline ngoài spec (không thêm transition graph, không đổi rule `lost_reason`/terminal đã có).
- Không redesign CRM board ngoài phần cần thiết cho kéo-thả.
- Không biến `showToast()` thành toast framework dùng chung — hàm này chỉ dùng nội bộ trong `crm-pipeline-drag.js`, không export ra module khác, không đăng ký global.
- Không chuẩn hóa response cho 11 controller khác đang dùng `ZenaContractResponseTrait` — chỉ 2 controller trong phạm vi (`ApiOpportunityController`, `CrmPageController`) bị đụng tới, và `CrmPageController` không thêm trait đó.
- Không refactor service nào khác ngoài việc tạo mới `OpportunityStageTransitionService`.
- Không đổi permission (`crm.manage`/`crm.view`) hay route path/tên route nào — chỉ đổi tham số method `updateStage()` (bỏ `ApiOpportunityController $apiController`), route definition ở `routes/web.php:1016` không cần sửa.
