# Brainstorm: Không gian điều hành công việc theo vai trò ("Hôm nay")

Date: 2026-07-31 (revised sau vòng architectural/product review)
Status: brainstorm — chờ review kiến trúc/sản phẩm tiếp theo, chưa viết design spec hay implementation plan.

---

## 1. Executive summary

Hiện trạng: ZenaManage có RBAC đa vai trò thật (pivot `user_roles`), một `Team` model với `team_lead_id` thật, và 3 màn hình cá nhân/quản lý rời rạc đã production (`/app/dashboard`, `/app/my-work`, `/app/workload`). Không có "Hôm nay" hợp nhất, không có time-tracking, không có leave/attendance, không có KPI cá nhân.

Phát hiện nghiêm trọng nhất của vòng review này: **project progress không có một nguồn sự thật nào cả** — có ít nhất 7 nơi tính/ghi "tiến độ dự án" khác công thức nhau (`ProjectRepository::getProgress()` = completed/total task không trọng số; `CalculationService::calculateProjectProgress()` = trọng số theo `$task->weight`, nhưng cột `weight` **không tồn tại** trong bất kỳ migration nào — luôn fallback về `1`, tức thực chất không có trọng số; `Project::recalculateProgress()` = trọng số theo `planned_cost` của `Component`; `ProjectPhase` tính bằng `sum()` chứ không phải `avg()` của `progress_percent` — một công thức khả nghi). `progress` (trên `Project`) và `progress_percent` (trên `Component`/`ProjectTask`) là hai cột khác nhau trên hai model khác nhau, không phải alias của nhau. Đồng thời `Task.assigned_to` và `TaskAssignment.user_id` là hai quy ước tên cột khác nhau cho cùng khái niệm "ai được giao việc" trên hai bảng liên quan trực tiếp. Kết luận: **không được hiển thị bất kỳ con số % tiến độ dự án "canonical" nào trong Today MVP** cho tới khi có quyết định business chọn 1 công thức.

Cơ hội lớn nhất không đổi: ghép các màn hình/luồng đã có (my-work, workload, document-approvals, pending-approval của RFI/Submittal/CR) thành một trang tổng hợp theo vai trò, không cần bảng mới.

Xung đột navigation đã được đóng ở vòng review này (xem mục 2 và 6): route RBAC luôn là lớp bảo mật bắt buộc; navigation phải được render theo permission/capability để ẩn khu vực không liên quan; hiện trạng render link không điều kiện là một implementation convention hiện tại, không phải một yêu cầu sản phẩm đã chốt ngược lại yêu cầu ẩn menu.

Hướng đề xuất: giữ Approach C, nhưng bổ sung một ranh giới kiến trúc rõ ràng — `TodayWorkspaceQuery` (application read-model), controller chỉ gọi query object và render, không tự chứa logic tổng hợp. Today MVP hiển thị việc mở của tôi, Current Work (việc đang `in_progress`, không phải timer), overdue/blocked, "Action Required" chỉ gồm các item có actor/hành động/route xác định (tách biệt khỏi "Unread updates"), milestone sắp tới, và khối exception cho PM/trưởng nhóm — không hiển thị % hoàn thành dự án, không suy luận "rảnh/quá tải", không xây time-tracking bền vững.

---

## 2. Current-state evidence map

