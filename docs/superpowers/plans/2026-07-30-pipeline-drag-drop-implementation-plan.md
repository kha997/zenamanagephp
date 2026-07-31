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

    public function test_group_key_set_matches_expected_stable_keys(): void
    {
        // KHÔNG dùng array_keys() === array_unique(array_keys()): PHP associative
        // array không thể có key literal trùng nhau (key sau ghi đè key trước ngay ở
        // compile-time), nên so sánh đó là tautology, không bảo vệ được gì. Test này
        // khóa đúng TẬP HỢP 6 key ổn định — sort cả 2 phía trước khi so sánh vì thứ tự
        // hiển thị (nếu là contract) được kiểm ở test riêng bên dưới, không trộn vào đây.
        $expected = ['consulting_survey', 'lost_nurture', 'negotiation_contract', 'new', 'quote', 'won'];
        $actual = array_keys($this->boardGroups());
        sort($actual);

        $this->assertSame($expected, $actual);

        foreach (array_keys($this->boardGroups()) as $key) {
            $this->assertMatchesRegularExpression(
                '/^[a-z][a-z0-9_]*$/',
                $key,
                "Group key '{$key}' không đúng định dạng snake_case."
            );
        }
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

Expected: FAIL — `test_group_key_set_matches_expected_stable_keys` thất bại vì `BOARD_GROUPS` hiện tại key theo label tiếng Việt (`'Mới'`, `'Tư vấn / Khảo sát'`, ...), không khớp tập `['consulting_survey', 'lost_nurture', 'negotiation_contract', 'new', 'quote', 'won']` (và cũng fail luôn ở check snake_case vì `'Mới'` không khớp regex). `getConstant('BOARD_GROUPS')` trả về `array<string,list<string>>` (không có `stages`/`default_entry_stage` con) → các test khác lỗi `Undefined array key "stages"` (TypeError/ArrayAccess lỗi). Đây là thất bại đúng lý do: cấu trúc cũ chưa khớp shape mới.

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
- Consumes: `$board` (Task 2 — mỗi phần tử có `label`, `items`, `count`, `total_fee`, `default_entry_stage`, `requires_choice`, `choice_options`), `Opportunity::isTerminal()`, `$opportunity->estimated_fee`, `auth()->user()?->hasPermission('crm.manage')` (pattern có sẵn ở `resources/views/crm/opportunity-show.blade.php:132,157,205,342` — không tạo helper mới, xem spec rev 4).
- Produces: DOM contract mà các slice frontend (Task 7-11) phụ thuộc: `data-board-group`, `data-column-label`, `data-requires-choice`, `data-default-entry-stage`, `data-choice-options`, `data-column-count`, `data-column-total`, `data-opportunity-id`, `data-current-stage`, `data-terminal`, `data-amount`, class `.crm-drag-handle`, `.crm-stage-transition-btn`, `[data-crm-stage-dialog]`, `.crm-dialog-group-option`. Handle/nút chỉ render khi **đồng thời** `!isTerminal()` **và** `hasPermission('crm.manage')` — actor chỉ có `crm.view` thấy card nhưng không thấy 2 điều khiển này.

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

    public function test_crm_index_hides_drag_handle_and_transition_button_for_view_only_user(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];
        $viewer = $this->createTenantUser($this->tenant, [], ['crm_viewer'], ['crm.view']);
        $account = \App\Models\Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'display_name' => 'Khách hàng view-only test',
        ]);
        $opportunity = Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Cơ hội view-only',
            'pipeline_stage' => Opportunity::STAGE_NEW_LEAD, // KHÔNG terminal — chỉ permission là lý do ẩn
            'sales_owner_id' => (string) $viewer->id,
            'created_by' => (string) $viewer->id,
        ]);

        $response = $this->actingAs($viewer)
            ->get(route('operator.crm.index'), $headers);

        $html = $response->getContent();
        $cardStart = strpos($html, 'data-opportunity-id="' . $opportunity->id . '"');
        $this->assertNotFalse($cardStart);

        $liEnd = strpos($html, '</li>', $cardStart);
        $cardHtml = substr($html, $cardStart, $liEnd - $cardStart);

        $this->assertStringContainsString('data-terminal="0"', $cardHtml); // xác nhận rõ: KHÔNG terminal
        $this->assertStringNotContainsString('crm-drag-handle', $cardHtml);
        $this->assertStringNotContainsString('draggable="true"', $cardHtml);
        $this->assertStringNotContainsString('crm-stage-transition-btn', $cardHtml);
    }
```

- [ ] **Step 2: Chạy test, xác nhận thất bại đúng lý do**

Chạy:

```bash
./vendor/bin/phpunit tests/Feature/Zena/OperatorCrmUiTest.php --filter "test_crm_index_renders_drag_drop_dom_contract|test_crm_index_terminal_card_has_no_drag_handle_or_transition_button|test_crm_index_hides_drag_handle_and_transition_button_for_view_only_user"
```

Expected: FAIL — 2 test đầu fail vì không có `data-opportunity-id`, `data-board-group`, `crm-drag-handle`, `data-crm-stage-dialog` nào trong HTML hiện tại (view Task 2 chỉ đổi label, chưa thêm metadata này). Test thứ 3 (`..._for_view_only_user`) cũng fail cùng lý do (chưa có `data-opportunity-id` để `strpos` tìm — `assertNotFalse($cardStart)` fail trước khi kịp tới các assertion phân biệt permission) — sau Step 3 bên dưới, test 1-2 chuyển GREEN trước, test thứ 3 chỉ GREEN thật sự sau khi thêm điều kiện `hasPermission('crm.manage')` vào markup (không chỉ thêm data attribute).

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
                                    @if (!$opportunity->isTerminal() && auth()->user()?->hasPermission('crm.manage'))
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
                                        @if (!$opportunity->isTerminal() && auth()->user()?->hasPermission('crm.manage'))
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
./vendor/bin/phpunit tests/Feature/Zena/OperatorCrmUiTest.php --filter "test_crm_index_renders_drag_drop_dom_contract|test_crm_index_terminal_card_has_no_drag_handle_or_transition_button|test_crm_index_hides_drag_handle_and_transition_button_for_view_only_user"
```

Expected: `OK (3 tests, N assertions)`.

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
### Task 6: Frontend scaffolding — module skeleton + Vite registration

**Files:**
- Create: `resources/js/crm-pipeline-drag.js` (khung rỗng, KHÔNG có hành vi — chỉ để trang tải được)
- Modify: `vite.config.js`
- Modify: `resources/views/layouts/operator.blade.php:10`
- Test: `tests/Browser/Crm/PipelineDragDropTest.php` (mới — 1 smoke test duy nhất, không phải chu trình RED/GREEN hành vi vì task này không có hành vi để kiểm)

**Interfaces:**
- Consumes: không có (thuần scaffolding).
- Produces: `resources/js/crm-pipeline-drag.js` được `crm.index` tải qua Vite. Task 7-11 (5 lát dọc) lần lượt thêm hành vi vào file này — không task nào trong số đó còn phải đụng tới `vite.config.js`/layout nữa.

Task này không theo chu trình RED→GREEN vì chưa có hành vi nào để viết test hành vi cho nó — đây là ngoại lệ scaffolding user đã cho phép ("Có thể tạo module skeleton và Vite import tối thiểu trước test nếu đó chỉ là scaffolding cần để Dusk tải trang"). Bài test duy nhất ở đây là smoke test xác nhận trang vẫn tải bình thường sau khi thêm entry mới — không khẳng định hành vi kéo-thả nào.

- [ ] **Step 1: Tạo module skeleton — chỉ khung IIFE, cố ý để trống**

Tạo `resources/js/crm-pipeline-drag.js`:

```js
/**
 * Kéo-thả (và click "Chuyển giai đoạn" làm fallback) để đổi pipeline_stage
 * trên board CRM. Vanilla JS — layout operator không có Alpine. Copy pattern
 * từ work-template-apply.js.
 *
 * File này được xây dần qua nhiều task (xem
 * docs/superpowers/plans/2026-07-30-pipeline-drag-drop-implementation-plan.md).
 * Hiện tại đây chỉ là khung rỗng để trang crm.index tải được — chưa có hành
 * vi nào. Task tiếp theo (slice 1: module init + click mở dialog) thêm hành
 * vi đầu tiên.
 */
(function () {
    'use strict';
    // Cố ý để trống.
})();
```

