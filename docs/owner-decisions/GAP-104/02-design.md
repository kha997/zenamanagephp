---
work_id: GAP-104
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-11-03-gap-104-project-oppm-phase1-evidence.md
  plan: docs/superpowers/plans/2026-11-03-gap-104-project-oppm-phase1.md
  branch: docs/GAP-104-project-oppm-phase1
  pr: https://github.com/kha997/zenamanagephp/pull/394
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-03T20:48:38+07:00"
  owner_response_reference: "Owner decision in-session on 2026-11-03, verbatim: 'APPROVE GAP-104 Gate 2 V-1 W-A T-1 R-1'. Reviewed design head: 76c9b0491ab77f88e203fb10a9f636f95296b9bf. Approves V-1 (separate read-only page /app/projects/{project}/oppm with print layout), W-A (root components as work groups, max 12, task-status summary fallback), T-1 (no Treasury alert count on OPPM; optional includeTreasuryAlerts flag on ProjectHealthReadService), R-1 (rbac:project.view plus tenant check; Treasury block behind treasury.view-project), with the exact allowlist in this packet; no migration, no new permission, no writes; not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-03T20:55:00+07:00"
  updated_at: "2026-11-03T20:48:38+07:00"
generated_by: agent
---

# GAP-104 — Project OPPM Phase 1 (chỉ đọc): Gate 2 design

## OWNER GATE 2: APPROVED — V-1, W-A, T-1, R-1

Owner approved V-1, W-A, T-1 and R-1 in-session on 2026-11-03 against reviewed design head
`76c9b0491ab77f88e203fb10a9f636f95296b9bf`. This authorizes only the bounded implementation defined by this packet; it
does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

Trang **"OPPM một trang"** chỉ đọc cho từng dự án. Trang mở từ nút trên trang chi tiết dự án và in được trên khổ ngang.
Mọi con số lấy từ bộ đọc sức khoẻ dự án dùng chung của GAP-103, không có công thức mới. Thiếu dữ liệu thì trang ghi rõ
"Chưa có dữ liệu", không hiện 0.

| Vùng | Nội dung | Nguồn |
|---|---|---|
| Đầu trang | Tên, mã, trạng thái, khách hàng, PM, ngày bắt đầu/kết thúc, loại dịch vụ (Thiết kế / Thi công / Kiểm định, hoặc "Chưa phân loại"), mô tả phạm vi, ngày lập | `projects`, `ProjectServiceLine` |
| KPI | Tiến độ (kèm cơ sở tính và độ tin cậy); thời hạn so với kế hoạch gốc; mốc; số việc trễ / bị chặn | GAP-103 |
| Nhóm công việc | Tối đa 12 dòng: tên, người phụ trách, ngày, trạng thái, %, thanh thời gian, chi phí kế hoạch / ghi nhận, số việc bị chặn / trễ trong nhóm | theo **Điểm 2** |
| Trễ / bị chặn | Tối đa 10 việc bị chặn (kèm ghi chú vướng, người chặn), 10 việc quá hạn, 10 hạng mục thiết kế bị chặn | `tasks`, `design_items` |
| Hợp đồng – tài chính | Ngân sách, giá trị hợp đồng, đã thu, chi phí ghi nhận (*không phải tiền đã chi*), Ngân quỹ (nếu có quyền và có ví), link sang trang chi tiết; **không có số "lãi"** | GAP-103 |

**Anh/chị cần chọn 4 điểm:**
1. Trang riêng hay một khối trong trang dự án (đề xuất **V-1**, trang riêng).
2. Nguồn "nhóm công việc" (đề xuất **W-A**, hạng mục gốc).
3. Cảnh báo Ngân quỹ trên OPPM (đề xuất **T-1**, không đếm, chỉ để link).
4. Quyền xem (đề xuất **R-1**, `project.view`).

## Điểm 1 — Vị trí

| Phương án | Nội dung |
|---|---|
| **V-1 (đề xuất)** | Trang riêng `/app/projects/{project}/oppm`, nút "OPPM một trang" trên trang dự án, có bố cục in ngang. |
| V-2 | Thêm khối vào trang chi tiết dự án. Trang vốn đã dài, khó in gọn một trang. |

