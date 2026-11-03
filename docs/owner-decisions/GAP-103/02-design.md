---
work_id: GAP-103
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-11-03-gap-103-project-health-read-model-evidence.md
  plan: null
  branch: docs/GAP-103-project-health-read-model
  pr: https://github.com/kha997/zenamanagephp/pull/393
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-03T16:24:10+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-03T16:24:10+07:00"
  updated_at: "2026-11-03T16:24:10+07:00"
generated_by: agent
---

# GAP-103 — Shared Project Health Read Model: Gate 2 design

## OWNER GATE 2: AWAITING OWNER

## Owner Summary

Một dịch vụ **chỉ đọc** `ProjectHealthReadService` trả về "sức khoẻ" một dự án, gồm 4 phần. **Mọi con số có trạng thái
"có dữ liệu" / "chưa có dữ liệu" / "không áp dụng"**, không bao giờ tự hiện 0 / 0% / "đúng hạn" khi thiếu dữ liệu (SSOT §4).

| Phần | Nội dung |
|---|---|
| Tiến độ | Một con số tiến độ chính thức + **cơ sở tính** + mức tin cậy |
| Thời hạn | Trạng thái trễ so với kế hoạch gốc (dùng lại `ProjectDelayStatus`); mốc (milestone) đã xong / quá hạn |
| Cần chú ý | Số việc / hạng mục thiết kế bị chặn, việc quá hạn, mốc quá hạn, cảnh báo Ngân quỹ |
| Tiền | Ngân sách; giá trị hợp đồng; **đã thu** (tiền vào thật); **chi phí phát sinh** (ghi nhận, *không phải tiền đã chi*); số liệu Ngân quỹ (tiền đang giữ, chi đã ghi sổ) nếu dự án có ví — tách bạch, **không cộng trừ ra "lãi"** |

**Owner cần chọn 3 điểm:**
1. **Định nghĩa tiến độ** (đề xuất **P-A**).
2. **Trang dự án có đổi ngay không** (đề xuất **C-1**: ô "Tiến độ" dùng dịch vụ mới, hiện "Chưa có dữ liệu" thay cho 0%).
3. **Dịch vụ phân tích cũ hỏng** (`ProjectAnalyticsService`) (đề xuất **X-1**: tách Work ID riêng).

## Điểm 1 — Định nghĩa tiến độ

| Phương án | Cách tính | Khi thiếu dữ liệu |
|---|---|---|
| **P-A (đề xuất)** | Ưu tiên: bình quân **theo chi phí kế hoạch** của hạng mục gốc (component) — giống con số đang dùng nhưng **tính trực tiếp**, không đọc cột lưu sẵn. Nếu không có hạng mục có chi phí kế hoạch: **bình quân % của các task còn hiệu lực** (bỏ task huỷ), đánh dấu độ tin cậy "hạn chế". | Không có hạng mục lẫn task → "chưa có dữ liệu" |
| P-B | Chỉ theo task (bình quân %) | Không có task → "chưa có dữ liệu" |
| P-C | Giữ cột `projects.progress` đang lưu | Chỉ thêm: không có hạng mục → "chưa có dữ liệu" |

Lý do đề xuất P-A: giữ nguyên ý nghĩa con số người dùng đang thấy (theo chi phí), nhưng không lệ thuộc việc cột lưu có được tính
lại hay chưa, và dự án làm theo task (thiết kế) vẫn có tiến độ, kèm nhãn độ tin cậy. Tiến độ theo mốc
(`Project::calculateProgress()`) không được chọn vì nhiều dự án không có mốc.

Hạng mục đọc qua `App\Models\Component` (model chính thức), **không** qua `Src\CoreProject\Models\Component` mà
`Project::rootComponents()` đang dùng (lớp tương thích, SSOT ownership không cho mở rộng).

## Điểm 2 — Màn hình dùng ngay

| Phương án | Nội dung |
|---|---|
| **C-1 (đề xuất)** | Trang chi tiết dự án: ô "Tiến độ" lấy từ dịch vụ mới, hiện "Chưa có dữ liệu" khi thiếu, kèm nhãn cơ sở tính (theo chi phí / theo task). Không đổi ô nào khác. |
| C-2 | Không đổi giao diện; dịch vụ chỉ dùng cho OPPM sau này. Trang dự án vẫn hiện 0%. |

## Điểm 3 — `ProjectAnalyticsService` hỏng

| Phương án | Nội dung |
|---|---|
| **X-1 (đề xuất)** | Không đụng trong GAP-103; mở Work ID riêng để sửa hoặc gỡ endpoint (cần xem ai đang gọi). |
| X-2 | Sửa trong GAP-103: phần tổng quan của nó dùng dịch vụ mới. Mở rộng phạm vi. |

## Thiết kế chi tiết

### Dịch vụ

