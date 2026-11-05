---
work_id: GAP-112
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-11-05-gap-112-time-logging-evidence.md
  plan: null
  branch: docs/GAP-112-time-logging
  pr: https://github.com/kha997/zenamanagephp/pull/402
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
  created_at: "2026-11-05T15:05:00+07:00"
  updated_at: "2026-11-05T15:05:00+07:00"
generated_by: agent
---

# GAP-112 — Time logging per task: Gate 2 design

## Owner Summary

Anh/chị cần chọn **7 điểm**:

1. **Bấm giờ** — đề xuất **T-1**: mỗi người chỉ một đồng hồ chạy; quên tắt thì tự dừng (tối đa 10 giờ, không qua ngày).
2. **Nhập tay** — đề xuất **M-1**: được nhập / sửa tay, bắt buộc ghi lý do; chỉ sửa được khi chưa duyệt.
3. **Việc không thuộc công trình** — đề xuất **C-1**: danh mục cố định (họp nội bộ, đào tạo, hành chính, kinh doanh /
   báo giá, chờ việc, khác).
4. **Duyệt** — đề xuất **R-1**: PM của công trình (hoặc người có `project.update`) duyệt giờ của công trình đó theo tuần,
   từng người. Giờ không thuộc công trình do người có quyền lương (`payroll.manage`) duyệt. Từ chối phải ghi lý do.
5. **Khoá** — đề xuất **L-1**: tháng đã chốt lương (GAP-109) thì giờ trong tháng đó không thêm / sửa / duyệt được nữa.
6. **Giờ thực tế của công việc** — đề xuất **A-1**: không ghi đè các ô giờ cũ của công việc; giờ đã duyệt hiện riêng.
7. **Quyền** — đề xuất **P-1**: không thêm quyền mới.
   - Ai cũng ghi giờ được cho việc mình được giao, hoặc việc của công trình mình tham gia / làm PM.
   - Người duyệt theo R-1.
   - Báo cáo giờ: Owner / kế toán (`payroll.manage`) xem toàn công ty; PM xem công trình của mình.

Ngoài ra: **phát hành** một PR, một Gate 3.

## Điểm 1 — Bấm giờ

| Phương án | Nội dung |
|---|---|
| **T-1 (đề xuất)** | Nút **Bắt đầu** trên trang "Việc của tôi" và trang việc; nút **Dừng** luôn hiện ở đầu trang "Bảng giờ của tôi". Bắt đầu việc khác thì tự dừng việc đang chạy. Đồng hồ quên tắt: lần sau người đó mở trang hoặc bấm giờ, hệ thống tự dừng ở mốc sớm hơn trong hai mốc "bắt đầu + 10 giờ" và "23:59 cùng ngày", đánh dấu **"tự dừng"** để người duyệt chú ý. |
| T-2 | Không có đồng hồ, chỉ nhập tay cuối ngày. |

## Điểm 2 — Nhập tay

| Phương án | Nội dung |
|---|---|
| **M-1 (đề xuất)** | Nhập ngày + giờ bắt đầu / kết thúc (hoặc số phút), chọn việc hoặc loại việc không thuộc công trình, **bắt buộc lý do**. Sửa / xoá được dòng của mình khi chưa duyệt. Một người không quá 24 giờ / ngày; các dòng có giờ không được chồng nhau. |
| M-2 | Không cho nhập tay; chỉ bấm giờ. |

## Điểm 3 — Việc không thuộc công trình

| Phương án | Nội dung |
|---|---|
| **C-1 (đề xuất)** | Danh mục cố định: họp nội bộ, đào tạo, hành chính, kinh doanh / báo giá, chờ việc, khác (bắt buộc ghi chú). |
| C-2 | Danh mục do Owner tự thêm / sửa. Cần thêm trang cài đặt. |

## Điểm 4 — Duyệt

| Phương án | Nội dung |
|---|---|
| **R-1 (đề xuất)** | Trang **"Duyệt giờ"**: theo tuần, theo người, theo công trình. PM của công trình (hoặc người có `project.update`) duyệt / từ chối giờ của công trình đó; giờ không thuộc công trình do người có `payroll.manage` duyệt. Từ chối phải ghi lý do; dòng bị từ chối quay lại cho người ghi sửa. Không tự duyệt giờ của chính mình (trừ Owner). |
| R-2 | Không duyệt; giờ ghi xong là dùng luôn. |

