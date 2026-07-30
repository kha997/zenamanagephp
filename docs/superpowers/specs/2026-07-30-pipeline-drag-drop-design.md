# Kéo-thả pipeline CRM giữa các cột

Date: 2026-07-30 (rev 3 — khóa các điểm còn mở từ review lần 2: Service thay Action, board group key ổn định, fallback UI chốt cứng, DOM commit đầy đủ, JSON shape tường minh không qua trait mới, error normalization tập trung, pending-state test cụ thể)

## Bối cảnh

Trang `crm.index` (`resources/views/crm/index.blade.php`) hiển thị board Kanban 6 cột, nhưng các thẻ cơ hội không kéo-thả được — chỉ đổi giai đoạn qua dropdown ở trang chi tiết cơ hội (`opportunity-show.blade.php`). Đây là spec cho tính năng kéo-thả thật giữa các cột.

Rev 1 (chưa commit) là bản phác thảo sơ bộ. Rev 2 (commit `0567213a`) khóa 7 quyết định kiến trúc lớn nhưng để lại vài điểm mở. Rev 3 này khóa toàn bộ các điểm còn mở đó, dựa trên khảo sát bổ sung bên dưới.

## Khảo sát thực tế

### `BOARD_GROUPS` hiện tại — `app/Http/Controllers/Web/CrmPageController.php:41-47`

```php
private const BOARD_GROUPS = [
    'Mới' => [Opportunity::STAGE_NEW_LEAD, Opportunity::STAGE_QUALIFIED, Opportunity::STAGE_CONTACTED],
    'Tư vấn / Khảo sát' => [Opportunity::STAGE_BRIEF_DISCOVERY, Opportunity::STAGE_SURVEY_OR_INPUTS_RECEIVED, Opportunity::STAGE_SCOPE_DEFINED],
    'Báo giá' => [Opportunity::STAGE_PROPOSAL_DRAFT, Opportunity::STAGE_PROPOSAL_SENT],
    'Đàm phán / Hợp đồng' => [Opportunity::STAGE_NEGOTIATION, Opportunity::STAGE_CONTRACTING],
    'Thắng' => [Opportunity::STAGE_WON],
    'Thua / Nurture' => [Opportunity::STAGE_LOST, Opportunity::STAGE_NO_BID, Opportunity::STAGE_NURTURE],
];
```

`array<string, list<string>>` phẳng, dùng label hiển thị làm key — cần đổi sang key ổn định tách biệt khỏi label (Quyết định 2).

Xác nhận (grep `BOARD_GROUPS` toàn repo): constant này chỉ được đọc ở đúng 1 chỗ — `CrmPageController::index()` — nên đổi cấu trúc không lan ra ngoài file.

### Stage constants — `app/Models/Opportunity.php:22-69`

```php
STAGE_NEW_LEAD='new_lead', STAGE_QUALIFIED='qualified', STAGE_CONTACTED='contacted',
STAGE_BRIEF_DISCOVERY='brief_discovery', STAGE_SURVEY_OR_INPUTS_RECEIVED='survey_or_inputs_received', STAGE_SCOPE_DEFINED='scope_defined',
STAGE_PROPOSAL_DRAFT='proposal_draft', STAGE_PROPOSAL_SENT='proposal_sent',
STAGE_NEGOTIATION='negotiation', STAGE_CONTRACTING='contracting',
STAGE_WON='won', STAGE_LOST='lost', STAGE_NURTURE='nurture', STAGE_NO_BID='no_bid';

const TERMINAL_STAGES = [STAGE_WON, STAGE_LOST, STAGE_NO_BID]; // đã có sẵn
const VALID_STAGES = [... đủ 14 stage ...];

public function isTerminal(): bool {
    return in_array((string) $this->pipeline_stage, self::TERMINAL_STAGES, true);
}
```

`nurture` **không** terminal — thẻ ở `nurture` vẫn kéo-thả tiếp được. Mỗi stage trong 14 stage này chỉ xuất hiện đúng 1 lần trong toàn bộ `BOARD_GROUPS` (đối chiếu thủ công 6 nhóm × stages — không có stage nào bị liệt kê ở 2 nhóm), đây là tiền đề cho invariant test #2 ở Quyết định 2.

### Validation `lost`/`no_bid`/`nurture` hiện hành — `app/Http/Controllers/Api/OpportunityController.php:287-346`

```php
$validator = Validator::make($request->all(), [
    'pipeline_stage' => ['required', Rule::in(Opportunity::VALID_STAGES)],
    'lost_reason' => ['required_if:pipeline_stage,' . Opportunity::STAGE_LOST, 'nullable', 'string', 'max:500'],
]);
if ($opportunity->isTerminal()) {
    return $this->validationError(['pipeline_stage' => ['Won/lost/no-bid opportunities can no longer change stage.']]);
}
$opportunity->lost_reason = $to === Opportunity::STAGE_LOST ? (string) $request->input('lost_reason') : null;
if ($to === Opportunity::STAGE_WON) { $opportunity->forecast_category = 'closed_won'; }
elseif (in_array($to, [Opportunity::STAGE_LOST, Opportunity::STAGE_NO_BID], true)) { $opportunity->forecast_category = 'closed_lost'; }
$opportunity->save();
$this->recordEvent($opportunity, 'crm.opportunity.stage_changed', ['from' => $from, 'to' => $to]);
```

