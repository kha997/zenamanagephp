# CRM Appointments Implementation Plan (Goal #1 Slice — Lịch tư vấn/khảo sát)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Đặt lịch hẹn tư vấn/khảo sát gắn với Opportunity — model mới `OpportunityAppointment` + card trên trang opportunity-show, khép một phần khoảng trống goal #1 (CRM sale).

**Architecture:** Model mới ULID+TenantScope theo mẫu `Quote` (TRANSITIONS map); mở rộng `CrmPageController::showOpportunity()` (không viết controller riêng — đây là sub-resource của opportunity, giống cách quote card được nhúng); card mới trên `resources/views/crm/opportunity-show.blade.php`. Spec: `docs/superpowers/specs/2026-07-16-crm-appointments-design.md`.

## Global Constraints

- `Model::query()`; pattern auth trong `CrmPageController` là `auth()->user()?->tenant_id` (file này ĐANG dùng `auth()` helper xuyên suốt — giữ nguyên phong cách file, KHÔNG đổi sang Auth facade giữa chừng 1 file).
- CẤM sửa `tests/TestCase.php`; CSRF test = `$this->get('/login');` trong `setUp()`.
- File mới không có entry baseline (diff kiểm trước báo cáo); `HasFactory` mới = kèm `/** @use HasFactory<...> */`.
- **EventRecord LUÔN đúng schema thật**: cột là `event_key` / `actor_user_id` / `payload` / `occurred_at` — TUYỆT ĐỐI KHÔNG dùng `event_type`/`actor_id` (bug 500 thật đã xảy ra ở slice quote trước, đã sửa — đừng lặp lại). Mẫu đúng: xem `CrmPageController::acceptQuote` hoặc `Api\OpportunityController::recordEvent`.
- Test mutation PHẢI assert response (redirect/session), không chỉ DB state.
- Tên index/FK migration ≤ 64 ký tự — bảng mới `opportunity_appointments` khá dài, kiểm kỹ tên tự sinh, đặt tên tường minh nếu cần (vd `opp_appt_tenant_opp_index`).
- Checklist sau MỖI task: Architecture 29 / `--testsuite=Feature` toàn bộ xanh / phpstan exit 0. Push cuối: guardrails CI success.
- Claim "pre-existing failure" phải kèm bằng chứng chạy trên base commit hiện tại của `main`.
- PR: base `main`. Sau khi tạo dán `gh pr view <n> --json baseRefName,commits,mergeable` vào báo cáo. KHÔNG merge.
- **KHÔNG đụng** file `WorkTemplate*` untracked nếu thấy trong working tree (thuộc phiên khác, không liên quan) — không add, không xóa, không sửa.

---

### Task 1: Model + migration + guard

**Files:** Create migration `create_opportunity_appointments_table`; Create `app/Models/OpportunityAppointment.php`; Create `database/factories/OpportunityAppointmentFactory.php`; Modify `tests/Feature/Models/TenantScopedCrmModelsTest.php` (thêm `OpportunityAppointment::class` vào mảng guard — đọc file này trước, nó đã có sẵn nhiều model, chỉ thêm 1 dòng); Test: Create `tests/Feature/Models/OpportunityAppointmentModelTest.php`.

**Interfaces (Task 2 dùng đúng):**

```php
class OpportunityAppointment extends Model
{
    use HasUlids, TenantScope;
    /** @use HasFactory<\Database\Factories\OpportunityAppointmentFactory> */
    use HasFactory;

    public const TYPE_CONSULTATION = 'consultation';
    public const TYPE_SURVEY = 'survey';
    public const VALID_TYPES = [self::TYPE_CONSULTATION, self::TYPE_SURVEY];

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_RESCHEDULED = 'rescheduled';

    public const TRANSITIONS = [
        self::STATUS_SCHEDULED => [self::STATUS_COMPLETED, self::STATUS_CANCELLED, self::STATUS_RESCHEDULED],
        self::STATUS_COMPLETED => [],
        self::STATUS_CANCELLED => [],
        self::STATUS_RESCHEDULED => [],
    ];

    public static function canTransition(string $from, string $to): bool;
    public function opportunity(): BelongsTo;
    public function assignee(): BelongsTo; // assigned_to -> User
}
```

