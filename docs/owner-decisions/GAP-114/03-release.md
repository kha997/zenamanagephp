---
work_id: GAP-114
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
  spec: docs/audits/2026-11-05-gap-114-overhead-project-pnl-evidence.md
  plan: docs/superpowers/plans/2026-11-05-gap-114-overhead-project-pnl.md
  branch: docs/GAP-114-overhead-project-pnl
  pr: https://github.com/kha997/zenamanagephp/pull/404
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
  created_at: "2026-11-05T23:33:15+07:00"
  updated_at: "2026-11-05T23:33:15+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-114 at subject 8943eebb6ac90831da9e40aecf6ae018890ccb8f (Gate 2 N-1, C-1, P-1, A-1, R-1, D-1, V-1; exact allowlist; one PR, one implementation commit). One migration creates overhead_periods (tenant + period unique, draft / closed), overhead_cost_lines (period, category, description, amount) and overhead_allocations (period, nullable project, minutes, amount; unique period + project); no existing table changes. OverheadService: payroll.manage adds / edits / deletes lines of a draft month (fixed categories, amount > 0 with two decimals, description required) under a lock on the month row, audited; close by payroll.close locks the payroll month row then the overhead row, requires the payroll month closed, computes pool = lines + GAP-113 labour outside projects (stored rows of the closed payroll month), splits it by approved project minutes from GAP-113 in whole dong by largest remainder (cents, or the whole pool when no project minutes, in the row without project), stores the rows and audits pool / lines / labour outside / unallocated; reopen needs a reason and removes the rows. PayrollService::reopen refuses while that month's overhead is closed (shared lock on the overhead row). ProjectPnlService: per project revenue = paid contract payments by paid date (R-1), direct cost = posted Treasury expense documents by transaction date or posting date (reversed documents excluded) (D-1), contract expenses for reference only, labour (GAP-113), overhead; profit, margin; month() from stored rows when the overhead month is closed and as preview otherwise; cumulative() over closed overhead months with live contract value and projected profit; company totals incl. unallocated overhead; CSV with formula guard; fixed query count. Web: overhead page and P&L page (rbac:payroll.manage; close / reopen rbac:payroll.close; tenant-scoped lookups, 404 across tenants), links from the payroll list; OPPM shows the closed P&L summary to payroll.manage holders and the project's PM only (V-1). No new permission, dependency or menu item. Tests: OverheadTest 3, ProjectPnlTest 3, red before (classes / routes missing) and green after; payroll, OPPM and time tracking suites 58 green; payroll + time tracking 35 green with DB_CONNECTION=mysql on MySQL 8; migration up, down, up on MySQL 8. PHPStan, php-cs-fixer, SSOT test lint, ssot:lint, deptrac pass. Full-suite and exact-head CI results are recorded in the body."
technical_evidence:
  base_sha: "b4a9174f104a84be8e1af30c3d8dd2fbb4eb263a"
  subject_sha: "8943eebb6ac90831da9e40aecf6ae018890ccb8f"
  implementation_tree_digest: "a94c6d5d7aa9df54bf6b7acbf8a330851d7507299e04b8471532181996d5741a"
  verified_pr_head_sha: "8943eebb6ac90831da9e40aecf6ae018890ccb8f"
  verified_at: "2026-11-05T23:33:15+07:00"
owner_decision_binding: null
---

# GAP-114 — Gate 3 release decision (overhead allocation and project P&L)

## Gói quyết định phát hành

**1. Vấn đề là gì?** Đã có chi phí nhân công từng công trình nhưng chưa tính chi phí chung và chưa ra lời lỗ thật.

**2. Sau thay đổi (Gate 2 N-1, C-1, P-1, A-1, R-1, D-1, V-1)** — subject `8943eebb`:

**Trang "Chi phí chung"** (mở từ trang Nhân sự & lương)
- Chọn tháng, nhập từng khoản:
  - loại: thuê văn phòng, điện nước, internet / điện thoại, phần mềm, văn phòng phẩm, khấu hao, khác;
  - mô tả và số tiền.
- Sửa / xoá được khi tháng chưa chốt. Có nhật ký.
- Trang hiện:
  - tổng các khoản đã nhập;
  - lương chưa vào công trình (GAP-113);
  - tổng chi phí chung;
  - bảng tạm tính chia cho từng công trình.
- **Chốt chi phí chung** (Owner): chỉ chốt được sau khi đã chốt lương tháng đó. Lúc chốt, hệ thống chia theo tỷ lệ giờ đã
  duyệt và lưu cố định.
  - Làm tròn đồng; tổng khớp.
  - Tháng không có giờ công trình thì để "chưa phân bổ".
