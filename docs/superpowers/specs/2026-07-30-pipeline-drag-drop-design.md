# Kéo-thả pipeline CRM giữa các cột

Date: 2026-07-30 (rev 2 — cập nhật theo quyết định kiến trúc chi tiết từ review)

## Bối cảnh

Trang `crm.index` (`resources/views/crm/index.blade.php`) hiển thị board Kanban 6 cột, nhưng các thẻ cơ hội không kéo-thả được — chỉ đổi giai đoạn qua dropdown ở trang chi tiết cơ hội (`opportunity-show.blade.php`). Đây là spec cho tính năng kéo-thả thật giữa các cột.

Rev 1 của spec này (chưa commit) đề xuất kiến trúc sơ bộ. Rev 2 này thay thế hoàn toàn theo 7 quyết định kiến trúc bắt buộc từ review, dựa trên khảo sát thực tế codebase bên dưới.

## Khảo sát thực tế (bắt buộc trước khi chốt spec)

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

Hiện là `array<string, list<string>>` phẳng — không có chỗ khai báo `default_entry_stage` hay per-option metadata. Cần đổi cấu trúc (xem Quyết định 1).

### Stage constants — `app/Models/Opportunity.php:22-69`

```php
STAGE_NEW_LEAD='new_lead', STAGE_QUALIFIED='qualified', STAGE_CONTACTED='contacted',
STAGE_BRIEF_DISCOVERY='brief_discovery', STAGE_SURVEY_OR_INPUTS_RECEIVED='survey_or_inputs_received', STAGE_SCOPE_DEFINED='scope_defined',
STAGE_PROPOSAL_DRAFT='proposal_draft', STAGE_PROPOSAL_SENT='proposal_sent',
STAGE_NEGOTIATION='negotiation', STAGE_CONTRACTING='contracting',
STAGE_WON='won', STAGE_LOST='lost', STAGE_NURTURE='nurture', STAGE_NO_BID='no_bid';

const TERMINAL_STAGES = [STAGE_WON, STAGE_LOST, STAGE_NO_BID]; // đã có sẵn, KHÔNG cần thêm mới
const VALID_STAGES = [... đủ 14 stage ...];

public function isTerminal(): bool {
    return in_array((string) $this->pipeline_stage, self::TERMINAL_STAGES, true);
}
```

`nurture` **không** nằm trong `TERMINAL_STAGES` — thẻ ở `nurture` vẫn kéo-thả tiếp được.

### Validation `lost`/`no_bid`/`nurture` hiện hành — `app/Http/Controllers/Api/OpportunityController.php:287-346`

```php
$validator = Validator::make($request->all(), [
    'pipeline_stage' => ['required', Rule::in(Opportunity::VALID_STAGES)],
    'lost_reason' => ['required_if:pipeline_stage,' . Opportunity::STAGE_LOST, 'nullable', 'string', 'max:500'],
]);
// ...
if ($opportunity->isTerminal()) {
    return $this->validationError(['pipeline_stage' => ['Won/lost/no-bid opportunities can no longer change stage.']]);
}
$opportunity->lost_reason = $to === Opportunity::STAGE_LOST ? (string) $request->input('lost_reason') : null;
if ($to === Opportunity::STAGE_WON) { $opportunity->forecast_category = 'closed_won'; }
elseif (in_array($to, [Opportunity::STAGE_LOST, Opportunity::STAGE_NO_BID], true)) { $opportunity->forecast_category = 'closed_lost'; }
$opportunity->save();
$this->recordEvent($opportunity, 'crm.opportunity.stage_changed', ['from' => $from, 'to' => $to]);
```

Chỉ `lost` bắt buộc `lost_reason`. `no_bid`/`nurture` không có ràng buộc field bổ sung. Không có khái niệm "transition graph" (bất kỳ stage nào trong `VALID_STAGES` đều nhảy được tới, miễn opportunity chưa terminal) — spec này giữ nguyên hành vi đó, "kiểm tra transition" ở Quyết định 2 nghĩa là kiểm tra `isTerminal()` + `lost_reason`, không phải một state machine mới.

### Permission & policy

- Route web: `POST /crm/opportunities/{id}/stage`, tên `operator.crm.opportunities.stage`, middleware `rbac:crm.manage` (`routes/web.php:1016`).
- Route API: `POST /api/zena/crm/opportunities/{id}/stage`, tên `api.zena.crm.opportunities.stage`, cùng middleware `rbac:crm.manage` (`routes/api_zena.php:377`), nhóm ngoài còn có `auth:sanctum, tenant.isolation, input.sanitization, error.envelope`.
- Policy: `app/Policies/OpportunityPolicy.php::update()` — `belongsToUserTenant() && hasPermission('crm.manage')`.
- `CrmPageController::showOpportunity` (dòng 342-351) đã có pattern fetch-scoped-rồi-authorize: `Opportunity::query()->forTenant($tenantId)->findOrFail($id); $this->authorize('view', $opportunity);` — **cùng pattern** sẽ dùng cho `updateStage` sau khi bỏ việc proxy sang `ApiOpportunityController`.

