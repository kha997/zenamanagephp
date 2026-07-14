# Portal Client Actions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Khách hàng duyệt / yêu cầu sửa DesignItem `sent_to_client` ngay trên portal (hiệu lực tức thì, evidence `client_portal`), kèm notification cho người phụ trách.

**Architecture:** T1 tách nguyên văn logic transition từ `Api\DesignItemController::updateStatus()` sang `App\Services\DesignItemStatusService` (pure refactor, mọi test hiện có xanh nguyên vẹn); T2 routes+controller portal dùng service + scoping account theo pattern `PortalDashboardController` + limiter mới; T3 UI portal. Spec: `docs/superpowers/specs/2026-07-14-portal-client-actions-design.md`.

## Global Constraints

- Toàn bộ ràng buộc handoff #1 + các quy tắc đã thành nếp: `Model::query()->...`, CẤM helper `auth()` trong code mới (dùng facade/`$request->user()`; với portal guard: `Auth::guard('client')->user()` — xem cách `portal.auth`/`PortalDashboardController` lấy account hiện tại và làm y hệt), count `phpstan-baseline.neon` không bao giờ tăng.
- Checklist sau MỖI task: `php artisan test tests/Feature/Architecture/` (29) + `--testsuite=Feature` (897) + `vendor/bin/phpstan analyse --memory-limit=1G` exit 0. **CI Routes Guardrails giờ ĐANG XANH — không được làm nó đỏ lại**; sau push cuối, kiểm `gh run list --workflow=routes-guardrails.yml --limit 1` phải success.
- Test POST portal: nhớ `$this->get('/login');` warm-up CSRF trong `setUp()`; đăng nhập khách trong test theo đúng cách các test Portal hiện có làm (`tests/Feature/Portal/` — đọc trước, copy pattern `actingAs($account, 'client')` hoặc magic-link flow tùy test cũ).
- Anti-enumeration: MỌI trường hợp từ chối truy cập item (không tồn tại / khác tenant / khác account) trả về CÙNG một 404.
- Không đụng `/api/v1/*`; migration KHÔNG có trong slice này (không đổi schema).

---

### Task 1: Tách `DesignItemStatusService` (pure refactor)

**Files:**
- Create: `app/Services/DesignItemStatusService.php`
- Modify: `app/Http/Controllers/Api/DesignItemController.php` (`updateStatus()` delegate), `app/Models/DesignItem.php` (docblock: đổi câu "only ever changed via updateStatus()" → "only ever changed via DesignItemStatusService")
- Test: KHÔNG viết test mới — thước đo của refactor là bộ test cũ.

**Interfaces (T2 dùng đúng chữ ký này):**

```php
namespace App\Services;

final class DesignItemStatusService
{
    /**
     * Nguồn chân lý duy nhất cho chuyển trạng thái review_status.
     * @param array{client_feedback_notes?: string|null, approval_evidence?: string|null,
     *        actor_user_id?: string|null, actor_account_id?: string|null} $options
     * @throws \Illuminate\Validation\ValidationException
     */
    public function transition(\App\Models\DesignItem $item, string $to, array $options = []): \App\Models\DesignItem
}
```

- [ ] **Step 1 — chụp baseline:** `php artisan test --filter=DesignItem` (21) + `tests/Feature/Zena/DesignItemRevisionCycleTest.php` → ghi lại số passed.
- [ ] **Step 2 — di chuyển logic:** copy NGUYÊN VĂN từ `updateStatus()` (từ đoạn lấy `$from`/kiểm `canTransition` đến hết EventRecord::create, bao gồm cả validate-điều-kiện sent_to_client/approved/revision_requested và DB::transaction ghi revision) vào `transition()`. Khác biệt duy nhất được phép: (a) các `return $this->validationError(...)` đổi thành `throw ValidationException::withMessages([...])` với cùng key+message; (b) `Auth::id()` đổi thành `$options['actor_user_id'] ?? null`; (c) EventRecord payload thêm `'actor_account_id' => $options['actor_account_id'] ?? null` (giữ nguyên các key cũ); (d) đọc feedback/evidence từ `$options` thay vì `$request`.
- [ ] **Step 3 — controller delegate:** `updateStatus()` giữ authz + validator hiện có, rồi:

```php
        try {
            $item = app(\App\Services\DesignItemStatusService::class)->transition($item, $to, [
                'client_feedback_notes' => $request->input('client_feedback_notes'),
                'approval_evidence' => $request->input('approval_evidence'),
                'actor_user_id' => (string) Auth::id(),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationError($e->errors());
        }
```

  (Xóa phần logic đã dời đi; giữ response cuối như cũ.)
- [ ] **Step 4 — chạy lại đúng bộ test Step 1: số passed PHẢI bằng baseline, 0 sửa test.** Full checklist. Commit `refactor(design-items): extract status transition authority into DesignItemStatusService`.

---

### Task 2: Portal endpoints + scoping + limiter + notification

**Files:**
- Modify: `app/Providers/RouteServiceProvider.php` (limiter `portal-actions`: `Limit::perMinute(10)->by(($request->user('client')?->id ?? 'guest') . '|' . $request->ip())` — đặt cạnh limiter `ai-suggest`)
- Modify: `routes/web.php` — trong group `portal/{tenantSlug}` sau các route dashboard, bên trong `portal.auth`:

```php
        Route::get('/design-items/{id}', [App\Http\Controllers\Web\Portal\PortalDesignItemController::class, 'show'])->name('design-items.show');
        Route::post('/design-items/{id}/approve', [App\Http\Controllers\Web\Portal\PortalDesignItemController::class, 'approve'])->middleware('throttle:portal-actions')->name('design-items.approve');
        Route::post('/design-items/{id}/request-revision', [App\Http\Controllers\Web\Portal\PortalDesignItemController::class, 'requestRevision'])->middleware('throttle:portal-actions')->name('design-items.request-revision');
```

  (Tên route đầy đủ sẽ có prefix `portal.` của group — xác minh `php artisan route:list | grep portal`.)
- Create: `app/Http/Controllers/Web/Portal/PortalDesignItemController.php`
- Test: `tests/Feature/Portal/PortalDesignItemActionsTest.php`

**Controller (mirror cách `PortalDashboardController` lấy `$tenant`/`$account` — đọc file đó trước, dùng đúng cùng cơ chế):**

```php
    private function accountProjectIds(string $tenantId, string $accountId): \Illuminate\Support\Collection
    {
        return \App\Models\Opportunity::query()
            ->where('tenant_id', $tenantId)
            ->where('account_id', $accountId)
            ->whereNotNull('converted_project_id')
            ->pluck('converted_project_id')->unique()->values();
    }

    private function findOwnedItem(string $tenantId, string $accountId, string $id): \App\Models\DesignItem
    {
        return \App\Models\DesignItem::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('project_id', $this->accountProjectIds($tenantId, $accountId))
            ->with('revisions', 'project:id,tenant_id,name')
            ->findOrFail($id);   // mọi nhánh từ chối đều 404 đồng nhất
    }
```

- `show`: render `portal.design-item` với item.
- `approve`: `findOwnedItem` → nếu `review_status !== sent_to_client` → `back()->withErrors(['action' => 'Phương án không còn ở trạng thái chờ phản hồi.'])` → ngược lại service `transition($item, DesignItem::STATUS_APPROVED, ['approval_evidence' => DesignItem::EVIDENCE_CLIENT_PORTAL, 'actor_account_id' => (string) $account->id])` (bắt ValidationException → back errors) → notification (dưới) → `back()->with('success', 'Bạn đã duyệt phương án. Cảm ơn bạn!')`.
- `requestRevision`: validate `client_feedback_notes => required|string|max:2000` → cùng guard trạng thái → `transition($item, DesignItem::STATUS_REVISION_REQUESTED, ['client_feedback_notes' => ..., 'actor_account_id' => ...])` → notification → success "Đã ghi nhận yêu cầu chỉnh sửa của bạn."
- Notification (private helper, gọi sau transition thành công, bọc try/catch nuốt lỗi notification — hành động của khách KHÔNG được fail vì notify hỏng):

