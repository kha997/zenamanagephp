# Today Workspace MVP — role-aware read model and action-oriented composition

Date: 2026-07-31 (rev 3 — sửa theo architectural/product review lần 3)
Status: DESIGN — final, sẵn sàng chuyển sang implementation plan.
Nguồn gốc: `docs/superpowers/brainstorms/2026-07-31-role-based-workspace-brainstorm.md` (đã qua architectural/product review, hướng Approach C đã chốt, không mở lại brainstorm rộng).

**Thay đổi so với rev 2:** (1) bỏ hẳn Action Required khỏi runtime MVP — 0 nguồn deterministic hiện có (RFI chờ GAP-030, Document đã đăng ký GAP-031, CR/Submittal chờ GAP-012/013) nghĩa là không có gì để implement hay hiển thị; giữ lại contract làm điều kiện gia nhập tương lai, không dựng `ActionRequiredQuery` rỗng chỉ để "giữ chỗ". (2) làm cơ chế gán permission cho nav item xác định 100% từ route middleware thật (không còn để implementation tự phán đoán permission cho 27 item). (3) cập nhật kiến trúc/test/query budget/non-goal cho khớp với việc bỏ Action Required.

---

## 1. Scope of this design

Thiết kế đúng 1 lát cắt: trang `/app/today` ("Hôm nay") — một read-model tổng hợp theo vai trò, dùng dữ liệu domain hiện có, không bảng mới, không rule engine, không materialized projection, không background job. Lát cắt này cũng bao gồm việc tách `OpenWorkReadQuery` ra khỏi `WorkloadPageController` (mục 3) và cơ chế permission-aware navigation cho toàn bộ sidebar operator, xác định permission bằng route middleware thật (mục 6.7).

**Không thiết kế/không implement ở đây** (non-goal, mục 13): canonical Project Progress, báo cáo tài chính dự án, Focus timer bền vững, time-tracking, leave/attendance, capacity %, KPI cá nhân, predictive delay, role Ban giám đốc, ActionItem ledger, hợp nhất multi-assignee, sửa notification lifecycle của GAP-012/013, sửa RFI escalation permission (GAP-030), sửa Document approval (GAP-031), **Action Required runtime (0 nguồn đủ điều kiện — xem mục 7)**, PM Command Center đầy đủ, Admin operating dashboard đầy đủ.

## 2. Binding decisions (không mở lại)

- Route RBAC luôn bắt buộc; navigation render theo permission/capability; ẩn nav không bao giờ thay thế route authorization.
- `/app/today` là đích landing page sau đăng nhập; việc thay landing page có thể triển khai **sau** khi acceptance test của Today (bao gồm navigation retrofit, mục 6.7) pass.
- Today MVP không hiển thị % hoàn thành dự án canonical; không chọn công thức nào trong số các công thức hiện có.
- Không hiển thị `Project.budget_actual` hay bất kỳ số tài chính nào trong MVP.
- `Task.assigned_to` là primary-assignee SSOT cho Today MVP; không trộn `TaskAssignment`/`assignedUsers` vào query.
- Action Required **không được implement ở MVP này** — 0 nguồn trong repo hiện tại thoả đủ actor/hành động/record/trạng thái/quyền/route xác định (xem mục 7 để biết điều kiện gia nhập tương lai, không phải câu hỏi mở).
- Unread Updates là section runtime độc lập trong MVP, không phụ thuộc vào việc Action Required có tồn tại hay không.
- "Đang thực hiện" không suy ra một task "đang làm ngay", hiển thị nhiều task `in_progress` theo `assigned_to`, sắp xếp xác định.
- Section PM/team không dùng nhãn "Rảnh"/"Khả dụng X%"/"Quá tải X%" — gọi là "recorded workload/operational exception view".
- Navigation permission-aware áp dụng cho toàn bộ sidebar operator trong MVP, permission của mỗi item được **suy ra tự động từ route middleware thật**, không phải do implementation tự gán (mục 6.7).

## 3. Kiến trúc — read boundary

### 3.1 `OpenWorkReadQuery` — trích xuất khỏi `WorkloadPageController`

`WorkloadPageController::collectOpenItems(string $tenantId): Collection` (`app/Http/Controllers/Web/WorkloadPageController.php:81-131`) hiện là `private`, không thể gọi từ ngoài. Trích xuất thành `App\Services\OpenWorkReadQuery::collect(string $tenantId): Collection<OpenWorkItem>` — phụ thuộc chung của `WorkloadPageController` (giữ nguyên `index()`/`myWork()`, nay gọi ra ngoài) và `TodayWorkspaceReadService`.

**Trách nhiệm duy nhất:** trả về toàn bộ open work item (Task + DesignItem) của 1 tenant, chưa lọc theo actor, chưa nhóm theo người — đúng logic `collectOpenItems()` hôm nay, không thêm/bớt nghiệp vụ.

**DTO — `OpenWorkItem` (readonly), mở rộng additive so với mảng hiện tại:**

