# Operational Gap Register

*Bắt buộc bởi `PROJECT_CONSTITUTION.md` mục 4 (Operational Gap Detection). Đây là nơi hợp nhất mọi lỗ thủng vận hành đã phát hiện — không tự động sửa, chỉ ghi nhận, chấm điểm, và xếp thứ tự theo mục 5 (Priority Rule).*

*Tạo lần đầu: 2026-07-23, hợp nhất từ 14 file trong `docs/audits/*` (2026-03-19 → 2026-07-09) và `docs/roadmap/backlog.yaml`. Đã spot-check lại tình trạng hiện tại (route/file/controller) cho các mục có khả năng đã fix, thời điểm 2026-07-23 — repo HEAD nhánh `main`.*

## Cách đọc bảng này

- **Priority tier** = số theo mục 5 hiến pháp (1 = quan trọng nhất). Bảng được nhóm theo tier.
- **Status**:
  - `OPEN (verified)` — đã kiểm tra lại code hôm nay, xác nhận vẫn tồn tại.
  - `RESOLVED (verified)` — đã kiểm tra lại, xác nhận đã fix từ khi audit gốc viết; giữ trong register làm lịch sử, không cần hành động.
  - `UNVERIFIED` — chưa re-check trong lần hợp nhất này; coi như `ASSUMPTION: có thể vẫn còn`, cần verify trước khi tin hoặc hành động theo mục 3 hiến pháp.
  - `BLOCKED (external)` — không thể đóng chỉ bằng code, cần xác nhận từ người ngoài repo (vd Slack admin).
- **Mọi dòng đều có nguồn trích dẫn file:line** theo mục 8 hiến pháp — không có claim không bằng chứng.

---

## Tier 1 — Tính toàn vẹn dữ liệu & Tenant Isolation

| ID | Tiêu đề | Status | Bằng chứng | Ghi chú / hành động đề xuất |
|---|---|---|---|---|
| GAP-001 | Cross-tenant IDOR trong Web `TaskController`/`DocumentController` (đợt migrate G7) | UNVERIFIED (audit nói đã vá 2026-07-09) | `docs/audits/2026-07-09-acceptance-review.md:33` | `show/edit`/`show/approvals/create` từng thiếu tenant scope, ghi nhận "đã tenant-scope toàn bộ". Cần 1 test cross-tenant xác nhận không hồi quy trước khi coi là đóng vĩnh viễn. |
| GAP-002 | Route gốc `/projects`, `/tasks`, `/documents` là closure JSON song song với `/app/*` chính danh — `POST /tasks` trả `{"message":"Task created"}` **mà không lưu DB thật** | **RESOLVED (verified)** | `routes/web.php:484,510,519,529,545,549,553` | Đây là bẫy toàn vẹn dữ liệu nghiêm trọng: người dùng/gọi API tưởng đã tạo task nhưng không có gì được ghi. Không phải chỉ là "dead code" — nó **trả response thành công giả**. Đề xuất: xoá các route/closure này hoặc redirect về `/app/*` thật, có test chặn hồi quy. Đã sửa 2026-07-23: `POST /tasks` nay delegate sang `Web\TaskController::store` (giống `/app/tasks`), có `rbac:task.create`. Xem `tests/Feature/Legacy/LegacyTaskCreationPersistsTest.php`. Lưu ý: `POST /projects`, `PUT/DELETE /projects/{project}`, `GET /projects/{project}` root-level vẫn còn trùng lặp kiến trúc (không fake, nhưng vẫn nên dọn sau) — chưa nằm trong phạm vi sửa lần này. |
| GAP-003 | Provenance bảng `zena_submittals` vs `submittals` chưa rõ ràng (alias runtime trỏ khác migration tạo) | UNVERIFIED | `docs/audits/2026-03-19-system-review-roadmap-repair.md:151` | Submittals gate phê duyệt vật tư/procurement — dữ liệu sai bảng ảnh hưởng cost-reconciliation (tier 5) lẫn toàn vẹn dữ liệu. Cần trace toàn bộ migration + model trước khi động vào bảng này. |

## Tier 2 — Bảo mật, phân quyền, auditability

