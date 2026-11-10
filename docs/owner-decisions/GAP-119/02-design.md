---
work_id: GAP-119
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-11-10-gap-119-project-analytics-cleanup-evidence.md
  plan: null
  branch: docs/GAP-119-project-analytics-cleanup
  pr: https://github.com/kha997/zenamanagephp/pull/409
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-10T12:52:26+07:00"
  updated_at: "2026-11-10T12:52:26+07:00"
generated_by: agent
---

# GAP-119 — Broken project analytics API and dead event code: Gate 2 design

## Owner Summary

Anh/chị cần chọn **3 điểm**:

1. **API và dịch vụ thống kê dự án** — đề xuất **A-1**:
   - gỡ hẳn 4 địa chỉ API đang lỗi 500, bộ điều khiển và dịch vụ thống kê;
   - thêm test chặn chúng quay lại.

   Thông tin sức khoẻ dự án đã có ở trang dự án và OPPM.
2. **Mã sự kiện / thông báo cũ** — đề xuất **E-1**: gỡ cả nhóm không dùng:
   - 3 sự kiện, gồm "hoàn thành mốc";
   - bộ ghi nhật ký cho các sự kiện đó;
   - dịch vụ thông báo thời gian thực;
   - 3 hàm ghi nhật ký chỉ nhóm này dùng.

   Bảng nhật ký dự án và các nơi đang dùng nó giữ nguyên.
3. **Hàm tính tiến độ theo số mốc** — đề xuất **P-1**: gỡ hàm này và test riêng của nó. Tiến độ thật đã tính theo hạng mục và
   "sức khoẻ dự án".

**Nguyên tắc:**
- Chỉ gỡ mã đã chứng minh là chết (không ai gọi) hoặc luôn lỗi.
- Không đổi màn hình nào.
- Không migration.

**Phát hành:** một PR, một Gate 3.

## Điểm 1 — API và dịch vụ thống kê dự án

| Phương án | Nội dung |
|---|---|
| **A-1 (đề xuất)** | **Gỡ:** 4 route `api/analytics/{projects/{project}, filter-options, search-suggestions, search}` (`routes/api.php`), `Api\ProjectAnalyticsController`, `ProjectAnalyticsService`. **Giữ nguyên:** route `api/analytics/dashboard` (thuộc `AnalyticsController`, đang chạy) và các route khác của `AnalyticsController`. **Test chặn mới:** 4 route của `ProjectAnalyticsController` không còn; hai class không còn; `api/analytics/dashboard` vẫn do `AnalyticsController` phục vụ. Cách làm giống lần gỡ API mốc ngày 22/07. |
| A-2 | Như A-1, nhưng làm lại `GET api/analytics/projects/{project}` trả JSON từ `ProjectHealthReadService`. Hiện chưa màn hình hay ứng dụng nào cần, nên thêm việc mà chưa ai dùng. |

## Điểm 2 — Mã sự kiện / thông báo cũ

| Phương án | Nội dung |
|---|---|
| **E-1 (đề xuất)** | **Gỡ:** `ProjectMilestoneCompleted`, `ProjectTaskUpdated`, `ProjectTeamMemberJoined` (thiếu import, không bao giờ phát), `Listeners\LogProjectActivity` (chưa đăng ký, chỉ xử lý 3 sự kiện trên), `RealTimeNotificationService` (không ai gọi), và `ProjectActivity::logMilestoneCompleted / logTaskUpdated / logTeamMemberJoined` (chỉ hai file trên dùng). **Giữ:** model `ProjectActivity` và các hàm khác (`log`, `logProjectCreated`…) đang được `AuditService` và `RealTimeController` dùng. **Test chặn:** các class đã gỡ không còn; `ProjectActivity` vẫn ghi được. |
| E-2 | Chỉ gỡ phần mốc: `ProjectMilestoneCompleted`, `logMilestoneCompleted`, hàm `sendMilestoneCompleted` trong dịch vụ thông báo. Phần còn lại (2 sự kiện, bộ ghi, dịch vụ) vẫn chết và vẫn hỏng. |
| E-3 | Để nguyên. |

## Điểm 3 — Hàm tính tiến độ theo số mốc

| Phương án | Nội dung |
|---|---|
| **P-1 (đề xuất)** | Gỡ `Project::calculateProgress()` cùng test riêng của nó (`ProjectModuleTest::test_project_progress_calculation`). Test đó chỉ kiểm tra chính hàm bị gỡ, không phải tắt test để che lỗi. Test chặn mới khẳng định hàm không còn. `recalculateProgress()` (tính theo hạng mục) giữ nguyên. |
| P-2 | Giữ hàm. |