- [ ] **Step 2: Đăng ký Vite entry**

Trong `vite.config.js`, tìm dòng:

```js
            input: ['resources/css/app.css', 'resources/css/operator.css', 'resources/js/app.js', 'resources/js/money-format.js', 'resources/js/ai-lead-suggest.js', 'resources/js/ai-design-item-suggest.js', 'resources/js/ai-opportunity-summary.js', 'resources/js/work-template-apply.js'],
```

Đổi thành:

```js
            input: ['resources/css/app.css', 'resources/css/operator.css', 'resources/js/app.js', 'resources/js/money-format.js', 'resources/js/ai-lead-suggest.js', 'resources/js/ai-design-item-suggest.js', 'resources/js/ai-opportunity-summary.js', 'resources/js/work-template-apply.js', 'resources/js/crm-pipeline-drag.js'],
```

- [ ] **Step 3: Đăng ký `@vite(...)` trong layout**

Trong `resources/views/layouts/operator.blade.php:10`, tìm:

```blade
    @vite(['resources/css/operator.css', 'resources/js/money-format.js', 'resources/js/ai-lead-suggest.js', 'resources/js/ai-design-item-suggest.js', 'resources/js/ai-opportunity-summary.js', 'resources/js/work-template-apply.js'])
```

Đổi thành:

```blade
    @vite(['resources/css/operator.css', 'resources/js/money-format.js', 'resources/js/ai-lead-suggest.js', 'resources/js/ai-design-item-suggest.js', 'resources/js/ai-opportunity-summary.js', 'resources/js/work-template-apply.js', 'resources/js/crm-pipeline-drag.js'])
```

- [ ] **Step 4: Build, xác nhận thành công**

Chạy:

```bash
npm run build
```

Expected: exit code 0, output liệt kê 1 file build mới cho `crm-pipeline-drag` (kèm hash, ví dụ `public/build/assets/crm-pipeline-drag-XXXXXXXX.js`).

- [ ] **Step 5: Viết smoke test — trang tải được, có đủ DOM contract từ Task 5**

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
        $managePermission = Permission::firstOrCreate(
            ['code' => 'crm.manage'],
            ['name' => 'crm.manage', 'module' => 'crm', 'action' => 'manage']
        );
        $viewPermission = Permission::firstOrCreate(
            ['code' => 'crm.view'],
            ['name' => 'crm.view', 'module' => 'crm', 'action' => 'view']
        );
        $role->permissions()->sync([$managePermission->id, $viewPermission->id]);
        UserRole::create(['user_id' => (string) $this->user->id, 'role_id' => (string) $role->id]);

        $this->account = Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'display_name' => 'Khách hàng Dusk test',
        ]);
    }

    protected function makeOpportunity(array $overrides = []): Opportunity
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

    public function test_crm_index_page_loads_with_pipeline_drag_script_and_dom_contract(): void
    {
        $opportunity = $this->makeOpportunity();

        $this->browse(function (Browser $browser) use ($opportunity) {
            $browser->loginAs($this->user)
                ->visit('/app/crm')
                ->waitFor('[data-board-group="new"]', 10)
                ->assertSee('Pipeline kinh doanh')
                ->assertPresent('[data-opportunity-id="' . $opportunity->id . '"]')
                ->assertPresent('[data-opportunity-id="' . $opportunity->id . '"] .crm-drag-handle')
                ->assertPresent('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn')
                ->assertPresent('[data-crm-stage-dialog]');
        });
    }
}
```

Test này chỉ xác nhận HTML/DOM contract từ Task 5 render đúng và trang không vỡ khi thêm `@vite` entry mới — **không** xác nhận bất kỳ hành vi JS nào (click/drag) vì chưa có hành vi nào tồn tại.

- [ ] **Step 6: Chạy test, xác nhận pass**

Chạy:

```bash
php artisan dusk tests/Browser/Crm/ --env=testing --without-tty
```

Expected: `OK (1 test, N assertions)`.

- [ ] **Step 7: Commit**

```bash
git add resources/js/crm-pipeline-drag.js vite.config.js resources/views/layouts/operator.blade.php tests/Browser/Crm/PipelineDragDropTest.php
git commit -m "chore(crm): scaffold crm-pipeline-drag.js entry (no behavior yet)"
```

---

### Task 7: Slice 1 — Module init + click mở dialog (group picker)

**Files:**
- Modify: `resources/js/crm-pipeline-drag.js`
- Modify: `resources/css/operator.css` (CSS tối thiểu cho `<dialog>`/backdrop — cần ngay vì dialog xuất hiện lần đầu ở slice này, không để CSS chờ tới cuối)
- Test: `tests/Browser/Crm/PipelineDragDropTest.php` (thêm test)

**Interfaces:**
- Consumes: DOM contract Task 5/6 (`.crm-stage-transition-btn`, `[data-crm-stage-dialog]`, `[data-dialog-group-picker]`, `.crm-dialog-group-option`, `[data-board-group]`, `[data-dialog-cancel]`).
- Produces: `initializePipelineDragDrop()` (entry point), `initClickFallback()`, `openStageDialog(card)` — **chỉ 1 tham số** ở slice này, chỉ hỗ trợ bước "chọn cột đích" (group picker). Task 8 (slice 2) sẽ refactor thêm tham số `targetGroupKey` tùy chọn khi cần bước preselect cho choice_options — refactor đó có RED test riêng của chính nó ở Task 8, không viết trước ở đây. Cancel-button handler cũng thuộc slice này (vòng đời dialog, không phải logic chọn lựa).

- [ ] **Step 1: Viết Dusk test thất bại**

Thêm vào `tests/Browser/Crm/PipelineDragDropTest.php`, sau `test_crm_index_page_loads_with_pipeline_drag_script_and_dom_contract`:

```php
    public function test_click_transition_opens_dialog_with_group_picker_excluding_current_group(): void
    {
        $opportunity = $this->makeOpportunity(); // stage mặc định new_lead → group 'new'

        $this->browse(function (Browser $browser) use ($opportunity) {
            $browser->loginAs($this->user)
                ->visit('/app/crm')
                ->waitFor('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn', 10)
                ->click('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn')
                ->waitFor('[data-crm-stage-dialog][open]', 10)
                ->assertVisible('[data-crm-stage-dialog] .crm-dialog-group-option[data-group="consulting_survey"]')
                ->assertVisible('[data-crm-stage-dialog] .crm-dialog-group-option[data-group="quote"]')
                ->assertVisible('[data-crm-stage-dialog] .crm-dialog-group-option[data-group="negotiation_contract"]')
                ->assertVisible('[data-crm-stage-dialog] .crm-dialog-group-option[data-group="won"]')
                ->assertVisible('[data-crm-stage-dialog] .crm-dialog-group-option[data-group="lost_nurture"]')
                ->assertScript(
                    "return document.querySelector('[data-crm-stage-dialog] .crm-dialog-group-option[data-group=\"new\"]').classList.contains('hidden');",
                    true
                );
        });
    }

    public function test_cancel_dialog_closes_without_changing_card(): void
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
```

- [ ] **Step 2: Chạy test, xác nhận thất bại đúng lý do**

Chạy:

```bash
php artisan dusk tests/Browser/Crm/PipelineDragDropTest.php --env=testing --without-tty --filter "test_click_transition_opens_dialog_with_group_picker_excluding_current_group|test_cancel_dialog_closes_without_changing_card"
```

Expected: FAIL cả 2 test — module hiện là khung rỗng (Task 6), không có listener nào gắn vào `.crm-stage-transition-btn`, click không làm gì, `waitFor('[data-crm-stage-dialog][open]', 10)` timeout sau 10 giây.

- [ ] **Step 3: Triển khai tối thiểu — chỉ đủ để mở/đóng dialog ở bước group-picker**

Thay nội dung `resources/js/crm-pipeline-drag.js` (giữ nguyên comment đầu file, thay phần thân IIFE):

```js
(function () {
    'use strict';

    var activeDialogContext = null; // { card } — Task 8 mở rộng thêm targetGroupKey

    function openStageDialog(card) {
        var dialog = document.querySelector('[data-crm-stage-dialog]');
        var nameEl = dialog.querySelector('[data-dialog-opportunity-name]');
        var groupPicker = dialog.querySelector('[data-dialog-group-picker]');

        var opportunityNameEl = card.querySelector('.operator-link');
        nameEl.textContent = opportunityNameEl ? opportunityNameEl.textContent.trim() : '';

        var currentGroupKey = card.closest('[data-board-group]').dataset.boardGroup;
        groupPicker.classList.remove('hidden');
        groupPicker.querySelectorAll('.crm-dialog-group-option').forEach(function (btn) {
            btn.classList.toggle('hidden', btn.dataset.group === currentGroupKey);
        });

        activeDialogContext = { card: card };
        dialog.showModal();
    }

    function initStageDialog() {
        var dialog = document.querySelector('[data-crm-stage-dialog]');
        if (!dialog) return;

        var cancelBtn = dialog.querySelector('[data-dialog-cancel]');
        cancelBtn.addEventListener('click', function () {
            activeDialogContext = null;
            dialog.close();
        });
    }

    function initClickFallback() {
        document.querySelectorAll('.crm-stage-transition-btn').forEach(function (btn) {
            btn.addEventListener('click', function (event) {
                var card = event.currentTarget.closest('[data-opportunity-id]');
                if (card.getAttribute('aria-busy') === 'true') return;
                openStageDialog(card);
            });
        });
    }

    function initializePipelineDragDrop() {
        if (!document.querySelector('[data-board-group]')) return; // không phải trang crm.index
        initStageDialog();
        initClickFallback();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializePipelineDragDrop);
    } else {
        initializePipelineDragDrop();
    }
})();
```

- [ ] **Step 4: CSS tối thiểu cho `<dialog>`/backdrop**

Thêm vào cuối `resources/css/operator.css`:

```css
/* Kéo-thả pipeline CRM — dialog dùng chung */
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