Chỉ `lost` bắt buộc `lost_reason`. Không có "transition graph" — mọi stage trong `VALID_STAGES` nhảy được tới miễn opportunity chưa terminal. Spec này giữ nguyên hành vi đó.

### Permission & policy

- Route web: `POST /crm/opportunities/{id}/stage`, tên `operator.crm.opportunities.stage`, middleware `rbac:crm.manage` (`routes/web.php:1016`).
- Route API: `POST /api/zena/crm/opportunities/{id}/stage`, tên `api.zena.crm.opportunities.stage`, cùng middleware (`routes/api_zena.php:377`), nhóm ngoài thêm `auth:sanctum, tenant.isolation, input.sanitization, error.envelope`.
- Policy: `app/Policies/OpportunityPolicy.php::update()` — `belongsToUserTenant() && hasPermission('crm.manage')`.
- `CrmPageController::showOpportunity` (dòng 342-351) đã có pattern fetch-scoped-rồi-authorize dùng được cho `updateStage`.

### `work-template-apply.js` — pattern JS chuẩn của dự án

- *"Vanilla JS — layout operator không có Alpine."*
- `csrfToken()` đọc `<meta name="csrf-token">` (có sẵn ở `layouts/operator.blade.php:6`).
- `postJson(url, body)`: `fetch` với `Content-Type/X-CSRF-TOKEN/X-Requested-With/Accept: application/json`; trả `{ok, body}`.
- Lỗi hiển thị inline, dự án chưa có toast client-side.
- `vite.config.js` liệt kê thủ công từng JS module — file mới phải thêm cả vào `vite.config.js` và `@vite(...)` trong `layouts/operator.blade.php:10`.

### `ZenaContractResponseTrait` — khảo sát bổ sung theo yêu cầu (Quyết định 5)

```
grep -rln "ZenaContractResponseTrait" app/Http/Controllers/
→ 16 file, TOÀN BỘ nằm trong app/Http/Controllers/Api/*
→ 0 file trong app/Http/Controllers/Web/*
```

- Trait tự thân **không** phụ thuộc `isZenaRequest()` (đó là method riêng, private, nằm trong `app/Exceptions/Handler.php`, không liên quan). Trait không có side effect ẩn — chỉ build mảng response.
- Nhưng **quy ước sử dụng thực tế trong repo là 100% Api-only** (16/16 controller dùng nó đều ở namespace `Api`). Không có tiền lệ nào một Web controller dùng trait này.
- Thêm trait vào `CrmPageController` không phá gì về mặt kỹ thuật, nhưng phá vỡ một quy ước ngầm nhất quán tuyệt đối trong toàn repo, chỉ để phục vụ đúng 1 response của đúng 1 action.

**Kết luận:** không thêm trait. `CrmPageController::updateStage` dùng `response()->json([...], $status)` tường minh cho nhánh JSON — xem Quyết định 5.

### Test infrastructure JS — khảo sát bổ sung theo yêu cầu (Quyết định 7)

```
package.json → không có jest/vitest/@testing-library/mocha/karma trong devDependencies
→ không có bộ chạy JS unit test nào trong repo hiện tại
```

Ngược lại, Dusk **đã có sẵn** khả năng chạy JS tuỳ ý trong trang qua `$browser->script(...)` — xác nhận đang dùng thật ở `tests/Browser/Projects/ProjectCreateTest.php:152` và `tests/Browser/Projects/WorkTemplateApplyBrowserTest.php:122-136` (đọc state DOM qua JS injection). Đây là API chuẩn của Laravel Dusk (wrap Selenium `executeScript`), không phải pattern tự chế của dự án — dùng để stub `window.fetch` trước khi thao tác là khả thi và nhất quán với cách Dusk đang được dùng ở đây.

**Kết luận:** không thêm bộ test runner JS mới (ngoài phạm vi tính năng). Dùng `$browser->script(...)` để stub `window.fetch` — xem Quyết định 7.

### HTML card hiện tại — `resources/views/crm/index.blade.php:17-47`

`<li>` bọc trực tiếp một `<a>` link tới trang chi tiết — nếu đặt `draggable` lên toàn `<li>` sẽ xung đột với click-để-mở-link. Cần drag handle riêng (giữ nguyên từ rev 2, không đổi).

### Công thức tổng tiền cột — `CrmPageController::index()`, gần dòng 74

```php
'total_fee' => (float) $items->sum('estimated_fee'),
```

Tổng đơn giản, không trọng số. An toàn để JS tính lại từ `data-amount` thô sau khi move DOM — giữ nguyên từ rev 2.

## Mục tiêu

