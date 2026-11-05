---
work_id: GAP-113
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-11-05-gap-113-project-labour-cost-evidence.md
  plan: docs/superpowers/plans/2026-11-05-gap-113-project-labour-cost.md
  branch: docs/GAP-113-project-labour-cost
  pr: https://github.com/kha997/zenamanagephp/pull/403
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-05T18:32:52+07:00"
  owner_response_reference: "Owner decision in-session on 2026-11-05, verbatim: 'APPROVE GAP-113 Gate 2 S-1 H-1 R-1 O-1 D-1 V-1'. Reviewed design head: 4a90ab996eca59d4067176ceec900e3fb84aeb5d. Approves S-1 (allocation computed and stored at payroll close, cleared on reopen, recomputed on re-close; draft months shown as preview only), H-1 (payroll close blocked while the month has running or submitted time entries), R-1 (project amount = company cost x approved project hours / max(total approved hours, standard hours); remainder to not-in-project; whole-unit rounding with totals matching), O-1 (is_overhead staff: whole cost to not-in-project), D-1 (labour cost page linked from payroll pages, CSV; closed-month cumulative line on the project OPPM page; no new menu item), V-1 (report payroll.manage only; OPPM line for payroll.manage and the project's pm_id, total only; no new permission), one PR / one Gate 3, with the exact allowlist in this packet; one additive migration (one new table); not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-05T18:00:00+07:00"
  updated_at: "2026-11-05T18:32:52+07:00"
generated_by: agent
---

# GAP-113 — Labour cost per project: Gate 2 design

## OWNER GATE 2: APPROVED — S-1, H-1, R-1, O-1, D-1, V-1

Owner approved S-1, H-1, R-1, O-1, D-1 and V-1 in-session on 2026-11-05 against reviewed design head
`4a90ab996eca59d4067176ceec900e3fb84aeb5d`. This authorizes only the bounded implementation defined by this packet; it does not authorize Gate 3, merge,
release, or deployment.

## Owner Summary

Anh/chị cần chọn **6 điểm**:

1. **Khi nào ra số** — đề xuất **S-1**: số tiền nhân công được tính và **lưu cố định khi chốt lương**. Mở lại tháng thì
   xoá số đó, chốt lại thì tính lại. Tháng chưa chốt chỉ hiện **"tạm tính"**.
2. **Giờ chưa duyệt lúc chốt** — đề xuất **H-1**: **không cho chốt lương** khi trong tháng còn giờ đang chạy hoặc chờ
   duyệt. Trang chốt lương liệt kê các giờ này để xử lý trước.
3. **Cách tính tiền** — đề xuất **R-1**: giờ đã duyệt × chi phí 1 giờ. Nếu người đó ghi **nhiều hơn** giờ chuẩn thì chia
   theo tỷ lệ, để tổng không vượt chi phí công ty thực trả cho người đó.
4. **Nhân viên văn phòng** — đề xuất **O-1**: toàn bộ chi phí của người được đánh dấu "chi phí chung" (GAP-109) đưa vào
   chi phí chung, kể cả khi họ có ghi giờ cho công trình.
5. **Nơi hiển thị** — đề xuất **D-1**:
   - trang **"Chi phí nhân công"** mở từ trang Nhân sự & lương;
   - dòng **"Chi phí nhân công (đã chốt)"** trên trang OPPM của công trình.
6. **Ai được xem tiền** — đề xuất **V-1**:
   - Owner / kế toán (`payroll.manage`) xem đầy đủ, kể cả từng người;
   - PM của công trình chỉ thấy **tổng** chi phí nhân công công trình mình trên trang OPPM, không thấy từng người.

Ngoài ra: **phát hành** một PR, một Gate 3.

## Điểm 1 — Khi nào ra số

| Phương án | Nội dung |
|---|---|
| **S-1 (đề xuất)** | Lúc **chốt lương** (GAP-109), hệ thống tính và lưu bảng phân bổ cho tháng đó (người × công trình, cộng một dòng "chưa vào công trình" cho mỗi người). Số đã chốt không đổi dù ai sửa gì sau đó. **Mở lại** tháng thì xoá bảng phân bổ của tháng; **chốt lại** thì tính lại. Mọi lần tính / xoá đều ghi nhật ký. Tháng chưa chốt: trang báo cáo tính tại chỗ, ghi rõ **"tạm tính"**, không cộng vào luỹ kế công trình. |
| S-2 | Luôn tính tại chỗ, không lưu. Đơn giản hơn nhưng số của tháng cũ có thể đổi nếu dữ liệu nguồn bị sửa sau này. |

## Điểm 2 — Giờ chưa duyệt lúc chốt

| Phương án | Nội dung |
|---|---|
| **H-1 (đề xuất)** | Chốt lương bị chặn nếu trong tháng còn dòng giờ **đang chạy** hoặc **chờ duyệt**. Trang bảng lương hiện số dòng còn treo theo người, kèm link sang trang duyệt giờ. Lý do: sau khi chốt, giờ của tháng bị khoá (GAP-112 L-1), nên giờ treo sẽ không bao giờ được tính. |
| H-2 | Vẫn cho chốt, chỉ cảnh báo. Giờ treo không được tính; phần tiền đó nằm ở "chưa vào công trình". |