## Thiết kế chi tiết (theo A-1, E-1, P-1)

### Gỡ
- **`routes/api.php`:** 4 dòng route của `ProjectAnalyticsController`, thay bằng một dòng chú thích dẫn tới hồ sơ này.
- **File xoá:**
  - `app/Http/Controllers/Api/ProjectAnalyticsController.php`
  - `app/Services/ProjectAnalyticsService.php`
  - `app/Events/ProjectMilestoneCompleted.php`
  - `app/Events/ProjectTaskUpdated.php`
  - `app/Events/ProjectTeamMemberJoined.php`
  - `app/Listeners/LogProjectActivity.php`
  - `app/Services/RealTimeNotificationService.php`
- **`app/Models/ProjectActivity.php`:** bỏ 3 hàm `log*` trên, cùng import chỉ chúng dùng.
- **`app/Models/Project.php`:** bỏ `calculateProgress()`.
- **`tests/Feature/ProjectModuleTest.php`:** bỏ `test_project_progress_calculation`.
- **`tests/Feature/ProjectMilestones/ProjectMilestoneServiceTest.php`:** sửa chú thích nhắc `ProjectAnalyticsService`, vì dịch vụ không còn.
- **`phpstan-baseline.neon`:** bỏ các dòng của file đã xoá. Không bắt buộc vì `reportUnmatchedIgnoredErrors: false`, nhưng
  giữ baseline sạch.
- **`docs/architecture/project-model-reference-inventory.md`:** ghi `ProjectAnalyticsController` đã gỡ (GAP-119).

### Test chặn (đỏ trước / xanh sau)
`tests/Feature/Architecture/DeadProjectAnalyticsAndEventCodeRemovedTest.php` (mới):
- 4 URI cũ (`SSOT_ALLOW_ORPHAN` negative probe) không còn route nào trỏ tới `ProjectAnalyticsController`;
- `api/analytics/dashboard` vẫn có route;
- các class đã gỡ không còn (`class_exists` = false);
- `ProjectActivity` không còn 3 hàm `log*` đã gỡ, nhưng vẫn có `log` / `logProjectCreated`;
- `Project` không còn `calculateProgress`, vẫn còn `recalculateProgress`.

Toàn bộ test hiện có phải vẫn xanh (trừ test P-1 được gỡ có chủ đích).

## Files (allowlist)

- `routes/api.php`
- **Xoá:**
  - `app/Http/Controllers/Api/ProjectAnalyticsController.php`
  - `app/Services/ProjectAnalyticsService.php`
  - `app/Events/ProjectMilestoneCompleted.php`
  - `app/Events/ProjectTaskUpdated.php`
  - `app/Events/ProjectTeamMemberJoined.php`
  - `app/Listeners/LogProjectActivity.php`
  - `app/Services/RealTimeNotificationService.php`
- `app/Models/ProjectActivity.php`
- `app/Models/Project.php`
- `tests/Feature/ProjectModuleTest.php`
- `tests/Feature/ProjectMilestones/ProjectMilestoneServiceTest.php` (chỉ chú thích)
- `tests/Feature/Architecture/DeadProjectAnalyticsAndEventCodeRemovedTest.php` (mới)
- `phpstan-baseline.neon`
- `docs/architecture/project-model-reference-inventory.md`
- `docs/owner-decisions/GAP-119/*`, `docs/audits/2026-11-10-gap-119-project-analytics-cleanup-evidence.md`,
  `docs/superpowers/plans/2026-11-10-gap-119-project-analytics-cleanup.md`

File nào khác cần đổi sẽ hỏi Owner trước và ghi thành bổ sung (amendment) trong hồ sơ này.

## Ngoài phạm vi

- `AnalyticsController` và route của nó.
- `ProjectHealthReadService`, OPPM.
- `RealTimeController`.
- Model `ProjectActivity` (ngoài 3 hàm).
- Cấu hình broadcasting.
- Các tài liệu lịch sử nhắc tới class cũ.
- Deploy.

## Verification required at Gate 3

- Test chặn đỏ trước khi gỡ, xanh sau.
- Toàn bộ Unit + Feature + Integration và Feature/Api.
- `php artisan route:list` không lỗi.
- PHPStan, php-cs-fixer, `ssot:lint`, deptrac, governance lint.
- CI trên đúng head.

## Rollback

Revert PR. Không migration, không đổi dữ liệu.

## Decision Needed

Owner chọn: `APPROVE GAP-119 Gate 2 A-1 E-1 P-1` (hoặc phương án khác cho từng điểm) / Request more information / Decline /
Defer.

## What the owner is NOT being asked to decide

Gate 3, merge, deploy.