- Kéo-thả thẻ cơ hội giữa các cột pipeline để đổi `pipeline_stage`, dùng route/permission/validation sẵn có.
- Không cài thêm thư viện (không Alpine, không Sortable.js, không bộ test runner JS mới).
- Không phá vỡ hành vi form "Chuyển giai đoạn" cũ ở trang chi tiết cơ hội.
- Không tự ý mở rộng pattern kiến trúc (Action) ra ngoài phạm vi tính năng này — mọi đề xuất pattern mới phải tách riêng, không âm thầm áp dụng.

## Ngoài phạm vi (MVP)

- **Không hỗ trợ sắp xếp thứ tự thẻ trong cùng một cột.** Thả vào đúng cột nguồn là no-op tuyệt đối: không gọi API, không đổi `pipeline_stage`, không đổi DOM.
- Không đổi API JSON shape hiện có của `ApiOpportunityController::updateStage` (giữ nguyên cho consumer khác).
- Không đổi hành vi các form web khác dùng chung `DelegatesToApiControllers`.
- Không thêm bộ lọc/tìm kiếm/board reorder cột.
- Không đưa pattern `App\Actions\*` vào repo (xem Quyết định 1 — đề xuất riêng, không áp dụng ở đây).

## Quyết định kiến trúc

### 1. Service dùng chung, không tạo pattern Action mới