Migration columns theo spec Data (`tenant_id`, `opportunity_id` indexed FK, `type`, `scheduled_at` datetime, `location` string(255) nullable, `assigned_to` FK users nullable, `status` default `scheduled`, `outcome_notes` text nullable, `created_by` FK users, timestamps).

Test: tạo appointment thành công cả 2 type; `canTransition` bảng chân lý đủ các cặp (scheduled→3 đích hợp lệ, 3 trạng thái cuối→[] rỗng); TenantScope guard (thêm vào file guard chung).

- [ ] Steps: failing test → migration + model + factory → PASS → checklist → commit `feat(crm): OpportunityAppointment model with lifecycle and TenantScope guard`.

---

### Task 2: Controller actions + routes

**Files:** Modify `routes/web.php` (route mới cạnh `crm.opportunities.*` hiện có, tìm bằng grep `operator.crm.opportunities` dòng ~1023-1031); Modify `app/Http/Controllers/Web/CrmPageController.php` (4 method mới + mở rộng `showOpportunity()` để truyền `appointments`); Test: Create `tests/Feature/OpportunityAppointmentLifecycleTest.php`.

```php
Route::post('/crm/opportunities/{id}/appointments', [App\Http\Controllers\Web\CrmPageController::class, 'storeAppointment'])->middleware('rbac:crm.manage')->name('crm.opportunities.appointments.store');
Route::post('/crm/appointments/{id}/complete', [App\Http\Controllers\Web\CrmPageController::class, 'completeAppointment'])->middleware('rbac:crm.manage')->name('crm.appointments.complete');
Route::post('/crm/appointments/{id}/cancel', [App\Http\Controllers\Web\CrmPageController::class, 'cancelAppointment'])->middleware('rbac:crm.manage')->name('crm.appointments.cancel');
Route::post('/crm/appointments/{id}/reschedule', [App\Http\Controllers\Web\CrmPageController::class, 'rescheduleAppointment'])->middleware('rbac:crm.manage')->name('crm.appointments.reschedule');
```

Trong `showOpportunity()`, thêm vào mảng trả về (đọc method hiện tại trước — nó đã có `boqCard`/`contractCard`/`users`/`events`, thêm 1 key nữa theo đúng pattern):

```php
'appointments' => \App\Models\OpportunityAppointment::query()
    ->where('tenant_id', $tenantId)
    ->where('opportunity_id', $id)
    ->with('assignee:id,name')
    ->orderByDesc('scheduled_at')
    ->get(),
```

**Methods** (mirror pattern `storeQuote`/`acceptQuote` trong cùng file — `auth()->user()?->tenant_id`, scoped-fetch, `back()->with(...)`):

- `storeAppointment(Request $request, string $id)`: validate `type` (in TYPE list), `scheduled_at` (required, date, after:now), `location` (nullable string), `assigned_to` (nullable, exists trong users cùng tenant — validate thủ công không dùng rule `exists` xuyên tenant); tạo `STATUS_SCHEDULED`; EventRecord `event_key='opportunity_appointment.scheduled'`.
- `completeAppointment(Request $request, string $id)`: validate `outcome_notes` required; guard `canTransition`; update `status=completed` + lưu outcome_notes; EventRecord `event_key='opportunity_appointment.completed'`.
- `cancelAppointment(Request $request, string $id)`: `outcome_notes` nullable (lý do hủy); guard `canTransition`; EventRecord `event_key='opportunity_appointment.cancelled'`.
- `rescheduleAppointment(Request $request, string $id)`: validate `scheduled_at` mới (after:now); trong `DB::transaction`: tạo appointment MỚI (copy `opportunity_id`/`type`/`location`/`assigned_to`, `status=scheduled`, `scheduled_at` mới) + appointment gốc `status=rescheduled`; EventRecord `event_key='opportunity_appointment.rescheduled'` trên bản gốc.

