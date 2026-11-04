---
work_id: GAP-109
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-11-04-gap-109-staff-payroll-evidence.md
  plan: null
  branch: docs/GAP-109-staff-payroll
  pr: https://github.com/kha997/zenamanagephp/pull/399
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
  created_at: "2026-11-04T20:20:00+07:00"
  updated_at: "2026-11-04T20:20:00+07:00"
generated_by: agent
---

# GAP-109 — Staff register and monthly payroll cost: Gate 2 design

## Owner Summary

Anh/chị cần chọn **7 điểm**:

1. **Các khoản trong bảng lương** — đề xuất **C-1**: kế toán **nhập số** từng khoản, hệ thống chỉ cộng trừ. Không tự tính
   bảo hiểm / thuế theo luật.
2. **Thưởng lớn** (tháng 13, thưởng dự án) — đề xuất **B-1**: khai "thưởng dự kiến cả năm" cho từng người, mỗi tháng tự
   cộng 1/12 vào chi phí. Khoản thưởng thực trả chỉ tính vào tiền thực nhận.
3. **Giờ chuẩn** — đề xuất **H-1**: mỗi tháng nhập số ngày công chuẩn (mặc định 26) × 8 giờ; người làm bán thời gian có
   số giờ riêng.
4. **Chốt tháng** — đề xuất **L-1**: kế toán nhập, Owner chốt; chốt rồi không sửa được; mở lại phải ghi lý do.
5. **Chi trả lương** — đề xuất **P-1**: GAP này chỉ ghi nhận "đã trả" (ngày, số chứng từ). Ghi chi lương vào Ngân quỹ cấp
   công ty làm ở phần 4.
6. **Ai xem lương** — đề xuất **Q-1**: hai quyền mới: `payroll.manage` (Owner + kế toán) và `payroll.close` (chỉ
   Owner).
7. **Phát hành** — đề xuất **R-1**: một PR, một Gate 3.

## Điểm 1 — Các khoản trong bảng lương

| Phương án | Nội dung |
|---|---|
| **C-1 (đề xuất)** | Mỗi người, mỗi tháng, kế toán nhập: lương cơ bản, phụ cấp, tăng ca, thưởng thực trả, BHXH / BHYT / BHTN **phần công ty đóng**, chi phí khác công ty chịu (công tác phí, đồng phục…); khấu trừ: bảo hiểm phần người lao động, thuế TNCN, trừ tạm ứng, khấu trừ khác. Hệ thống tự tính **thực nhận** và **tổng chi phí công ty**. |
| C-2 | Như C-1, thêm tự tính bảo hiểm theo tỷ lệ (công ty 21,5%, người lao động 10,5%) trên "lương đóng bảo hiểm". Thuế TNCN vẫn nhập tay. Phải sửa khi luật đổi tỷ lệ hoặc trần lương. |

Theo C-1:
- **Thực nhận** = lương + phụ cấp + tăng ca + thưởng thực trả − các khoản khấu trừ.
- **Tổng chi phí công ty trong tháng** = lương + phụ cấp + tăng ca + bảo hiểm phần công ty + chi phí khác + **thưởng
  phân bổ của tháng** (điểm 2).

## Điểm 2 — Thưởng lớn

| Phương án | Nội dung |
|---|---|
| **B-1 (đề xuất)** | Mỗi người có "thưởng dự kiến cả năm" (sửa được). Mỗi tháng tự cộng 1/12 vào tổng chi phí. Thưởng thực trả chỉ vào thực nhận, không cộng thêm vào chi phí. Chi phí công trình mỗi tháng ổn định. |
| B-2 | Thưởng tính vào chi phí đúng tháng trả. Đơn giản hơn, nhưng công trình chạy tháng có thưởng sẽ bị lỗ ảo. |

## Điểm 3 — Giờ chuẩn

| Phương án | Nội dung |
|---|---|
| **H-1 (đề xuất)** | Khi tạo bảng lương tháng, nhập số ngày công chuẩn (mặc định 26, sửa được) × 8 giờ. Người làm bán thời gian có "giờ chuẩn / tháng" riêng. **Đơn giá giờ** = tổng chi phí công ty ÷ giờ chuẩn của người đó, lưu cố định khi chốt tháng. |
| H-2 | Giờ chuẩn cố định cho mọi tháng (ví dụ 208 giờ). Đơn giản, nhưng tháng có ngày lễ / Tết bị sai lệch. |

## Điểm 4 — Chốt tháng