Rev 2 chọn `UpdateOpportunityStageAction` (`App\Actions\Crm\`). Sau khi được yêu cầu xác nhận lại: repo hiện có **0** namespace `App\Actions\*` và convention nhất quán 100% là `App\Services\*Service.php` (60+ class). Đưa pattern Action vào chỉ để phục vụ 1 tính năng kéo-thả là mở rộng kiến trúc vượt phạm vi.

**Quyết định:** dùng `App\Services\Crm\OpportunityStageTransitionService`, đặt trong `app/Services/Crm/` (thư mục con mới trong `Services` — chưa có `Services/Crm/` nhưng đây vẫn là cùng 1 namespace gốc `App\Services`, không phải namespace mới, khác hẳn việc tạo hẳn `App\Actions`).

**Chữ ký (nội dung logic giữ nguyên như rev 2, chỉ đổi tên class/namespace):**

```php
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
     * @throws ValidationException nếu terminal, hoặc lost thiếu lost_reason, hoặc $toStage không hợp lệ
     */
    public function transition(User $actor, Opportunity $opportunity, string $toStage, ?string $lostReason): Opportunity
    {
        Gate::forUser($actor)->authorize('update', $opportunity);

        if (!in_array($toStage, Opportunity::VALID_STAGES, true)) {
            throw ValidationException::withMessages(['pipeline_stage' => ['Giai đoạn không hợp lệ.']]);
        }
        if ($opportunity->isTerminal()) {
            throw ValidationException::withMessages(['pipeline_stage' => ['Won/lost/no-bid opportunities can no longer change stage.']]);
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

Nội dung bê nguyên logic hiện có trong `ApiOpportunityController::updateStage` (dòng 306-345). `Gate::forUser($actor)->authorize(...)` dùng facade (không phải trait `AuthorizesRequests` của controller) — gọi được từ bất kỳ class nào, giữ "authorization động" bên trong Service mà không cần Service extends/implements gì đặc biệt.

**`ApiOpportunityController::updateStage`**: giữ nguyên fetch-scoped + validate request-shape, gọi `app(OpportunityStageTransitionService::class)->transition(...)`, bắt `ValidationException`/`AuthorizationException` để giữ nguyên shape response JSON hiện tại — API contract không đổi.

**`CrmPageController::updateStage`**: tự fetch `Opportunity::query()->forTenant($tenantId)->findOrFail($id)` (theo pattern đã có ở `showOpportunity()`), validate request-shape, gọi cùng Service, rồi trả JSON (Quyết định 5) hoặc redirect như cũ tuỳ `$request->wantsJson()`. Không còn gọi chéo sang `ApiOpportunityController` — loại bỏ tham số `ApiOpportunityController $apiController` khỏi `updateStage()`; các action khác của `CrmPageController` không đổi.

**Đề xuất kiến trúc riêng (KHÔNG áp dụng trong tính năng này):** nếu trong tương lai dự án tích lũy nhiều "một-hành-động-một-kết-quả" tương tự (không phải nhóm nghiệp vụ đa method như `*Service` hiện tại), có thể cân nhắc đưa pattern `App\Actions\*` vào như một quyết định kiến trúc riêng, áp dụng đồng loạt và có review riêng — không quyết định ngầm qua một tính năng đơn lẻ như kéo-thả pipeline. Ghi nhận ở đây để không mất ý tưởng, không phải một TODO của spec này.

### 2. Board group key ổn định, tách khỏi label hiển thị

```php
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

`index()` đổi từ `foreach (self::BOARD_GROUPS as $label => $stages)` (rev cũ) hoặc `foreach (self::BOARD_GROUPS as $group)` (rev 2, chưa có key) sang `foreach (self::BOARD_GROUPS as $groupKey => $group)`. Board array trả về view giữ `$groupKey` làm key ngoài (thay vì label) để Blade truyền thẳng xuống `data-board-group="{{ $groupKey }}"`.

Blade (`resources/views/crm/index.blade.php`):

```html
@foreach ($board as $groupKey => $column)
    <x-ui.card
        data-board-group="{{ $groupKey }}"
        data-column-label="{{ $column['label'] }}"
        data-requires-choice="{{ !empty($column['requires_choice']) ? '1' : '0' }}"
        data-default-entry-stage="{{ $column['default_entry_stage'] ?? '' }}"
        @if (!empty($column['choice_options']))
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
        <ul>...</ul>
    </x-ui.card>
@endforeach
```

`data-requires-choice`/`data-default-entry-stage` trên chính phần tử cột (không phải trên dialog) là nguồn dữ liệu mà `requestStageTransition()` ở Quyết định 3 đọc qua `targetGroupEl.dataset.requiresChoice`/`targetGroupEl.dataset.defaultEntryStage` — `targetGroupEl` chính là phần tử `<x-ui.card data-board-group="...">` này, lấy qua `document.querySelector('[data-board-group="' + targetGroupKey + '"]')`.

JS **không được** so sánh `"Mới"`/`"Thua / Nurture"` hay bất kỳ chuỗi tiếng Việt nào để xác định logic nguồn/đích — chỉ dùng `dataset.boardGroup` (giá trị `new`/`consulting_survey`/.../`lost_nurture`). Label tiếng Việt chỉ dùng để hiển thị, đọc riêng qua `data-column-label` nếu JS cần hiện tên cột trong dialog/toast.

**Invariant tests bắt buộc** (unit, không cần DB):

1. **Group key duy nhất** — `array_keys(BOARD_GROUPS)` không có phần tử trùng (PHP array key tự nhiên đã đảm bảo điều này ở cấp ngôn ngữ, nhưng test vẫn viết tường minh để giữ invariant nếu ai đó đổi cấu trúc sau này bằng list thay vì assoc array).
2. **Một stage không thuộc nhiều group** — với mọi cặp group khác nhau, `array_intersect($groupA['stages'], $groupB['stages'])` phải rỗng. Union toàn bộ `stages` của 6 group phải bằng đúng tập `Opportunity::VALID_STAGES` (không thiếu, không thừa, không lặp) — test này cũng bắt lỗi nếu sau này thêm stage mới vào model mà quên thêm vào board.
3. **`default_entry_stage` thuộc `stages`** — với mọi group có `default_entry_stage !== null`, giá trị đó phải nằm trong `stages` của chính group đó.
4. **Group `requires_choice=true` không có target mặc định mơ hồ** — mọi group có `requires_choice === true` bắt buộc `default_entry_stage === null` (không được vừa có `default_entry_stage` vừa có `requires_choice`, tránh nhập nhằng JS không biết theo cái nào).
5. **`choice_option.stage` thuộc `stages` của group** — với group có `choice_options`, mọi phần tử `choice_options[].stage` phải nằm trong `stages` của chính group đó.

### 3. UI fallback "Chuyển giai đoạn" — chốt cứng, không còn là câu hỏi mở

**Markup:**

```html
<li class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2"
    data-opportunity-id="{{ $opportunity->id }}"
    data-current-stage="{{ $opportunity->pipeline_stage }}"
    data-terminal="{{ $opportunity->isTerminal() ? '1' : '0' }}"
    data-amount="{{ (int) ($opportunity->estimated_fee ?? 0) }}">
    <div class="flex items-start gap-2">
        @if (!$opportunity->isTerminal())
            <button type="button" class="crm-drag-handle" draggable="true" aria-label="Kéo để chuyển giai đoạn">⋮⋮</button>
        @endif
        <div class="flex-1">
            <a href="{{ route('operator.crm.opportunities.show', $opportunity->id) }}" class="operator-link font-medium">
                {{ $opportunity->opportunity_name }}
            </a>
            <div class="text-xs text-slate-500">...</div>
            @if (!$opportunity->isTerminal())
                <button type="button" class="crm-stage-transition-btn text-xs operator-link">Chuyển giai đoạn</button>
            @endif
        </div>
    </div>
</li>
```

Card terminal: **không render** handle lẫn nút (loại hẳn khỏi DOM, không phải chỉ disable — backend chặn tuyệt đối nên không có lý do hiện điều khiển chết).

**Dialog dùng chung cho mọi lối vào (kéo-thả VÀ click):**

```html
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

        <div data-dialog-choice-picker class="hidden">
            <!-- render động bởi JS khi group đích có requires_choice="1" — JS đọc
                 data-choice-options/data-default-entry-stage trực tiếp từ phần tử cột
                 tương ứng (document.querySelector('[data-board-group="'+groupKey+'"]')),
                 KHÔNG đọc từ nút .crm-dialog-group-option (nút đó chỉ mang data-group,
                 không lặp lại choice_options — một nguồn dữ liệu duy nhất, xem Quyết định 2) -->
        </div>

        <textarea data-dialog-reason placeholder="Lý do (bắt buộc nếu chọn Thua)" class="hidden"></textarea>
        <button type="button" data-dialog-cancel>Hủy</button>
        <button type="button" data-dialog-confirm disabled>Xác nhận</button>
    </form>
</dialog>
```

Nút `.crm-dialog-group-option` chỉ mang `data-group` — mọi dữ liệu khác (`requires_choice`, `choice_options`, `default_entry_stage`) JS tra cứu lại từ phần tử cột tương ứng (`document.querySelector('[data-board-group="'+groupKey+'"]')`, xem Quyết định 2), tránh lặp cùng 1 dữ liệu ở 2 nơi trong DOM.

**Orchestration function dùng chung tuyệt đối cho cả kéo-thả và click** — đây là điểm chốt quan trọng nhất của Quyết định 3, không có 2 luồng logic riêng:

```js
// card: <li data-opportunity-id> đang được thao tác
// targetGroupKey: 'new' | 'consulting_survey' | ... | 'lost_nurture'
// preselectedStage, reason: optional — chỉ có giá trị khi gọi lại sau khi dialog xác nhận
function requestStageTransition(card, targetGroupKey, preselectedStage, reason) {
    var targetGroupEl = document.querySelector('[data-board-group="' + targetGroupKey + '"]');
    var requiresChoice = targetGroupEl.dataset.requiresChoice === '1';

    if (requiresChoice && preselectedStage === undefined) {
        openStageDialog(card, targetGroupKey); // dialog tự gọi lại requestStageTransition() với preselectedStage khi user xác nhận
        return;
    }

    var toStage = preselectedStage || targetGroupEl.dataset.defaultEntryStage;
    submitStageChange(card, targetGroupKey, toStage, reason || null); // luồng pessimistic update, Quyết định 4
}
```

**Luồng kéo-thả** gọi `requestStageTransition(card, targetGroupKey)` ngay khi `drop` — nếu cột đích thường (`requires_choice` false) thì gửi thẳng; nếu cột đích là "Thua / Nurture" thì hàm tự mở dialog, **với `targetGroupKey` đã preselect sẵn** (dialog mở thẳng vào bước chọn `choice_options`, bỏ qua bước "chọn cột đích" vì đã biết cột đích từ hành động kéo-thả).

**Luồng click "Chuyển giai đoạn"** mở dialog ở bước "chọn cột đích" trước (`data-dialog-group-picker`, liệt kê 6 nút trừ cột hiện tại). Group hiện tại của card không cần tra cứu `stages` — card nằm lồng ngay trong DOM của cột (`<x-ui.card data-board-group="...">` bọc `<ul>` chứa `<li>`), nên chỉ cần `card.closest('[data-board-group]').dataset.boardGroup` để biết group hiện tại và ẩn đúng 1 nút group đó khỏi danh sách. Sau khi user bấm 1 group, gọi `requestStageTransition(card, chosenGroupKey)` — tái sử dụng đúng logic ở trên, tự động mở tiếp bước chọn `choice_options` nếu cần.

Không có `dragDropTransition()` và `clickTransition()` riêng biệt — chỉ có **1** `requestStageTransition()` và **1** `submitStageChange()`.

Chọn `lost` trong `choice_options` → `textarea[data-dialog-reason]` hiện ra và bắt buộc không rỗng mới enable nút Xác nhận (giữ nguyên rev 2).

### 4. DOM commit đầy đủ sau JSON success

Sau khi `submitStageChange()` nhận response thành công, JS phải cập nhật **toàn bộ** danh sách sau trên `<li>` (không chỉ vị trí):

```js
function commitStageChange(card, targetGroupKey, responseData) {
    var sourceGroupEl = card.closest('[data-board-group]');
    var targetGroupEl = document.querySelector('[data-board-group="' + targetGroupKey + '"]');

    // 1. vị trí card
    targetGroupEl.querySelector('ul').appendChild(card);

    // 2. data-current-stage
    card.dataset.currentStage = responseData.pipeline_stage;

    // 3. stage badge hiển thị (nếu card có phần tử badge riêng — xem ghi chú dưới)
    var badgeEl = card.querySelector('[data-stage-badge]');
    if (badgeEl) badgeEl.textContent = stageLabelFor(responseData.pipeline_stage);

    // 4 + 5 + 6. data-terminal + drag handle + nút "Chuyển giai đoạn"
    card.dataset.terminal = responseData.is_terminal ? '1' : '0';
    if (responseData.is_terminal) {
        var handle = card.querySelector('.crm-drag-handle');
        if (handle) handle.remove();
        var transitionBtn = card.querySelector('.crm-stage-transition-btn');
        if (transitionBtn) transitionBtn.remove();
    }
    // responseData.pipeline_stage === 'nurture' → is_terminal luôn false (đã xác nhận ở khảo sát:
    // TERMINAL_STAGES chỉ gồm won/lost/no_bid) → nhánh trên tự động KHÔNG xóa handle/nút cho nurture,
    // không cần nhánh else riêng.

    // 7 + 8. count và tổng estimated_fee của cột nguồn và cột đích
    recomputeColumn(sourceGroupEl);
    recomputeColumn(targetGroupEl);

    releasePendingState(card);
}
```

**Ghi chú về "stage badge hiển thị":** markup rev 2/3 hiện tại của card (Quyết định 3) chưa có phần tử badge riêng biệt hiển thị tên stage cụ thể trên mỗi card (chỉ tên cơ hội + khách hàng + sales). Nếu implementation thêm 1 dòng "Giai đoạn: {label}" vào card (không bắt buộc theo spec này, nhưng nếu có sẽ cần cập nhật đồng bộ), phần tử đó phải mang `data-stage-badge` để bước 3 ở trên áp dụng được — nếu không thêm badge, bỏ qua bước 3 khi implement (đã liệt kê tường minh ở đây để không bị bỏ sót ngầm, không phải bắt buộc thêm UI mới ngoài phạm vi).

`recomputeColumn()` giữ nguyên logic rev 2 (tính lại từ `data-amount` thô của các `<li>` thực tế còn trong cột, dùng `Intl.NumberFormat('vi-VN', {style:'currency', currency:'VND', maximumFractionDigits:0})`).

Nếu thất bại: hoàn toàn không chạm DOM (giữ nguyên rev 2, xem Quyết định pessimistic update ở Testing/Content negotiation).

### 5. JSON success shape — tường minh, không thêm trait mới vào Web controller

Theo khảo sát ở trên: không thêm `ZenaContractResponseTrait` vào `CrmPageController` (phá quy ước Api-only). `CrmPageController::updateStage` dùng `response()->json([...], 200)` trực tiếp cho nhánh JSON, với shape **khóa cứng**:

```json
{
  "message": "Đã cập nhật giai đoạn.",
  "data": {
    "id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
    "pipeline_stage": "lost",
    "is_terminal": true
  }
}
```

```php
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
```

Đây là shape **riêng của route web cho tính năng này**, khác với `zenaSuccessResponse()` (route API, không đổi — Ngoài phạm vi). JS module mới chỉ cần đọc `body.data.pipeline_stage`/`body.data.is_terminal`, không đọc `body.success`/`body.status` (những field đó không tồn tại trong shape này — khác với giả định sai ở rev 2).

### 6. Chuẩn hoá lỗi JS tập trung — 1 hàm duy nhất, không tự xử lý rải rác

```js
// response: đối tượng Response từ fetch (chưa parse), hoặc null nếu fetch reject (mất mạng)
function parseErrorResponse(response) {
    if (!response) {
        return { userMessage: 'Có lỗi xảy ra, vui lòng thử lại.' };
    }

    switch (response.status) {
        case 401:
            return { userMessage: 'Phiên đăng nhập không còn hợp lệ, vui lòng đăng nhập lại.' };
        case 403:
            return { userMessage: 'Bạn không có quyền thực hiện thao tác này.' };
        case 419:
            return { userMessage: 'Phiên làm việc đã hết hạn, vui lòng tải lại trang.', reload: true };
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
            // body không phải JSON hợp lệ — fallback thuần theo status code
            return { userMessage: response.status >= 500
                ? 'Có lỗi xảy ra, vui lòng thử lại.'
                : 'Có lỗi xảy ra, vui lòng thử lại (mã lỗi ' + response.status + ').' };
        });
}
```

Vì 401/403/419 được xử lý bằng message cố định (không đọc `body`), `parseErrorResponse` với 3 status này **không** cần parse JSON (tránh phụ thuộc shape) — trả ngay object đồng bộ. Với 422/500+/khác, hàm trả Promise (do phải đọc `body`), và **luôn có `.catch()`** để không vỡ nếu response không phải JSON hợp lệ (ví dụ lỗi hạ tầng trả HTML). Đây chính là điểm khóa cứng theo yêu cầu: **1 hàm duy nhất**, mọi handler khác (`submitStageChange`, dialog, v.v.) gọi qua hàm này, không tự viết `if (status === ...)` rải rác ở nơi khác.

`reload: true` (chỉ có ở 419) là tín hiệu để lớp gọi hiển thị thêm nút "Tải lại trang" trong toast thay vì chỉ đóng sau vài giây.

Bảng đối chiếu (không đổi so với rev 2, nay được thực thi bởi đúng 1 hàm thay vì mô tả rời rạc):

| Status | Nguồn gốc | `userMessage` |
|---|---|---|
| 401 | Chưa đăng nhập | "Phiên đăng nhập không còn hợp lệ, vui lòng đăng nhập lại." |
| 403 | Thiếu quyền hoặc sai tenant | "Bạn không có quyền thực hiện thao tác này." |
| 419 | CSRF hết hạn | "Phiên làm việc đã hết hạn, vui lòng tải lại trang." (+ `reload: true`) |
| 422 | Terminal / thiếu `lost_reason` / stage không hợp lệ | field lỗi đầu tiên trong `body.errors`, fallback `body.message` |
| 500+ | Lỗi hệ thống | "Có lỗi xảy ra, vui lòng thử lại." |
| Không parse được JSON | Hạ tầng trả HTML/lỗi lạ | Theo status code, không cố đọc `body.message` |
| Network reject | Mất mạng | "Có lỗi xảy ra, vui lòng thử lại." |

## Toast lỗi mới (gap cần lấp — không có sẵn trong dự án)

Giữ nguyên rev 2: `components/ui/toast.blade.php` là banner flash server-side, không dùng được cho lỗi AJAX. Thêm hàm nhỏ dùng lại class Tailwind màu lỗi đã có:

```js
function showErrorToast(message, options) {
    var el = document.createElement('div');
    el.className = 'fixed bottom-4 right-4 z-50 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800 shadow-lg';
    el.textContent = message;
    if (options && options.reload) {
        var btn = document.createElement('button');
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

## Pessimistic DOM update (giữ nguyên rev 2, không đổi)

1. Đánh dấu card pending: `card.setAttribute('aria-busy', 'true')`, disable drag handle (`handle.draggable = false`), disable nút "Chuyển giai đoạn".
2. Gửi `postJson`.
3. Thành công: `commitStageChange(...)` (Quyết định 4).
4. Thất bại: không đổi DOM, gỡ `aria-busy`, khôi phục handle/nút, `showErrorToast(parseErrorResponse(...).userMessage, {...})`.
5. Trong lúc pending: không nhận `dragstart` mới, không cho double-submit dialog (nút Xác nhận/handle đã disable từ bước 1).

## Không sắp xếp trong cùng cột (giữ nguyên rev 2)

```js
if (sourceGroupKey === targetGroupKey) {
    return; // no-op tuyệt đối
}
```

## Aggregate cột dùng số thô (giữ nguyên rev 2)

```html
<li data-opportunity-id="{{ $opportunity->id }}" data-amount="{{ (int) ($opportunity->estimated_fee ?? 0) }}">
```

```js
function recomputeColumn(columnEl) {
    var cards = columnEl.querySelectorAll('[data-opportunity-id]');
    var total = 0;
    cards.forEach(function (card) { total += parseInt(card.dataset.amount, 10) || 0; });
    columnEl.querySelector('[data-column-count]').textContent = cards.length;
    columnEl.querySelector('[data-column-total]').textContent = formatVnd(total);
}
function formatVnd(amount) {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(amount);
}
```

## Content negotiation

Route giữ nguyên `POST /crm/opportunities/{id}/stage`. Request form cũ (không `Accept: application/json`) → redirect như hiện tại, không đổi. Request AJAX → JSON theo shape khóa ở Quyết định 5.

## Asset build

Thêm `resources/js/crm-pipeline-drag.js` vào `vite.config.js` (mảng `input`) và `@vite([...])` trong `layouts/operator.blade.php:10` — cả hai liệt kê thủ công, không auto-discovery.

## Testing

### Feature tests (`tests/Feature/`)

1. `Accept: application/json` → JSON đúng shape khóa ở Quyết định 5 (`message`, `data.id`, `data.pipeline_stage`, `data.is_terminal`), status 200.
2. Request thường (không header `Accept: application/json`) → vẫn redirect với `session('success')`, hành vi cũ không đổi.
3. Thiếu `crm.manage` + `Accept: application/json` → 403 JSON.
4. `pipeline_stage` không thuộc `Opportunity::VALID_STAGES` + `Accept: application/json` → 422 JSON.
5. Chuyển sang `lost` thiếu `lost_reason` + `Accept: application/json` → 422 JSON.
6. Opportunity đã terminal → mọi request đổi stage (JSON lẫn redirect) đều bị chặn, opportunity không đổi.
7. Cùng kịch bản (vd #5) gọi qua **cả hai** route (web JSON path và API) → cùng bị chặn cùng lý do, xác nhận cả hai controller dùng chung `OpportunityStageTransitionService`.
8. Route API giữ nguyên shape response như trước refactor (regression).

### Unit tests cho invariant `BOARD_GROUPS` (5 invariant ở Quyết định 2)

9. Group key duy nhất.
10. Không stage nào thuộc nhiều group; union `stages` của toàn bộ group bằng đúng `Opportunity::VALID_STAGES`.
11. `default_entry_stage` (khi khác null) thuộc `stages` của chính group.
12. Group `requires_choice=true` luôn có `default_entry_stage === null`.
13. Mọi `choice_options[].stage` thuộc `stages` của chính group chứa nó.

### Render/view contract tests (`tests/Feature/`, DOM assertions qua response Blade, không cần Dusk)

14. Card thường có `data-opportunity-id`, `data-current-stage`, `data-amount` (số thô), `data-terminal="0"`.
15. Card terminal: `data-terminal="1"`, không có `.crm-drag-handle`, không có `.crm-stage-transition-btn`.
16. Mỗi cột có `data-board-group` = đúng key ổn định (`new`, `consulting_survey`, `quote`, `negotiation_contract`, `won`, `lost_nurture`) — **không phải** label tiếng Việt.
17. Cột "Thua / Nurture" (`data-board-group="lost_nurture"`) có `data-requires-choice="1"`, không có `data-default-entry-stage` (hoặc rỗng).
18. Dialog `[data-crm-stage-dialog]` chứa đủ 6 nút `.crm-dialog-group-option` với `data-group` khớp 6 key ổn định (không mang `data-choice-options` — dữ liệu đó chỉ có trên phần tử cột, xem Quyết định 2/3). Cột `data-board-group="lost_nurture"` có `data-choice-options` là JSON hợp lệ chứa đủ 3 phần tử `lost`/`no_bid`/`nurture` với đúng `requires_reason`; các cột còn lại không có attribute này.

### Dusk tests (`tests/Browser/`) — qua luồng click "Chuyển giai đoạn", không giả lập HTML5 drag thật

19. Card terminal không có nút/handle chuyển giai đoạn nào hiển thị.
20. Click "Chuyển giai đoạn" → dialog mở, liệt kê đúng 5 group (loại trừ group hiện tại của card).
21. Chọn group "Thua / Nurture" → dialog chuyển sang bước `choice_options`, hiện đủ 3 lựa chọn.
22. Chọn "Thua" không nhập lý do → nút Xác nhận vẫn disabled.
23. Chọn "Thua", nhập lý do, Xác nhận → card chuyển đúng cột, count/tổng tiền 2 cột cập nhật, card không còn handle/nút chuyển giai đoạn (vì `lost` là terminal — đối chiếu Quyết định 4 mục 4-6).
24. Chọn group thường (không `requires_choice`) → không hiện bước chọn `choice_options`, submit thẳng, card chuyển tới đúng `default_entry_stage` của group đó, card **vẫn còn** handle/nút chuyển giai đoạn (không terminal).
25. Mở dialog rồi bấm Hủy → không có request nào được gửi, card giữ nguyên.
26. Giả lập backend trả lỗi (seed opportunity đã terminal, thử đổi stage qua thao tác khác nếu còn thao tác nào lộ ra được — hoặc test qua tầng Feature test thay vì Dusk nếu không dựng được kịch bản UI-only) → toast lỗi hiện, card giữ nguyên cột cũ.

**Pending-state test (chốt cách làm, không còn là câu hỏi mở):**

27. Trong cùng 1 Dusk test: trước khi click "Chuyển giai đoạn" → Xác nhận, gọi `$browser->script("window.__pendingCount = 0; var realFetch = window.fetch; window.fetch = function() { window.__pendingCount++; return new Promise(function(){}); };")` để thay `window.fetch` bằng 1 Promise không bao giờ resolve và đếm số lần gọi. Sau đó thao tác chọn group thường + Xác nhận, rồi:
    - `$browser->assertAttribute('@card-selector', 'aria-busy', 'true')`.
    - Xác nhận nút/handle có `disabled`/`draggable="false"` qua `$browser->script(...)` đọc lại DOM.
    - Bấm lại nút "Chuyển giai đoạn" (hoặc thử kéo lại) lần thứ hai → `$browser->script("return window.__pendingCount;")` phải vẫn bằng 1 (không tăng thêm), xác nhận không double-submit.

    Đây dùng `$browser->script(...)` — API Dusk chuẩn, đã có tiền lệ dùng thật trong `ProjectCreateTest.php`/`WorkTemplateApplyBrowserTest.php` (xem khảo sát). Không cần thêm bộ test runner JS mới, không để lại như câu hỏi mở.

## Rủi ro / câu hỏi còn mở

1. **`BOARD_GROUPS` đổi cấu trúc** vẫn là thay đổi breaking cho property này (xác nhận chỉ 1 nơi dùng qua grep tại thời điểm viết spec) — cần grep lại lúc implement vì code có thể trôi giữa lúc viết spec và lúc code.
2. **CSS cho `<dialog>` gốc** — dự án chưa dùng `<dialog>` ở đâu, chưa khảo sát Tailwind/CSS reset hiện tại có đè style mặc định (backdrop, position) hay không; cần xác nhận ở bước viết plan/implementation, có thể cần vài dòng CSS tối thiểu (không phải thư viện).
3. Markup chi tiết của bước "chọn cột đích" trong dialog (danh sách nút dọc, hay dropdown `<select>`) chưa vẽ pixel-cụ thể — spec đã chốt hành vi và cấu trúc dữ liệu (`data-group`, `data-choice-options`, `data-default-entry-stage`) đủ để implement, phần trình bày trực quan (CSS) để lại cho bước implementation, không ảnh hưởng logic.

Không còn câu hỏi kiến trúc mở nào (Service vs Action, board group key, fallback UI, DOM commit checklist, JSON shape, error normalization, pending-state test) — toàn bộ đã chốt ở rev 3 này.
