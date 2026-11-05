---
work_id: GAP-114
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-11-05-gap-114-overhead-project-pnl-evidence.md
  plan: docs/superpowers/plans/2026-11-05-gap-114-overhead-project-pnl.md
  branch: docs/GAP-114-overhead-project-pnl
  pr: https://github.com/kha997/zenamanagephp/pull/404
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-05T23:23:31+07:00"
  owner_response_reference: "Owner decision in-session on 2026-11-05, verbatim: 'APPROVE GAP-114 Gate 2 N-1 C-1 P-1 A-1 R-1 D-1 V-1'. Reviewed design head: 015478958690b5414e440e3ac8af6377b9a9e6e2. Approves N-1 (monthly overhead cost lines on a new page, fixed categories, payroll.manage), C-1 (separate monthly overhead close by payroll.close after the payroll month is closed; allocation stored; reopen with reason clears it; payroll reopen refused while overhead is closed), P-1 (pool = entered lines + GAP-113 labour outside projects), A-1 (allocation by approved project minutes; whole-dong rounding with totals matching; no project hours = not allocated), R-1 (monthly revenue = contract payments paid by paid date; cumulative also shows projected P&L on live contract value), D-1 (direct cost = posted Treasury expenses net of reversals by document date; contract expenses shown for reference only), V-1 (P&L page payroll.manage only; OPPM closed P&L line for payroll.manage and the project's pm_id; no new permission), one PR / one Gate 3, with the exact allowlist in this packet; one additive migration (three new tables); not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-05T23:20:00+07:00"
  updated_at: "2026-11-05T23:23:31+07:00"
generated_by: agent
---

# GAP-114 — Overhead allocation and project P&L: Gate 2 design

## OWNER GATE 2: APPROVED — N-1, C-1, P-1, A-1, R-1, D-1, V-1

Owner approved N-1, C-1, P-1, A-1, R-1, D-1 and V-1 in-session on 2026-11-05 against reviewed design head
`015478958690b5414e440e3ac8af6377b9a9e6e2`. This authorizes only the bounded implementation defined by this packet; it does not authorize Gate 3, merge,
release, or deployment.

## Owner Summary

Anh/chị cần chọn **7 điểm**:

1. **Nhập chi phí chung** — đề xuất **N-1**: trang **"Chi phí chung"** theo tháng, nhập từng dòng (thuê văn phòng, điện,
   nước, internet / điện thoại, phần mềm, văn phòng phẩm, khấu hao, khác).
2. **Chốt số** — đề xuất **C-1**: **chốt chi phí chung** riêng cho từng tháng, sau khi đã chốt lương tháng đó. Lúc chốt,
   hệ thống chia và lưu cố định.
3. **Gộp vào chi phí chung** — đề xuất **P-1**: các dòng đã nhập **cộng** phần lương chưa vào công trình (GAP-113).
4. **Cách chia** — đề xuất **A-1**: theo **tỷ lệ giờ đã duyệt** của từng công trình trong tháng (như đã thống nhất).
5. **Doanh thu** — đề xuất **R-1**: hiện cả hai:
   - lời lỗ **theo tiền đã thu**;
   - lời lỗ **dự kiến theo giá trị hợp đồng**.
6. **Chi phí trực tiếp** — đề xuất **D-1**: lấy **tiền đã chi qua Ngân quỹ**. Chi phí ghi theo hợp đồng chỉ hiện để đối
   chiếu, không cộng thêm, tránh tính trùng.
7. **Ai được xem** — đề xuất **V-1**:
   - Owner / kế toán: trang lời lỗ đầy đủ;
   - PM: dòng tóm tắt lời lỗ (đã chốt) trên trang OPPM của công trình mình.

Ngoài ra: **phát hành** một PR, một Gate 3.

## Điểm 1 — Nhập chi phí chung

| Phương án | Nội dung |
|---|---|
| **N-1 (đề xuất)** | Trang **"Chi phí chung"**, mở từ trang Nhân sự & lương. Chọn tháng, nhập từng dòng: loại (danh mục cố định: thuê văn phòng, điện, nước, internet / điện thoại, phần mềm, văn phòng phẩm, khấu hao, khác), mô tả, số tiền. Sửa / xoá được khi tháng chưa chốt. Có nhật ký. Người có `payroll.manage` nhập. |
| N-2 | Mở rộng Ngân quỹ cho chứng từ cấp công ty (không gắn công trình). Đụng nhiều phần Ngân quỹ đang chạy, rủi ro cao hơn. |

## Điểm 2 — Chốt số