```php
final class OpenWorkItem
{
    public function __construct(
        public readonly string $sourceType,      // 'task' | 'design_item'
        public readonly string $sourceId,
        public readonly ?string $assignedTo,
        public readonly string $name,
        public readonly ?string $projectId,       // MỚI (additive)
        public readonly string $projectName,
        public readonly ?Carbon $endDate,
        public readonly bool $isOverdue,
        public readonly bool $isBlocked,
        public readonly ?string $blockerNote,     // MỚI (additive)
        public readonly ?string $blockedBy,       // MỚI (additive) — text thô, không FK
        public readonly ?string $priority,        // MỚI (additive) — null cho design_item
        public readonly string $status,
        public readonly string $url,
    ) {}
}
```

**Tenant filter tường minh, không dựa hoàn toàn vào global scope:** giữ nguyên `Task::query()->where('tenant_id', $tenantId)` / `DesignItem::query()->where('tenant_id', $tenantId)` tường minh (`WorkloadPageController.php:86,92`) dù cả hai model đã có `TenantScope` (`Task.php:16`, `DesignItem.php:42`).

**Regression:** `MyWorkPageTest`/`WorkloadPageTest` giữ nguyên, không sửa assertion — bài test hồi quy cho refactor này. Thêm `OpenWorkReadQueryTest` (unit) xác nhận field mới không ảnh hưởng 2 controller cũ.

### 3.2 `TodayWorkspaceReadService` — orchestration boundary

```
App\Http\Controllers\Web\TodayController::index()
    → resolve actor (Auth::user()) + tenant (Auth::user()->tenant_id)
    → App\Services\TodayWorkspaceReadService::build(User $actor): TodayWorkspaceViewModel
    → return view('app.today', ['workspace' => $viewModel])
```

`TodayController::index()` không chứa Eloquent query hay logic gom nhóm. `TodayWorkspaceReadService::build()` chỉ điều phối — gọi các query object độc lập, bọc kết quả vào `TodaySectionResult` (mục 5), lắp thành `TodayWorkspaceViewModel` (mục 4).

**Nội bộ collaborator — đúng 4 query object, không hơn (đã bỏ `ActionRequiredQuery`):**

| Query object | Trách nhiệm | Dùng cho section |
|---|---|---|
| `OpenWorkReadQuery` (dùng chung với Workload/My Work) | Toàn bộ open Task+DesignItem của tenant | 6.1, 6.2 (lọc `status=in_progress`), 6.3 (lọc overdue/blocked), 6.6 (lọc theo `project_id` PM quản lý) |
| `UpcomingMilestoneQuery` | Milestone overdue/sắp tới của các project actor có liên quan | 6.4 |
| `UnreadUpdateQuery` | Notification chưa đọc của actor | 6.5 |
| `TeamExceptionQuery` | Tổng hợp theo thành viên cho project/team actor quản lý | 6.6 |

Không có query object nào cho Action Required ở MVP — không dựng interface/class rỗng chỉ để "giữ chỗ kiến trúc". Khi 1 nguồn Action Required đủ điều kiện xuất hiện trong tương lai (mục 7), lúc đó mới thêm 1 query object cụ thể cho đúng nguồn đó (VD `RfiEscalationActionQuery` nếu GAP-030 đóng), không thêm trước.

**Permission-aware navigation KHÔNG phải collaborator của `TodayWorkspaceReadService`** — đây là 1 hệ thống độc lập (`App\Support\Navigation\OperatorNavigationComposer`), dùng cho **toàn bộ layout operator** (mọi trang, không riêng Today), xem mục 6.7.

**Độ phân rã lỗi:** `TodayWorkspaceReadService::build()` gọi 4 query object trong try/catch riêng — 1 lỗi không ảnh hưởng 3 cái còn lại, không ảnh hưởng request (lỗi auth/tenant-resolution xảy ra ở middleware, trước `build()`).

Lý do không tạo bảng/ledger/rule-engine/materialized projection/background job: chưa có bằng chứng nào cho thấy read-model trực tiếp không đạt ngân sách hiệu năng (mục 10) — nếu phát sinh bằng chứng đó, phải dừng và báo cáo, không âm thầm mở rộng thiết kế.

## 4. View model

```php
final class TodayWorkspaceViewModel
{
    public function __construct(
        public readonly TodaySectionResult $personalOpenWork,
        public readonly TodaySectionResult $inProgress,
        public readonly TodaySectionResult $overdueAndBlocked,
        public readonly TodaySectionResult $upcomingMilestones,
        public readonly TodaySectionResult $unreadUpdates,
        public readonly ?TodaySectionResult $teamException, // null nếu actor không có vai trò PM/lead
    ) {}
}
```

Không có field `actionRequired` trong view model MVP — không có gì để render (mục 7 giải thích lý do và điều kiện gia nhập tương lai).

DTO item (immutable, không leak Eloquent model ra Blade):

```php
final class TodayMilestoneItem
{
    public function __construct(
        public readonly string $milestoneId,
        public readonly string $name,
        public readonly string $projectId,
        public readonly string $projectName,
        public readonly ?Carbon $targetDate,
        public readonly bool $isOverdue,
        public readonly string $status,
        public readonly string $url,
    ) {}
}

final class TodayNotificationItem
{
    public function __construct(
        public readonly string $notificationId,
        public readonly string $title,
        public readonly ?string $body,
        public readonly ?string $url,   // từ Notification.link_url — có thể null
        public readonly Carbon $createdAt,
    ) {}
}
```

