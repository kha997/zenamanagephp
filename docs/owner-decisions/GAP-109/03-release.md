---
work_id: GAP-109
gate: 3
gate_status: awaiting_owner
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_correction_or_defer"
references:
  spec: docs/audits/2026-11-04-gap-109-staff-payroll-evidence.md
  plan: docs/superpowers/plans/2026-11-05-gap-109-staff-payroll.md
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
  created_at: "2026-11-04T22:30:02+07:00"
  updated_at: "2026-11-04T22:30:02+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-109 at subject 4960e6c0a642456653c266b04a9bbd3ab9f28fab (Gate 2 C-1, B-1, H-1, L-1, P-1, Q-1, R-1; exact allowlist; one PR, one implementation commit). One migration creates staff_members (unique tenant + code; FKs to tenants, users, treasury_financial_parties), payroll_periods (unique tenant + period) and payroll_lines (unique period + staff; FKs to tenants, periods, staff); no existing table changes. StaffService: payroll.manage only; code and name required, unique code per tenant; type employee / collaborator; login and Treasury party must belong to the tenant; end date not before start; part-time standard hours 0-744; annual bonus estimate not negative; audited create / update with old and new values. PayrollService: createPeriod (YYYY-MM, 1-31 working days, hours per day 0-24, one per tenant and month) with a line for every person working in the month and bonus accrual = annual estimate / 12; updateLine in draft only (entered items 0 to 9,999,999,999,999.99; missing keeps, empty means zero); net pay = salary + allowance + overtime + bonus paid - employee insurance - PIT - advance - other deductions; company cost = salary + allowance + overtime + employer insurance + other company cost + bonus accrual; standard hours = working days x hours per day or the person's own hours; hourly cost = company cost / standard hours rounded half up to the cent, recomputed on period settings change and frozen at close; close (payroll.close) and reopen with a mandatory reason; markPaid after close only (date, reference); every write in a transaction on the locked period row and audited; integer-cent arithmetic; CSV export with formula-injection guard. Web pages under rbac:payroll.manage (close / reopen rbac:payroll.close) with tenant-scoped lookups (404 across tenants); menu item 'Nhân sự & lương' shown only to payroll.manage holders. Permissions payroll.manage (Admin / owner roles, Finance) and payroll.close (Admin / owner roles) seeded by ZenaPermissionsSeeder, ZenaRbacSeeder and the new PayrollRolePermissionSeeder (DatabaseSeeder). No API, no Treasury posting, no new dependency. Tests: StaffRegisterTest 3 and PayrollPeriodTest 4, red before the code (missing classes) and green after; the 7 also green with DB_CONNECTION=mysql on MySQL 8; migration up, down, up on MySQL 8. Architecture, navigation and Treasury seeder tests 82 green. Unit + Feature + Integration 2941 tests with only the 2 known root-only failures; Feature/Api 824 green (run before a test-only change that inlined the two tests' helpers; the 7 payroll tests re-run green on SQLite and MySQL after it). PHPStan, php-cs-fixer, SSOT test lint, ssot:lint, route guard, deptrac pass. Exact-head CI pending at packet time."
technical_evidence:
  base_sha: "f0fad9ccce66e620829870f9998705b1ca23fedf"
  subject_sha: "4960e6c0a642456653c266b04a9bbd3ab9f28fab"
  implementation_tree_digest: "9eba6a01b8ff6c278e059973e6460e248369d442023f20f7087167f1a35f6d37"
  verified_pr_head_sha: "4960e6c0a642456653c266b04a9bbd3ab9f28fab"
  verified_at: "2026-11-04T22:30:02+07:00"
owner_decision_binding: null
---

# GAP-109 — Gate 3 release decision (staff register and monthly payroll cost)

## Gói quyết định phát hành

**1. Vấn đề là gì?** Chưa có số chi phí thật của từng người theo tháng nên chưa tính được nhân công cho công trình.

**2. Sau thay đổi (Gate 2 C-1, B-1, H-1, L-1, P-1, Q-1, R-1)** — subject `4960e6c0`:

**Menu mới "Nhân sự & lương"** (mục Tài chính). Chỉ người có quyền lương mới thấy.

**Danh sách nhân sự**
- Nhân viên và cộng tác viên, mỗi người có:
  - mã, họ tên;
  - đánh dấu **khối văn phòng** (sau này tính vào chi phí chung);
  - ngày bắt đầu, ngày nghỉ;
  - **giờ chuẩn riêng** cho người làm bán thời gian;
  - **thưởng dự kiến cả năm**;
  - liên kết tài khoản đăng nhập (dùng cho ghi giờ ở phần 2) và đối tác Ngân quỹ (không bắt buộc).
- Không trùng mã trong công ty; mỗi lần thêm / sửa đều ghi nhật ký.