| Năng lực | Trạng thái | Bằng chứng | Có thể tái sử dụng | Ghi chú |
|---|---|---|---|---|
| Multi-role per user (RBAC) | EXISTING | `app/Models/User.php:93-98` `roles()` BelongsToMany; `app/Traits/HasRoles.php:90-108` `assignRole()` dùng `syncWithoutDetaching` | Có | `DashboardRoleBasedService::getRoleConfiguration($user->role)` (`app/Services/DashboardRoleBasedService.php:34-41`) chỉ đọc cột `role` string đơn — không dùng cho Today |
| Role/Permission engine | CONFLICT | 2 cây model song song: `app/Models/{Role,Permission,RolePermission}.php` và `src/RBAC/Models/{Role,Permission,RolePermission}.php`; `User` chỉ tham chiếu `App\Models\Role` | Một phần | Chưa rõ cây `src/RBAC` có còn dùng không — UNCERTAIN |
| Role naming | CONFLICT | `RoleSeeder.php:22-46` seed "System Admin"/"Project Manager"/"Project Member"; middleware/policy check `super_admin`/`admin`/`pm`/`project_manager` (`RoleBasedAccessControlMiddleware.php:135,192-270`; `UserPolicy.php:18,24`) | Không trực tiếp | Lệch casing/vocabulary — tiền lệ đã sửa 1 lần (`d4c764a2`), rủi ro tái diễn |
| Team leadership (trưởng nhóm) | EXISTING | `app/Models/Team.php:42,81,105-168,239` — `team_lead_id`, `teamLead()`, `leaders()`, `ROLE_LEAD`, `addMember()` | Có | Quan hệ thật, không chỉ permission string |
| Professional role taxonomy (Kiến trúc sư/Kỹ sư/...) | MISSING | `RoleSeeder` chỉ có 3 role RBAC; role hiển thị trên `profile.blade.php:16`/`team-content.blade.php:48` đọc cột `role` string tự do, không liên quan RBAC pivot (`docs/superpowers/specs/2026-07-20-my-work-page-design.md:10`) | Không | Cố tình deferred, brainstorm riêng trong tương lai |
| **Navigation hiding** | **QUYẾT ĐỊNH ĐÃ CHỐT (xem mục 6)** | `resources/views/layouts/operator.blade.php` hiện liệt kê `<a>` không điều kiện — đây là **implementation convention hiện tại**, được mô tả trong `docs/superpowers/specs/2026-07-16-knowledge-base-design.md:15`; không tìm thấy tài liệu canonical nào (constitution, gap register) *quy định* rằng nav không được ẩn theo quyền — đó là một lựa chọn triển khai của 1 spec trước, không phải nguyên tắc bất biến | Có | Route RBAC (`RoleBasedAccessControlMiddleware`) tiếp tục là lớp bảo mật bắt buộc, không đổi; việc ẩn nav là bổ sung ở lớp trình bày, không thay thế route authorization |
| Post-login landing | EXISTING nhưng không theo vai trò | `routes/web.php:31,359` → `AppController::dashboard()` (`app/Http/Controllers/Web/AppController.php:16-53`) — 1 dashboard chung mọi role | Có (làm nền) | Không có nhánh role trong controller/view |
| Role-based widget config | PARTIAL | `app/Services/DashboardRoleBasedService.php` (1059 dòng) định nghĩa widget theo role nhưng route chính `app.dashboard` không gọi service này | Có, cần nối dây | Xây sẵn nhưng chưa dùng ở nơi người dùng thật nhìn thấy |
| Cá nhân "Việc của tôi" | EXISTING | `routes/web.php:392`; `WorkloadPageController::myWork()`; `resources/views/app/my-work.blade.php` (spec `2026-07-20-my-work-page-design.md`) | Có, nền tảng chính cho MVP | Chỉ Task + DesignItem, lọc theo `assigned_to` |
| Quản lý "Khối lượng" (workload theo người) | EXISTING | `routes/web.php:387`; `WorkloadPageController::index()` (spec `2026-07-19-workload-view-design.md`) | Có | Không có ngưỡng quá tải (cố ý deferred — thiếu dữ liệu giờ công) |
| Hàng đợi phê duyệt hợp nhất (đa domain) | MISSING | `documents/approvals` chỉ cho Document (`DocumentController::approvals`, `routes/web.php:417`); RFI/Submittal/CR mỗi domain tự có review-flow riêng | Một phần (từng domain đã có) | Không có 1 điểm "cần tôi duyệt" tổng hợp |
| Approval model tổng quát | CONFLICT | `App\Models\Approval` (`app/Models/Approval.php:11-37`) chỉ gắn `work_instance_step_id`, không tái dùng cho RFI/Submittal/CR | Không | "Generic" chỉ trên danh nghĩa |
| Notification — action required vs unread | PARTIAL/MISSING | `Notification` model không có cờ phân biệt hành động (`app/Models/Notification.php:27-89`); GAP-012/GAP-013 xác nhận Change Request `apply()` và Submittal lifecycle **hoàn toàn không có** notification fan-out (`OPERATIONAL_GAP_REGISTER.md` Tier 3) | Một phần | Xem định nghĩa "action required" chốt ở mục 6 — GAP-012/013 nghĩa là action queue KHÔNG THỂ phủ đầy đủ CR/Submittal ở MVP |
| Blocker (chặn công việc) | PARTIAL | `blocked_at`/`blocker_note`/`blocked_by` trên Task/DesignItem, `blocked_by` là **string tự do, không phải FK** (`database/migrations/2026_07_13_100200_add_blocker_fields_to_tasks_and_design_items.php:11-16`) | Có, hạn chế | Không dựng được đồ thị "việc A chặn việc B" có cấu trúc |
| Escalation | PARTIAL | Chỉ có `RfiEscalation` (`app/Models/RfiEscalation.php:24-85`) — đặc thù RFI, không generic | Có (mẫu tham khảo) | Không tái dùng nguyên trạng cho domain khác |
| Milestone (kế hoạch vs thực tế) | EXISTING | `App\Models\ProjectMilestone` — `target_date` vs `completed_date`, auto-overdue (`app/Models/ProjectMilestone.php:17-127`) | Có | Không có liên kết milestone↔task dependency |
| Task dependency | PARTIAL | `TaskDependency` pivot đơn giản, không type/lag (`app/Models/TaskDependency.php:14-20`) | Có, hạn chế | Không nối với Milestone |
| **Assignment column semantics** | **CONFLICT (xác nhận)** | `Task.assigned_to` (`app/Models/Task.php:75,204,209,214`; migration `2025_09_17_043044_add_missing_fields_to_tasks_table.php:22`) vs `TaskAssignment.user_id` (`app/Models/TaskAssignment.php:23,39,188`; migrations `2025_09_14_160316`, `2025_09_16_082723`) — hai quy ước tên cột khác nhau cho "ai được giao việc" trên hai bảng liên quan trực tiếp. `DesignItem`, `change_requests`, `rfis`, `ncrs` đều dùng `assigned_to`; `support_tickets` có cả hai (`user_id`=creator, `assigned_to`=assignee) | Có, hạn chế | `TaskAssignment`/`assignedUsers` (`role`: assignee/reviewer/watcher, `split_percent`) tồn tại nhưng **không màn hình nào dùng** — my-work/workload đều lọc theo `Task.assigned_to` |
| Leave/Attendance/Business trip | MISSING | Tìm toàn bộ models/migrations cho Leave/Attendance/Vacation/TimeOff — 0 kết quả | Không | Không có cách biết "ai đang nghỉ hôm nay" |
| Calendar domain event (họp/khảo sát/nghỉ) | MISSING/CONFLICT | `CalendarEvent` chỉ là mirror lịch ngoài, không có `type` (leave/trip/meeting) (`app/Models/CalendarEvent.php:32-89`); không tìm thấy migration tạo bảng `calendar_events` — rủi ro bảng không tồn tại | Không | Cần xác minh riêng trước khi coi model này là nền tảng nào cả |
| Time tracking / Focus session | MISSING | Không có `TimeEntry`/`Timesheet`/session pause-resume; gần nhất là `TaskAssignment.started_at/completed_at` (1 cặp mốc, không phải phiên có pause) (`app/Models/TaskAssignment.php:27-32,264-286`) | Có (điểm khởi đầu hạn chế) | Không đủ cho "đếm thời gian thực tế"; không dùng cho Current Work (chỉ đọc trạng thái, không log phiên) |
| Workload/capacity tổng hợp | MISSING | Không có field capacity/workload rollup ở User/Team; chỉ có `assigned_hours`/`actual_hours` từng task | Không | Quyết định 2026-07-19 đã né ngưỡng quá tải vì lý do này — vẫn giữ nguyên |
| Audit log đủ cho KPI | PARTIAL | `AuditLog` model tồn tại nhưng chỉ được gọi thủ công ở 9 controller (`app/Services/ZenaAuditLogger.php:32-60`) | Một phần | Không đủ phủ để tính KPI toàn hệ thống; `DesignItemRevision`/`SubmittalRevision` chỉ 2 entity |
| Business KPI (doanh thu, pipeline) | EXISTING nhưng tài chính, không phải hiệu suất cá nhân | `app/Services/BusinessKpiService.php:19,40,55,104` | Có (không liên quan Today cá nhân) | Đừng nhầm với `KpiService` (mock/dead code, `docs/superpowers/specs/2026-07-09-zena-ops-roadmap-design.md:233`) |