| ID | Tiêu đề | Status | Bằng chứng | Ghi chú / hành động đề xuất |
|---|---|---|---|---|
| GAP-004 | Viewer role có thể tạo task qua `POST /app/tasks` do thiếu `rbac:task.create` | UNVERIFIED (audit nói đã vá 2026-07-09) | `docs/audits/2026-07-09-acceptance-review.md:38-41` | Đã vá 5 route + có regression test theo audit gốc; PR#220 (22-23/07) tiếp tục hardening RBAC nên rủi ro hồi quy thấp — vẫn nên verify lại nếu động vào các route này. |
| GAP-005 | SSRF qua webhook URL (cloud metadata / mạng nội bộ, kể cả DNS rebinding) | UNVERIFIED (audit nói đã vá) | `docs/audits/2026-07-09-acceptance-review.md:23` | Vá 2 lớp (create-time + DNS-resolve-time). Cần test cụ thể nếu có ai sửa lại luồng webhook. |
| GAP-006 | Race condition tạo trùng daily log → lỗi 500 (TOCTOU) | UNVERIFIED (audit nói đã vá) | `docs/audits/2026-07-09-acceptance-review.md:24` | Vá bằng unique constraint DB + catch QueryException 23000 → 422. |
| GAP-007 | Webhook retry đếm trùng lần thất bại (4 lần thay vì đúng số cấu hình) | UNVERIFIED (audit nói đã vá) | `docs/audits/2026-07-09-acceptance-review.md:25` | Vá bằng guard `attempts < tries`. |
| GAP-008 | LIKE-filter injection trong tìm kiếm activity feed (`%`/`_` chưa escape) | UNVERIFIED (audit nói đã vá) | `docs/audits/2026-07-09-acceptance-review.md:26` | |
| GAP-009 | Tạo API token không có rate limit | UNVERIFIED (audit nói đã vá) | `docs/audits/2026-07-09-acceptance-review.md:27` | Vá bằng `throttle:6,1`. |
| GAP-010 | Cụm lỗi nhỏ: CSV formula injection, lộ secret qua flash message, OOM khi export, lệch timezone Gantt | UNVERIFIED (audit nói đã vá toàn bộ) | `docs/audits/2026-07-09-acceptance-review.md:28` | |
| GAP-011 | 21 route `/_debug/*` vẫn tồn tại kể cả login-bypass bằng credential cứng | **OPEN (verified)** cho non-prod / **RESOLVED (verified)** cho prod | `routes/web.php` (18 chỗ có `_debug`), `app/Http/Middleware/DebugGateMiddleware.php:16-24` | `DebugGateMiddleware` chặn 404 ngoài `local/testing/development` — an toàn ở prod. Nhưng vẫn là bề mặt gây nhầm lẫn ở non-prod, và không có test bất biến chống drift (xem GAP-024). |

## Tier 3 — Workflow cốt lõi tạo doanh thu / thu tiền

| ID | Tiêu đề | Status | Bằng chứng | Ghi chú / hành động đề xuất |
|---|---|---|---|---|
| GAP-012 | Change Request `apply()` không gửi bất kỳ thông báo nào cho bên liên quan | OPEN (tự khai trong backlog) | `docs/roadmap/backlog.yaml:296` (S3.2a) | Story đã đánh dấu `done` nhưng cố tình chừa gap này — người phê duyệt/PM không biết CR đã áp dụng trừ khi tự vào xem. Đây là workflow dead-end đúng nghĩa mục 4 hiến pháp. |
| GAP-013 | Submittal (vật tư) không có fan-out thông báo khi submit/review/approve/reject | OPEN (tự khai trong backlog) | `docs/roadmap/backlog.yaml:388` (S4.3) | Ảnh hưởng trực tiếp tốc độ phê duyệt vật tư → tốc độ thi công/thu tiền theo mốc. |

## Tier 4 — Tiến độ, trách nhiệm, cảnh báo

| ID | Tiêu đề | Status | Bằng chứng | Ghi chú / hành động đề xuất |
|---|---|---|---|---|
| GAP-014 | NCR/CAPA: dashboard và ngữ nghĩa thông báo còn deferred; liên kết NCR↔task lưu trữ lâu dài còn UNKNOWN | UNVERIFIED (một phần có thể đã đóng) | `docs/roadmap/backlog.yaml:461` (S5.2) | S5.3 (escalation rules) đã `done` (2026-07-07) nên phần escalation có thể đã xong — nhưng **dashboard** và **reverse-link storage** chưa có bằng chứng đóng. Cần verify riêng từng phần trước khi coi cả cụm là đóng. |
| GAP-015 | Không có UI/screen owner đã xác nhận cho toàn bộ engine WorkTemplate → WorkInstance (được audit gọi là "hệ con mạnh nhất" của hệ thống) | OPEN (tự khai là ngoài phạm vi MVP) | `docs/audits/2026-04-04-s6-4-project-template-walkthrough-coverage.md:119-121`, `docs/roadmap/backlog.yaml:553-556` (S6.4) | Backend/API/DB đã chứng minh hoạt động nhưng người dùng thực tế không có màn hình để dùng — dữ liệu được lưu nhưng không tạo ra quyết định quản trị (đúng dấu hiệu mục 4). Đây là gap **chiến lược**, không phải bug — cần quyết định business trước khi làm UI, không tự ý code thêm. |