## Điểm 5 — Khoá

| Phương án | Nội dung |
|---|---|
| **L-1 (đề xuất)** | Ngày làm việc rơi vào tháng đã chốt lương thì không thêm / sửa / xoá / duyệt / từ chối được. Owner mở lại tháng (GAP-109) thì mở khoá. |
| L-2 | Khoá riêng cho giờ, không theo tháng lương. |

## Điểm 6 — Giờ thực tế của công việc

| Phương án | Nội dung |
|---|---|
| **A-1 (đề xuất)** | Không ghi đè `actual_hours` / `spent_hours` hiện có (đang được nơi khác dùng). Trang việc / báo cáo hiện "giờ đã ghi / đã duyệt" lấy từ bảng giờ mới. |
| A-2 | Tự cập nhật `actual_hours` = tổng giờ đã duyệt. Có thể làm lệch các báo cáo cũ đang đọc ô này. |

## Điểm 7 — Quyền

| Phương án | Nội dung |
|---|---|
| **P-1 (đề xuất)** | Không thêm quyền mới. Ghi giờ: người dùng đã đăng nhập (`task.view`) cho việc mình được giao, hoặc việc của công trình mình là thành viên / PM; việc không thuộc công trình luôn được. Duyệt: theo R-1. Báo cáo: `payroll.manage` xem toàn công ty; PM / `project.update` xem công trình của mình. Không ai thấy giờ của công ty khác. |
| P-2 | Thêm quyền mới `time.approve`. |

## Thiết kế chi tiết (theo T-1, M-1, C-1, R-1, L-1, A-1, P-1)

### Dữ liệu (một migration, chỉ tạo bảng mới)
**`time_entries`**
- `id`, `tenant_id`, `user_id`;
- `project_id` (trống được), `task_id` (trống được), `category` (trống được; bắt buộc khi không có việc);
- `work_date`, `started_at`, `ended_at` (trống khi đang chạy), `minutes`;
- `source` (timer / manual), `auto_stopped` (bool), `note` (lý do / ghi chú);
- `status` (running / submitted / approved / rejected);
- `reviewed_by`, `reviewed_at`, `review_note`;
- timestamps.
- Chỉ mục: (`tenant_id`, `user_id`, `work_date`), (`project_id`, `work_date`, `status`).
- Một người có tối đa một dòng `running` (kiểm tra trong transaction, khoá theo người).

### Dịch vụ
**`TimeEntryService`**
- `start(user, task|category)`: khoá theo người, đóng đồng hồ đang chạy (tự dừng nếu quá hạn), tạo dòng mới.
- `stop(user)`.
- `closeStale(user)`: tự dừng theo T-1.
- `addManual(user, data)`: M-1, kiểm tra chồng giờ, tối đa 24 giờ / ngày, lý do.
- `update`, `delete`: chỉ dòng của mình, chưa duyệt.

Áp dụng cho mọi thao tác:
- quyền ghi theo P-1;
- khoá theo tháng lương (L-1): ngày làm việc thuộc `payroll_periods` đã chốt thì từ chối;
- mỗi thay đổi ghi `AuditLog`.

**`TimeReviewService`**
- `queue(reviewer, week)`: các dòng chờ duyệt mà người này được duyệt.
- `approve(reviewer, ids)`, `reject(reviewer, ids, reason)`.
- Không tự duyệt (trừ người có `payroll.close`). Áp dụng L-1. Nhật ký.

**`TimeReportService`**
- Giờ theo công trình × người, theo người × công trình / loại, theo tuần / tháng.
- Tách đã duyệt / chờ duyệt.
- Giới hạn theo P-1. Số truy vấn cố định.

### Giao diện (web)
- **"Việc của tôi"**: nút **Bắt đầu** trên từng việc; dải "Đang ghi giờ: …" kèm nút **Dừng**.
- **"Bảng giờ của tôi"**:
  - xem theo tuần;
  - tổng mỗi ngày;
  - nhập tay;
  - sửa / xoá dòng chưa duyệt;
  - dòng tự dừng / bị từ chối được đánh dấu.