## Điểm 3 — Cách tính tiền

| Phương án | Nội dung |
|---|---|
| **R-1 (đề xuất)** | Mỗi người, mỗi tháng: **tiền công trình = chi phí công ty × giờ duyệt trên công trình ÷ max(tổng giờ duyệt, giờ chuẩn)**. Ghi ít hơn giờ chuẩn thì công trình nhận đúng "giờ × chi phí 1 giờ", phần còn lại (giờ trống, họp, hành chính…) vào "chưa vào công trình". Ghi nhiều hơn giờ chuẩn thì chia tỷ lệ, tổng không vượt chi phí thực. Tính bằng đồng nguyên, tổng các dòng khớp đúng chi phí công ty của người đó. |
| R-2 | Luôn lấy giờ × chi phí 1 giờ, kể cả khi vượt giờ chuẩn. Tổng phân bổ có thể lớn hơn chi phí thực, phần "chưa vào công trình" bị âm. |
| R-3 | Chia toàn bộ chi phí theo tỷ lệ giờ đã ghi. Giờ trống cũng bị đẩy hết vào công trình, không còn phần cho chi phí chung. |

## Điểm 4 — Nhân viên văn phòng

| Phương án | Nội dung |
|---|---|
| **O-1 (đề xuất)** | Người có cờ "chi phí chung" (`is_overhead`) thì toàn bộ chi phí vào "chưa vào công trình" (sang phần 4 phân bổ như chi phí chung). Giờ họ ghi vẫn hiện trong báo cáo giờ, nhưng không thành tiền công trình. |
| O-2 | Tính giờ công trình của họ như người khác; chỉ phần còn lại vào chi phí chung. |

**Trường hợp đặc biệt (áp dụng với mọi phương án):**
- Người có lương nhưng chưa nối tài khoản đăng nhập: toàn bộ chi phí vào "chưa vào công trình".
- Người ghi giờ nhưng không có trong bảng lương tháng đó: giờ hiện riêng là **"giờ chưa có đơn giá"**, không ra tiền.

## Điểm 5 — Nơi hiển thị

| Phương án | Nội dung |
|---|---|
| **D-1 (đề xuất)** | Trang **"Chi phí nhân công"** (link từ trang Nhân sự & lương và trang từng tháng lương). Trang có: chọn tháng; bảng công trình × người (giờ, tiền); dòng "chưa vào công trình"; luỹ kế từng công trình qua các tháng đã chốt; xuất CSV. Trên trang OPPM của công trình thêm dòng **"Chi phí nhân công (đã chốt)"** = luỹ kế các tháng đã chốt. Không thêm mục menu mới. |
| D-2 | Chỉ trang báo cáo, không thêm vào trang OPPM. |

## Điểm 6 — Ai được xem tiền

| Phương án | Nội dung |
|---|---|
| **V-1 (đề xuất)** | Trang "Chi phí nhân công": chỉ `payroll.manage`. Dòng trên trang OPPM: `payroll.manage` và PM của công trình đó (`pm_id`). PM chỉ thấy tổng, không thấy từng người. Không thêm quyền mới. Lưu ý: công trình chỉ có một người làm thì tổng gần bằng chi phí của người đó. |
| V-2 | Chỉ `payroll.manage` thấy mọi số tiền nhân công; PM không thấy. |
| V-3 | PM thấy cả chi tiết từng người của công trình mình. |

## Thiết kế chi tiết (theo S-1, H-1, R-1, O-1, D-1, V-1)

### Dữ liệu (một migration, chỉ tạo bảng mới)
**`labour_cost_allocations`**
- `id`, `tenant_id`, `payroll_period_id`, `staff_member_id`;
- `project_id` (trống = "chưa vào công trình");
- `minutes`, `amount` (decimal 15,2);
- timestamps.
- Duy nhất theo (`payroll_period_id`, `staff_member_id`, `project_id`).
- Xoá theo tháng lương khi tháng bị xoá.

### Dịch vụ
**`LabourCostService`**
- `allocate(period)`: tính theo R-1 / O-1 từ dòng lương đã tính lại và giờ đã duyệt của tháng, ghi bảng phân bổ.
- `clear(period)`: xoá bảng phân bổ của tháng.
- `pendingEntries(period)`: dòng giờ đang chạy / chờ duyệt trong tháng (cho H-1).
- `preview(period)`: tính tại chỗ, không ghi (tháng chưa chốt).
- Làm tròn theo đồng, phần dư dồn theo phần lẻ lớn nhất để tổng khớp đúng chi phí công ty.

**`PayrollService` (sửa)**
- `close`: kiểm H-1 trước; sau khi tính lại dòng lương thì gọi `allocate`, cùng một transaction.
- `reopen`: gọi `clear`, cùng một transaction.
- Nhật ký ghi tổng tiền vào công trình và tổng "chưa vào công trình".

**`LabourCostReportService`**
- Theo tháng: công trình × người.
- Luỹ kế theo công trình qua các tháng đã chốt.
- Tổng một công trình (cho OPPM).
- CSV.
- Số truy vấn cố định.