### `work-template-apply.js` — pattern JS chuẩn của dự án (`resources/js/work-template-apply.js`)

- Comment đầu file: *"Vanilla JS — layout operator không có Alpine."* → xác nhận không dùng Alpine.
- `csrfToken()` đọc `<meta name="csrf-token">` (đã có sẵn ở `layouts/operator.blade.php:6`).
- `postJson(url, body)`: `fetch` với `Content-Type: application/json`, `X-CSRF-TOKEN`, `X-Requested-With: XMLHttpRequest`, `Accept: application/json`; trả `{ok: response.ok, body: response.json()}`.
- Lỗi hiển thị **inline trong 1 phần tử cố định** (`errorEl.textContent = message`), **không phải toast nổi** — dự án chưa có toast client-side nào (xem mục Gap bên dưới).
- Asset build: `vite.config.js` liệt kê từng file JS module trong mảng `input` (không có bundler tự động quét thư mục) — file JS mới phải được thêm thủ công vào `vite.config.js` **và** `@vite(...)` trong `layouts/operator.blade.php:10`.

### HTML card hiện tại — `resources/views/crm/index.blade.php:17-47`

```html
<ul class="space-y-2">
    @foreach ($column['items'] as $opportunity)
        <li class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
            <a href="{{ route('operator.crm.opportunities.show', $opportunity->id) }}" class="operator-link font-medium">
                {{ $opportunity->opportunity_name }}
            </a>
            <div class="text-xs text-slate-500">...</div>
        </li>
    @endforeach
</ul>
```

`<li>` bọc trực tiếp một `<a>` link tới trang chi tiết — nếu đặt `draggable` lên toàn `<li>`, thao tác kéo sẽ xung đột với click-để-mở-link (đúng như Quyết định 7 cảnh báo). Cần drag handle riêng.

### Công thức tổng tiền cột — `app/Http/Controllers/Web/CrmPageController.php` (method `index`, gần dòng 74)

```php
$board[$label] = [
    'items' => $items,
    'count' => $items->count(),
    'total_fee' => (float) $items->sum('estimated_fee'),
];
```

Xác nhận: tổng cột = **tổng đơn giản `estimated_fee`** (`decimal:0` cast trên model, không có phần thập phân VND), không có công thức phức tạp (không nhân xác suất, không trừ chi phí). An toàn để JS tính lại từ `data-amount` thô của các thẻ thực tế trong DOM sau khi di chuyển — **không cần** endpoint backend trả aggregate riêng.

### Toast / dialog / modal có sẵn

- `resources/views/components/ui/toast.blade.php` — **tên gây nhầm lẫn**: đây thực chất là banner flash message server-side (`session('success')`/`session('error')`, render 1 lần khi tải trang), **không phải** toast JS động cho lỗi AJAX. Không tái dùng được cho luồng pessimistic-update.
- Không có `<dialog>` nào trong `resources/views` hiện tại — đây sẽ là lần đầu dùng.
- Không có `App\Actions\*` nào trong `app/` — toàn bộ business logic tách biệt hiện nằm ở `app/Services/*Service.php` (60+ class).

### Content negotiation lỗi đã có sẵn (quan trọng — tránh làm lại)

- `RoleBasedAccessControlMiddleware::handle()` (`app/Http/Middleware/RoleBasedAccessControlMiddleware.php:29-40`): nếu chưa đăng nhập và `$request->expectsJson()`, trả `ErrorEnvelopeService::authenticationError(...)` (401) — **đã JSON sẵn**, không cần code thêm. Middleware `rbac:crm.manage` cũng tự trả JSON khi permission thiếu (cùng cơ chế `expectsJson()`).
- `app/Exceptions/Handler.php:147-186`: `AuthenticationException`/`AuthorizationException`/`ModelNotFoundException` chỉ được bọc bằng `ErrorEnvelopeService` khi `isZenaRequest()` đúng — tức route path bắt đầu `api/zena` **hoặc** route name bắt đầu `zena.`. Route web CRM của ta (`operator.crm.opportunities.stage`) **không khớp điều kiện này** → các exception đó rơi về JSON mặc định của Laravel (`{"message": "..."}`, đúng status code) khi `expectsJson()` là true. Vẫn là JSON hợp lệ, chỉ khác shape so với `ErrorEnvelopeService`. Route API (`api.zena.crm.opportunities.stage`) thì có `isZenaRequest() === true` → dùng shape `ErrorEnvelopeService`.
- CSRF mismatch (419): `Handler.php:147-159` chỉ can thiệp thủ công khi `app()->environment('testing')`. Ở môi trường thường, hành vi mặc định của Laravel (`ExceptionHandler::render()` tự kiểm `expectsJson()`) đã trả JSON `{"message": "..."}"` với status 419 khi `TokenMismatchException` xảy ra và request có `Accept: application/json`. Không cần code thêm cho 419 phía server — JS chỉ cần đọc status code.
- 422 (validation lỗi từ `$request->validate()` trong `CrmPageController` hoặc `Validator::make()` trong `ApiOpportunityController`): route API dùng `validationError()` riêng (shape khác), route web dùng Laravel `ValidationException` mặc định (`{"message": "...", "errors": {field: [...]}}`) khi `expectsJson()`.
- **Kết luận:** JS **không được giả định một shape lỗi cố định**. Luôn đọc HTTP status code trước; dùng `body.message` nếu có (cả hai shape đều có field này) làm text hiển thị; với 422 đọc thêm `body.errors[field]` nếu cần hiển thị theo field (MVP không cần, chỉ hiện `message` chung).