## 5. Data trust / provenance (áp dụng nguyên tắc Dashboard Data Trust Guardrails, không mở rộng scope của spec đó)

`docs/superpowers/specs/2026-07-25-dashboard-data-trust-guardrails-design.md` đã định nghĩa mô hình 3 chiều cho đúng 6 widget cụ thể của nó — spec đó tự giới hạn phạm vi, không sửa/mở rộng ở đây. Today Workspace áp dụng cùng nguyên tắc bằng 1 struct riêng:

```php
enum TodayAvailability { case AVAILABLE; case NO_DATA; case ERROR; }
enum TodayReliability { case RELIABLE; case LIMITED; case UNKNOWN; }

final class TodaySectionResult
{
    public function __construct(
        public readonly array $items,
        public readonly TodayAvailability $availability,
        public readonly TodayReliability $reliability,
        public readonly ?string $explanation,
    ) {}
}
```

(Bỏ giá trị `NOT_APPLICABLE` khỏi enum so với rev 2 — giá trị đó chỉ tồn tại để mô tả Action Required "chưa có nguồn", nhưng Action Required không còn là 1 section runtime nên không cần giá trị này trong `TodayAvailability` của MVP; nếu thêm section có khái niệm "không áp dụng" trong tương lai, mở rộng enum lúc đó.)

Mỗi query object trả 1 `TodaySectionResult`, phân biệt "0 vì thật sự không có việc" (`AVAILABLE`, `items=[]`) với "0 vì 1 nguồn dữ liệu lỗi" (`ERROR`).

## 6. Section contracts (runtime MVP — 6 section)

### 6.1 Personal Open Work

- **Người dùng:** mọi user có `task.view`.
- **Nguồn:** `OpenWorkReadQuery::collect($tenantId)`, lọc `assignedTo === $actor->id`.
- **Trường nguồn:** `Task.assigned_to`, `Task.end_date`, `Task.blocked_at`, `Task.status`, `Task.priority`; `DesignItem.assigned_to`, `DesignItem.review_status`.
- **Tenant filter:** `tenant_id`, tường minh trong `OpenWorkReadQuery`; global scope liên quan `TenantScope`, không thay thế điều kiện tường minh.
- **Sắp xếp:** overdue trước, `end_date` gần nhất, `priority` (`critical > high > medium > low`), `id` tie-breaker. `DesignItem` xếp sau `Task`, theo `name` rồi `id`.
- **Dedup:** không áp dụng — `assigned_to` là SSOT duy nhất.
- **Giới hạn/pagination:** tối đa 20 item; link "Xem tất cả" → `/app/my-work`.
- **Route đích:** `route('app.tasks.show', $id)`; route DesignItem tương ứng.
- **Quyền:** `rbac:task.view` ở route `/app/today`.
- **Cross-tenant test:** item cùng `assigned_to` ở tenant khác không xuất hiện.
- **Empty state:** "Bạn chưa có việc nào đang mở."
- **Partial-data state:** nếu 1 trong 2 nguồn lỗi, phần còn lại vẫn hiển thị, `availability=ERROR` cho phần lỗi.
- **Loại trừ tường minh:** `TaskAssignment`/`assignedUsers`.

### 6.2 Đang thực hiện

- **Người dùng:** mọi user có `task.view`.
- **Nguồn:** `OpenWorkReadQuery::collect($tenantId)`, lọc `sourceType==='task' AND status===Task::STATUS_IN_PROGRESS AND assignedTo===actor.id`.
- **Sắp xếp:** (1) overdue trước, (2) `end_date` gần nhất (null cuối), (3) `priority` giảm dần, (4) `id` tie-breaker.
- **Không suy luận "1 task đang làm ngay":** hiển thị toàn bộ task `in_progress`, không đánh dấu "primary".
- **Trùng với 6.1 là có chủ đích** (6.2 là ống kính hẹp hơn) — test khẳng định rõ, không phải lỗi dedup.
- **Giới hạn:** 10 item. **Route đích:** `route('app.tasks.show', $id)`.
- **Hành động hiển thị (nếu workflow hỗ trợ):** chỉ link ra trang task hiện có đã hỗ trợ báo bị chặn/gửi kiểm tra/hoàn thành.
- **Quyền/tenant/empty/partial:** giống 6.1. **Loại trừ tường minh:** không timer/session.

### 6.3 Overdue and Blocked

- **Nguồn:** `OpenWorkReadQuery::collect($tenantId)`, lọc `assignedTo===actor.id AND (isOverdue OR isBlocked)`.
- **Sắp xếp:** overdue-và-blocked trước, overdue-only, blocked-only, sau đó `end_date`.
- **Giới hạn:** 20 item, link "Xem tất cả" → `/app/my-work`.
- **Blocker context:** `blockerNote` nếu có; `blockedBy` hiển thị **text thô, không link** (không FK tới `User`).
- **Quyền/tenant/empty/partial:** giống 6.1. **Loại trừ tường minh:** không dựng đồ thị "task A chặn task B".