`App\Services\ProjectHealth\ProjectHealthReadService::forProject(Project $project, ?Carbon $today = null): array`

- Chỉ đọc, không migration, không ghi. Mọi truy vấn lọc theo `tenant_id` và `project_id` của dự án đã được kiểm tra quyền.
- Số truy vấn **cố định**, không phụ thuộc số task / hạng mục (dùng aggregate `SUM / COUNT`).
- Mỗi số đo: `{ value, availability: available|no_data|not_applicable, basis, label }`.

### Tiến độ (theo P-A)
`basis`: `component_cost_weighted` (tin cậy bình thường) | `task_average` (tin cậy hạn chế) | `null` (no_data).

### Thời hạn
`ProjectDelayStatus::evaluate($project, $latestBaseline)` (giữ nguyên); mốc: tổng / đã xong / quá hạn
(`status = overdue` hoặc `pending` có `target_date` < hôm nay); không có mốc → no_data.

### Cần chú ý
Task bị chặn (`blocked_at` không rỗng, chưa xong/huỷ); hạng mục thiết kế bị chặn; task quá hạn (`end_date` < hôm nay, chưa
xong/huỷ); mốc quá hạn; trạng thái thời hạn `late` / `forecast_late`; số cảnh báo Ngân quỹ (từ
`TreasuryDashboardService::dashboard`) khi dự án có ví, nếu không → not_applicable.

### Tiền
- `budget`: `projects.budget_total` > 0 → available, ngược lại no_data.
- `contract_value`: tổng `total_value` các hợp đồng không huỷ; không có hợp đồng → no_data.
- `cash_received`: tổng `ContractPayment` `status = paid` (tiền vào thật).
- `cost_incurred`: tổng `ContractExpense.amount`, nhãn **"chi phí ghi nhận (không phải tiền đã chi)"** (SSOT §6.3, §8.1).
- `treasury`: `TreasuryBalanceService::projectSummary` (`held_total`, `expenses` đã ghi sổ) khi có ví; không có → not_applicable.
- **Không** có số "lãi" / "lợi nhuận" / cộng trừ giữa các đại lượng khác bản chất (SSOT §8).

### Test (đỏ trước / xanh sau)
`tests/Feature/ProjectHealth/ProjectHealthReadServiceTest.php` mới:
- Tiến độ: có hạng mục có chi phí → theo chi phí; chỉ có task → bình quân task, tin cậy hạn chế, bỏ task huỷ; không có gì →
  no_data (không phải 0).
- Thời hạn: no_baseline, late, forecast_late; mốc quá hạn.
- Cần chú ý: đếm đúng task / hạng mục thiết kế bị chặn, task quá hạn.
- Tiền: ngân sách 0 → no_data; không hợp đồng → no_data; đã thu chỉ tính khoản `paid`; chi phí ghi nhận mang nhãn không phải
  tiền chi; có / không có ví Ngân quỹ.
- Cách ly tenant / dự án: dữ liệu dự án khác không lọt vào.
- Ngân sách truy vấn: số truy vấn bằng nhau với 1 và 50 task.
- C-1: trang dự án hiện "Chưa có dữ liệu" khi không có hạng mục lẫn task.

### Files (allowlist, theo P-A / C-1 / X-1)
- Mới `app/Services/ProjectHealth/ProjectHealthReadService.php`.
- `app/Http/Controllers/Web/ProjectController.php` (show: lấy health) và `resources/views/projects/show.blade.php` (ô
  "Tiến độ") — chỉ khi C-1.
- Mới `tests/Feature/ProjectHealth/ProjectHealthReadServiceTest.php`.
- Plan `docs/superpowers/plans/2026-11-03-gap-103-project-health-read-model.md`; packet và evidence GAP-103.

## Verification required at Gate 3

Đỏ trước / xanh sau; Unit + Feature + Integration và `tests/Feature/Api` không lỗi mới; PHPStan, php-cs-fixer, SSOT lint,
route guard, deptrac sạch; CI trên đúng head xanh.

## Rollback

Revert squash commit (không migration, không dữ liệu).

## Explicit Exclusions

Trang OPPM; danh mục dự án / Control Tower; đổi cột `projects.progress` hay cách nó được lưu; sửa
`ProjectAnalyticsService` (X-1); quyết định §12 mục 7 của SSOT (dòng tiền chi theo tiền thật — thuộc Finance Control); sửa
SSOT OWN-2026-009 (bảng §15 đã cũ về Ngân quỹ — cần Gate 2 revision riêng); deploy.

## Decision Needed

Owner chọn, ví dụ: `APPROVE GAP-103 Gate 2 P-A C-1 X-1` (khuyến nghị) — hoặc tổ hợp khác của P-A/P-B/P-C, C-1/C-2,
X-1/X-2 — / Request changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, deploy; thiết kế trang OPPM.