Mọi method sau khi xong: `return back()->with('success', '...')`; guard sai → `return back()->with('error', '...')`; cross-tenant/not-found → `findOrFail` tự 404.

Test (nhớ assert redirect + session, không chỉ DB — bài học bắt buộc):
- Đặt lịch thành công cả 2 loại → redirect + success + DB đúng.
- Hoàn thành thiếu `outcome_notes` → error, status không đổi.
- Hoàn thành đủ `outcome_notes` → success + EventRecord `event_key='opportunity_appointment.completed'` tồn tại đúng 1 bản ghi.
- Hủy → success.
- Dời lịch → bản gốc `rescheduled`, bản mới `scheduled` với `scheduled_at` đúng, cả 2 cùng `opportunity_id`.
- Transition sai (hoàn thành appointment đã `completed`) → error.
- Cross-tenant appointment → 404.
- Thiếu quyền `crm.manage` (chỉ có `crm.view`) → POST bất kỳ action → 403.

- [ ] Steps: failing test → routes + methods + showOpportunity mở rộng → PASS → checklist → commit `feat(crm): appointment scheduling actions for opportunities`.

---

### Task 3: UI — card "Lịch hẹn"

**Files:** Modify `resources/views/crm/opportunity-show.blade.php` (thêm `<x-ui.card title="Lịch hẹn">` — đọc cấu trúc card "Báo giá (native)" hiện có trong file này làm mẫu định dạng bảng+form+nút, dòng ~166); Test: bổ sung render assertions vào `OpportunityAppointmentLifecycleTest.php`.

- Bảng lịch hẹn: cột Loại (Tư vấn/Khảo sát — label tiếng Việt, không hiện raw `consultation`/`survey`)/Ngày giờ/Người phụ trách/Trạng thái (badge màu mirror cách quote-show làm badge)/Kết quả (outcome_notes nếu có).
- Form "Đặt lịch mới": select `type`, `input type="datetime-local"` cho `scheduled_at`, input text `location`, select `assigned_to` từ `$users` (đã có sẵn trong view data).
- Mỗi dòng `scheduled`: 3 form riêng — Hoàn thành (textarea `outcome_notes` bắt buộc + submit), Hủy (textarea `outcome_notes` tùy chọn + submit, confirm), Dời lịch (input `scheduled_at` mới + submit).

Test: trang opportunity-show render thấy tiêu đề "Lịch hẹn", thấy label "Tư vấn"/"Khảo sát" (không phải raw value), thấy nút "Hoàn thành"/"Hủy"/"Dời lịch" khi có appointment `scheduled`; appointment `completed` hiện `outcome_notes`, KHÔNG hiện 3 nút hành động.

- [ ] Steps: failing render test → view → PASS → checklist → commit `feat(crm): appointment card UI on opportunity page`.

---

### Task 4: Final verification + PR

- [ ] 3 con số (Architecture 29 / Feature suite full xanh / phpstan 0) + baseline diff 0 path mới + regression `CrmApiTest` + `QuoteLifecycleTest` nguyên bộ + guardrails CI success.
- [ ] `gh pr create` head nhánh này **base `main`**; dán `gh pr view <n> --json baseRefName,commits,mergeable` vào báo cáo. KHÔNG merge.

## Self-review notes

- Spec coverage: Data+lifecycle→T1, actions+wiring→T2, UI→T3.
- `OpportunityAppointment::TRANSITIONS`/`canTransition` khai báo 1 lần ở T1, T2 dùng đúng chữ ký.
- Bài học slice trước nhúng cứng: schema EventRecord đúng (`event_key`/`actor_user_id`/`occurred_at`), test phải assert response, không đụng WorkTemplate* untracked, PR base main trực tiếp (không branch trung gian — bài học vụ #167-169 bị lạc merge).
- Không tự động đổi `pipeline_stage` của Opportunity — quyết định có chủ đích trong spec Out of scope, tránh side-effect ẩn giữa 2 module.