### 6.4 Upcoming Milestones

- **Người dùng:** mọi user có ít nhất 1 open work item (qua `OpenWorkReadQuery.projectId`) hoặc là PM (`Project.pm_id===actor.id`).
- **Nguồn:** `ProjectMilestone` — `target_date`/`completed_date` (cast `date`), `status`.
- **Overdue tính live, không dựa cột `status` một mình:** `target_date < today AND completed_date IS NULL` (không dùng `scopeOverdue()` một mình vì nó chỉ khớp `status='overdue'` đã lưu qua `saving` hook, có thể lệch).
- **Lọc "project liên quan" (định nghĩa thu hẹp, xác định được):** `project_id IN (project_id từ OpenWorkReadQuery của actor) ∪ (Project.pm_id===actor.id)`. Không dùng `project_user_roles`/`project_team_members`/`project_teams`/`UserRoleProject` — các cơ chế này phân mảnh, không thống nhất, không có tiền lệ dùng trong repo (`WorkloadPageController` không dùng bất kỳ cái nào).
- **Tenant isolation — bắt buộc join tường minh:** `ProjectMilestone` không có `tenant_id`, không có `TenantScope` → `whereHas('project', fn($q) => $q->where('tenant_id', $tenantId))`.
- **Sắp xếp:** overdue trước, sau đó `target_date` gần nhất. **Giới hạn:** 10 item.
- **Route đích:** `route('app.projects.show', $projectId)` — route này xác nhận **không có** middleware `rbac:*`, chỉ `auth`+`TenantIsolationMiddleware`. Không có trang milestone-detail riêng.
- **Quyền:** không cần thêm ngoài `task.view` ở `/app/today`.
- **Empty state:** "Không có milestone nào sắp tới hoặc trễ cho các dự án bạn tham gia."
- **Loại trừ tường minh:** không hiển thị % hoàn thành milestone.

### 6.5 Unread Updates

- **Người dùng:** mọi user đăng nhập.
- **Nguồn:** `Notification` — `user_id`, `read_at`, `link_url`.
- **Tenant filter tường minh:** `Notification::where('tenant_id', $tenantId)->forUser($actor->id)->unread()`.
- **Không tự động là Action Required** — không có khái niệm Action Required nào để dedup vào ở MVP (mục 7); mọi notification chưa đọc hiển thị nguyên trạng, không lọc/ẩn bớt vì bất kỳ lý do "đã có action tương ứng".
- **Sắp xếp:** `created_at` mới nhất trước. **Giới hạn:** 10 item. **Không có link "Xem tất cả"** — xác nhận không tồn tại trang danh sách notification nào (`settings.notifications` chỉ redirect stub về `app.settings`). Mỗi item link riêng qua `link_url` (có thể null → hiển thị text, không link).
- **Quyền:** không cần permission riêng. **Empty state:** "Không có thông báo chưa đọc."
- **Loại trừ tường minh:** không gọi bất kỳ notification nào là "cần phản hồi"/"cần duyệt".

### 6.6 PM/Team Exceptions

- **Người dùng:** chỉ hiển thị nếu `Team.team_lead_id===actor.id` (≥1 team) hoặc `Project.pm_id===actor.id` (≥1 project) — `manager_id` không phải cột riêng, chỉ alias vào `pm_id`.
- **Nguồn:** `OpenWorkReadQuery` lọc `projectId IN (Project::where('pm_id', actor.id))`, cộng `Team::where('team_lead_id', actor.id)->with('activeMembers')`, cộng `UpcomingMilestoneQuery` mở rộng theo cùng tập `project_id`.
- **Tenant filter tường minh:** `Team::where('tenant_id', $tenantId)->where('team_lead_id', actor.id)`; `Project::where('tenant_id', $tenantId)->where('pm_id', actor.id)`.
- **Nhãn bắt buộc:** "Khối lượng công việc đã ghi nhận"/"Ngoại lệ vận hành" — không "Rảnh"/"Khả dụng X%"/"Quá tải X%".
- **Hiển thị:** open/overdue/blocked count theo thành viên, upcoming deadlines, milestone trễ. Không suy luận trạng thái con người.
- **Giới hạn:** tối đa 10 thành viên nhiều open item nhất, link "Xem đầy đủ" → `/app/workload`.
- **Quyền:** ẩn hoàn toàn (không render, không 403) nếu actor không thoả điều kiện — `teamException=null`.
- **Empty state:** "Không có thành viên nào có việc đang mở."
- **Loại trừ tường minh:** `budget_actual`; `TaskAssignment.assigned_hours`/`actual_hours`; các cơ chế "project participant" phân mảnh khác.

### 6.7 Permission-aware navigation

**Nguồn sự thật cho permission: route middleware thật, suy ra tự động — không phải bảng dữ liệu do implementation tự gán tay (chọn "Preferred mechanism" của review).**