### ⚠ Progress Integrity Gate (finding mức nghiêm trọng cao)

Xác minh trực tiếp trong code (không suy đoán từ tên hàm) cho thấy **ít nhất 7 nơi khác nhau** tính hoặc lưu "tiến độ dự án", với ít nhất 3 công thức không tương thích nhau:

1. `app/Repositories/ProjectRepository.php:464-474` `getProgress()` — completed/total task, **không trọng số**.
2. `app/Repositories/ProjectRepository.php:487-505` `calculateEstimatedCompletion()` — dùng lại tỉ lệ trên để dự đoán ngày hoàn thành.
3. `app/Services/CalculationService.php:17-47` `calculateProjectProgress()` — có vẻ trọng số theo `$task->weight ?? 1`, nhưng **`weight` không phải cột thật** (`grep -rn weight database/migrations/*.php` = 0 kết quả) → luôn fallback `1`, **thực chất không trọng số**. Hàm này ghi đè trực tiếp vào `Project.progress`.
4. `app/Services/CalculationService.php:81-124` `calculateProjectTimeline()` — một `progressPercentage` **khác hẳn**, tính theo thời gian đã trôi qua, không phải theo task.
5. `app/Models/Project.php:360-395` `recalculateProgress()` — trọng số theo `planned_cost` của `Component` gốc (không phải `weight`), ghi vào `Project.progress`, bắn event `Project.ProgressUpdated`.
6. `app/Models/Component.php:290-309` — rollup `progress_percent` con vào cha, cũng theo `planned_cost`.
7. `app/Models/ProjectPhase.php:109-119` — tính bằng `sum()` của `progress_percent` các task hiển thị, **không phải trung bình** — khả nghi/có thể là lỗi, không phải một công thức tiến độ hợp lệ về mặt toán học (tổng % có thể vượt 100%).