- [ ] **Step 5: Build**

Chạy:

```bash
npm run build
```

Expected: exit code 0.

- [ ] **Step 6: Chạy lại test, xác nhận pass**

Chạy:

```bash
php artisan dusk tests/Browser/Crm/PipelineDragDropTest.php --env=testing --without-tty
```

Expected: `OK` toàn bộ file (3 test: smoke Task 6 + 2 test mới).

- [ ] **Step 7: Commit**

```bash
git add resources/js/crm-pipeline-drag.js resources/css/operator.css tests/Browser/Crm/PipelineDragDropTest.php
git commit -m "feat(crm): slice 1 — click Chuyển giai đoạn opens shared dialog group picker"
```

---

### Task 8: Slice 2 — `requestStageTransition()` dùng chung, choice options, validation `lost_reason`

**Files:**
- Modify: `resources/js/crm-pipeline-drag.js`
- Test: `tests/Browser/Crm/PipelineDragDropTest.php` (thêm test)

**Interfaces:**
- Consumes: `openStageDialog(card)`, `activeDialogContext` (Task 7); DOM `data-requires-choice`/`data-choice-options`/`data-default-entry-stage` trên phần tử cột (Task 5).
- Produces: **`requestStageTransition(card, targetGroupKey)`** — hàm orchestration dùng chung DUY NHẤT theo đúng Global Constraint, lần đầu xuất hiện ở đây (không phải ở slice 5 — vì click cũng cần nó ngay khi user chọn 1 group trong dialog, không chỉ kéo-thả mới cần). `submitStageTransition(card, targetGroupKey, toStage, reason)` — **stub tối thiểu** ở slice này (chỉ đóng dialog + đặt pending, CHƯA gọi mạng — Task 9/slice 3 thay thân hàm bằng bản đầy đủ). `setCardPending()`. `renderChoiceOptions()`. `openStageDialog()` được **refactor** thêm tham số `targetGroupKey` tùy chọn (chế độ preselect — bỏ qua group-picker, vào thẳng choice_options). Task 9 phụ thuộc `submitStageTransition()` đã tồn tại (dạng stub) để thay thân hàm. Task 11 (slice 5, kéo-thả) phụ thuộc `requestStageTransition()` đã tồn tại đầy đủ — **không viết lại** ở Task 11, chỉ gọi lại.

- [ ] **Step 1: Viết Dusk test thất bại**

Thêm vào `tests/Browser/Crm/PipelineDragDropTest.php`:

```php
    public function test_selecting_lost_nurture_group_shows_three_choice_options(): void
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
                ->assertPresent('[data-dialog-choice-picker] input[value="no_bid"]')
                ->assertPresent('[data-dialog-choice-picker] input[value="nurture"]')
                // preselect: bước group-picker bị ẩn đi khi đã vào bước choice_options
                ->assertScript(
                    "return document.querySelector('[data-dialog-group-picker]').classList.contains('hidden');",
                    true
                )
                // dialog vẫn đang mở (không đóng lại khi chuyển bước)
                ->assertScript("return document.querySelector('[data-crm-stage-dialog]').open;", true);
        });
    }

    public function test_choosing_lost_requires_reason_before_confirm_enables(): void
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
                ->assertScript(
                    "return document.querySelector('[data-dialog-confirm]').disabled;",
                    true
                )
                ->type('[data-dialog-reason]', 'Khách chọn đối thủ khác')
                ->assertScript(
                    "return document.querySelector('[data-dialog-confirm]').disabled;",
                    false
                );
        });
    }

    public function test_choosing_no_bid_does_not_require_reason(): void
    {
        $opportunity = $this->makeOpportunity();

        $this->browse(function (Browser $browser) use ($opportunity) {
            $browser->loginAs($this->user)
                ->visit('/app/crm')
                ->waitFor('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn', 10)
                ->click('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn')
                ->waitFor('[data-crm-stage-dialog][open]', 10)
                ->click('[data-crm-stage-dialog] .crm-dialog-group-option[data-group="lost_nurture"]')
                ->waitFor('[data-dialog-choice-picker] input[value="no_bid"]', 10)
                ->click('[data-dialog-choice-picker] input[value="no_bid"]')
                ->assertScript(
                    "return document.querySelector('[data-dialog-confirm]').disabled;",
                    false
                );
        });
    }

    public function test_selecting_normal_group_closes_dialog_and_sets_pending(): void
    {
        // Group KHÔNG requires_choice: chọn group xong là submit ngay (không cần bước
        // Xác nhận riêng) — đây là hành vi 1-bước cho group thường, đúng
        // Global Constraint "click và kéo-thả đi qua CÙNG MỘT requestStageTransition()".
        // Ở slice này submitStageTransition() còn là stub (chỉ đóng dialog + đặt pending,
        // CHƯA gọi mạng thật) nên card sẽ pending vĩnh viễn trong phạm vi test này — đúng
        // như kỳ vọng của slice 2, Task 9 mới hoàn thiện phần gọi mạng.
        $opportunity = $this->makeOpportunity();

        $this->browse(function (Browser $browser) use ($opportunity) {
            $browser->loginAs($this->user)
                ->visit('/app/crm')
                ->waitFor('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn', 10)
                ->click('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn')
                ->waitFor('[data-crm-stage-dialog][open]', 10)
                ->click('[data-crm-stage-dialog] .crm-dialog-group-option[data-group="consulting_survey"]')
                ->waitUntilMissing('[data-crm-stage-dialog][open]', 10)
                ->assertAttribute('[data-opportunity-id="' . $opportunity->id . '"]', 'aria-busy', 'true');
        });
    }
```

- [ ] **Step 2: Chạy test, xác nhận thất bại đúng lý do**

Chạy:

```bash
php artisan dusk tests/Browser/Crm/PipelineDragDropTest.php --env=testing --without-tty --filter "test_selecting_lost_nurture_group_shows_three_choice_options|test_choosing_lost_requires_reason_before_confirm_enables|test_choosing_no_bid_does_not_require_reason|test_selecting_normal_group_closes_dialog_and_sets_pending"
```

Expected: FAIL cả 4 test — nút `.crm-dialog-group-option` chưa có listener nào (Task 7 chỉ mở dialog, chưa xử lý click bên trong group picker), nên không có gì xảy ra khi bấm 1 group: dialog không đóng, `[data-dialog-choice-picker]` luôn rỗng, `aria-busy` không đổi.

- [ ] **Step 3: Triển khai tối thiểu — `requestStageTransition()`, stub `submitStageTransition()`, `renderChoiceOptions()`, refactor `openStageDialog()`**