```php
final class OperatorNavItem
{
    public function __construct(
        public readonly string $label,
        public readonly string $routeName,
        public readonly string $section,
    ) {}
    // KHÔNG có field `permission` tĩnh — permission được suy ra tại runtime từ route middleware, không lưu tay để tránh lệch khỏi route thật theo thời gian.
}

final class OperatorNavigationComposer
{
    public function __construct(private RouteCollection $routes) {}

    /** @return string[] danh sách permission code bắt buộc, rỗng nếu route không yêu cầu permission nào */
    public function requiredCapabilities(string $routeName): array
    {
        $route = $this->routes->getByName($routeName);
        if ($route === null) {
            // Route không tồn tại — fail closed, coi như "không permission nào thoả được", item luôn bị ẩn.
            return ['__unresolvable__'];
        }
        return collect($route->gatherMiddleware())
            ->filter(fn ($m) => str_starts_with($m, 'rbac:'))
            ->map(fn ($m) => substr($m, strlen('rbac:')))
            ->values()
            ->all();
    }

    public function visibleFor(User $actor): array
    {
        return collect(OperatorNavigationDefinition::items())
            ->filter(function (OperatorNavItem $item) use ($actor) {
                $capabilities = $this->requiredCapabilities($item->routeName);
                if ($capabilities === ['__unresolvable__']) {
                    return false; // fail closed
                }
                if ($capabilities === []) {
                    return true; // route xác nhận không có rbac:* — hiển thị cho mọi user tenant đã đăng nhập
                }
                return collect($capabilities)->every(fn ($cap) => $actor->hasPermission($cap)); // AND — khớp đúng cách middleware stack yêu cầu TẤT CẢ phải qua
            })
            ->groupBy(fn ($item) => $item->section)
            ->all(); // section không còn item nào bị loại bỏ hoàn toàn khỏi kết quả groupBy, Blade không render header rỗng
    }
}
```

**3 trường hợp đã xác minh bằng bằng chứng thật trong repo, không suy đoán:**

1. **Đúng 1 middleware `rbac:*`** (VD `routes/web.php` phần lớn route `/app/*` có dạng `->middleware('rbac:task.view')`) → permission bắt buộc = đúng chuỗi sau `rbac:` (xác nhận không route nào trong `routes/web.php`/`routes/api_zena.php` dùng tham số phụ dạng `rbac:permission,project_param` — `grep` không tìm thấy trường hợp nào có dấu phẩy trong chuỗi `rbac:` — nên toàn bộ phần sau `rbac:` luôn là permission code, không cần tách thêm).
2. **Nhiều middleware `rbac:*` trên cùng 1 route** — trường hợp thật đã xác nhận: `routes/web.php:981` (`design-items.suggest-description`) có cả `rbac:design-item.manage` và `rbac:ai.suggest`; `routes/web.php:1010-1011` tương tự cho CRM AI suggestion. Middleware stack của Laravel yêu cầu **tất cả** middleware phải pass mới vào được action — composer áp đúng ngữ nghĩa đó: actor phải có **toàn bộ** permission liệt kê (AND), không phải 1-trong-nhiều.
3. **Không có middleware `rbac:*` nào** — trường hợp thật đã xác nhận: `app.projects.show` (`routes/web.php:365`, chỉ `auth`+`TenantIsolationMiddleware`), `app.tasks.show` (`routes/web.php:396`, tương tự) → permission bắt buộc = mảng rỗng, item hiển thị cho mọi user tenant đã đăng nhập — khớp đúng thực tế route (không ẩn nav chặt hơn route thật cho phép).

**Route name không resolve được (`Route::getByName()` trả null — VD label bị gõ sai, hoặc route đã bị xoá nhưng nav definition chưa cập nhật):** **fail closed** — `requiredCapabilities()` trả giá trị sentinel `['__unresolvable__']`, `visibleFor()` luôn ẩn item đó, không bao giờ hiển thị 1 nav item không rõ nó dẫn tới đâu.

**Test bắt buộc:**
- `test_navigation_definition_routes_all_resolve` — build-time/test-time integrity check: lặp toàn bộ `OperatorNavigationDefinition::items()`, assert `Route::has($item->routeName)` đúng cho **mọi** item — bắt lỗi cấu hình (route bị xoá/đổi tên) ngay ở CI, trước khi 1 nav item "chết" âm thầm bị ẩn ở production mà không ai biết.
- `test_navigation_menu_for_employee_hides_admin_and_pm_only_items` — dùng permission thật của actor, đối chiếu với permission thật suy ra từ route middleware (không hardcode danh sách permission kỳ vọng tách biệt khỏi route — nếu route đổi middleware, test tự động phản ánh đúng, không cần sửa test tay).
- `test_navigation_menu_for_pm_shows_pm_scope_items` / `test_navigation_menu_for_admin_shows_all_items` — tương tự.
- `test_navigation_item_with_multiple_rbac_middleware_requires_all` — dùng route thật có 2 middleware `rbac:*` (VD `design-items.suggest-description`), actor chỉ có 1 trong 2 permission → item vẫn ẩn (AND, không phải OR).
- `test_unresolvable_navigation_route_fails_closed` — trỏ 1 `routeName` giả không tồn tại, assert item luôn ẩn với mọi actor kể cả super-admin.
- `test_hidden_navigation_does_not_weaken_route_rbac` — actor không đủ quyền gõ thẳng URL vẫn bị middleware chặn, bất kể nav ẩn hay hiện.