| Phương án | Nội dung |
|---|---|
| **L-1 (đề xuất)** | Bảng lương tháng: nháp (kế toán nhập / sửa) → **đã chốt** (Owner). Đã chốt thì không sửa được, đơn giá giờ cố định. Owner mở lại được, bắt buộc ghi lý do. Mọi lần sửa / chốt / mở đều ghi nhật ký. |
| L-2 | Không cho mở lại. Sai thì điều chỉnh vào tháng sau. |

## Điểm 5 — Chi trả lương

| Phương án | Nội dung |
|---|---|
| **P-1 (đề xuất)** | Sau khi chốt, kế toán đánh dấu từng dòng "đã trả" (ngày, số chứng từ / UNC). Không tạo bút toán Ngân quỹ. Ghi chi lương vào Ngân quỹ **cấp công ty** để ở phần 4, cùng tiền nhà, điện nước. |
| P-2 | Làm luôn Ngân quỹ cấp công ty trong GAP này để chi lương ra từ ví công ty. Thay đổi lớn: mọi chứng từ Ngân quỹ hiện bắt buộc thuộc một dự án. |

## Điểm 6 — Ai xem lương

| Phương án | Nội dung |
|---|---|
| **Q-1 (đề xuất)** | Hai quyền mới: `payroll.manage` (Owner, kế toán: xem / sửa danh sách nhân sự và bảng lương nháp, đánh dấu đã trả) và `payroll.close` (chỉ Owner: chốt / mở lại). Người khác không thấy trang lương. Sau này quản lý dự án chỉ thấy tổng nhân công của công trình. |
| Q-2 | Chỉ Owner xem và sửa. Kế toán không dùng được. |

## Điểm 7 — Phát hành

| Phương án | Nội dung |
|---|---|
| **R-1 (đề xuất)** | Một PR, một Gate 3. |
| R-2 | Hai PR: danh sách nhân sự trước, bảng lương sau. |

## Thiết kế chi tiết (theo C-1, B-1, H-1, L-1, P-1, Q-1, R-1)

### Dữ liệu (một migration, chỉ tạo bảng mới)
**`staff_members`** — danh sách nhân sự của công ty.
- `id`, `tenant_id`, `code`, `name`;
- `staff_type`: nhân viên / cộng tác viên;
- `is_overhead`: thuộc khối văn phòng hay không (phần 4 đưa vào chi phí chung);
- `user_id` (tài khoản đăng nhập, cho phép trống — dùng cho ghi giờ ở phần 2);
- `financial_party_id` (đối tác Ngân quỹ, cho phép trống);
- `standard_hours_override` (giờ chuẩn riêng / tháng, cho phép trống);
- `annual_bonus_estimate` (thưởng dự kiến cả năm, mặc định 0);
- `start_date`, `end_date`, `is_active`, timestamps.
- Unique (`tenant_id`, `code`).

**`payroll_periods`** — một bảng lương mỗi tháng.
- `id`, `tenant_id`, `period` (`YYYY-MM`), `working_days` (mặc định 26), `hours_per_day` (mặc định 8);
- `status` (draft / closed), `closed_at`, `closed_by`, timestamps.
- Unique (`tenant_id`, `period`).

**`payroll_lines`** — mỗi người một dòng trong bảng lương tháng.
- `id`, `tenant_id`, `payroll_period_id`, `staff_member_id`;
- các khoản ở C-1;
- `bonus_accrual` (1/12 thưởng năm, chép lúc tạo dòng, sửa được khi nháp);
- `net_pay`, `company_cost`, `standard_hours`, `hourly_cost` (tính lại mỗi lần lưu, cố định khi chốt);
- `paid_at`, `payment_reference`, `note`, timestamps.
- Unique (`payroll_period_id`, `staff_member_id`).

Tiền lưu `decimal(15,2)`; số âm bị từ chối.

### Dịch vụ
**`StaffService`**: tạo / sửa / ngưng nhân sự, với các ràng buộc:
- mã không trùng trong công ty;
- tài khoản đăng nhập và đối tác Ngân quỹ phải cùng công ty;
- ngày nghỉ phải sau ngày bắt đầu.

**`PayrollService`**
- `createPeriod(period, working_days)`: tạo bảng lương nháp, sinh một dòng cho mỗi người còn làm trong tháng; chép thưởng
  phân bổ = thưởng năm ÷ 12.
- `updateLine(...)`: chỉ khi nháp; tính lại thực nhận, chi phí công ty, giờ chuẩn và đơn giá giờ.
- `close(period)` / `reopen(period, reason)`: khoá dòng bảng lương, ghi nhật ký.
- `markPaid(line, date, reference)`: chỉ khi đã chốt.

Mọi thao tác chạy trong transaction, khoá dòng bảng lương tháng, và ghi `AuditLog` kèm giá trị cũ / mới.