| Phương án | Nội dung |
|---|---|
| **C-1 (đề xuất)** | Mỗi tháng có nút **"Chốt chi phí chung"** (người có `payroll.close`). Chỉ chốt được khi lương tháng đó đã chốt, vì cần số nhân công cố định. Lúc chốt, hệ thống chia và lưu cố định. Sau khi chốt: khoá sửa các dòng chi phí; muốn mở lại lương thì phải mở lại chi phí chung trước. Mở lại cần lý do và xoá số đã chia; chốt lại thì chia lại. |
| C-2 | Chia luôn lúc chốt lương. Đơn giản hơn, nhưng hoá đơn điện / nước về muộn thì phải mở lại cả bảng lương. |

## Điểm 3 — Gộp vào chi phí chung

| Phương án | Nội dung |
|---|---|
| **P-1 (đề xuất)** | Tổng chi phí chung của tháng = các dòng đã nhập **+** lương chưa vào công trình của tháng đó (GAP-113: nhân viên văn phòng, giờ họp / hành chính / giờ trống, người chưa nối tài khoản). |
| P-2 | Chỉ các dòng đã nhập. Lương chưa vào công trình để riêng, không chia cho công trình. |

## Điểm 4 — Cách chia

| Phương án | Nội dung |
|---|---|
| **A-1 (đề xuất)** | Theo tỷ lệ **giờ đã duyệt** trên từng công trình trong tháng (lấy từ GAP-113). Làm tròn đồng, tổng khớp. Tháng không có giờ công trình nào thì chi phí chung không chia được, hiện ở dòng **"chưa phân bổ"** của công ty. |
| A-2 | Theo tỷ lệ **chi phí nhân công** của công trình. Công trình dùng người lương cao sẽ gánh nhiều hơn. |
| A-3 | Theo tỷ lệ **doanh thu đã thu** trong tháng. |

## Điểm 5 — Doanh thu

| Phương án | Nội dung |
|---|---|
| **R-1 (đề xuất)** | **Theo tháng:** doanh thu = các đợt thanh toán hợp đồng đã thu, theo ngày thu. **Luỹ kế:** hiện cả "lời lỗ theo tiền đã thu" và "lời lỗ dự kiến" (giá trị hợp đồng còn hiệu lực − chi phí luỹ kế). |
| R-2 | Chỉ theo giá trị hợp đồng. |

## Điểm 6 — Chi phí trực tiếp

| Phương án | Nội dung |
|---|---|
| **D-1 (đề xuất)** | Lấy **chi đã ghi sổ qua Ngân quỹ** của công trình, theo ngày chứng từ, trừ các khoản đã đảo. "Chi phí ghi nhận theo hợp đồng" chỉ hiện để đối chiếu, không cộng vào lời lỗ. **Lưu ý:** không trả lương qua Ngân quỹ, nếu không sẽ bị tính hai lần với nhân công. |
| D-2 | Lấy chi phí ghi nhận theo hợp đồng. |
| D-3 | Cộng cả hai. Dễ tính trùng. |

## Điểm 7 — Ai được xem

| Phương án | Nội dung |
|---|---|
| **V-1 (đề xuất)** | Trang **"Lời lỗ công trình"** chỉ người có `payroll.manage`. Trên trang OPPM thêm dòng **"Lời lỗ (đã chốt)"** (doanh thu đã thu, tổng chi phí, lời / lỗ) cho `payroll.manage` và PM của công trình đó. Không thêm quyền mới. |
| V-2 | Chỉ `payroll.manage` thấy lời lỗ; PM không thấy. |

## Thiết kế chi tiết (theo N-1, C-1, P-1, A-1, R-1, D-1, V-1)

### Dữ liệu (một migration, chỉ tạo bảng mới)
- **`overhead_periods`**: `id`, `tenant_id`, `period` (YYYY-MM, duy nhất theo công ty), `status` (draft / closed),
  `closed_at`, `closed_by`, timestamps.
- **`overhead_cost_lines`**: `id`, `tenant_id`, `overhead_period_id`, `category`, `description`, `amount`, `created_by`,
  timestamps.
- **`overhead_allocations`**: `id`, `tenant_id`, `overhead_period_id`, `project_id` (trống = chưa phân bổ), `minutes`,
  `amount`, timestamps.

### Dịch vụ
- **`OverheadService`**:
  - tạo tháng; thêm / sửa / xoá dòng (chỉ khi tháng chưa chốt);
  - `close`: lương tháng đó phải đã chốt; tính P-1 / A-1 và lưu cố định;
  - `reopen`: cần lý do; xoá số đã chia;
  - mọi thao tác khoá theo dòng tháng, có nhật ký.