- **Route `/app/today`:** `GET /app/today`, tên `app.today`, middleware `rbac:task.view`.
- **Bất biến bắt buộc:** ẩn nav không thay route middleware.

## 7. Action Required — future extension contract (không implement ở MVP)

**Kết luận sau khi xác minh code: 0 nguồn trong repo hiện tại thoả đủ điều kiện actor/hành động/record/trạng thái/quyền/route xác định. Do đó Action Required không xuất hiện trong `TodayWorkspaceViewModel`, không có query object, không có UI, không có section trong Blade view của MVP.**

- **RFI escalation** — loại trừ vì `POST /api/zena/rfis/{id}/resolve-escalation` gate bằng `rbac:rfi.escalate` (`routes/api_zena.php:288`), nhưng permission này gate ai được TẠO escalation, không phải ai được RESOLVE — 1 `escalated_to` hợp lệ có thể không giữ `rfi.escalate` và bị middleware chặn trước khi tới được logic target-check đúng trong `RfiController::resolveEscalation()` (`app/Http/Controllers/Api/RfiController.php:513-567`). Đã đăng ký **GAP-030** (`OPERATIONAL_GAP_REGISTER.md:39`, "OPEN (verified) — intentionally deferred").
- **Document approval** — loại trừ vì `Document` không có cột approver nào; `DocumentController::approve()`/`reject()` ghi các field (`approved_by`, `approved_at`, `approval_note`) không nằm trong `$fillable` (bị mass-assignment bỏ qua âm thầm). Đã đăng ký **GAP-031** (`OPERATIONAL_GAP_REGISTER.md`, Tier 2).
- **Change Request / Submittal** — loại trừ vì GAP-012/GAP-013 (chưa có notification fan-out, không có cách xác định actor/state đáng tin).

**Điều kiện gia nhập Today trong tương lai (áp dụng cho bất kỳ nguồn Action Required nào, không riêng RFI):** một workflow chỉ được thêm vào Action Required khi đồng thời có:

1. Actor xác định được bằng 1 điều kiện truy vấn cụ thể (cột trực tiếp hoặc join xác định, không suy đoán).
2. Hành động xác định được (tên hành động cụ thể, không mơ hồ).
3. Trạng thái áp dụng được xác định (record đang ở trạng thái nào thì hành động này hợp lệ).
4. Permission bắt buộc ở route đích **khớp với chính xác** tập actor xác định ở (1) — không tạo item trỏ tới route mà actor xác định ở bước (1) có thể không vào được (đây chính là lỗi đã sửa của RFI escalation, không được lặp lại).
5. Route/destination xác định được, không cần Today tự thực hiện side-effect (Today chỉ link, không tự POST).

**Identity tương lai (giữ nguyên tên, chưa cần dùng ở MVP):** `source_type + source_id + action_code` — dùng làm khoá ổn định cho Blade key và cho quy tắc dedup với Unread Updates **khi và chỉ khi** có ít nhất 1 nguồn Action Required thật tồn tại. Không cần runtime dedup nào ở MVP vì không có gì để dedup — không dựng cơ chế dedup speculative chỉ để "sẵn sàng".

**Khi 1 gap đóng (VD GAP-030 được sửa theo đúng hướng đã ghi trong entry của nó):** thêm 1 query object cụ thể cho đúng nguồn đó (VD `RfiEscalationActionQuery`), thêm field `actionRequired` trở lại `TodayWorkspaceViewModel`, thêm UI section — đây là 1 lát cắt bổ sung riêng, có spec riêng, không phải sửa ngầm trong spec này.

## 8. Cập nhật `OPERATIONAL_GAP_REGISTER.md` — GAP-031 (giữ nguyên, đã đăng ký ở rev 2, không đổi)

`GAP-031` (Tier 2) vẫn được đăng ký nguyên trạng: `Document::$fillable` không có field approver; `DocumentController::approve()`/`reject()` ghi field không fillable, bị bỏ qua âm thầm; không có cách xác định approver cụ thể cho 1 Document. Không sửa Document implementation ở thiết kế này — chỉ loại trừ khỏi Today Action Required (mục 7).

## 9. Data trust — bảng tóm tắt provenance theo section (runtime MVP)