### `money-format.js` — không tái dùng được

File này chỉ định dạng **input đang gõ** (`1.234.567,89` khi user nhập số), không có formatter hiển thị dùng `Intl.NumberFormat`. Module mới phải tự viết formatter hiển thị riêng — không phải trùng lặp, vì mục đích khác hẳn.

## Mục tiêu

- Kéo-thả thẻ cơ hội giữa các cột pipeline để đổi `pipeline_stage`, dùng route/permission/validation sẵn có.
- Không cài thêm thư viện (không Alpine, không Sortable.js).
- Không phá vỡ hành vi form "Chuyển giai đoạn" cũ ở trang chi tiết cơ hội.

## Ngoài phạm vi (MVP)

- **Không hỗ trợ sắp xếp thứ tự thẻ trong cùng một cột.** Chưa có cột `board_position` hay endpoint reorder. Thả thẻ vào đúng cột nguồn (không đổi cột) là no-op tuyệt đối — không gọi API, không đổi `pipeline_stage`, không di chuyển DOM, không tạo ảo giác thứ tự mà reload trang sẽ mất.
- Không đổi API JSON shape hiện có của `ApiOpportunityController::updateStage` (giữ nguyên cho các consumer khác đang dùng).
- Không đổi hành vi các form web khác dùng chung `DelegatesToApiControllers`.
- Không thêm bộ lọc/tìm kiếm/board reorder cột.

## Quyết định kiến trúc

### 1. Cấu trúc nhóm board tường minh — bỏ ngầm định "phần tử đầu mảng"

Đổi `BOARD_GROUPS` từ `array<string, list<string>>` phẳng sang khai báo tường minh:

```php
private const BOARD_GROUPS = [
    [
        'label' => 'Mới',
        'stages' => [Opportunity::STAGE_NEW_LEAD, Opportunity::STAGE_QUALIFIED, Opportunity::STAGE_CONTACTED],
        'default_entry_stage' => Opportunity::STAGE_NEW_LEAD,
    ],
    [
        'label' => 'Tư vấn / Khảo sát',
        'stages' => [Opportunity::STAGE_BRIEF_DISCOVERY, Opportunity::STAGE_SURVEY_OR_INPUTS_RECEIVED, Opportunity::STAGE_SCOPE_DEFINED],
        'default_entry_stage' => Opportunity::STAGE_BRIEF_DISCOVERY,
    ],
    [
        'label' => 'Báo giá',
        'stages' => [Opportunity::STAGE_PROPOSAL_DRAFT, Opportunity::STAGE_PROPOSAL_SENT],
        'default_entry_stage' => Opportunity::STAGE_PROPOSAL_DRAFT,
    ],
    [
        'label' => 'Đàm phán / Hợp đồng',
        'stages' => [Opportunity::STAGE_NEGOTIATION, Opportunity::STAGE_CONTRACTING],
        'default_entry_stage' => Opportunity::STAGE_NEGOTIATION,
    ],
    [
        'label' => 'Thắng',
        'stages' => [Opportunity::STAGE_WON],
        'default_entry_stage' => Opportunity::STAGE_WON,
    ],
    [
        'label' => 'Thua / Nurture',
        'stages' => [Opportunity::STAGE_LOST, Opportunity::STAGE_NO_BID, Opportunity::STAGE_NURTURE],
        'default_entry_stage' => null, // không có mặc định — luôn cần dialog chọn (xem Quyết định 5)
        'requires_choice' => true,
        'choice_options' => [
            ['stage' => Opportunity::STAGE_LOST, 'label' => 'Thua', 'requires_reason' => true, 'terminal' => true],
            ['stage' => Opportunity::STAGE_NO_BID, 'label' => 'Không tham gia', 'requires_reason' => false, 'terminal' => true],
            ['stage' => Opportunity::STAGE_NURTURE, 'label' => 'Nuôi dưỡng', 'requires_reason' => false, 'terminal' => false],
        ],
    ],
];
```