Đồng thời: `Project.progress` (migration `2025_09_15_041906`) và `Component.progress_percent`/`ProjectTask.progress_percent` (migration `2025_09_19_173121`) là **hai cột trên hai model khác nhau**, không phải cùng một trường đổi tên — không thể dùng lẫn cho nhau khi đọc dữ liệu.

**Hệ quả bắt buộc:** Today MVP không được hiển thị bất kỳ con số % hoàn thành dự án nào lấy từ các nguồn trên như thể nó là "canonical". Việc chọn 1 công thức chính thức là một **prerequisite/design task riêng** (xem mục 9 và 12), không phải điều để implementation tự chọn.

---

## 3. Current user journeys (chỉ dựa trên bằng chứng)

**Nhân viên:** Đăng nhập → hiện tại `/app/dashboard` (số liệu chung toàn tenant) → phải tự vào `/app/my-work` để thấy việc của mình (chỉ Task + DesignItem, không có RFI/Submittal/CR đang chờ họ, không có thông báo "cần hành động" vì 2 luồng CR/Submittal chưa fan-out thông báo — GAP-012/GAP-013).

**PM/Trưởng nhóm:** Đăng nhập → cùng `/app/dashboard` chung → có thể vào `/app/workload` để xem ai đang mở bao nhiêu việc (không có ngưỡng quá tải) → duyệt tài liệu qua `/documents/approvals`, nhưng RFI/Submittal/CR đang chờ duyệt phải vào từng module riêng.

**Admin:** Có thêm `/admin/dashboard` (gate `rbac:admin`) và một số view admin khác — một vài route admin cũ trỏ tới view không tồn tại (`GAP-016`). Hiện trạng nav không thu gọn theo quyền (người không có quyền vẫn thấy link, bị chặn khi bấm vào) — đây chính là điểm mà mục 6 dưới đây chốt phải sửa khi xây Today.

---

## 4. Gaps and operational problems

- **Thiếu giao diện:** không có trang "Hôm nay" hợp nhất; không có hàng đợi phê duyệt đa domain; không có UI cho `TaskAssignment` (responsible/support) dù dữ liệu đã có cấu trúc.
- **Thiếu dữ liệu:** Leave/Attendance/Business trip 0%; time-tracking phiên làm việc 0%; capacity rollup 0%; deadline-change reason ở cấp task 0%; cột `weight` dùng trong tính tiến độ không tồn tại trong schema.
- **Thiếu nghiệp vụ:** không có quy trình escalation/blocker tổng quát (chỉ RFI có); Approval model "generic" không thực sự dùng chung; không có 1 công thức tiến độ dự án chính thức.
- **Thiếu phân quyền (giao diện):** nav hiện chưa lọc theo permission — đây là nợ kỹ thuật cần đóng khi xây Today, không còn là xung đột chưa quyết định (xem mục 6).
- **Thiếu quy trình vận hành:** Change Request và Submittal lifecycle không thông báo ai khi có việc cần xử lý (GAP-012/GAP-013) — giới hạn trực tiếp phạm vi "Action Required" của Today MVP.
- **Thiếu audit/khả năng đo lường:** audit log phủ 9/rất-nhiều controller; revision count chỉ có ở 2 entity; 7 công thức tiến độ không tương thích — bất kỳ KPI/% dựa trên các số liệu này hiện tại đều không đáng tin.

---

## 5. Design approaches