- **`PayrollService::reopen` (sửa):** từ chối khi chi phí chung của tháng đó đã chốt.
- **`ProjectPnlService`**:
  - theo tháng: từng công trình — doanh thu đã thu, chi Ngân quỹ, nhân công, chi phí chung, lời / lỗ, tỷ lệ;
  - luỹ kế (chỉ tháng đã chốt chi phí chung): thêm giá trị hợp đồng và lời lỗ dự kiến;
  - tổng công ty, gồm chi phí chung chưa phân bổ;
  - CSV;
  - số truy vấn cố định.

### Giao diện (web)
- Trang **"Chi phí chung"**: chọn tháng; dòng chi phí; tổng (đã nhập + lương chưa vào công trình); nút chốt / mở lại;
  số đã chia theo công trình.
- Trang **"Lời lỗ công trình"**: theo tháng và luỹ kế; tổng công ty; xuất CSV. Tháng chưa chốt chi phí chung ghi
  **"Tạm tính"**.
- Link từ trang Nhân sự & lương; không thêm mục menu.
- OPPM: dòng "Lời lỗ (đã chốt)" theo V-1.

### Test (đỏ trước / xanh sau)
**Chi phí chung (`OverheadTest`)**
- Nhập / sửa / xoá, khoá khi đã chốt.
- Chưa chốt lương thì không chốt được.
- Chia theo giờ, gộp lương chưa vào công trình, làm tròn khớp tổng.
- Tháng không có giờ thì để "chưa phân bổ".
- Mở lại xoá số; chốt lại chia lại; không mở lại lương khi chi phí chung đã chốt.
- Nhật ký, quyền, cách ly công ty.

**Lời lỗ (`ProjectPnlTest`)**
- Doanh thu theo ngày thu; chi Ngân quỹ trừ khoản đảo; nhân công; chi phí chung.
- Lời lỗ tháng và luỹ kế, dự kiến theo hợp đồng.
- Tạm tính khi chưa chốt.
- Quyền trang và dòng OPPM; CSV; số truy vấn cố định.

**Migration**: lên / gỡ / lên trên MySQL 8 thật. Test mới chạy xanh với MySQL.

### Files (allowlist)
- Mới:
  - `database/migrations/2026_11_05_190000_create_overhead_tables.php`;
  - `app/Models/Payroll/OverheadPeriod.php`, `OverheadCostLine.php`, `OverheadAllocation.php`;
  - `app/Services/Payroll/OverheadService.php`, `ProjectPnlService.php`;
  - `app/Http/Controllers/Web/Payroll/OverheadPageController.php`, `ProjectPnlPageController.php`;
  - views `resources/views/payroll/overhead.blade.php`, `pnl.blade.php`;
  - tests `tests/Feature/Payroll/OverheadTest.php`, `ProjectPnlTest.php`.
- Sửa:
  - `app/Services/Payroll/PayrollService.php` (chặn mở lại lương);
  - `resources/views/payroll/index.blade.php` (link);
  - `routes/web.php`;
  - `app/Http/Controllers/Web/ProjectOppmController.php` và `resources/views/projects/_oppm-body.blade.php` (dòng lời
    lỗ);
  - test hiện có chỉ khi chúng liệt kê cố định route.
- Plan `docs/superpowers/plans/2026-11-05-gap-114-overhead-project-pnl.md`; packet và evidence GAP-114.

Không phụ thuộc mới, không quyền mới, không đổi bảng cũ, không đổi Ngân quỹ / lương / bảng giờ (ngoài chặn mở lại
lương).

## Ngoài phạm vi

- Bút toán kế toán, thuế / VAT.
- Ngân sách, dự báo.
- Chứng từ Ngân quỹ cấp công ty (N-2).
- Deploy.

## Verification required at Gate 3

- Đỏ trước / xanh sau.
- Unit + Feature + Integration và `tests/Feature/Api` không lỗi mới.
- Migration chạy trên MySQL thật.
- PHPStan, php-cs-fixer, SSOT lint, route guard, deptrac sạch.
- CI trên đúng head xanh.

## Rollback

Revert squash commit. Migration `down()` xoá 3 bảng mới (mất chi phí chung đã nhập và số đã chia). Lương, bảng giờ, Ngân
quỹ không đổi.

## Decision Needed

Owner chọn: `APPROVE GAP-114 Gate 2 N-1 C-1 P-1 A-1 R-1 D-1 V-1` (hoặc phương án khác cho từng điểm) / Changes /
Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, phát hành.