`index()` giữ nguyên việc lọc `whereIn('pipeline_stage', $group['stages'])` (đổi từ đọc trực tiếp mảng phẳng sang đọc `$group['stages']`), và truyền cả cấu trúc nhóm (không chỉ nhãn) xuống view.

**Đây là điểm gây thay đổi rộng nhất trong file này**: mọi chỗ hiện đang lặp `foreach (self::BOARD_GROUPS as $label => $stages)` trong `index()` phải đổi thành `foreach (self::BOARD_GROUPS as $group)`. Xác nhận qua grep: `BOARD_GROUPS` chỉ được dùng ở đúng 1 chỗ (`index()`, dòng ~66-75) trong `CrmPageController.php` — không có nơi khác trong repo tham chiếu constant này, nên đổi cấu trúc không lan ra ngoài file.

**Invariant test bắt buộc** (đơn vị, không cần DB): với mọi nhóm có `default_entry_stage !== null`, giá trị đó phải nằm trong `stages` của chính nhóm đó. Nhóm `requires_choice: true` bắt buộc mọi `choice_options[].stage` cũng phải nằm trong `stages` của nhóm.

Thứ tự phần tử trong `stages` chỉ ảnh hưởng thứ tự hiển thị nếu tương lai có UI liệt kê — hiện tại không có UI nào lặp qua `stages` để hiển thị thứ tự, nên không có business rule ngầm nào bị ảnh hưởng bởi thứ tự mảng.

### 2. Domain action dùng chung, controller chỉ transport

**Vấn đề cần nêu rõ (xung đột với convention hiện tại):** codebase hiện có `app/Services/*Service.php` (60+ class) cho tách logic nghiệp vụ, **không có** `app/Actions/*`. Yêu cầu tạo `UpdateOpportunityStageAction` sẽ là class đầu tiên theo pattern Action trong dự án.

- **Phương án A (khuyến nghị):** tạo `app/Actions/Crm/UpdateOpportunityStageAction.php` — single-purpose command object, tách bạch rõ với các `*Service` đa năng hiện có (không method nào khác ngoài `execute()`). Đây là pattern Laravel phổ biến, hợp lý khi cần đúng 1 use-case tái dùng ở 2 transport layer khác nhau (web + API) như yêu cầu. Rủi ro: thêm 1 namespace mới, nhưng không đụng code cũ.
- **Phương án B:** đặt tên `app/Services/OpportunityStageTransitionService.php` để khớp convention đặt tên hiện tại, nhưng nghĩa "Service" trong dự án này thường gắn với nhiều method liên quan (vd `BusinessKpiService`, `AuditService`), không khớp ngữ nghĩa "một hành động, một kết quả" mà yêu cầu mô tả.

→ **Chọn phương án A**, giữ đúng tên `UpdateOpportunityStageAction` như yêu cầu, đặt ở `app/Actions/Crm/`.

**Chữ ký:**