**A — Aggregator đọc trực tiếp qua một read-model boundary (recommended base, đã bổ sung).** Một `TodayWorkspaceQuery` (application-layer query/read-model, không phải Eloquent model mới, không phải bảng mới) tổng hợp read-only trên các bảng đã có: tái dùng chính xác logic `WorkloadPageController::collectOpenItems()` (my-work), cộng 1-2 truy vấn "đang chờ tôi duyệt" gọi thẳng vào state hiện có của từng domain (RFI/Submittal/CR/Document). Web controller (`TodayController` hoặc tương đương) chỉ gọi `TodayWorkspaceQuery::build($user)` và render — không tự chứa logic tổng hợp.
- Ưu điểm: rủi ro thấp, tái dùng pattern đã kiểm chứng, và ranh giới query rõ ràng giúp test được độc lập với HTTP layer, tránh controller phình to (God controller) như đã từng xảy ra ở các domain khác trong repo.
- Nhược điểm: mỗi domain phê duyệt có schema khác nhau → query object phải biết về N domain (coupling nội bộ, nhưng bị cô lập trong 1 lớp, không rò vào controller/view).
- Rủi ro: nếu có domain thứ 5 cần "cần duyệt", phải sửa `TodayWorkspaceQuery`, nhưng chỉ 1 chỗ.

**B — Ledger "ActionItem" tổng quát.** Tạo 1 bảng canonical ghi mọi "việc cần tôi xử lý", được ghi bởi observer/event listener ở mọi domain.
- Nhược điểm: đụng write-path của 5+ domain cùng lúc — lặp lại đúng rủi ro đã từng xảy ra với 3-4 hệ WorkTemplate song song (nợ kiến trúc đã ghi nhận trong repo). Không làm ở MVP.

**C — Hybrid, khuyến nghị (giữ nguyên hướng, siết chặt ranh giới).** MVP = Approach A với `TodayWorkspaceQuery` là read-model boundary bắt buộc. Không xây Approach B ngay; chỉ cân nhắc sau nếu công việc đóng GAP-012/GAP-013 tạo ra write-path đáng để gộp vào 1 ledger.

**Khuyến nghị: Approach C**, với yêu cầu kiến trúc bổ sung: mọi truy vấn tổng hợp nằm trong `TodayWorkspaceQuery` (hoặc service tương đương), controller là lớp mỏng.

---

## 6. Recommended product model

**Kiến trúc đọc:** `TodayWorkspaceQuery` (application read-model/service) là ranh giới bắt buộc giữa controller và dữ liệu domain. Controller web (`TodayController::index()`) chỉ: xác thực user, gọi `TodayWorkspaceQuery::forUser($user)`, truyền kết quả cho view. Không có SQL/Eloquent query trực tiếp trong controller.

**Navigation & RBAC (đã chốt, không còn là open decision):**
- Route authorization/RBAC (`rbac:<permission>` middleware) luôn được giữ nguyên — là lớp bảo mật bắt buộc, không thể thay thế bằng việc ẩn nav.
- Navigation (sidebar/menu) được render theo permission/capability của user hiện tại — mục không liên quan đến vai trò/quyền của user thì không hiển thị, thay vì hiển thị rồi chặn khi bấm vào (403).
- Hiện trạng `operator.blade.php` render `<a>` không điều kiện là một **implementation convention hiện tại** của một spec trước (`2026-07-16-knowledge-base-design.md`), không phải nguyên tắc sản phẩm bất biến — không có bằng chứng canonical (constitution/gap register) nào cấm việc ẩn nav theo quyền. Khi xây Today, nav item mới (và lý tưởng là nav hiện có nói chung, nếu nằm trong phạm vi lát cắt) phải theo nguyên tắc permission-aware.

**Information architecture:** `/app/today` ("Hôm nay") trở thành **đích điều hướng mặc định sau đăng nhập** (route post-login redirect trỏ tới `/app/today` thay vì `/app/dashboard`). `/app/dashboard` không bị xoá, chỉ không còn là landing mặc định.

**Role-based composition:** cùng 1 read-model, nhánh section theo role thật của user (`roles()` đa vai trò, không dùng cột `role` đơn của `DashboardRoleBasedService`) — nhân viên thấy block cá nhân; nếu user có vai trò team-lead (`Team.team_lead_id`) hoặc PM (`Project.pm_id`/`manager_id`), thêm block exception tương ứng bên dưới, không phải trang riêng.