## Điểm 2 — Nhóm công việc

| Phương án | Nội dung | Ưu / nhược |
|---|---|---|
| **W-A (đề xuất)** | **Hạng mục gốc** (component không có cha), sắp theo ngày bắt đầu, tối đa 12 dòng. Mỗi dòng có tiến độ và chi phí riêng; số việc bị chặn / trễ đếm theo `tasks.component_id` gộp theo hạng mục gốc. Dự án không có hạng mục → khối hiện "Chưa có nhóm công việc", kèm thống kê task theo trạng thái (đếm, không liệt kê). | Khớp cách tính tiến độ GAP-103, nên tổng và từng nhóm cùng logic. Nhược: hạng mục hiện chỉ tạo qua API, nên nhiều dự án sẽ thấy khối trống. |
| W-B | Quy trình mẫu (work instance): mỗi quy trình là một dòng, % theo số bước xong. | Có dữ liệu từ giao diện web. Nhưng đây là quy trình duyệt hồ sơ, không phải kế hoạch dự án; quyền `work.view` riêng; công thức % mới chưa có trong GAP-103. |
| W-C | Giai đoạn (phase). | Không đề xuất: dữ liệu mỏng, đọc qua model tương thích `Src\CoreProject` (SSOT cấm mở rộng). |

Hạng mục con không hiện riêng. Việc gán vào hạng mục con được tính vào hạng mục gốc của nó; cây hạng mục được đọc bằng một
truy vấn rồi xử lý trong bộ nhớ, không truy vấn theo từng dòng.

## Điểm 3 — Cảnh báo Ngân quỹ

Codex đã chỉ ra ở GAP-103: phần đếm cảnh báo Ngân quỹ chạy khoảng 8 truy vấn cho mỗi khoản tạm ứng.

| Phương án | Nội dung |
|---|---|
| **T-1 (đề xuất)** | OPPM không đếm cảnh báo Ngân quỹ. Khối tài chính chỉ hiện tiền đang giữ / chi đã ghi sổ (chi phí cố định), kèm link "Xem Ngân quỹ". `ProjectHealthReadService` thêm tham số tuỳ chọn để bỏ qua phần cảnh báo; mặc định giữ nguyên. |
| T-2 | Đếm như GAP-103. Trang chậm dần theo số khoản tạm ứng. |
| T-3 | Viết lại phần đếm cảnh báo theo lô. Phải sửa mã Ngân quỹ (ngoài phạm vi OPPM); để Work ID riêng. |

## Điểm 4 — Quyền

| Phương án | Nội dung |
|---|---|
| **R-1 (đề xuất)** | Route mới có `rbac:project.view` cộng kiểm tra tenant như trang dự án. Khối Ngân quỹ chỉ hiện khi có quyền `treasury.view-project`. Không thêm quyền mới (SSOT §11). |
| R-2 | Giống trang dự án hiện nay (chỉ kiểm tra tenant). Lỏng hơn mức cần cho một trang tổng hợp tài chính. |

## Thiết kế chi tiết (theo V-1, W-A, T-1, R-1)

### Route và controller
- `GET /app/projects/{project}/oppm` → `Web\ProjectOppmController@show`, middleware `rbac:project.view`, tên `app.projects.oppm`.
- Controller tải dự án bằng `where('tenant_id', user tenant)->findOrFail` (404 nếu khác tenant), gọi dịch vụ đọc, trả view.
- Không có logic truy vấn trong controller hoặc Blade.

### Dịch vụ đọc
`App\Services\ProjectOppm\ProjectOppmReadService::forProject(Project $project, bool $includeTreasury): array`:
- `health`: lấy từ `ProjectHealthReadService::forProject($project, includeTreasuryAlerts: false)`.
- `header`: các trường dự án, quản lý, khách hàng, loại dịch vụ (`ProjectServiceLine`; không có dòng nào → "Chưa phân loại").
- `workstreams`: cây hạng mục (một truy vấn), số việc bị chặn / quá hạn theo `component_id` (một truy vấn tổng hợp),
  vị trí thanh thời gian (% so với ngày bắt đầu / kết thúc dự án; thiếu ngày → không vẽ thanh).