```php
namespace App\Actions\Crm;

final class UpdateOpportunityStageAction
{
    /**
     * @throws \Illuminate\Auth\Access\AuthorizationException nếu $actor không có quyền update $opportunity
     * @throws \Illuminate\Validation\ValidationException nếu terminal, hoặc lost thiếu lost_reason, hoặc $toStage không hợp lệ
     */
    public function execute(User $actor, Opportunity $opportunity, string $toStage, ?string $lostReason): Opportunity
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

Nội dung bê nguyên logic hiện có trong `ApiOpportunityController::updateStage` (dòng 306-345), chỉ đổi cách nhận `$actor`/`$lostReason` làm tham số tường minh thay vì đọc trực tiếp `$request`/`Auth::` — để Action không phụ thuộc HTTP request, test được độc lập.

`Gate::forUser($actor)->authorize(...)` dùng **facade**, không phải trait `AuthorizesRequests` của controller — nên gọi được từ bất kỳ class nào, không chỉ controller. Đây là cách hợp lệ để giữ "authorization động" bên trong Action như yêu cầu, không mâu thuẫn với Policy `OpportunityPolicy` sẵn có (Action dùng lại đúng policy đó, không viết luật mới).

**`ApiOpportunityController::updateStage`** (dòng 287-346) đổi thành: giữ nguyên fetch-scoped + validate request-shape (`pipeline_stage`, `lost_reason` là chuỗi hợp lệ), gọi `app(UpdateOpportunityStageAction::class)->execute(...)`, bắt `ValidationException`/`AuthorizationException` để giữ nguyên shape response JSON hiện tại (`validationError()`, `unauthorized()`) — **API contract không đổi**, chỉ đổi nơi chứa logic.

**`CrmPageController::updateStage`** (dòng 444-459) đổi thành: tự fetch `Opportunity::query()->forTenant($tenantId)->findOrFail($id)` (theo đúng pattern đã có ở `showOpportunity()`), validate request-shape, gọi cùng Action, rồi:
- Nếu `$request->wantsJson()`: trả JSON (xem Quyết định 6 cho shape) hoặc bắt exception → JSON lỗi đúng status.
- Ngược lại: giữ nguyên hành vi `redirect()->with('success'/'error')` như hiện tại — **không đổi** luồng form cũ.

Không còn `CrmPageController → ApiOpportunityController` gọi chéo nữa (loại bỏ tham số `ApiOpportunityController $apiController` khỏi `updateStage()` — các action khác của controller này vẫn dùng `DelegatesToApiControllers`/gọi API controller như cũ, **không refactor lan ra ngoài `updateStage`**).

### 3. Không sắp xếp trong cùng cột (đã nêu ở "Ngoài phạm vi")

```js
if (sourceColumnId === targetColumnId) {
    return; // no-op tuyệt đối: không gọi API, không đổi DOM
}
```

### 4. Pessimistic DOM update

Luồng bắt buộc cho mọi lần đổi stage (dù trigger bằng kéo-thả hay bằng nút "Chuyển giai đoạn" — xem Quyết định 8):

1. Đánh dấu card pending: `card.setAttribute('aria-busy', 'true')`, disable drag handle (`handle.draggable = false`, `handle.setAttribute('aria-disabled', 'true')`), disable nút "Chuyển giai đoạn" tương ứng.
2. Gửi `postJson` tới route hiện có.
3. Thành công (`ok && body.success`): di chuyển `<li>` từ `<ul>` cột nguồn sang `<ul>` cột đích trong DOM; cập nhật `data-target-stage`/state nếu cần; tính lại count + tổng tiền 2 cột liên quan (Quyết định 6); gỡ `aria-busy`.
4. Thất bại: **không đổi DOM** (card đã ở nguyên cột cũ suốt từ đầu — không cần rollback vì chưa từng move); gỡ `aria-busy`, khôi phục draggable/nút; hiện toast lỗi (Quyết định mới — xem "Toast lỗi mới" bên dưới).
5. Trong lúc pending: không nhận `dragstart` mới trên card đó, không cho double-submit dialog.

### 5. Cột "Thua / Nurture" — cấu hình từ backend, không hardcode trong JS

Blade render `choice_options` (từ `BOARD_GROUPS[...]['choice_options']`, Quyết định 1) thành `<dialog>`:

```html
<dialog data-stage-choice-dialog data-column-label="Thua / Nurture">
    <form method="dialog">
        <p>Chuyển "<span data-dialog-opportunity-name></span>" sang:</p>
        @foreach ($group['choice_options'] as $option)
            <label>
                <input type="radio" name="stage_choice" value="{{ $option['stage'] }}"
                       data-requires-reason="{{ $option['requires_reason'] ? '1' : '0' }}">
                {{ $option['label'] }}
            </label>
        @endforeach
        <textarea data-dialog-reason placeholder="Lý do (bắt buộc nếu chọn Thua)" class="hidden"></textarea>
        <button type="button" data-dialog-cancel>Hủy</button>
        <button type="button" data-dialog-confirm disabled>Xác nhận</button>
    </form>
</dialog>
```

JS: chọn radio có `data-requires-reason="1"` → hiện `textarea`, nút Xác nhận chỉ enable khi (không cần lý do) hoặc (cần lý do và `textarea` không rỗng). Bấm Hủy → `dialog.close()`, không gọi API, card giữ nguyên (không có gì để rollback vì DOM chưa đổi — nhất quán với Quyết định 4). Xác nhận → gọi `requestStageTransition(card, chosenStage, reason)` dùng chung luồng pessimistic update.

Cột "Thua / Nurture" **không có `data-target-stage`** (giá trị `null`/không render attribute này) — JS phát hiện thiếu attribute này (hoặc đọc `data-requires-choice="1"`) để biết phải mở dialog thay vì gửi thẳng.

Tuyệt đối không có JS nào viết `['lost', 'no_bid', 'nurture']` hay tương tự dạng hardcode — toàn bộ 3 lựa chọn + nhãn + `requires_reason` đọc từ DOM do Blade render từ `choice_options` ở PHP.

### 6. Aggregate cột dùng số thô

```html
<li data-opportunity-id="{{ $opportunity->id }}"
    data-terminal="{{ $opportunity->isTerminal() ? '1' : '0' }}"
    data-amount="{{ (int) ($opportunity->estimated_fee ?? 0) }}">