```php
        if ($item->assigned_to) {
            \App\Models\Notification::query()->create([
                'tenant_id' => (string) $item->tenant_id,
                'user_id' => (string) $item->assigned_to,
                'type' => 'portal_client_action',
                'title' => $actionLabel . ': ' . $item->name,   // 'Khách đã duyệt phương án' | 'Khách yêu cầu chỉnh sửa'
                'body' => $body,                                 // feedback của khách khi yêu cầu sửa
                'link_url' => route('operator.design-items.show', $item->id),
            ]);
        }
```

  (Đối chiếu fillable/bắt buộc thực tế của `App\Models\Notification` trước khi viết — nếu thiếu cột `tenant_id` trong fillable thì bỏ key đó; kiểm bằng test.)

**Test (`PortalDesignItemActionsTest` — setup theo test Portal hiện có: tenant, account, opportunity converted→project, design item `sent_to_client`, đăng nhập client guard, `$this->get('/login')` warm-up; user assignee qua `TenantUserFactoryTrait`):**
1. approve happy path: 302 + status approved + evidence client_portal + EventRecord payload chứa actor_account_id + Notification cho assignee.
2. request-revision happy path: revision_requested + DesignItemRevision mới (client_feedback đúng nguyên văn, revision_count 1, requested_by null).
3. approve khi item đang `draft` → back error, status không đổi.
4. item thuộc account khác (cùng tenant) → 404; item tenant khác → 404; id không tồn tại → 404 (một test 3 case, assert CÙNG mã 404).
5. Chưa đăng nhập portal → redirect về login portal.
6. Throttle: request thứ 11 trong phút → 429.
7. Item không có assignee → hành động vẫn thành công, không có Notification, không lỗi.

- [ ] Steps: failing test → limiter → routes → controller → PASS → full checklist → commit `feat(portal): client approve/request-revision actions on design items`.

---

### Task 3: Portal UI

**Files:** Modify `resources/views/portal/dashboard.blade.php` (item → link + badge "Chờ bạn phản hồi" khi `sent_to_client`); Create `resources/views/portal/design-item.blade.php`; Modify `PortalDashboardController` nếu cần truyền thêm route param.

- Trang design-item theo layout/tông dashboard portal (đọc dashboard.blade trước, dùng cùng khung): tên item + dự án, badge trạng thái (nhãn tiếng Việt như `$statusLabels` operator — copy mảng nhãn, KHÔNG include partial operator), hạn `due_to_client_at`, timeline `revisions` (Sửa lần N — ngày — nội dung — đã xử lý/đang xử lý), flash success/errors.
- Khi `sent_to_client`: nút "Duyệt phương án" (form POST `portal.design-items.approve`, `@csrf`, `onsubmit="return confirm('Xác nhận DUYỆT phương án này? Hành động có giá trị xác nhận chính thức.')"`), và form "Yêu cầu chỉnh sửa" (textarea `client_feedback_notes` required maxlength 2000 + confirm tương tự). Trạng thái khác: dòng ghi chú "Phương án đang được đội ngũ xử lý" / "Bạn đã duyệt phương án này".
- [ ] Steps: bổ sung render assertions vào `PortalDesignItemActionsTest` (thấy nút Duyệt khi sent_to_client; KHÔNG thấy nút khi approved; thấy "Sửa lần 1" sau request-revision; dashboard có badge "Chờ bạn phản hồi") → implement → PASS → full checklist → commit `feat(portal): design item detail page with client action UI`.

---

### Task 4: Final verification

- [ ] 3 con số chuẩn (Architecture 29 / Feature ≥897+mới / phpstan exit 0, baseline diff 0 tăng) + toàn bộ suite Portal + DesignItem xanh.
- [ ] Push, rồi xác nhận `gh run list --workflow=routes-guardrails.yml --limit 1` = success.
- [ ] Báo cáo. Không merge.

## Self-review notes

- Spec coverage: C1→T1 (pure refactor, thước đo = 0 test sửa), C2→T2 (đủ 7 test case khớp mục Testing của spec), C3→T3; anti-enumeration nằm trong `findOwnedItem` + test case 4.
- Chữ ký `transition()` khai báo một lần ở T1, T2 gọi đúng; nhãn/thông điệp tiếng Việt nhất quán giữa controller và view.
- Không migration, không đổi API contract operator — backward compatible toàn phần.