Trong `resources/js/crm-pipeline-drag.js`, refactor `openStageDialog(card)` (Task 7) thành `openStageDialog(card, targetGroupKey)` — thay toàn bộ hàm:

```js
    function openStageDialog(card, targetGroupKey) {
        var dialog = document.querySelector('[data-crm-stage-dialog]');
        var nameEl = dialog.querySelector('[data-dialog-opportunity-name]');
        var groupPicker = dialog.querySelector('[data-dialog-group-picker]');
        var choicePicker = dialog.querySelector('[data-dialog-choice-picker]');
        var reasonEl = dialog.querySelector('[data-dialog-reason]');
        var confirmBtn = dialog.querySelector('[data-dialog-confirm]');

        var opportunityNameEl = card.querySelector('.operator-link');
        nameEl.textContent = opportunityNameEl ? opportunityNameEl.textContent.trim() : '';

        choicePicker.classList.add('hidden');
        choicePicker.innerHTML = '';
        reasonEl.classList.add('hidden');
        reasonEl.value = '';
        confirmBtn.disabled = true;

        if (targetGroupKey) {
            // Preselect: bỏ qua bước chọn cột, vào thẳng choice_options (dùng cho kéo-thả
            // vào lost_nurture VÀ cho click khi user vừa chọn group requires_choice trong
            // group-picker đang mở).
            groupPicker.classList.add('hidden');
            activeDialogContext = { card: card, targetGroupKey: targetGroupKey };
            renderChoiceOptions(targetGroupKey, choicePicker, reasonEl, confirmBtn);
            if (!dialog.open) dialog.showModal();
            return;
        }

        var currentGroupKey = card.closest('[data-board-group]').dataset.boardGroup;
        groupPicker.classList.remove('hidden');
        groupPicker.querySelectorAll('.crm-dialog-group-option').forEach(function (btn) {
            btn.classList.toggle('hidden', btn.dataset.group === currentGroupKey);
        });

        activeDialogContext = { card: card };
        dialog.showModal();
    }
```

Thêm `renderChoiceOptions`, `setCardPending`, `submitStageTransition` (stub), `requestStageTransition` (đặt trước `initStageDialog`):

```js
    function renderChoiceOptions(groupKey, choicePicker, reasonEl, confirmBtn) {
        var groupEl = document.querySelector('[data-board-group="' + groupKey + '"]');
        var options = JSON.parse(groupEl.dataset.choiceOptions || '[]');

        choicePicker.innerHTML = '';
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

        reasonEl.oninput = function () {
            var checked = choicePicker.querySelector('input[name="stage_choice"]:checked');
            var requiresReason = checked && checked.dataset.requiresReason === '1';
            confirmBtn.disabled = requiresReason && reasonEl.value.trim() === '';
        };
    }

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

    function submitStageTransition(card, targetGroupKey, toStage, reason) {
        var dialog = document.querySelector('[data-crm-stage-dialog]');
        if (dialog.open) dialog.close();
        activeDialogContext = null;
        setCardPending(card, true);
        // Task 9 (slice 3) thay toàn bộ phần dưới đây: gọi postStageUpdate(), xử lý
        // thành công (Task 10 gắn commitCardTransition) / lỗi qua parseErrorResponse()
        // + showToast(). Ở slice này CHƯA gọi mạng — card sẽ pending vĩnh viễn, đúng ý.
    }

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
```

Sửa `initStageDialog()` — thêm wiring group-option (gọi `requestStageTransition`) + confirm button (chỉ dùng ở bước choice_options, gọi thẳng `submitStageTransition`):

```js
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
                var card = activeDialogContext.card;
                requestStageTransition(card, btn.dataset.group);
            });
        });

        confirmBtn.addEventListener('click', function () {
            if (confirmBtn.disabled || !activeDialogContext) return;

            var card = activeDialogContext.card;
            var targetGroupKey = activeDialogContext.targetGroupKey;
            var checked = choicePicker.querySelector('input[name="stage_choice"]:checked');
            if (!checked) return;

            var toStage = checked.value;
            var reason = checked.dataset.requiresReason === '1' ? reasonEl.value.trim() : null;

            submitStageTransition(card, targetGroupKey, toStage, reason);
        });

        cancelBtn.addEventListener('click', function () {
            activeDialogContext = null;
            dialog.close();
        });
    }
```

Lưu ý: nút group-option (bước group-picker) đọc `card` từ `activeDialogContext.card` — do `openStageDialog(card)` (không preselect) đã set `activeDialogContext = { card: card }` trước khi mở, nên tại thời điểm user bấm 1 group option, `activeDialogContext.card` luôn sẵn có.

- [ ] **Step 4: Build**

```bash
npm run build
```

Expected: exit code 0.

- [ ] **Step 5: Chạy lại test, xác nhận pass**

```bash
php artisan dusk tests/Browser/Crm/PipelineDragDropTest.php --env=testing --without-tty
```

Expected: `OK` toàn bộ file.

- [ ] **Step 6: Commit**

```bash
git add resources/js/crm-pipeline-drag.js
git commit -m "feat(crm): slice 2 — shared requestStageTransition(), choice options, lost_reason validation"
```

---

### Task 9: Slice 3 — Hoàn thiện `submitStageTransition()`: gọi mạng thật, pending state, double-submit, error normalization

**Files:**
- Modify: `resources/js/crm-pipeline-drag.js`
- Modify: `resources/css/operator.css` (pending state — cần ngay vì slice này lần đầu thực sự set `aria-busy` bằng network thật)
- Test: `tests/Browser/Crm/PipelineDragDropTest.php` (thêm test)

**Interfaces:**
- Consumes: route `POST /crm/opportunities/{id}/stage` (Task 4, đã trả JSON đúng shape), stub `submitStageTransition()` (Task 8), `<meta name="csrf-token">`.
- Produces: `csrfToken()`, `postJson()`, `postStageUpdate()`, `parseErrorResponse()`, `showToast()`. **Thay thân hàm** `submitStageTransition()` (không đổi chữ ký, không đổi call site nào ở Task 8) từ stub sang bản gọi mạng thật, nhánh thành công tạm thời chỉ gọi `setCardPending(card, false)` (Task 10/slice 4 thay tiếp bằng `commitCardTransition`).

- [ ] **Step 1: Viết Dusk test thất bại**

Thêm vào `tests/Browser/Crm/PipelineDragDropTest.php`:

```php
    public function test_normal_group_click_sends_request_and_blocks_duplicate(): void
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
                ->pause(300)
                ->assertAttribute('[data-opportunity-id="' . $opportunity->id . '"]', 'aria-busy', 'true');

            $pendingAfterClick = $browser->script('return window.__pendingCount;')[0];
            $this->assertSame(1, $pendingAfterClick);

            // Nút transition đã bị disable bởi setCardPending — không còn cách nào double-submit
            // qua UI. Đợi thêm rồi xác nhận __pendingCount không tự tăng thêm lần nào nữa.
            $browser->pause(500);
            $pendingAfterWait = $browser->script('return window.__pendingCount;')[0];
            $this->assertSame(1, $pendingAfterWait);

            $browser->assertScript(
                "return document.querySelector('[data-opportunity-id=\"{$opportunity->id}\"] .crm-stage-transition-btn').disabled;",
                true
            );
        });
    }

    public function test_error_403_response_shows_toast_clears_pending_and_keeps_card(): void
    {
        $opportunity = $this->makeOpportunity();

        $this->browse(function (Browser $browser) use ($opportunity) {
            $browser->loginAs($this->user)
                ->visit('/app/crm')
                ->waitFor('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn', 10)
                ->script([
                    "window.fetch = function() { return Promise.resolve(new Response(JSON.stringify({message: 'Bạn không có quyền thực hiện thao tác này.'}), {status: 403})); };",
                ])
                ->click('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn')
                ->waitFor('[data-crm-stage-dialog][open]', 10)
                ->click('[data-crm-stage-dialog] .crm-dialog-group-option[data-group="consulting_survey"]')
                ->waitForText('Bạn không có quyền thực hiện thao tác này.', 10)
                ->assertAttribute('[data-opportunity-id="' . $opportunity->id . '"]', 'aria-busy', 'false')
                ->assertPresent('[data-board-group="new"] [data-opportunity-id="' . $opportunity->id . '"]');
        });

        $opportunity->refresh();
        $this->assertSame(Opportunity::STAGE_NEW_LEAD, $opportunity->pipeline_stage);
    }

    public function test_error_500_response_shows_generic_toast_and_clears_pending(): void
    {
        $opportunity = $this->makeOpportunity();

        $this->browse(function (Browser $browser) use ($opportunity) {
            $browser->loginAs($this->user)
                ->visit('/app/crm')
                ->waitFor('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn', 10)
                ->script([
                    "window.fetch = function() { return Promise.resolve(new Response('Internal Server Error', {status: 500})); };",
                ])
                ->click('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn')
                ->waitFor('[data-crm-stage-dialog][open]', 10)
                ->click('[data-crm-stage-dialog] .crm-dialog-group-option[data-group="consulting_survey"]')
                ->waitForText('Có lỗi xảy ra, vui lòng thử lại.', 10)
                ->assertAttribute('[data-opportunity-id="' . $opportunity->id . '"]', 'aria-busy', 'false')
                ->assertPresent('[data-board-group="new"] [data-opportunity-id="' . $opportunity->id . '"]');
        });

        $opportunity->refresh();
        $this->assertSame(Opportunity::STAGE_NEW_LEAD, $opportunity->pipeline_stage);
    }

    public function test_successful_submit_clears_pending_state(): void
    {
        // Backend thật (đã nối xong từ Task 4) — chỉ xác nhận pending được gỡ,
        // CHƯA xác nhận card di chuyển cột (đó là Task 10/slice 4, chưa tồn tại).
        $opportunity = $this->makeOpportunity();

        $this->browse(function (Browser $browser) use ($opportunity) {
            $browser->loginAs($this->user)
                ->visit('/app/crm')
                ->waitFor('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn', 10)
                ->click('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn')
                ->waitFor('[data-crm-stage-dialog][open]', 10)
                ->click('[data-crm-stage-dialog] .crm-dialog-group-option[data-group="consulting_survey"]')
                ->pause(500);

            $ariaBusy = $browser->script(
                "return document.querySelector('[data-opportunity-id=\"{$opportunity->id}\"]').getAttribute('aria-busy');"
            )[0];
            $this->assertSame('false', $ariaBusy);
        });

        $opportunity->refresh();
        $this->assertSame(Opportunity::STAGE_BRIEF_DISCOVERY, $opportunity->pipeline_stage); // backend ĐÃ đổi thật
    }
```

- [ ] **Step 2: Chạy test, xác nhận thất bại đúng lý do**

Chạy:

```bash
php artisan dusk tests/Browser/Crm/PipelineDragDropTest.php --env=testing --without-tty --filter "test_normal_group_click_sends_request_and_blocks_duplicate|test_error_403_response_shows_toast_clears_pending_and_keeps_card|test_error_500_response_shows_generic_toast_and_clears_pending|test_successful_submit_clears_pending_state"
```

Expected: FAIL cả 4 test — `submitStageTransition()` (Task 8) hiện là stub, không gọi `fetch` nào (`window.__pendingCount` luôn 0), không có toast nào xuất hiện, `aria-busy` bị kẹt ở `true` vĩnh viễn (không bao giờ về `false`) vì stub không có nhánh hoàn tất.

- [ ] **Step 3: Thay thân `submitStageTransition()` — gọi mạng thật + error normalization tập trung**

Thêm vào `resources/js/crm-pipeline-drag.js` (các hàm mới, đặt trước `setCardPending`):

```js
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

    function postStageUpdate(opportunityId, toStage, reason) {
        var url = '/crm/opportunities/' + encodeURIComponent(opportunityId) + '/stage';
        var body = { pipeline_stage: toStage };
        if (reason) body.lost_reason = reason;
        return postJson(url, body);
    }

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
```

Thay thân `submitStageTransition()` (Task 8) — từ:

```js
    function submitStageTransition(card, targetGroupKey, toStage, reason) {
        var dialog = document.querySelector('[data-crm-stage-dialog]');
        if (dialog.open) dialog.close();
        activeDialogContext = null;
        setCardPending(card, true);
        // Task 9 (slice 3) thay toàn bộ phần dưới đây: gọi postStageUpdate(), xử lý
        // thành công (Task 10 gắn commitCardTransition) / lỗi qua parseErrorResponse()
        // + showToast(). Ở slice này CHƯA gọi mạng — card sẽ pending vĩnh viễn, đúng ý.
    }
```

thành:

```js
    function submitStageTransition(card, targetGroupKey, toStage, reason) {
        var dialog = document.querySelector('[data-crm-stage-dialog]');
        if (dialog.open) dialog.close();
        activeDialogContext = null;
        setCardPending(card, true);

        return postStageUpdate(card.dataset.opportunityId, toStage, reason)
            .then(function (response) {
                if (response.ok) {
                    // Task 10 (slice 4) thay dòng dưới bằng commitCardTransition(card, targetGroupKey, body.data)
                    setCardPending(card, false);
                    return;
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
```

Chữ ký `submitStageTransition()` không đổi, mọi call site ở Task 8 (group-option handler, confirm handler) không cần sửa.

- [ ] **Step 4: CSS pending state**

Thêm vào cuối `resources/css/operator.css`:

```css
[aria-busy="true"] {
    opacity: 0.6;
    pointer-events: none;
}
.crm-drag-handle:disabled {
    cursor: not-allowed;
    opacity: 0.4;
}
```

- [ ] **Step 5: Build**

```bash
npm run build
```

Expected: exit code 0.

- [ ] **Step 6: Chạy lại test, xác nhận pass**

```bash
php artisan dusk tests/Browser/Crm/PipelineDragDropTest.php --env=testing --without-tty
```

Expected: `OK` toàn bộ file.

- [ ] **Step 7: Commit**

```bash
git add resources/js/crm-pipeline-drag.js resources/css/operator.css
git commit -m "feat(crm): slice 3 — real network submit, pending/double-submit guard, centralized error parsing"
```

---

### Task 10: Slice 4 — DOM commit, stage badge, aggregate, terminal controls

**Files:**
- Modify: `resources/js/crm-pipeline-drag.js`
- Test: `tests/Browser/Crm/PipelineDragDropTest.php` (thêm test)

**Interfaces:**
- Consumes: `submitStageTransition()` (Task 9), JSON response shape `{message, data:{id,pipeline_stage,is_terminal}}` (Task 4), `data-column-count`/`data-column-total`/`data-amount` (Task 5).
- Produces: `commitCardTransition()`, `recalculateColumnSummary()`, `stageLabelFor()`, `formatVnd()`. Task 11 (slice 5) gọi lại đúng các hàm này (gián tiếp qua `submitStageTransition`) sau khi kéo-thả thành công — không viết logic DOM-commit riêng cho drag.

- [ ] **Step 1: Viết Dusk test thất bại**

Thêm vào `tests/Browser/Crm/PipelineDragDropTest.php`:

```php
    public function test_successful_submit_moves_card_and_updates_column_aggregates(): void
    {
        $opportunityToMove = $this->makeOpportunity(['estimated_fee' => 1000000000]);
        $otherInSourceColumn = $this->makeOpportunity(['estimated_fee' => 500000000]);

        $this->browse(function (Browser $browser) use ($opportunityToMove, $otherInSourceColumn) {
            $browser->loginAs($this->user)
                ->visit('/app/crm')
                ->waitFor('[data-opportunity-id="' . $opportunityToMove->id . '"] .crm-stage-transition-btn', 10)
                ->click('[data-opportunity-id="' . $opportunityToMove->id . '"] .crm-stage-transition-btn')
                ->waitFor('[data-crm-stage-dialog][open]', 10)
                ->click('[data-crm-stage-dialog] .crm-dialog-group-option[data-group="consulting_survey"]')
                ->waitFor('[data-board-group="consulting_survey"] [data-opportunity-id="' . $opportunityToMove->id . '"]', 10)
                ->assertMissing('[data-board-group="new"] [data-opportunity-id="' . $opportunityToMove->id . '"]')
                ->assertScript(
                    "return document.querySelector('[data-opportunity-id=\"{$opportunityToMove->id}\"]').dataset.currentStage;",
                    Opportunity::STAGE_BRIEF_DISCOVERY
                )
                ->assertScript(
                    "return document.querySelector('[data-board-group=\"new\"] [data-column-count]').textContent.trim();",
                    '1' // chỉ còn $otherInSourceColumn
                )
                ->assertScript(
                    "return document.querySelector('[data-board-group=\"consulting_survey\"] [data-column-count]').textContent.trim();",
                    '1'
                );
        });
    }

    public function test_terminal_response_removes_handle_and_transition_button(): void
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
                ->waitFor('[data-board-group="lost_nurture"] [data-opportunity-id="' . $opportunity->id . '"]', 10)
                ->assertMissing('[data-opportunity-id="' . $opportunity->id . '"] .crm-drag-handle')
                ->assertMissing('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn');
        });

        $opportunity->refresh();
        $this->assertSame(Opportunity::STAGE_LOST, $opportunity->pipeline_stage);
        $this->assertSame('Khách chọn đối thủ khác', $opportunity->lost_reason);
    }

    public function test_nurture_response_keeps_handle_and_transition_button(): void
    {
        $opportunity = $this->makeOpportunity();

        $this->browse(function (Browser $browser) use ($opportunity) {
            $browser->loginAs($this->user)
                ->visit('/app/crm')
                ->waitFor('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn', 10)
                ->click('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn')
                ->waitFor('[data-crm-stage-dialog][open]', 10)
                ->click('[data-crm-stage-dialog] .crm-dialog-group-option[data-group="lost_nurture"]')
                ->waitFor('[data-dialog-choice-picker] input[value="nurture"]', 10)
                ->click('[data-dialog-choice-picker] input[value="nurture"]')
                ->click('[data-dialog-confirm]')
                ->waitFor('[data-board-group="lost_nurture"] [data-opportunity-id="' . $opportunity->id . '"]', 10)
                ->assertPresent('[data-opportunity-id="' . $opportunity->id . '"] .crm-drag-handle')
                ->assertPresent('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn');
        });

        $opportunity->refresh();
        $this->assertSame(Opportunity::STAGE_NURTURE, $opportunity->pipeline_stage);
        $this->assertFalse($opportunity->isTerminal());
    }
```

- [ ] **Step 2: Chạy test, xác nhận thất bại đúng lý do**

Chạy:

```bash
php artisan dusk tests/Browser/Crm/PipelineDragDropTest.php --env=testing --without-tty --filter "test_successful_submit_moves_card_and_updates_column_aggregates|test_terminal_response_removes_handle_and_transition_button|test_nurture_response_keeps_handle_and_transition_button"
```

Expected: FAIL cả 3 test — nhánh thành công của `submitStageTransition()` (Task 9) hiện chỉ gọi `setCardPending(card, false)`, không di chuyển `<li>` sang cột khác, không cập nhật count/tổng, không xóa handle/nút khi terminal → mọi `waitFor(...)` chờ card xuất hiện ở cột đích sẽ timeout.

- [ ] **Step 3: Triển khai tối thiểu — DOM commit đầy đủ 8 mục**

Thêm vào `resources/js/crm-pipeline-drag.js` (đặt trước `submitStageTransition`):

```js
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
```

Sửa nhánh thành công của `submitStageTransition()` (Task 9) — thay:

```js
                if (response.ok) {
                    // Task 10 (slice 4) thay dòng dưới bằng commitCardTransition(card, targetGroupKey, body.data)
                    setCardPending(card, false);
                    return;
                }
```

bằng:

```js
                if (response.ok) {
                    return response.json().then(function (body) {
                        commitCardTransition(card, targetGroupKey, body.data);
                    });
                }
```

- [ ] **Step 4: Build**

```bash
npm run build
```

Expected: exit code 0.

- [ ] **Step 5: Chạy lại test, xác nhận pass**

```bash
php artisan dusk tests/Browser/Crm/PipelineDragDropTest.php --env=testing --without-tty
```

Expected: `OK` toàn bộ file. Đặc biệt xác nhận `test_successful_submit_clears_pending_state` (Task 9) vẫn pass (giờ card thực sự di chuyển thay vì chỉ gỡ pending, nhưng assertion của test đó chỉ kiểm `aria-busy`, không kiểm vị trí — không xung đột).

- [ ] **Step 6: Commit**

```bash
git add resources/js/crm-pipeline-drag.js
git commit -m "feat(crm): slice 4 — full DOM commit (position, stage, terminal controls, column aggregates)"
```

---

### Task 11: Slice 5 — HTML5 drag events gọi lại `requestStageTransition()` đã có, `data-dragover` đầy đủ

**Files:**
- Modify: `resources/js/crm-pipeline-drag.js`
- Modify: `resources/css/operator.css` (drag handle cursor + highlight cột đích)
- Test: `tests/Browser/Crm/PipelineDragDropTest.php` (thêm test)

**Interfaces:**
- Consumes: `requestStageTransition()` (Task 8 — **đã tồn tại đầy đủ, không viết lại, không refactor**), `openStageDialog(card, targetGroupKey)` (Task 8, chế độ preselect đã có sẵn).
- Produces: `initDragDrop()` — hàm DUY NHẤT mới ở slice này, chỉ làm nhiệm vụ wiring HTML5 Drag & Drop event lên DOM và gọi lại `requestStageTransition()` đã có. `data-dragover` được set/clear đầy đủ theo đúng vòng đời `dragenter`→`dragleave`/`drop`/`dragend`.

Không cần Dusk giả lập chuột kéo-thả thật (WebDriver Actions API cho HTML5 DnD nổi tiếng không ổn định) — dùng `$browser->script()` để `dispatchEvent` synthetic `DragEvent`/`Event` trực tiếp lên đúng phần tử, gọi thẳng listener thật đã gắn trong JS (không phải giả lập qua helper riêng — đây chính là cách xác nhận `data-dragover` luôn được clear đúng, có tiêu chí pass/fail rõ ràng qua `assertScript`).

- [ ] **Step 1: Viết Dusk test thất bại**

Thêm vào `tests/Browser/Crm/PipelineDragDropTest.php`:

```php
    public function test_dragover_sets_and_clears_data_dragover_through_full_lifecycle(): void
    {
        $opportunity = $this->makeOpportunity();

        $this->browse(function (Browser $browser) use ($opportunity) {
            $browser->loginAs($this->user)
                ->visit('/app/crm')
                ->waitFor('[data-opportunity-id="' . $opportunity->id . '"] .crm-drag-handle', 10);

            // dragenter cột khác cột nguồn → data-dragover="1"
            $browser->script([
                "document.querySelector('[data-board-group=\"consulting_survey\"]').dispatchEvent(new Event('dragenter', {bubbles: true, cancelable: true}));",
            ]);
            $browser->assertScript(
                "return document.querySelector('[data-board-group=\"consulting_survey\"]').getAttribute('data-dragover');",
                '1'
            );

            // dragleave thật sự rời cột → clear
            $browser->script([
                "document.querySelector('[data-board-group=\"consulting_survey\"]').dispatchEvent(new Event('dragleave', {bubbles: true, cancelable: true}));",
            ]);
            $browser->assertScript(
                "return document.querySelector('[data-board-group=\"consulting_survey\"]').getAttribute('data-dragover');",
                null
            );

            // dragenter lại rồi drop → clear
            $browser->script([
                "document.querySelector('[data-board-group=\"consulting_survey\"]').dispatchEvent(new Event('dragenter', {bubbles: true, cancelable: true}));"
                . "document.querySelector('[data-board-group=\"consulting_survey\"]').dispatchEvent(new Event('drop', {bubbles: true, cancelable: true}));",
            ]);
            $browser->assertScript(
                "return document.querySelector('[data-board-group=\"consulting_survey\"]').getAttribute('data-dragover');",
                null
            );

            // dragenter nhiều cột rồi dragend (thả ngoài board) → clear TOÀN BỘ cột, không riêng 1 cột
            $browser->script([
                "document.querySelector('[data-board-group=\"quote\"]').dispatchEvent(new Event('dragenter', {bubbles: true, cancelable: true}));"
                . "document.querySelector('[data-board-group=\"won\"]').dispatchEvent(new Event('dragenter', {bubbles: true, cancelable: true}));"
                . "document.querySelector('.crm-drag-handle').dispatchEvent(new Event('dragend', {bubbles: true, cancelable: true}));",
            ]);
            $browser->assertScript(
                "return document.querySelector('[data-board-group=\"quote\"]').getAttribute('data-dragover');",
                null
            );
            $browser->assertScript(
                "return document.querySelector('[data-board-group=\"won\"]').getAttribute('data-dragover');",
                null
            );
        });
    }

    public function test_drop_on_different_column_triggers_stage_transition(): void
    {
        $opportunity = $this->makeOpportunity();

        $this->browse(function (Browser $browser) use ($opportunity) {
            $browser->loginAs($this->user)
                ->visit('/app/crm')
                ->waitFor('[data-opportunity-id="' . $opportunity->id . '"] .crm-drag-handle', 10)
                ->script([
                    "var handle = document.querySelector('[data-opportunity-id=\"{$opportunity->id}\"] .crm-drag-handle');"
                    . "var dt = new DataTransfer();"
                    . "handle.dispatchEvent(new DragEvent('dragstart', {bubbles: true, cancelable: true, dataTransfer: dt}));"
                    . "var target = document.querySelector('[data-board-group=\"consulting_survey\"]');"
                    . "target.dispatchEvent(new DragEvent('drop', {bubbles: true, cancelable: true, dataTransfer: dt}));",
                ])
                ->waitFor('[data-board-group="consulting_survey"] [data-opportunity-id="' . $opportunity->id . '"]', 10);
        });

        $opportunity->refresh();
        $this->assertSame(Opportunity::STAGE_BRIEF_DISCOVERY, $opportunity->pipeline_stage);
    }

    public function test_drop_into_lost_nurture_column_opens_dialog_preselected(): void
    {
        $opportunity = $this->makeOpportunity();

        $this->browse(function (Browser $browser) use ($opportunity) {
            $browser->loginAs($this->user)
                ->visit('/app/crm')
                ->waitFor('[data-opportunity-id="' . $opportunity->id . '"] .crm-drag-handle', 10)
                ->script([
                    "var handle = document.querySelector('[data-opportunity-id=\"{$opportunity->id}\"] .crm-drag-handle');"
                    . "var dt = new DataTransfer();"
                    . "handle.dispatchEvent(new DragEvent('dragstart', {bubbles: true, cancelable: true, dataTransfer: dt}));"
                    . "var target = document.querySelector('[data-board-group=\"lost_nurture\"]');"
                    . "target.dispatchEvent(new DragEvent('drop', {bubbles: true, cancelable: true, dataTransfer: dt}));",
                ])
                ->waitFor('[data-crm-stage-dialog][open]', 10)
                ->assertVisible('[data-dialog-choice-picker] input[value="lost"]')
                ->assertScript(
                    "return document.querySelector('[data-dialog-group-picker]').classList.contains('hidden');",
                    true
                );
        });

        $opportunity->refresh();
        $this->assertSame(Opportunity::STAGE_NEW_LEAD, $opportunity->pipeline_stage); // chưa submit, chỉ mở dialog
    }

    public function test_drop_on_same_column_is_noop(): void
    {
        $opportunity = $this->makeOpportunity();

        $this->browse(function (Browser $browser) use ($opportunity) {
            $browser->loginAs($this->user)
                ->visit('/app/crm')
                ->waitFor('[data-opportunity-id="' . $opportunity->id . '"] .crm-drag-handle', 10)
                ->script([
                    "var handle = document.querySelector('[data-opportunity-id=\"{$opportunity->id}\"] .crm-drag-handle');"
                    . "var dt = new DataTransfer();"
                    . "handle.dispatchEvent(new DragEvent('dragstart', {bubbles: true, cancelable: true, dataTransfer: dt}));"
                    . "var target = document.querySelector('[data-board-group=\"new\"]');"
                    . "target.dispatchEvent(new DragEvent('drop', {bubbles: true, cancelable: true, dataTransfer: dt}));",
                ])
                ->pause(300)
                ->assertPresent('[data-board-group="new"] [data-opportunity-id="' . $opportunity->id . '"]')
                ->assertMissing('[data-crm-stage-dialog][open]');
        });

        $opportunity->refresh();
        $this->assertSame(Opportunity::STAGE_NEW_LEAD, $opportunity->pipeline_stage);
    }
```

- [ ] **Step 2: Chạy test, xác nhận thất bại đúng lý do**

Chạy:

```bash
php artisan dusk tests/Browser/Crm/PipelineDragDropTest.php --env=testing --without-tty --filter "test_dragover_sets_and_clears_data_dragover_through_full_lifecycle|test_drop_on_different_column_triggers_stage_transition|test_drop_into_lost_nurture_column_opens_dialog_preselected|test_drop_on_same_column_is_noop"
```

Expected: FAIL cả 4 test — chưa có `initDragDrop()` nào gắn listener `dragenter`/`dragover`/`dragleave`/`drop`/`dragend` lên cột hay `dragstart`/`dragend` lên handle, nên không có gì xảy ra khi dispatch các event này (`data-dragover` không bao giờ xuất hiện, không có request nào được gửi, dialog không mở).

- [ ] **Step 3: Triển khai tối thiểu — chỉ `initDragDrop()`, gọi lại `requestStageTransition()` sẵn có**

Thêm vào `resources/js/crm-pipeline-drag.js` (đặt sau `initClickFallback`, trước `initializePipelineDragDrop`):

```js
    var draggedCard = null;

    function clearAllDragover() {
        document.querySelectorAll('[data-board-group][data-dragover]').forEach(function (el) {
            el.removeAttribute('data-dragover');
        });
    }

    function initDragDrop() {
        document.querySelectorAll('.crm-drag-handle').forEach(function (handle) {
            handle.addEventListener('dragstart', function (event) {
                var card = event.currentTarget.closest('[data-opportunity-id]');
                if (card.getAttribute('aria-busy') === 'true') {
                    event.preventDefault();
                    return;
                }
                draggedCard = card;
                if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
            });

            handle.addEventListener('dragend', function () {
                draggedCard = null;
                clearAllDragover();
            });
        });

        document.querySelectorAll('[data-board-group]').forEach(function (columnEl) {
            columnEl.addEventListener('dragenter', function (event) {
                event.preventDefault();
                if (!draggedCard) return;
                var sourceGroupKey = draggedCard.closest('[data-board-group]').dataset.boardGroup;
                if (columnEl.dataset.boardGroup === sourceGroupKey) return; // không highlight cột nguồn
                columnEl.setAttribute('data-dragover', '1');
            });

            columnEl.addEventListener('dragover', function (event) {
                event.preventDefault(); // bắt buộc để drop bắn được
            });

            columnEl.addEventListener('dragleave', function (event) {
                // chỉ clear khi con trỏ THỰC SỰ rời cột (relatedTarget không còn nằm trong columnEl)
                if (event.relatedTarget && columnEl.contains(event.relatedTarget)) return;
                columnEl.removeAttribute('data-dragover');
            });

            columnEl.addEventListener('drop', function (event) {
                event.preventDefault();
                columnEl.removeAttribute('data-dragover');
                if (!draggedCard) return;

                var sourceGroupKey = draggedCard.closest('[data-board-group]').dataset.boardGroup;
                var targetGroupKey = columnEl.dataset.boardGroup;
                var card = draggedCard;
                draggedCard = null;

                if (sourceGroupKey === targetGroupKey) {
                    return; // no-op tuyệt đối — không gọi API, không đổi DOM
                }

                requestStageTransition(card, targetGroupKey);
            });
        });
    }
```

Sửa `initializePipelineDragDrop()` — thêm gọi `initDragDrop()`:

```js
    function initializePipelineDragDrop() {
        if (!document.querySelector('[data-board-group]')) return;
        initStageDialog();
        initClickFallback();
        initDragDrop();
    }
```

Không sửa `requestStageTransition()`, `openStageDialog()`, `submitStageTransition()` — cả 3 hàm đã đầy đủ từ Task 8/9/10, `initDragDrop()` chỉ gọi lại, đúng yêu cầu "không viết logic chuyển stage riêng biệt giữa drag và click".

- [ ] **Step 4: CSS drag handle + highlight cột đích**

Thêm vào cuối `resources/css/operator.css`:

```css
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
[data-board-group][data-dragover="1"] {
    outline: 2px dashed theme('colors.emerald.400');
    outline-offset: -4px;
}
```

- [ ] **Step 5: Build**

```bash
npm run build
```

Expected: exit code 0.

- [ ] **Step 6: Chạy lại toàn bộ file Dusk, xác nhận pass**

```bash
php artisan dusk tests/Browser/Crm/PipelineDragDropTest.php --env=testing --without-tty
```