```

`estimated_fee` cast `decimal:0` trên model (đã xác nhận ở khảo sát) → không có phần thập phân VND, ép `(int)` an toàn, không mất dữ liệu.

Sau khi move DOM thành công, JS **tính lại từ các `<li>` thực tế còn trong 2 `<ul>` liên quan** (không tính lũy kế thủ công tăng/giảm — tránh lệch số nếu có race condition hoặc bug):

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

Vì công thức tổng cột đã xác nhận là tổng đơn giản (không trọng số/không công thức phức tạp — xem khảo sát), tính lại phía client là an toàn, **không cần** backend trả aggregate riêng sau mỗi lần cập nhật.

Blade cần đổi `{{ $column['count'] }} · {{ number_format(...) }}₫` thành có `data-column-count`/`data-column-total` để JS ghi đè được, và mỗi `<ul>` cột cần `data-column-id` (dùng `$group['label']` làm id, đã là chuỗi duy nhất trong `BOARD_GROUPS`) để JS xác định nguồn/đích khi `dragover`/`drop`.

### 7. Drag handle riêng, không phải toàn `<li>`

```html
<li class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2"
    data-opportunity-id="{{ $opportunity->id }}"
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
                <button type="button" class="crm-stage-transition-btn text-xs operator-link" data-opportunity-id="{{ $opportunity->id }}">
                    Chuyển giai đoạn
                </button>
            @endif
        </div>
    </div>