**Bảng lương tháng**
- Tạo bảng lương: chọn tháng và số ngày công chuẩn (mặc định 26 × 8 giờ). Hệ thống tự thêm mọi người đang làm trong tháng.
  Thưởng phân bổ của mỗi người = thưởng năm ÷ 12, sửa được.
- Kế toán nhập từng người:
  - lương, phụ cấp, tăng ca, thưởng thực trả;
  - bảo hiểm phần công ty đóng, chi phí khác;
  - khấu trừ: bảo hiểm người lao động, thuế TNCN, trừ tạm ứng, khấu trừ khác.
- Hệ thống tính:
  - **thực nhận**;
  - **tổng chi phí công ty**;
  - **giờ chuẩn** và **đơn giá giờ**.

  Ví dụ trong test: tổng chi phí 14.950.000 ÷ 208 giờ = 71.875 đ/giờ.
- **Chốt** (chỉ Owner): khoá số liệu và đơn giá giờ. **Mở lại** phải ghi lý do. Sau khi chốt, kế toán **ghi "đã trả"** (ngày,
  số chứng từ).
- Xuất **CSV** cả bảng.

**Quyền**
- `payroll.manage`: Owner, kế toán.
- `payroll.close`: chỉ Owner.
- Người khác không thấy menu và không mở được trang. Công ty khác không truy cập được.

Không đổi bảng cũ, không ghi vào Ngân quỹ, không thêm API hay thư viện. Có 1 migration tạo 3 bảng mới.

**3. Đã kiểm thử** — base `f0fad9cc`; subject `4960e6c0a642456653c266b04a9bbd3ab9f28fab`;
digest `9eba6a01b8ff6c278e059973e6460e248369d442023f20f7087167f1a35f6d37`.
- **7 test mới** (3 nhân sự, 4 bảng lương), hỏng khi chưa có mã, xanh khi có mã. Kiểm tra:
  - nhân sự: thêm / sửa, mã trùng, loại sai, ngày sai, giờ chuẩn sai, thưởng âm, liên kết khác công ty, quyền, nhật ký,
    menu, cách ly công ty;
  - bảng lương:
    - đúng người trong tháng (bỏ người đã nghỉ / chưa vào);
    - thưởng phân bổ 1/12; công thức thực nhận / chi phí / đơn giá giờ, kể cả người bán thời gian và khi đổi số ngày công;
    - số âm / sai bị từ chối; tháng trùng bị từ chối;
    - chốt, mở lại cần lý do, ghi đã trả chỉ sau khi chốt, nhật ký;
    - trang, CSV, quyền, công ty khác bị chặn.
- **MySQL 8 thật:** migration chạy lên, gỡ, rồi chạy lại đều được; 7 test mới xanh khi chạy với MySQL.
- **Test kiến trúc, menu, quyền Ngân quỹ:** 82 test xanh.
- **Unit + Feature + Integration:** 2.941 test, chỉ 2 lỗi có sẵn do chạy bằng root. **Feature/Api:** 824 test xanh.
  - Hai bộ này chạy trước một chỉnh sửa chỉ trong file test: gộp hàm hỗ trợ vào chính 2 file test để không thêm file ngoài
    danh sách đã duyệt.
  - Sau chỉnh sửa đó, 7 test lương chạy lại xanh trên SQLite và MySQL.
- PHPStan, php-cs-fixer, SSOT lint, route guard, deptrac sạch.
- CI trên đúng head: đang chạy lúc lập hồ sơ.

**4. Lưu ý vận hành**
- Sau khi merge cần chạy seeder quyền (hoặc gán tay) để Owner / kế toán có `payroll.manage` / `payroll.close`. Việc này
  thuộc bước triển khai, chưa được duyệt.
- Bảo hiểm và thuế nhập số, hệ thống không tự tính theo luật.
- Trả lương mới chỉ **ghi nhận**, chưa trừ tiền ví Ngân quỹ (phần 4).

**5. Ngoài phạm vi** — ghi giờ (phần 2); nhân công theo công trình (phần 3); chi phí chung, Ngân quỹ cấp công ty, lời lỗ
công trình (phần 4); tự tính thuế / bảo hiểm; phiếu lương; file chi lương ngân hàng; API; deploy.

**6. Rủi ro còn lại** — Thấp:
- chỉ thêm bảng và trang mới, không đụng số liệu cũ;
- dữ liệu lương nhạy cảm được chặn bằng quyền riêng.

**7. Hoàn tác** — revert PR. Migration `down()` xoá 3 bảng mới (mất danh sách nhân sự và bảng lương đã nhập). Hai quyền mới
còn trong bảng quyền nhưng không còn trang nào dùng.

**8. Đề xuất** — Phát hành (merge).

## Decision Needed

Owner chọn: `APPROVE GAP-109 Gate 3` / Correction / Defer.

## What the owner is NOT being asked to decide

Deploy, phát hành production, chạy seeder trên môi trường thật.