- `task_summary`: đếm task theo trạng thái (dùng khi không có hạng mục).
- `blocked_tasks`, `overdue_tasks`, `blocked_design_items`: mỗi loại tối đa 10 dòng, kèm tổng số.
- Mọi truy vấn lọc theo `tenant_id` và `project_id`; bảng không có `tenant_id` đọc qua dự án đã kiểm tra.
- Số truy vấn cố định: không tăng theo số task / hạng mục.

### Giao diện
- `resources/views/projects/oppm.blade.php`: layout operator, các component `x-ui.*` có sẵn.
- Thanh thời gian vẽ bằng CSS, không dùng JS.
- `@media print` trong view: khổ A4 ngang (`@page { size: A4 landscape }`), ẩn menu / nút, cỡ chữ gọn. Nút "In" gọi `window.print()`.
- Nút "OPPM một trang" trên `projects/show.blade.php`, chỉ hiện khi người dùng có `project.view`.

### Sửa GAP-103 (nhỏ)
`ProjectHealthReadService::forProject(Project $project, bool $includeTreasuryAlerts = true)`: khi `false`,
`attention.treasury_alerts` trả `availability: not_requested` và không gọi `TreasuryDashboardService`.

### Test (đỏ trước / xanh sau)
Mới `tests/Feature/ProjectOppm/ProjectOppmPageTest.php`:
- Có `project.view` → 200; không có → 403; dự án tenant khác → 404.
- Dự án rỗng: mọi khối hiện "Chưa có dữ liệu" / "Chưa có nhóm công việc" / "Chưa phân loại", không có "0%".
- Hạng mục: đúng thứ tự, tối đa 12 dòng; việc thuộc hạng mục con được tính vào hạng mục gốc; số việc bị chặn / trễ đúng.
- Danh sách trễ / bị chặn tối đa 10 dòng và có hiện tổng số.
- Tài chính: không có chữ "lãi" / "lợi nhuận"; chi phí ghi nhận có nhãn "không phải tiền đã chi"; khối Ngân quỹ ẩn khi
  không có quyền Ngân quỹ.
- Không gọi `TreasuryDashboardService::dashboard` (mock).
- Cách ly tenant / dự án; số truy vấn bằng nhau với 1 và 50 task / 1 và 20 hạng mục.
- Trang dự án có nút OPPM.

Thêm vào `ProjectHealthReadServiceTest`: `includeTreasuryAlerts: false` không gọi dashboard.

### Files (allowlist)
- Mới `app/Http/Controllers/Web/ProjectOppmController.php`.
- Mới `app/Services/ProjectOppm/ProjectOppmReadService.php`.
- `app/Services/ProjectHealth/ProjectHealthReadService.php` (thêm tham số tuỳ chọn).
- `routes/web.php` (1 route).
- Mới `resources/views/projects/oppm.blade.php`; `resources/views/projects/show.blade.php` (nút).
- Mới `tests/Feature/ProjectOppm/ProjectOppmPageTest.php`; `tests/Feature/ProjectHealth/ProjectHealthReadServiceTest.php`.
- Plan `docs/superpowers/plans/2026-11-03-gap-104-project-oppm-phase1.md`; packet và evidence GAP-104.

Không migration, không quyền mới, không ghi dữ liệu.

## Ngoài phạm vi

Nhập liệu quản trị (Phase 2); snapshot / PDF (Phase 3); nhập mốc; màn hình tạo hạng mục; CRUD Service Line; Contract /
Finance Control; viết lại phần đếm cảnh báo Ngân quỹ; Control Tower; `ProjectAnalyticsService`.

## Verification required at Gate 3

Đỏ trước / xanh sau; Unit + Feature + Integration và `tests/Feature/Api` không lỗi mới; PHPStan, php-cs-fixer, SSOT lint,
route guard, deptrac sạch; CI trên đúng head xanh.

## Rollback

Revert squash commit (không migration, không dữ liệu).

## Decision Needed

Owner chọn: `APPROVE GAP-104 Gate 2 V-1 W-A T-1 R-1` (hoặc phương án khác cho từng điểm) / Changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, phát hành.