**"Hôm nay" — các block, theo đúng ranh giới dữ liệu đã xác minh:**
- **Current Work:** task/design-item chính đang `in_progress` của user (không phải toàn bộ danh sách) — cho phép mở lại, hiển thị deadline/blocker/project, và các hành động đã tồn tại trong workflow hiện có (báo bị chặn, gửi kiểm tra, hoàn thành) nếu domain đó hỗ trợ. Không có timer, không log phiên.
- **Overdue & Blocked:** tái dùng định nghĩa/logic my-work (`end_date < today`, `blocked_at` not null).
- **Action Required:** chỉ gồm item có actor xác định, hành động xác định, record đích xác định, trạng thái cho phép hành động, và route/workflow tiếp theo xác định (ví dụ: RFI cần user giải quyết escalation, document đang chờ user duyệt). **Không bao gồm** Change Request/Submittal cho tới khi GAP-012/GAP-013 được đóng (hiện chưa có actor/route xác định vì không có fan-out).
- **Unread Updates:** thông báo chưa đọc từ `Notification` (`read_at IS NULL`) — hiển thị tách biệt hoàn toàn khỏi Action Required, không gọi là "cần phản hồi".
- **Upcoming Milestones:** từ `ProjectMilestone`, `target_date` sắp tới/trễ.
- **PM/Team exception block:** với PM/trưởng nhóm — tổng hợp theo dự án/team họ quản lý, dùng cùng `TodayWorkspaceQuery` với tham số phạm vi rộng hơn.

**Project progress — không hiển thị số canonical:** Today MVP hiển thị `Project.status`, milestone deadline, open/overdue/blocked count, và nhãn schedule-risk/exception dựa trên dữ liệu xác định được (ví dụ: số milestone trễ). Nếu tái dùng bất kỳ field progress hiện có nào (vì lý do UI cần một con số), phải gắn nhãn rõ ràng "reliability/limitation" (ví dụ: "ước tính, chưa xác nhận công thức chính thức") — không trình bày như một chỉ số đáng tin.

**Assignment semantics:** `Task.assigned_to` là primary-assignee SSOT cho Today MVP (khớp với cách `/app/my-work` đang hoạt động thật). `TaskAssignment`/`assignedUsers` (multi-assignee, `role`: assignee/reviewer/watcher) tồn tại nhưng **không được** âm thầm trộn vào `TodayWorkspaceQuery` — support/secondary participant chưa tự động thấy task trong Today của họ ở MVP. Thống nhất primary/support/multi-assignee là một design slice riêng, sau MVP.

**Manager/PM workload semantics:** không dùng nhãn "Rảnh"/"Khả dụng X%"/"Quá tải X%" (không có capacity/leave/calendar data đáng tin). Chỉ hiển thị: open item count, overdue count, blocked count, upcoming deadlines, project participation — với chú thích rõ đây không phải capacity. Trạng thái con người (nghỉ/công tác) và trạng thái công việc (đang mở/trễ/chặn) không được trộn lẫn hay suy luận lẫn nhau.

**Admin overview:** không mở rộng phạm vi nhiệm vụ này — brainstorm riêng sau khi MVP nhân viên/PM chứng minh giá trị.

---

## 7. Role and visibility matrix

| Hành động | Nhân viên | Trưởng nhóm | PM | Admin | Ban giám đốc |
|---|---|---|---|---|---|
| View "Hôm nay" của chính mình (nav item hiển thị vì mọi user có quyền cơ bản) | ✓ | ✓ | ✓ | ✓ | UNCERTAIN (role chưa tồn tại trong `RoleSeeder`) |
| View việc của người khác trong team (nav/section chỉ hiện nếu user có vai trò tương ứng) | ✗ | ✓ (team mình, qua `Team.team_lead_id`) | ✓ (dự án mình quản lý) | ✓ (toàn tenant) | UNCERTAIN |
| Create/update trạng thái việc của mình | ✓ | ✓ | ✓ | ✓ | UNCERTAIN |
| Reassign việc cho người khác | ✗ (MISSING UI cho mọi role — chưa ai làm được qua UI hiện tại, không riêng gì Today) | | | | |
| Approve — chỉ item thoả điều kiện "Action Required" (actor/action/record/route xác định) | chỉ nếu được gán làm approver domain đó | như trên | như trên, theo phạm vi dự án | ✓ toàn tenant | UNCERTAIN |
| See financial/contract data (`BusinessKpiService`, `Project.budget_actual`) | ✗ — route RBAC + nav ẩn cùng lúc chặn | ✗ mặc định | UNCERTAIN — xem open decision #1 | ✓ | ✓ (nếu role tồn tại) |
| See project progress % "canonical" | Không ai thấy — không tồn tại số canonical ở MVP (mục 6) | | | | |

Cột "Ban giám đốc" vẫn UNCERTAIN toàn bộ vì role này không tồn tại trong `RoleSeeder` — quyết định không tạo role mới trong MVP đã chốt ở mục 9/11.