### Giao diện (web)
- Trang **"Chi phí nhân công"** (`payroll.manage`), link từ trang Nhân sự & lương và trang tháng lương.
- Trang tháng lương: khi còn giờ treo thì hiện danh sách theo người và khoá nút chốt (H-1).
- Trang OPPM: dòng "Chi phí nhân công (đã chốt)" theo V-1. Bản chụp OPPM cũ không đổi.

### Test (đỏ trước / xanh sau)
**Phân bổ (`LabourCostAllocationTest`)**
- Chốt tạo phân bổ đúng.
- Ghi ít hơn giờ chuẩn: phần dư vào "chưa vào công trình".
- Ghi nhiều hơn giờ chuẩn: chia tỷ lệ, tổng bằng chi phí.
- Giờ không thuộc công trình không thành tiền công trình.
- Nhân viên văn phòng (O-1).
- Người chưa nối tài khoản; giờ chưa có đơn giá.
- Chỉ giờ đã duyệt.
- H-1 chặn chốt.
- Mở lại xoá phân bổ; chốt lại tính lại.
- Làm tròn khớp tổng.
- Nhật ký, cách ly công ty.

**Báo cáo (`LabourCostReportTest`)**
- Số theo tháng / luỹ kế đúng; tháng chưa chốt là "tạm tính" và không vào luỹ kế.
- Quyền theo V-1 (trang báo cáo; dòng OPPM cho PM / người khác).
- CSV.
- Số truy vấn không tăng theo số dòng.

**Migration**: lên / gỡ / lên trên MySQL 8 thật. Hai file test mới chạy xanh với MySQL. Test lương GAP-109 hiện có vẫn
xanh.

### Files (allowlist)
- Mới:
  - `database/migrations/2026_11_05_170000_create_labour_cost_allocations_table.php`;
  - `app/Models/Payroll/LabourCostAllocation.php`;
  - `app/Services/Payroll/LabourCostService.php`, `LabourCostReportService.php`;
  - `app/Http/Controllers/Web/Payroll/LabourCostPageController.php`;
  - view `resources/views/payroll/labour-cost.blade.php`;
  - tests `tests/Feature/Payroll/LabourCostAllocationTest.php`, `LabourCostReportTest.php`.
- Sửa:
  - `app/Services/Payroll/PayrollService.php` (close / reopen);
  - `app/Http/Controllers/Web/Payroll/PayrollPageController.php` và `resources/views/payroll/index.blade.php`,
    `period.blade.php` (link, giờ treo, khoá nút chốt);
  - `routes/web.php`;
  - `app/Http/Controllers/Web/ProjectOppmController.php` và `resources/views/projects/_oppm-body.blade.php` (dòng
    chi phí nhân công);
  - test hiện có chỉ khi chúng liệt kê cố định route (route guard / kiến trúc), hoặc test lương GAP-109 cần thêm giờ đã
    duyệt để vẫn chốt được.
- Plan `docs/superpowers/plans/2026-11-05-gap-113-project-labour-cost.md`; packet và evidence GAP-113.

Không phụ thuộc mới, không quyền mới, không đổi bảng cũ, không đổi cách tính lương hay bảng giờ.

## Owner amendment 1 — 2026-11-05 (Codex finding on PR #403)

Owner answer via AskUserQuestion on 2026-11-05T20:46:11+07:00, verbatim: "Fix now, amend allowlist (Recommended)".

Codex (P2) found that a time entry written or reviewed at the same moment as a payroll close could slip past the H-1
check: the open-month check in `TimeEntryService` runs outside the write transaction and takes no lock on the payroll
month. Fix: time-entry writes and reviews check the month inside their transaction with a shared lock on the payroll
month row; close already holds that row exclusively, so the two serialize.

Allowlist addition:
- `app/Services/TimeTracking/TimeEntryService.php` (month check inside the transactions, shared lock on the month row);
- tests in the already-allowed `tests/Feature/Payroll/LabourCostAllocationTest.php`.

Gate 3 must be re-presented after this change.

## Ngoài phạm vi

- Phân bổ chi phí chung, lời lỗ công trình (phần 4).
- Bút toán kế toán, ghi sổ Ngân quỹ.
- Tự tính lại các tháng đã chốt trước khi có tính năng này (mở lại rồi chốt lại để có số).
- Deploy.

## Verification required at Gate 3

- Đỏ trước / xanh sau.
- Unit + Feature + Integration và `tests/Feature/Api` không lỗi mới.
- Migration chạy trên MySQL thật.
- PHPStan, php-cs-fixer, SSOT lint, route guard, deptrac sạch.
- CI trên đúng head xanh.

## Rollback

Revert squash commit. Migration `down()` xoá bảng `labour_cost_allocations` (mất số đã phân bổ; tính lại được từ lương
và giờ). Lương và bảng giờ không đổi.

## Decision Needed

Owner chọn: `APPROVE GAP-113 Gate 2 S-1 H-1 R-1 O-1 D-1 V-1` (hoặc phương án khác cho từng điểm) / Changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, phát hành.