Expected: `OK` toàn bộ file (tổng cộng test từ Task 6-11).

- [ ] **Step 7: Commit**

```bash
git add resources/js/crm-pipeline-drag.js resources/css/operator.css
git commit -m "feat(crm): slice 5 — HTML5 drag events reuse requestStageTransition(), full data-dragover lifecycle"
```

---

### Task 12: Verification cuối — chạy toàn bộ suite liên quan, ghi lại kết quả

**Files:** không tạo/sửa file (trừ khi Step nào đó phát hiện lỗi cần fix — nếu có, quay lại đúng Task gây lỗi, không vá tại Task 12).

**Interfaces:**
- Consumes: toàn bộ Task 1-11.
- Produces: bằng chứng cuối cùng cho việc tính năng sẵn sàng review — không tuyên bố hoàn thành nếu bất kỳ command nào dưới đây chưa chạy và ghi lại kết quả thật.

- [ ] **Step 1: Targeted feature tests**

```bash
./vendor/bin/phpunit tests/Feature/Api/CrmApiTest.php tests/Feature/Zena/OperatorCrmUiTest.php
```

Expected: `OK` toàn bộ 2 file — bao gồm mọi test cũ (baseline Task 1) lẫn test mới (Task 2, 4, 5 — Task 5 giờ có 3 test render contract, không phải 2, vì có thêm test permission gating).

- [ ] **Step 2: Service + board-invariant unit tests**

```bash
./vendor/bin/phpunit tests/Unit/Services/Crm/OpportunityStageTransitionServiceTest.php tests/Unit/Http/Controllers/Web/CrmPageControllerBoardGroupsTest.php
```

Expected: `OK` cả 2 file (8 + 5 test — invariant #1 giờ là `test_group_key_set_matches_expected_stable_keys` thay vì test tautology cũ).

- [ ] **Step 3: Toàn bộ Dusk tests (Task 6-11 gộp trong 1 file)**

```bash
php artisan dusk tests/Browser/Crm/ --env=testing --without-tty
```

Expected: `OK` toàn bộ — tổng số test bằng tổng các test đã thêm qua Task 6 (1) + 7 (2) + 8 (4) + 9 (4) + 10 (3) + 11 (4) = 18 test.

- [ ] **Step 4: Static analysis (PHPStan) trên các file PHP đã đổi/tạo**

```bash
./vendor/bin/phpstan analyse app/Services/Crm/OpportunityStageTransitionService.php app/Http/Controllers/Api/OpportunityController.php app/Http/Controllers/Web/CrmPageController.php --level=6
```

Expected: `[OK] No errors`. Nếu môi trường làm việc là worktree copy+symlink (gotcha đã biết trong dự án — PHPStan local có thể lỗi fatal do autoload trùng), ghi rõ trong báo cáo và không coi đó là lỗi thật; đối chiếu bằng CI thật khi mở PR.

- [ ] **Step 5: Code style (Pint)**

```bash
./vendor/bin/pint --test app/Services/Crm/OpportunityStageTransitionService.php app/Http/Controllers/Api/OpportunityController.php app/Http/Controllers/Web/CrmPageController.php
```

Expected: `PASS`. Nếu có lệch, chạy `./vendor/bin/pint <cùng danh sách file>` rồi commit riêng "style: pint format".

- [ ] **Step 6: Frontend build**

```bash
npm run build
```

Expected: exit code 0.

- [ ] **Step 7: Route/security invariant có sẵn**

```bash
composer ssot:lint
```

Expected: exit code 0. Task 4 không thêm route mới (chỉ đổi tham số của `updateStage()`), nên không có route mới cần khai báo — chạy lệnh này để xác nhận thay đổi chữ ký method không phá vỡ guard nào đang tồn tại.

- [ ] **Step 8: Broader regression — toàn bộ CRM-liên quan**

```bash
./vendor/bin/phpunit --filter Crm
```

Expected: `OK` — lưới an toàn cuối, bắt bất kỳ test nào khác trong repo có chữ "Crm" trong tên class/method mà các bước trước chưa liệt kê tường minh.

- [ ] **Step 9: Tổng hợp báo cáo cuối**

Không có lệnh — tổng hợp kết quả 8 bước trên thành báo cáo cuối gửi cho người review, kèm số test/assertion mỗi bước, để đối chiếu với baseline Task 1. Không tuyên bố "hoàn thành" nếu bất kỳ bước nào ở trên chưa chạy hoặc chưa ghi lại kết quả thật (không đoán, không giả định "chắc sẽ pass").

---

## Đề xuất chia commit (đối chiếu với Task ở trên)

1. **Board config + invariants** — Task 2 (`refactor(crm): stable board group keys...`).
2. **Shared transition service refactor** — Task 3 (`feat(crm): extract OpportunityStageTransitionService...`) + Task 4 (`refactor(crm): wire both controllers...`) — 2 commit riêng vì Task 3 chưa nối controller, Task 4 mới nối.
3. **Web JSON content negotiation** — đã gộp trong Task 4 (cùng 1 thay đổi controller, tách thêm sẽ vụn).
4. **Blade drag/drop contract + permission gating** — Task 5 (`feat(crm): render drag-drop DOM contract...`) — nay bao gồm cả gate `crm.manage`, không tách riêng vì cùng 1 lần sửa markup.
5. **Frontend, chia theo 6 commit nhỏ (1 scaffold + 5 lát dọc) thay vì 1 commit lớn:**
   - Task 6 (`chore(crm): scaffold crm-pipeline-drag.js entry...`)
   - Task 7 (`feat(crm): slice 1 — click Chuyển giai đoạn opens shared dialog...`)
   - Task 8 (`feat(crm): slice 2 — shared requestStageTransition(), choice options...`)
   - Task 9 (`feat(crm): slice 3 — real network submit, pending/double-submit guard...`)
   - Task 10 (`feat(crm): slice 4 — full DOM commit...`)
   - Task 11 (`feat(crm): slice 5 — HTML5 drag events reuse requestStageTransition()...`)
6. **Browser tests** nằm rải trong mỗi commit slice (Task 6-11) thay vì 1 commit riêng cuối cùng — mỗi slice tự mang theo test của chính nó, đúng tinh thần test-first. Task 12 không tạo commit riêng trừ khi phát sinh fix (nếu có, commit theo đúng Task gây lỗi, không gộp vào 1 commit "fix everything").

Không commit nào trộn backend refactor + Blade + JS + Dusk trong cùng 1 lần — mỗi task ở trên đã tách theo đúng layer, và JS không còn gộp thành 1 commit khổng lồ như bản trước.

## Kiểm soát phạm vi — không làm gì ngoài danh sách sau

- Không reorder trong cùng cột, không `board_position`.
- Không đổi business rule pipeline ngoài spec (không thêm transition graph, không đổi rule `lost_reason`/terminal đã có).
- Không redesign CRM board ngoài phần cần thiết cho kéo-thả.
- Không biến `showToast()` thành toast framework dùng chung — hàm này chỉ dùng nội bộ trong `crm-pipeline-drag.js`, không export ra module khác, không đăng ký global.
- Không chuẩn hóa response cho 11 controller khác đang dùng `ZenaContractResponseTrait` — chỉ 2 controller trong phạm vi (`ApiOpportunityController`, `CrmPageController`) bị đụng tới, và `CrmPageController` không thêm trait đó.
- Không refactor service nào khác ngoài việc tạo mới `OpportunityStageTransitionService`.
- Không đổi permission (`crm.manage`/`crm.view`) hay route path/tên route nào — chỉ đổi tham số method `updateStage()` (bỏ `ApiOpportunityController $apiController`) và gate hiển thị 2 điều khiển theo `crm.manage` (Task 5) — route definition ở `routes/web.php:1016` không cần sửa.
- Không thêm Jest/Vitest hay bất kỳ bộ test runner JS nào — mọi hành vi JS được xác nhận qua Dusk (kể cả pending-state và `data-dragover`, dùng `$browser->script()` để stub `fetch`/dispatch synthetic DOM event thay vì cần một test runner riêng).
- Không tạo `dragDropTransition()`/`clickTransition()` hay bất kỳ hàm quyết định "có cần dialog không" riêng biệt cho từng lối vào — `requestStageTransition()` (Task 8) là điểm quyết định DUY NHẤT, Task 11 chỉ gọi lại.