### Giao diện (web)
- Menu mới **"Nhân sự & lương"**, chỉ hiện với người có `payroll.manage`.
- Trang **Nhân sự**: danh sách, thêm / sửa / ngưng.
- Trang **Bảng lương tháng**:
  - chọn tháng, nhập số ngày công;
  - bảng nhập từng người, có tổng cộng;
  - cột **Tổng chi phí công ty** và **Đơn giá giờ**;
  - nút **Chốt** / **Mở lại (lý do)**, đánh dấu đã trả;
  - xuất CSV.

Không làm API trong GAP này.

### Quyền
- `payroll.manage`, `payroll.close` thêm vào danh sách quyền (`ZenaPermissionsSeeder`) và gán vai trò qua
  `PayrollRolePermissionSeeder` mới:
  - Owner / Admin: cả hai quyền;
  - kế toán (Finance): `payroll.manage`.
- Mọi route kiểm tra quyền và cách ly theo công ty.

### Test (đỏ trước / xanh sau)
**Nhân sự (`StaffRegisterTest`)**
- Tạo / sửa / ngưng.
- Mã trùng, liên kết sai công ty, ngày sai bị từ chối.
- Cách ly giữa các công ty; quyền.

**Bảng lương (`PayrollPeriodTest`)**
- Tạo tháng sinh đúng các dòng; thưởng phân bổ = năm ÷ 12.
- Thực nhận, chi phí công ty, đơn giá giờ đúng, kể cả người có giờ chuẩn riêng.
- Số âm bị từ chối.
- Chốt rồi không sửa được; mở lại cần lý do; có nhật ký.
- Đánh dấu đã trả chỉ khi đã chốt.
- Tháng trùng bị từ chối.
- Trang, quyền (người không có quyền không thấy menu / trang), xuất CSV, cách ly công ty.

**Migration**: chạy lên / gỡ / lên trên MySQL 8 thật. Hai file test mới cũng chạy xanh với MySQL.

### Files (allowlist)
- Mới:
  - `database/migrations/2026_11_05_100000_create_staff_and_payroll_tables.php`;
  - `app/Models/Payroll/StaffMember.php`, `PayrollPeriod.php`, `PayrollLine.php`;
  - `app/Services/Payroll/StaffService.php`, `PayrollService.php`, `PayrollRuleViolation.php`;
  - `app/Http/Controllers/Web/Payroll/StaffPageController.php`, `PayrollPageController.php`;
  - `resources/views/payroll/staff.blade.php`, `staff-edit.blade.php`, `index.blade.php`, `period.blade.php`;
  - `database/seeders/PayrollRolePermissionSeeder.php`;
  - `tests/Feature/Payroll/StaffRegisterTest.php`, `tests/Feature/Payroll/PayrollPeriodTest.php`.
- Sửa:
  - `database/seeders/ZenaPermissionsSeeder.php`, `ZenaRbacSeeder.php`, `DatabaseSeeder.php` (quyền mới);
  - `routes/web.php`;
  - `app/Support/Navigation/OperatorNavigationDefinition.php` (menu);
  - `tests/Feature/Architecture/RouteRbacCodesSeededTest.php` (đăng ký seeder mới), cùng các test kiến trúc / điều hướng
    chỉ khi chúng liệt kê cố định quyền, route hoặc menu;
  - `deptrac.yaml` chỉ nếu tầng `Payroll` mới cần khai báo.
- Plan `docs/superpowers/plans/2026-11-05-gap-109-staff-payroll.md`; packet và evidence GAP-109.

Không phụ thuộc mới, không đổi bảng cũ, không đụng sổ Ngân quỹ.

## Ngoài phạm vi

Ghi giờ (phần 2); nhân công theo công trình (phần 3); chi phí chung, Ngân quỹ cấp công ty và lời lỗ công trình (phần 4);
tự tính thuế / bảo hiểm; phiếu lương; file chi lương ngân hàng; API; deploy.

## Verification required at Gate 3

Đỏ trước / xanh sau; Unit + Feature + Integration và `tests/Feature/Api` không lỗi mới; migration chạy trên MySQL thật;
PHPStan, php-cs-fixer, SSOT lint, route guard, deptrac sạch; CI trên đúng head xanh.

## Rollback

Revert squash commit. Migration `down()` xoá ba bảng mới (mất danh sách nhân sự và bảng lương đã nhập). Hai quyền mới còn
trong bảng quyền nhưng không còn route nào dùng.

## Decision Needed

Owner chọn: `APPROVE GAP-109 Gate 2 C-1 B-1 H-1 L-1 P-1 Q-1 R-1` (hoặc phương án khác cho từng điểm) / Changes /
Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, phát hành.