- **"Duyệt giờ"**: lọc theo tuần / người / công trình; duyệt / từ chối nhiều dòng một lần.
- **"Báo cáo giờ"**: theo công trình và theo người, xuất CSV.
- Menu: "Bảng giờ" (mọi người đăng nhập), "Duyệt giờ" và "Báo cáo giờ" (chỉ người có quyền theo R-1 / P-1).

### Test (đỏ trước / xanh sau)
**Ghi giờ (`TimeEntryTest`)**
- Bắt đầu / dừng.
- Bắt đầu việc khác tự dừng việc cũ.
- Tự dừng ở 10 giờ / cuối ngày, có cờ "tự dừng".
- Nhập tay cần lý do, không chồng giờ, không quá 24 giờ / ngày.
- Loại "khác" cần ghi chú.
- Chỉ ghi cho việc được phép.
- Sửa / xoá trước khi duyệt.
- Khoá theo tháng lương.
- Nhật ký, cách ly công ty.

**Duyệt (`TimeReviewTest`)**
- PM duyệt giờ công trình mình; người khác không duyệt được.
- Giờ không thuộc công trình do `payroll.manage` duyệt.
- Không tự duyệt.
- Từ chối cần lý do và quay lại cho người ghi.
- Khoá tháng.

**Báo cáo (`TimeReportTest`)**
- Tổng giờ đúng theo công trình / người, tách đã duyệt.
- Giới hạn phạm vi theo quyền.
- CSV.
- Số truy vấn không tăng theo số dòng.

**Migration**: chạy lên / gỡ / lên trên MySQL 8 thật. Ba file test mới cũng chạy xanh với MySQL.

### Files (allowlist)
- Mới:
  - `database/migrations/2026_11_05_150000_create_time_entries_table.php`;
  - `app/Models/TimeTracking/TimeEntry.php`;
  - `app/Services/TimeTracking/TimeEntryService.php`, `TimeReviewService.php`, `TimeReportService.php`,
    `TimeRuleViolation.php`;
  - `app/Http/Controllers/Web/TimeTracking/TimesheetPageController.php`, `TimeReviewPageController.php`,
    `TimeReportPageController.php`;
  - views `resources/views/time/timesheet.blade.php`, `review.blade.php`, `report.blade.php`;
  - tests `tests/Feature/TimeTracking/TimeEntryTest.php`, `TimeReviewTest.php`, `TimeReportTest.php`.
- Sửa:
  - `routes/web.php`;
  - `app/Support/Navigation/OperatorNavigationDefinition.php` (menu);
  - `app/Http/Controllers/Web/WorkloadPageController.php` và `resources/views/app/my-work.blade.php` (nút Bắt đầu /
    Dừng, dải đang ghi giờ);
  - các test điều hướng / kiến trúc chỉ khi chúng liệt kê cố định menu hoặc route.
- Plan `docs/superpowers/plans/2026-11-05-gap-112-time-logging.md`; packet và evidence GAP-112.

Không phụ thuộc mới, không quyền mới, không đổi bảng cũ, không tính tiền.

## Ngoài phạm vi

- Tính tiền (giờ × đơn giá, phần 3).
- Chi phí chung / lời lỗ (phần 4).
- Danh mục tuỳ chỉnh (nếu chọn C-1).
- Ứng dụng điện thoại; theo dõi màn hình.
- Nhắc giờ qua thông báo.
- Deploy.

## Verification required at Gate 3

- Đỏ trước / xanh sau.
- Unit + Feature + Integration và `tests/Feature/Api` không lỗi mới.
- Migration chạy trên MySQL thật.
- PHPStan, php-cs-fixer, SSOT lint, route guard, deptrac sạch.
- CI trên đúng head xanh.

## Rollback

Revert squash commit. Migration `down()` xoá bảng `time_entries` (mất giờ đã ghi). Các ô giờ cũ của công việc không đổi
(A-1).

## Decision Needed

Owner chọn: `APPROVE GAP-112 Gate 2 T-1 M-1 C-1 R-1 L-1 A-1 P-1` (hoặc phương án khác cho từng điểm) / Changes /
Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, phát hành.