## Tier 5 — Chi phí, lợi nhuận, đối soát

*(Không có gap riêng biệt ngoài GAP-003 ở Tier 1, vốn cũng ảnh hưởng đối soát vật tư/procurement — xem ghi chú tại đó. Không tìm thấy gap thuần cost/profit trong các audit hiện có; đây là điểm mù cần audit riêng nếu ưu tiên goal #2/#5 của ZENA vision.)*

## Tier 6 — Trải nghiệm người dùng (route/view chết hoặc gây nhầm lẫn)

| ID | Tiêu đề | Status | Bằng chứng | Ghi chú / hành động đề xuất |
|---|---|---|---|---|
| GAP-016 | Route `/admin-dashboard-enhanced` và `/projects-enhanced` trỏ tới view không tồn tại | **OPEN (verified)** | `routes/web.php:227-234`; view thiếu tại `resources/views/admin/dashboard-enhanced.blade.php`, `resources/views/app/projects-enhanced.blade.php` | Người dùng chạm route này sẽ gặp lỗi view-not-found. Đề xuất: xoá route (delete-candidate theo audit gốc). |
| GAP-017 | Link "invitation expired" trỏ view `invitations.expired` không tồn tại | **OPEN (verified)** | `app/Http/Controllers/Web/InvitationController.php:152`; `resources/views/invitations/` chỉ có `accept/create/manage/index.blade.php` | Người dùng bấm link mời hết hạn sẽ gặp lỗi 500 thay vì trang thông báo hợp lý — real user-facing dead-end, ưu tiên cao hơn các gap UX khác vì chạm trực tiếp workflow mời thành viên. |
| GAP-018 | 4 component "smart tools" (`smart-search`, `smart-filters`, `analysis-drawer`, `export-component`) + `test-smart-tools.blade.php` gọi sai prefix API (`/api/universal-frame/*` thay vì `/api/v1/universal-frame/*`), một số endpoint không tồn tại luôn | **OPEN (verified)** | Cả 5 file còn tồn tại tại `resources/views/components/*` và `resources/views/test-smart-tools.blade.php` | Không route nào mount tới các file này — an toàn nhưng là rác kiến trúc, dễ gây nhầm cho người sửa sau. Đề xuất: archive/xoá. |
| GAP-019 | `layouts/universal-frame.blade.php` (shell đầy đủ 8 component) không có route owner chính danh nào | **OPEN (verified)** | File còn tồn tại `resources/views/layouts/universal-frame.blade.php` | Đã có quyết định "giữ làm debug/demo shell, không hồi sinh nếu chưa có vòng xác định owner" — hợp lệ, giữ nguyên trạng thái này. |
| GAP-020 | `admin/users/index.blade.php` và `admin/tenants/index.blade.php` là file mồ côi — route thật render file khác (`admin.users`, `admin.tenants`) | **OPEN (verified)** | Cả hai file còn tồn tại `resources/views/admin/{users,tenants}/index.blade.php` | Rủi ro cao: tên file theo đúng convention Laravel nên người sửa sau dễ tưởng đây là file đang chạy thật. Đề xuất: archive kèm comment rõ, hoặc xoá. |
| GAP-021 | `/api/v1/tasks*` (compatibility) và `/api/zena/tasks*` (canonical) có 3 khác biệt: envelope response, middleware (`input.sanitization`+`error.envelope` thiếu ở compatibility), tên permission RBAC (`task.update` vs `task.edit`) | UNVERIFIED (audit tự nhận "contributor confusion: high") | `docs/audits/2026-03-19-tasks-contract-parity-audit.md:176-183`, `docs/audits/2026-03-19-tasks-v1-split-owner-route-inventory-audit.md:150-159` | 4 controller owner khác nhau cho cùng một khái niệm "task assignment" — nợ kỹ thuật thật, không phải lỗi tenant/security. Không khẩn nhưng nên gộp về 1 owner khi có dịp chạm vào task API. |

*(GAP-022, GAP-023, GAP-024 dưới đây đã xác nhận **RESOLVED** — giữ lại làm lịch sử theo mục 8, không cần hành động.)*

| ID | Tiêu đề | Status | Bằng chứng |
|---|---|---|---|
| GAP-022 | `/templates*` từng trỏ view không tồn tại | **RESOLVED (verified)** | `routes/web.php:433-435` có comment "Route /templates* đã gỡ 22/07: trang demo giả hoàn toàn chết" — khớp PR#218/#219 |
| GAP-023 | 3 route settings con (`general`/`security`/`notifications`) từng trỏ view không tồn tại | **RESOLVED (verified)** | `routes/web.php:441-449` nay `redirect()->route('app.settings')` thay vì render view chết |
| GAP-024 | `/app/projects/{project}` từng trả JSON thay vì HTML dù có view Blade sẵn | **RESOLVED (verified)** | `app/Http/Controllers/Web/ProjectController.php:139` nay khai báo `: View`, không còn `JsonResponse` |
| GAP-025 | 3 file demo tĩnh công khai (`api-demo.html`, `logo-test.html`, `projects-dashboard-test.html`) | **RESOLVED (verified)** | Cả 3 file xác nhận không còn tồn tại trong `public/` |

## Tier 7 — Tự động hoá & tối ưu nâng cao

| ID | Tiêu đề | Status | Bằng chứng | Ghi chú / hành động đề xuất |
|---|---|---|---|---|
| GAP-026 | `production.yml` vẫn dùng `secrets.SLACK_WEBHOOK_URL` không rõ kênh đích, trong khi 2 workflow khác đã chuyển sang secret theo kênh riêng | **BLOCKED (external)** | `.github/workflows/production.yml:153-182` vs `.github/workflows/automated-deployment.yml:26,125-682`, `.github/workflows/release-management.yml:29,302-386` | Không thể đóng chỉ bằng code — cần người quản trị Slack xác nhận secret hiện trỏ kênh nào trước khi sửa `production.yml`, nếu không có nguy cơ đổi route cảnh báo prod mà không ai biết. Nguồn thẩm quyền nhất trong 3 audit Slack: `docs/audits/slack-routing-secret-inventory.md` (2 file kia đã cũ hơn/stale). |
| GAP-027 | Không có test bất biến so khớp route `/_debug/*` đang mount với inventory snapshot | UNVERIFIED | `docs/audits/2026-03-19-debug-route-inventory.md:66,71` | Ưu tiên thấp; liên quan GAP-011. |

## Tier 8 — Trang trí / giá trị vận hành thấp

| ID | Tiêu đề | Status | Bằng chứng | Ghi chú / hành động đề xuất |
|---|---|---|---|---|
| GAP-028 | `README.md` và `SYSTEM_DOCUMENTATION.md` mô tả kiến trúc đã lỗi thời (Vue 3, microservices, universal-frame là UI chính) | UNVERIFIED (chưa re-check kể từ 2026-03-19) | `docs/audits/2026-03-19-system-review-roadmap-repair.md:126,128,143-146` | Rủi ro: gây hiểu sai cho người mới. Không khẩn cấp nghiệp vụ nhưng nên `git log --follow README.md` để verify trước khi xếp lịch sửa. |

---

## Đã hợp nhất từ (nguồn)

`docs/audits/2026-03-19-blade-ownership-reachability-audit.md`, `2026-03-19-debug-route-inventory.md`, `2026-03-19-legacy-surface-rationalization-backlog.md`, `2026-03-19-public-demo-artifact-audit.md`, `2026-03-19-system-review-roadmap-repair.md`, `2026-03-19-tasks-contract-parity-audit.md`, `2026-03-19-tasks-v1-mounted-source-drift-triage.md`, `2026-03-19-tasks-v1-split-owner-route-inventory-audit.md`, `2026-03-19-universal-frame-ownership-audit.md`, `2026-04-04-s6-4-project-template-walkthrough-coverage.md`, `2026-07-09-acceptance-review.md`, `production-slack-webhook-evidence.md`, `slack-notify-step-inventory.md` (lưu ý: chính audit này tự nhận đã stale so với bản dưới), `slack-routing-secret-inventory.md`, và `docs/roadmap/backlog.yaml`.

## Chưa làm trong lần hợp nhất này

- Chưa verify lại 9 mục đánh dấu `UNVERIFIED` liên quan bảo mật (GAP-001, GAP-004 đến GAP-010) — audit gốc tự báo đã vá, nhưng chưa có re-check trực tiếp trong phiên này.
- Chưa có audit riêng cho Tier 5 (cost/profit/reconciliation) — khoảng trống nhận thức, không phải kết luận "không có gap".
- Chưa hành động sửa bất kỳ mục nào — đúng tinh thần mục 4 hiến pháp (ghi nhận trước, không tự ý sửa).

## Cách cập nhật register này

Khi một gap được sửa: đổi `Status` thành `RESOLVED (verified)` kèm PR/commit liên quan, không xoá dòng — giữ làm lịch sử theo mục 8 hiến pháp. Khi phát hiện gap mới: thêm dòng mới đúng tier, có bằng chứng file:line, không suy đoán.