- Mở lại: cần lý do, xoá số đã chia. **Không mở lại được lương** khi chi phí chung tháng đó đã chốt; phải mở lại chi phí
  chung trước.

**Trang "Lời lỗ công trình"** (chỉ Owner / kế toán)
- Theo tháng, từng công trình:
  - doanh thu đã thu (các đợt thanh toán đã thu, theo ngày thu);
  - chi qua Ngân quỹ (đã ghi sổ, không tính khoản đã đảo);
  - nhân công;
  - chi phí chung;
  - lời / lỗ, tỷ suất;
  - chi phí ghi nhận theo hợp đồng (chỉ để đối chiếu).
- Dòng tổng toàn công ty, gồm chi phí chung chưa phân bổ.
- Tháng chưa chốt chi phí chung ghi **"Tạm tính"**.
- **Luỹ kế** các tháng đã chốt: lời lỗ theo tiền đã thu, giá trị hợp đồng, **lời lỗ dự kiến**.
- Xuất CSV.

**Trang OPPM:** dòng **"Lời lỗ (đã chốt)"** và **"Lời lỗ dự kiến theo hợp đồng"**. Chỉ Owner / kế toán và PM của công trình
đó thấy.

Không thêm quyền, thư viện hay mục menu. Không đổi Ngân quỹ, bảng giờ hay cách tính lương; chỉ thêm điều kiện chặn mở lại
lương. Có 1 migration tạo 3 bảng mới.

**3. Đã kiểm thử** — base `b4a9174f`; subject `8943eebb6ac90831da9e40aecf6ae018890ccb8f`; digest
`a94c6d5d7aa9df54bf6b7acbf8a330851d7507299e04b8471532181996d5741a`.
- **6 test mới**, hỏng khi chưa có mã, xanh khi có mã.
- **Chi phí chung (3):**
  - nhập / sửa / xoá; khoá khi đã chốt;
  - chưa chốt lương thì không chốt được;
  - chia theo giờ, gộp lương chưa vào công trình, làm tròn khớp tổng;
  - không mở lại lương khi đã chốt;
  - mở lại xoá số, chốt lại chia lại;
  - tháng không có giờ công trình;
  - kiểm tra dữ liệu nhập, quyền, trang, cách ly công ty.
- **Lời lỗ (3):**
  - doanh thu theo ngày thu;
  - chi Ngân quỹ không tính khoản đã đảo và khoản tháng khác;
  - nhân công, chi phí chung;
  - lời lỗ tháng, tổng công ty, luỹ kế, dự kiến;
  - tạm tính trước khi chốt;
  - quyền trang và dòng OPPM, CSV;
  - số truy vấn không tăng theo số công trình.
- Bộ test lương, OPPM, ghi giờ: 58 test xanh.
- **MySQL 8 thật:**
  - migration lên / gỡ / lên được;
  - 35 test lương + ghi giờ (gồm 6 test mới) xanh trên MySQL.
- PHPStan, php-cs-fixer, SSOT lint, deptrac sạch.
- Unit + Feature + Integration, Feature/Api và CI trên đúng head: xem mục 9.

**4. Lưu ý vận hành**
- Thứ tự hằng tháng:
  1. duyệt hết giờ;
  2. chốt lương;
  3. nhập đủ chi phí chung;
  4. chốt chi phí chung.
- Lời lỗ dựa trên **tiền đã chi qua Ngân quỹ**. Không trả lương qua Ngân quỹ, nếu không sẽ bị tính hai lần với nhân công.
- Doanh thu tính theo đợt thanh toán đã đánh dấu "đã thu" kèm ngày thu.

**5. Ngoài phạm vi**
- Bút toán kế toán, thuế / VAT.
- Ngân sách, dự báo.
- Chứng từ Ngân quỹ cấp công ty.
- Deploy.

**6. Rủi ro còn lại** — Thấp.
- Chỉ thêm bảng và trang mới.
- Chặn mở lại lương chỉ có hiệu lực khi chi phí chung đã chốt.
- Hai người cùng nhập khoản đầu tiên của một tháng mới đúng cùng lúc: một người có thể gặp lỗi và phải nhập lại.

**7. Hoàn tác**
- Revert PR.
- Migration `down()` xoá 3 bảng mới. Mất chi phí chung đã nhập và số đã chia; lương, giờ, Ngân quỹ không đổi.

**8. Đề xuất** — Phát hành (merge).

**9. Kết quả chạy toàn bộ** — RESULTS_PENDING

## Decision Needed

Owner chọn: `APPROVE GAP-114 Gate 3` / Correction / Defer.

## What the owner is NOT being asked to decide

Deploy, phát hành production.