---

## 8. Domain and data implications

**Entity hiện có, tái dùng được nguyên trạng:** `Task` (dùng `assigned_to` làm SSOT), `DesignItem`, `ProjectMilestone`, `Team` (`team_lead_id`), `Notification` (cho Unread Updates), `RfiEscalation` (mẫu Action Required tham khảo), `AuditLog` (một phần).

**Không dùng làm nền tảng tính toán ở MVP (do phát hiện ở Progress Integrity Gate):** `Project.progress`, `Component.progress_percent`, `ProjectTask.progress_percent`, `ProjectRepository::getProgress()`, `CalculationService::calculateProjectProgress()`/`calculateProjectTimeline()`, `ProjectPhase` progress-sum — tất cả để nguyên, không sửa, không chọn 1 cái làm canonical trong lát cắt MVP.

**Entity cần mở rộng (không phải bảng mới, ngoài MVP):**
- `Notification`: cần thêm phân biệt tường minh action-required vs informational (field hoặc convention) — hiện MVP tạm phân biệt bằng logic ở `TodayWorkspaceQuery` (whitelist domain có actor/route xác định), chưa cần sửa schema `Notification`.
- `CalendarEvent`: cần xác minh bảng có tồn tại thật không trước khi dùng làm bất kỳ nền tảng nào (không dùng ở MVP).

**Entity có thể cần mới (không làm ở MVP):** Leave/Attendance model, ActionItem ledger tổng quát (chỉ nếu Approach B kích hoạt sau này), professional role taxonomy, cột `weight` thật (nếu công thức progress tương lai cần).

**Prerequisite design task riêng (không phải MVP, không phải "open decision" để code tự chọn):** xác lập 1 công thức Project Progress chính thức — cần quyết định business về trọng số (theo cost, theo milestone, theo thời gian, hay theo task-count), sau đó mới có một field/API duy nhất mà Today (và mọi nơi khác) có thể hiển thị như con số đáng tin.

**Dữ liệu cần cho cảnh báo/KPI nhưng hiện chưa đủ:** revision count (chỉ 2 entity), thời gian dự kiến vs thực tế (không có time tracking), tỷ lệ đúng hạn cấp cá nhân (audit không đủ phủ).

Đây chưa phải migration plan — chỉ liệt kê hàm ý dữ liệu.

---

## 9. MVP recommendation

**Trong MVP (Today Workspace):**
1. Personal open work (tái dùng logic my-work, `Task.assigned_to` là SSOT).
2. Current Work — task/design-item chính đang `in_progress`, có thể mở lại, không phải timer.
3. Overdue and blocked work.
4. Deterministic Action Required (actor/action/record/route xác định; KHÔNG bao gồm CR/Submittal cho tới khi GAP-012/013 đóng).
5. Upcoming milestones (từ `ProjectMilestone`).
6. Unread updates — khu riêng biệt, không gọi là "cần phản hồi".
7. PM/team exception blocks (theo dự án/team quản lý).
8. Permission-aware navigation (nav item Today + mọi mục liên quan chỉ hiện khi user có quyền).
9. Tenant isolation và role visibility (mọi truy vấn trong `TodayWorkspaceQuery` scope theo tenant + role, theo đúng pattern `WorkloadPageController` đã kiểm chứng).
10. Query/performance budget — `TodayWorkspaceQuery` dùng tenant-scoped eager-load, không N+1, có ngân sách số lượng query rõ ràng khi thiết kế chi tiết.

**Chủ động CHƯA làm ở MVP:**
- Project completion percentage canonical (chờ prerequisite ở mục 8).
- Persistent Focus timer / session log / pause reason / actual-time analytics.
- Time tracking nói chung.
- Leave/Attendance.
- Capacity percentage / nhãn "rảnh"/"quá tải".
- KPI cá nhân đa chiều.
- Predictive delay scoring.
- Role RBAC mới "Ban giám đốc".
- ActionItem ledger tổng quát (Approach B).

---

## 10. Risks and constraints