| Section | Đếm gì | Không đếm gì | Nguồn | Complete/Partial | 0 nghĩa là gì |
|---|---|---|---|---|---|
| Personal Open Work | Task+DesignItem mở, `assigned_to=actor` | `TaskAssignment` | `tasks`, `design_items` | Complete nếu cả 2 query OK | 0 = thật sự không có việc mở |
| Đang thực hiện | Task `in_progress`, `assigned_to=actor` | DesignItem | `tasks` | Complete | 0 = không có task đang in_progress |
| Overdue and Blocked | Subset của Personal Open Work | Task/DesignItem đã đóng | `tasks`, `design_items` | Complete nếu Personal Open Work complete | 0 = không có gì overdue/blocked |
| Upcoming Milestones | Milestone overdue/sắp tới của project actor có work hoặc là PM | Milestone project khác | `project_milestones` join `projects` | Complete nếu join tenant thành công | 0 = không có milestone trong khung 30 ngày/quá hạn |
| Unread Updates | `Notification.read_at IS NULL` của actor | Notification đã đọc | `notifications` | Complete | 0 = không có thông báo chưa đọc |
| PM/Team Exceptions | Open/overdue/blocked theo thành viên, project `pm_id=actor` hoặc team `team_lead_id=actor` | Capacity/leave/calendar | `tasks`, `design_items`, `project_milestones`, `teams`, `projects` | Complete nếu actor quản lý ≥1 team/project | Section không hiển thị nếu actor không quản lý gì |

(Không có dòng Action Required trong bảng này — section không tồn tại ở runtime MVP, xem mục 7.)

## 10. Error and degradation model

- **Lỗi 1 section:** try/catch quanh từng query object — section hiển thị "Không thể tải mục này lúc này.", log kèm `tenant_id`/`user_id`/`request_id`/tên section/exception. Section khác không bị ảnh hưởng.
- **1 module không khả dụng với actor:** section tự nhiên rỗng do query luôn giới hạn theo actor.
- **Không có dữ liệu đạt điều kiện:** empty state của từng section.
- **Record đích không còn tồn tại:** route đích tự xử lý 404.
- **Quyền đổi giữa lúc load và lúc click:** route đích re-check độc lập.
- **Lỗi request-level (auth/tenant-resolution) không biến thành empty section:** xảy ra ở middleware, trước khi `build()` chạy.

## 11. Performance

- **Ngân sách:** `PROJECT_CONSTITUTION.md` Appendix A.8 — "page p95 < 500ms (20-50 rows)"; single-sample `microtime()` + `assertLessThan(500, ...)` theo tiền lệ thật (`tests/Feature/ApiPerformanceTest.php:65-71`, `tests/Performance/DashboardPerformanceTest.php:196`).
- **Query budget:** ≤ 12 query cho `/app/today` (4 query object thật, phần lớn 1-2 query/object nhờ eager-load) — giảm từ ước tính rev 2 (≤15) vì không còn `ActionRequiredQuery` (dù object đó vốn không query gì, việc bỏ nó khỏi kiến trúc đơn giản hoá tổng thể).
- **Navigation composer:** `requiredCapabilities()` gọi `Route::getByName()`/`gatherMiddleware()` cho ~27 item mỗi request — đây là tra cứu route đã compile trong bộ nhớ (không phải query DB), chi phí không đáng kể, nhưng nên tính 1 lần/request (không gọi lại trong vòng lặp Blade) — `visibleFor()` trả kết quả đã lọc sẵn cho layout dùng 1 lần.
- **Eager-loading:** `with('project:id,tenant_id,name')` chọn cột tối thiểu.
- **Per-section limit:** 10-20 item/section.
- **Không thêm cache ở thiết kế này** — chưa có bằng chứng cần.

## 12. Security and authorization

- **Route middleware:** `GET /app/today` → `auth` + `rbac:task.view`.
- **Section-level permission:** mỗi query object tự giới hạn theo actor; PM/Team Exceptions kiểm tra vai trò tường minh trước khi build.
- **Record-level policy:** không cần Policy mới.
- **Tenant isolation — bảng tổng hợp:**

| Model | Cột tenant | Global scope | Điều kiện tường minh bắt buộc trong Today | Dùng ở section |
|---|---|---|---|---|
| `Task` | `tenant_id` | `TenantScope` | `->where('tenant_id', $tenantId)` trong `OpenWorkReadQuery` | 6.1, 6.2, 6.3, 6.6 |
| `DesignItem` | `tenant_id` | `TenantScope` | như trên | 6.1, 6.3 |
| `Notification` | `tenant_id` | `TenantScope` | `->where('tenant_id', $tenantId)` trong `UnreadUpdateQuery` | 6.5 |
| `Team` | `tenant_id` | `TenantScope` | `->where('tenant_id', $tenantId)` trong `TeamExceptionQuery` | 6.6 |
| `Project` | `tenant_id` | `TenantScope` | `->where('tenant_id', $tenantId)` trong `TeamExceptionQuery`/`UpcomingMilestoneQuery` | 6.4, 6.6 |
| `ProjectMilestone` | **không có** | **không có** | **Bắt buộc join** `whereHas('project', ...)` | 6.4, 6.6 |

(`Rfi` đã xác minh cơ chế tenant-scope ở rev 2 nhưng không còn xuất hiện trong bảng này vì không dùng ở bất kỳ query nào của MVP — Action Required đã bỏ hẳn, mục 7.)

- **Chống lộ thông tin qua count/empty-state/error:** mọi section chỉ query dữ liệu actor được phép thấy ngay từ đầu.
- **Navigation rendering:** mục 6.7 — fail-closed cho route không resolve được, AND cho nhiều `rbac:*`.
- **Cross-tenant test:** bắt buộc cho mọi section (mục 13).
- **Permission downgrade:** request tiếp theo bị middleware chặn bình thường.