</li>
```

Card terminal: **không render** `crm-drag-handle` lẫn nút "Chuyển giai đoạn" (không phải chỉ disable — loại hẳn khỏi DOM, vì backend cũng chặn tuyệt đối nên không có lý do hiện điều khiển chết). `data-terminal="1"` vẫn giữ để JS/test kiểm tra nhanh không cần đếm children.

JS lấy card từ handle: `event.currentTarget.closest('[data-opportunity-id]')`.

Backend (Action, Quyết định 2) vẫn chặn terminal độc lập với frontend — frontend chỉ là UX, không phải security boundary.

## Accessibility & fallback — luồng chung cho chuột, cảm ứng, bàn phím

Nút **"Chuyển giai đoạn"** trên mỗi card (không terminal) là lối vào thứ hai, dùng được bằng bàn phím/touch mà không cần kéo-thả:

```js
function requestStageTransition(card, targetColumnEl, chosenStage, reason) {
    // targetColumnEl xác định qua data-column-id của cột đích
    // chosenStage: lấy từ data-target-stage của cột (nếu không cần chọn)
    //              hoặc từ lựa chọn trong dialog (nếu requires_choice)
    // dùng chung bởi: dragstart+drop VÀ click "Chuyển giai đoạn" (mở menu chọn cột đích thay vì kéo)
}
```

Click "Chuyển giai đoạn" mở một menu/dialog nhỏ liệt kê 6 cột đích (trừ cột hiện tại) — bấm chọn cột nào, nếu cột đó `requires_choice` thì tiếp tục mở dialog chọn stage cụ thể (Quyết định 5) trong cùng thao tác; nếu không, gọi thẳng `requestStageTransition`. Đây **không phải** UI dựng riêng để phục vụ test — là lối thao tác thật cho người dùng không dùng chuột/kéo-thả được (di động, bàn phím), và Dusk test tận dụng lại đúng luồng sản phẩm này (xem Testing).

## Toast lỗi mới (gap cần lấp — không có sẵn trong dự án)

`components/ui/toast.blade.php` hiện tại là banner flash server-side, không dùng được cho lỗi AJAX bất đồng bộ. Thêm hàm nhỏ trong module JS mới, tái dùng class Tailwind đã có ở banner đó cho nhất quán màu sắc:

```js
function showErrorToast(message) {
    var el = document.createElement('div');
    el.className = 'fixed bottom-4 right-4 z-50 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800 shadow-lg';
    el.textContent = message;
    document.body.appendChild(el);
    setTimeout(function () { el.remove(); }, 5000);
}
```

Không phải thư viện toast — 8 dòng, dùng lại đúng class màu lỗi (`border-rose-200 bg-rose-50 text-rose-800`) đã thấy ở `components/ui/toast.blade.php`.

## Content negotiation

Route giữ nguyên `POST /crm/opportunities/{id}/stage` (`operator.crm.opportunities.stage`).

- **Request form cũ** (không có `Accept: application/json`, ví dụ submit form "Chuyển giai đoạn" ở `opportunity-show.blade.php`): `CrmPageController::updateStage` giữ nguyên `redirect()->with(...)` như hiện tại — **hành vi không đổi**.
- **Request AJAX** (`crm-pipeline-drag.js` gửi `Accept: application/json`, `X-Requested-With: XMLHttpRequest`, `X-CSRF-TOKEN`): controller trả JSON.

JSON thành công (route web, nhánh mới — action đã trả `Opportunity` sau khi lưu):

```json
{
  "success": true,
  "status": "success",
  "status_text": "success",
  "message": "Đã cập nhật giai đoạn.",
  "data": {
    "id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
    "pipeline_stage": "qualified",
    "is_terminal": false
  }
}
```

Dùng đúng envelope `{success, status, status_text, data, message}` đã chuẩn hóa qua `ZenaContractResponseTrait::zenaSuccessResponse()` (khảo sát xác nhận đây là shape mọi JSON thành công khác trong app đang dùng, và `work-template-apply.js` đã kiểm `result.body.success`/`result.body.data` theo đúng shape này) — **không** dùng shape rút gọn `{message, data}` như đề xuất ban đầu, để nhất quán với phần còn lại của app và với cách JS hiện có đọc response.

**Chi tiết cần lưu ý khi implement:** `CrmPageController` hiện chỉ `use DelegatesToApiControllers` (đã xác nhận qua khảo sát — dòng khai báo class ở `app/Http/Controllers/Web/CrmPageController.php:36-38`), **không** có `ZenaContractResponseTrait` (trait này hiện chỉ được dùng bởi các controller trong `app/Http/Controllers/Api/`). Để gọi `$this->zenaSuccessResponse(...)` trong nhánh JSON mới của `updateStage()`, cần thêm `use ZenaContractResponseTrait;` vào `CrmPageController` (trait chỉ chứa các method dựng response thuần, không side-effect, an toàn khi thêm vào controller khác). Đây là 1 dòng `use` thêm vào — không phải refactor lan rộng.

Xử lý lỗi phía JS — **không giả định shape cố định** (lý do: đã khảo sát và xác nhận `isZenaRequest()` khiến route web/API trả 2 shape lỗi khác nhau cho cùng loại exception, xem mục khảo sát):

| Status | Nguồn gốc | Xử lý JS |
|---|---|---|
| 401 | Chưa đăng nhập (RBAC middleware, đã JSON sẵn) | Hiện toast: phiên đăng nhập không hợp lệ, đề nghị tải lại trang |
| 403 | Thiếu quyền `crm.manage` (RBAC middleware) hoặc sai tenant (Policy trong Action) | Hiện toast dùng `body.message` nếu có, fallback "Bạn không có quyền thực hiện thao tác này." |
| 419 | CSRF token hết hạn (Laravel mặc định) | Hiện toast cố định: "Phiên làm việc đã hết hạn, vui lòng tải lại trang." — **không** dùng `body.message` vì message mặc định của Laravel không thân thiện |
| 422 | Terminal / thiếu `lost_reason` / stage không hợp lệ (Action ném `ValidationException`) | Hiện toast dùng `body.message` (route web) hoặc field lỗi đầu tiên trong `body.data`/`body.errors` tùy shape (route API không dùng ở đây, chỉ web) |
| 500 | Lỗi không lường trước | Hiện toast chung: "Có lỗi xảy ra, vui lòng thử lại." — không phơi bày chi tiết lỗi |
| Network error (`fetch` reject, không có response) | Mất mạng | Cùng thông báo với 500 |

Mọi nhánh lỗi đều: gỡ `aria-busy`, khôi phục draggable/nút, **không** đổi DOM (đúng Quyết định 4).

## Asset build

Thêm `resources/js/crm-pipeline-drag.js` vào:
- `vite.config.js` → mảng `input` của `laravel()` plugin.
- `resources/views/layouts/operator.blade.php:10` → mảng `@vite([...])`.

(Cả hai chỗ này hiện liệt kê thủ công từng file — không có auto-discovery, đã xác nhận qua khảo sát.)

## Testing

### Feature tests (`tests/Feature/`)

1. `Accept: application/json` → JSON đúng shape `{success, status, status_text, data:{id,pipeline_stage,is_terminal}, message}`, status 200.
2. Request thường (không header `Accept: application/json`) → vẫn redirect với `session('success')`, y hệt hành vi hiện tại (regression test cho form cũ).
3. User thiếu `crm.manage` + `Accept: application/json` → 403 JSON.
4. `pipeline_stage` không thuộc `Opportunity::VALID_STAGES` + `Accept: application/json` → 422 JSON.
5. Chuyển sang `lost` thiếu `lost_reason` + `Accept: application/json` → 422 JSON.
6. Opportunity đã terminal (`won`/`lost`/`no_bid`) → mọi request đổi stage (JSON lẫn redirect) đều bị chặn, opportunity không đổi.
7. Test cùng 1 kịch bản (vd #5 — thiếu lost_reason) gọi qua **cả hai** route (web JSON path và API) → cùng bị chặn với cùng lý do, xác nhận `CrmPageController` và `ApiOpportunityController` cùng dùng `UpdateOpportunityStageAction` (không lệch hành vi giữa 2 transport).
8. API route (`api.zena.crm.opportunities.stage`) giữ nguyên shape response như trước refactor (regression — đảm bảo Quyết định 2 không đổi contract).

### Unit test cho invariant `default_entry_stage`

9. Với mọi phần tử `BOARD_GROUPS`: nếu `default_entry_stage !== null`, nó phải nằm trong `stages` của chính phần tử đó. Với phần tử có `requires_choice: true`, mọi `choice_options[].stage` phải nằm trong `stages`.

### Render/view contract tests (`tests/Feature/` dùng `assertSeeHtml`/DOM assertions qua `Illuminate\Testing\TestResponse`, không cần Dusk)

10. Card thường có `data-opportunity-id`, `data-amount` (số thô, không có dấu chấm/₫), `data-terminal="0"`.
11. Card terminal: `data-terminal="1"`, **không có** phần tử `.crm-drag-handle` (kiểm bằng `assertDontSee` trên class đó trong phạm vi `<li>` đó, hoặc parse DOM), không có nút "Chuyển giai đoạn".
12. Cột thường có `data-target-stage` khớp đúng `default_entry_stage` khai báo trong `BOARD_GROUPS`.
13. Cột "Thua / Nurture" **không có** `data-target-stage` (hoặc rỗng), có `data-requires-choice="1"`.
14. `<dialog data-stage-choice-dialog>` chứa đủ 3 `<input type="radio">` với đúng `value` = `lost`/`no_bid`/`nurture` và đúng `data-requires-reason`, sinh ra từ `choice_options` — đối chiếu ngược để xác nhận JS không thể hardcode được (vì giá trị chỉ tồn tại nhờ Blade loop).

### Dusk tests (`tests/Browser/`) — qua luồng click "Chuyển giai đoạn", không giả lập HTML5 drag

15. Card terminal không có nút/handle chuyển giai đoạn nào hiển thị.
16. Click "Chuyển giai đoạn" → chọn cột "Thua / Nurture" → dialog mở ra đủ 3 lựa chọn.
17. Chọn "Thua" không nhập lý do → nút Xác nhận vẫn disabled, không submit được.
18. Chọn "Thua", nhập lý do, Xác nhận → card biến mất khỏi cột cũ, xuất hiện ở cột "Thua / Nurture", count/tổng tiền 2 cột cập nhật đúng.
19. Mở dialog rồi bấm Hủy → không có request nào được gửi (kiểm qua network log nếu Dusk hỗ trợ, hoặc gián tiếp qua card không đổi cột + không có toast) → card giữ nguyên.
20. Giả lập backend trả lỗi (vd seed 1 opportunity đã terminal rồi cố chuyển qua route khác — hoặc mock permission) → toast lỗi hiện, card giữ nguyên cột cũ.
21. Trong lúc request đang treo (nếu Dusk có cách giả lập độ trễ mạng), nút "Chuyển giai đoạn"/handle bị disable, không submit lặp được — nếu Dusk không giả lập được độ trễ đáng tin cậy, thay bằng assertion tĩnh: sau khi bấm, nút có `aria-busy`/disabled ngay lập tức trước khi network trả lời (kiểm bằng JS eval nhanh nếu Dusk hỗ trợ, nếu không thì bỏ qua case này và ghi chú lại là giới hạn công cụ, không phải bỏ sót yêu cầu).

## Rủi ro / câu hỏi còn mở

1. **Đổi cấu trúc `BOARD_GROUPS`** (mảng liên kết → mảng chỉ số với key tường minh) là thay đổi breaking cho property này — đã xác nhận chỉ 1 nơi dùng (`index()`), nhưng cần double-check lại tại thời điểm code thật (grep `BOARD_GROUPS`) vì spec này viết trước khi implement, code có thể trôi.
2. **Dialog xác nhận "Thua"** dùng `<dialog>` gốc — cần kiểm tra Tailwind/CSS reset hiện tại của dự án có style mặc định nào đè `<dialog>` (backdrop, position) hay không; nếu `layouts/operator.blade.php` chưa có CSS cho `<dialog>::backdrop`, cần thêm style tối thiểu (không phải library, chỉ vài dòng CSS) — chưa khảo sát phần CSS, cần làm ở bước implementation.
3. **Test case Dusk #21** (pending state trong lúc request treo) phụ thuộc khả năng giả lập độ trễ mạng của Dusk trong dự án — cần xác nhận lúc viết plan liệu có pattern sẵn (vd throttle test route, hoặc queue:sync delay) hay phải bỏ qua.
4. Chưa quyết định: nút "Chuyển giai đoạn" mở menu chọn cột đích dạng gì (dropdown đơn giản hay dialog riêng) — spec chỉ mô tả hành vi (liệt kê 6 cột trừ cột hiện tại), chưa chốt markup cụ thể; sẽ chốt ở bước viết plan/implementation, không ảnh hưởng kiến trúc backend.