- **Progress Integrity:** 7 công thức tiến độ không tương thích đang tồn tại trong code, `weight` không phải cột thật, `progress` ≠ `progress_percent` giữa các model — rủi ro **đã xảy ra thật**, không phải giả thuyết; Today không được hiển thị số nào trong nhóm này như canonical.
- **Assignment ambiguity:** `Task.assigned_to` vs `TaskAssignment.user_id` là 2 quy ước song song; nếu `TodayWorkspaceQuery` vô tình trộn cả hai nguồn sẽ tạo dữ liệu trùng/sai người.
- **Query boundary erosion:** nếu không giữ kỷ luật `TodayWorkspaceQuery` là nơi duy nhất chứa logic tổng hợp, controller có nguy cơ phình to lặp lại vấn đề đã thấy ở các domain khác trong repo.
- **Dữ liệu chưa đầy đủ:** leave/time-tracking/capacity = 0%, giới hạn mạnh những gì Today có thể hiển thị đáng tin cậy.
- **Time-tracking gây phản cảm:** Current Work không được biến tướng thành theo dõi theo phút — chỉ đọc trạng thái `in_progress`, không log phiên.
- **KPI bị chơi số / chưa đủ nền tảng đo:** audit log phủ không đều, revision count chỉ 2 entity.
- **Nhầm lẫn trạng thái nhân viên vs trạng thái công việc:** không có dữ liệu nghỉ phép — không suy luận "không có Current Work = đang rảnh" hay "không task mở = đang nghỉ".
- **Action Required vs Unread bị trộn lẫn:** phải giữ kỷ luật whitelist domain có actor/route xác định cho Action Required; không tự động coi mọi notification chưa đọc là cần hành động.
- **Hiệu năng truy vấn:** nguy cơ N+1 khi hợp nhất nhiều domain — theo đúng pattern tenant-scoped eager-load đã dùng ở `WorkloadPageController`.
- **Quyền riêng tư:** dữ liệu tài chính (`BusinessKpiService`, `Project.budget_actual`) không được lọt vào Today của nhân viên qua `TodayWorkspaceQuery` dùng chung — filter theo role tường minh trong query, không dựa vào việc ẩn nav.
- **Mobile responsiveness:** không có bằng chứng trực tiếp được kiểm tra — UNCERTAIN, cần kiểm tra riêng layout `operator.blade.php`.

---

## 11. Open decisions

Chỉ còn 2 quyết định thực sự không thể kết luận từ code hoặc từ yêu cầu sản phẩm đã có trong nhiệm vụ này:

1. **Dữ liệu tài chính cấp dự án cho PM:** PM Today view có nên hiển thị `Project.budget_actual`/số liệu tài chính dự án hay tuyệt đối không hiển thị bất kể vai trò? (Đây là quyết định chính sách bảo mật/kinh doanh, không suy ra được từ RBAC hiện có vì chưa có policy nào gate cụ thể trường này cho PM.)
2. **Phạm vi Action Required khi ship:** Today MVP có nên ship ngay với Action Required chỉ phủ các domain đã có actor/route xác định (RFI escalation, Document approval), tự nhận CR/Submittal đang thiếu do GAP-012/GAP-013 — hay việc đóng GAP-012/GAP-013 phải là điều kiện tiên quyết trước khi ship mục này? (Đây là quyết định trình tự release, phụ thuộc ưu tiên roadmap ngoài phạm vi brainstorm này.)

Mọi mục khác trước đây liệt kê ở open decisions (navigation hiding, Today làm landing page, role "Ban giám đốc", công thức progress, Leave/Attendance, Focus timer) đã được chốt ở các mục 6/8/9 và không còn là quyết định bỏ ngỏ.

---

## 12. Recommended next design slice

**Today Workspace MVP — role-aware read model and action-oriented composition**

Lát cắt phải bao gồm:
- Query/read-model boundary: `TodayWorkspaceQuery` (hoặc tên tương đương), tách khỏi controller.
- Section contracts: hợp đồng dữ liệu rõ ràng cho từng block (Current Work, Overdue/Blocked, Action Required, Unread Updates, Upcoming Milestones, PM/team exception).
- Role/capability composition: cách mỗi block bật/tắt theo role thật (đa vai trò) của user.
- Data provenance cho từng section: field/bảng nguồn cụ thể, đặc biệt xác nhận KHÔNG dùng bất kỳ nguồn nào trong nhóm Progress Integrity Gate.
- Empty/no-data/error states cho từng block.
- Tenant isolation cho mọi query trong read-model.
- Navigation visibility: nav item Today + permission-aware rendering.
- Performance/query budget: số lượng query tối đa, chiến lược eager-load.
- Test strategy: theo pattern `WorkloadPageTest`/`MyWorkPageTest` đã có (bao gồm non-render assertions, cross-tenant isolation, permission 403/nav-hidden).
- Explicit non-goals: liệt kê lại đúng danh sách "chủ động chưa làm" ở mục 9.

Không mở rộng sang full KPI, full resource planning, hoặc full Focus/time-tracking.

---

Revised brainstorm report is ready for final architectural and product review. No implementation changes were made.