## 13. Testing strategy

1. `test_personal_open_work_contains_only_assigned_to_actor`.
2. `test_cross_tenant_data_never_appears` — lặp cho từng section (Personal Open Work, Đang thực hiện, Overdue/Blocked, Upcoming Milestones, Unread Updates, PM/Team Exceptions).
3. `test_secondary_task_assignment_alone_does_not_place_task_in_personal_open_work`.
4. `test_multiple_in_progress_tasks_shown_without_claiming_one_is_active`.
5. `test_pm_exception_visible_only_to_pm_id_or_team_lead_id`.
6. `test_employee_cannot_see_pm_sections`.
7. `test_navigation_definition_routes_all_resolve` (mục 6.7).
8. `test_navigation_menu_for_employee_hides_admin_and_pm_only_items`.
9. `test_navigation_menu_for_pm_shows_pm_scope_items`.
10. `test_navigation_menu_for_admin_shows_all_items`.
11. `test_navigation_item_with_multiple_rbac_middleware_requires_all`.
12. `test_unresolvable_navigation_route_fails_closed`.
13. `test_hidden_navigation_does_not_weaken_route_rbac`.
14. `test_project_progress_percentage_absent`.
15. `test_financial_data_absent`.
16. `test_no_action_required_section_rendered` — response HTML/view-model không chứa bất kỳ marker "Action Required"/`actionRequired` nào — xác nhận quyết định mục 7 được thực thi, không phải bị quên.
17. `test_empty_state_per_section`.
18. `test_partial_section_failure_does_not_break_page`.
19. `test_no_n_plus_1_regression`.
20. `test_query_budget_bounded` — `assertLessThanOrEqual(12, count($queries))`.
21. `test_destination_links_are_tenant_scoped`.
22. `test_open_work_read_query_regression` — `MyWorkPageTest`/`WorkloadPageTest` chạy xanh nguyên trạng.
23. `test_project_milestone_tenant_isolation_via_project_join`.

## 14. Explicit non-goals

- Canonical Project Progress.
- Project financial reporting.
- Persistent Focus timer.
- Time entries và actual-hours analytics.
- Leave và attendance.
- Capacity percentage.
- Personal KPI.
- Predictive delay scoring.
- New Executive/Board role.
- ActionItem ledger.
- Multi-assignee unification (`TaskAssignment`/`assignedUsers`).
- Notification lifecycle repair cho GAP-012/GAP-013.
- Sửa RFI escalation permission (GAP-030).
- Sửa Document approval fillable/approver field (GAP-031).
- **Action Required runtime implementation** — 0 nguồn đủ điều kiện; contract giữ lại làm điều kiện gia nhập tương lai (mục 7), không implement ở MVP này.
- Full PM Command Center.
- Full Admin operating dashboard.
- Rà soát/gán chính xác toàn bộ 27 nav item vào `OperatorNavigationDefinition::items()` (cơ chế suy permission đã xác định 100% ở mục 6.7; việc liệt kê `label`+`routeName`+`section` cho từng item là công việc cơ học của implementation, không phải quyết định thiết kế mở — permission không cần liệt kê tay vì được suy tự động).

## Self-review (rev 3)

1. **Không có section Action Required nào được implement/render:** mục 4 (view model không có field `actionRequired`), mục 6 (chỉ 6 section runtime), mục 7 (tường minh "không implement ở MVP"), test #16 xác nhận trực tiếp.
2. **Điều kiện gia nhập Action Required tương lai vẫn được ghi tài liệu đầy đủ:** mục 7, 5 điều kiện cụ thể + identity `source_type+source_id+action_code` giữ nguyên tên.
3. **Không có `ActionRequiredQuery` speculative nào:** mục 3.2 liệt kê đúng 4 query object, nêu rõ lý do không dựng query rỗng "giữ chỗ".
4. **Navigation permission có nguồn sự thật xác định:** mục 6.7 — suy từ `Route::gatherMiddleware()` thật, không phải bảng permission do implementation tự đoán; 3 trường hợp (1 rbac, nhiều rbac, 0 rbac) đều có bằng chứng route thật trong repo.
5. **Route không resolve được → fail closed:** mục 6.7, sentinel `__unresolvable__`, test #12.
6. **Route RBAC vẫn bắt buộc:** mục 2, 6.7 (bất biến), test #13.
7. **Không có code implementation nào bị đổi** — chỉ sửa file spec này (GAP-031 đã đăng ký từ rev 2, không sửa thêm ở rev 3).
8. **Không quyết định sản phẩm nào khác bị mở lại:** landing page timing, financial exclusion, assignment SSOT, PM/Team labeling, Unread≠Action Required nguyên tắc — tất cả giữ nguyên như mục 2 đã liệt kê, chỉ Action Required (rev 1→2→3) và navigation (rev 1→2→3) là 2 điểm được sửa qua các vòng review, đúng phạm vi được yêu cầu sửa.

---

Today Workspace MVP design (rev 3) is written for final specification review.
