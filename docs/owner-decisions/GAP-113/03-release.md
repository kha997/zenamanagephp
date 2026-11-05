---
work_id: GAP-113
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
  spec: docs/audits/2026-11-05-gap-113-project-labour-cost-evidence.md
  plan: docs/superpowers/plans/2026-11-05-gap-113-project-labour-cost.md
  branch: docs/GAP-113-project-labour-cost
  pr: https://github.com/kha997/zenamanagephp/pull/403
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
  created_at: "2026-11-05T18:43:29+07:00"
  updated_at: "2026-11-05T18:43:29+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-113 at subject ed18bad4bdf12643c0d663d8421958d51d88ef1f (Gate 2 S-1, H-1, R-1, O-1, D-1, V-1; exact allowlist; one PR, one implementation commit). One migration creates labour_cost_allocations (tenant, payroll period, staff member, nullable project, minutes, amount; FKs tenant / period / staff / project with cascade; unique period + staff + project; index tenant + project); no existing table changes. LabourCostService: compute() from the period's payroll lines (company cost, standard hours) and approved time entries of the month grouped by user and project (three queries); R-1 project amount = company cost x approved project minutes / max(total approved minutes, standard minutes), in 1/5-minute units; project total rounded half up to whole dong and never above the cost floored to whole dong, split across projects by largest remainder; the rest (non-project hours, idle time, cents) is the row without project; O-1 is_overhead staff, staff without a login or without project hours put the whole cost outside projects; a login linked to two staff records counts once (first by code); approved hours of a login without a payroll line in the month are returned as unpriced. allocate() replaces the stored rows and audits labour_cost.allocated (project and outside totals); clear() deletes them and audits labour_cost.cleared. PayrollService: close() refuses while the month has running or submitted time entries (H-1), then recomputes lines and calls allocate() in the same transaction on the locked period row (S-1); reopen() calls clear(). LabourCostReportService: month() from stored rows when closed, computed preview when draft, per project and per person, outside total, unpriced hours; cumulative() and projectTotal() over closed months only; pendingByPerson() for the payroll page; CSV with formula guard; fixed query count. Web: labour cost page (rbac:payroll.manage, tenant-scoped period lookup, 404 across tenants), CSV export, links from payroll list and month pages; month page lists running / waiting hours per person with a link to time review and hides the close button; OPPM page shows the closed-month labour cost total to payroll.manage holders and the project's PM only (V-1), snapshots do not carry it. No new permission, no new dependency, no menu item. Tests: LabourCostAllocationTest 5, LabourCostReportTest 5, red before (classes / routes missing) and green after; payroll, OPPM and time tracking suites 51 green; the payroll suite (17) also green with DB_CONNECTION=mysql on MySQL 8; migration up, down, up on MySQL 8. PHPStan, php-cs-fixer, SSOT test lint, ssot:lint, deptrac pass. Full-suite and exact-head CI results are recorded in the body."
technical_evidence:
  base_sha: "da686ca5faf42f8a02d4daeab7ccb3d81d956089"
  subject_sha: "ed18bad4bdf12643c0d663d8421958d51d88ef1f"
  implementation_tree_digest: "c035f00da04cb9cfde51f01e16596b62da9affc0de1b1ce36e4af27ffa9e305b"
  verified_pr_head_sha: "ed18bad4bdf12643c0d663d8421958d51d88ef1f"
  verified_at: "2026-11-05T18:43:29+07:00"
owner_decision_binding: null
---

# GAP-113 — Gate 3 release decision (labour cost per project)

## Gói quyết định phát hành

**1. Vấn đề là gì?** Đã có lương (GAP-109) và giờ làm (GAP-112) nhưng chưa biết mỗi công trình tốn bao nhiêu tiền nhân
công.

**2. Sau thay đổi (Gate 2 S-1, H-1, R-1, O-1, D-1, V-1)** — subject `ed18bad4`:

**Chốt lương**
- **Không cho chốt** khi trong tháng còn giờ đang chạy hoặc chờ duyệt. Trang bảng lương liệt kê theo người, có link sang
  trang duyệt giờ, và ẩn nút chốt.
- Khi chốt, hệ thống tính và **lưu cố định** chi phí nhân công theo người × công trình.
- **Mở lại** tháng thì xoá số đó; **chốt lại** thì tính lại.
- Có nhật ký cho cả tính và xoá.

**Cách tính**
- Tiền công trình = chi phí công ty của người đó × giờ duyệt trên công trình ÷ max(tổng giờ đã duyệt, giờ chuẩn).
  - Ghi ít hơn giờ chuẩn: công trình nhận đúng giờ × chi phí 1 giờ.
  - Ghi nhiều hơn giờ chuẩn: chia theo tỷ lệ, tổng không vượt chi phí thật.
- Tiền công trình làm tròn đồng; tổng các dòng khớp đúng chi phí công ty.
- Phần còn lại (họp, hành chính, giờ trống, số lẻ) để ở dòng **"chưa vào công trình"**.
- Toàn bộ chi phí vào "chưa vào công trình" khi:
  - người có cờ "văn phòng";
  - người chưa nối tài khoản đăng nhập;
  - người không có giờ công trình.
- Giờ của tài khoản không có dòng lương trong tháng hiện riêng là **"giờ chưa có đơn giá"**, không ra tiền.

**Trang "Chi phí nhân công"** (chỉ Owner / kế toán — `payroll.manage`)
- Mở từ trang Nhân sự & lương và trang từng tháng.
- Chọn tháng; xem theo công trình và chi tiết theo người; tổng vào công trình / chưa vào công trình.
- Tháng chưa chốt ghi **"Tạm tính"**.
- Luỹ kế theo công trình qua các tháng đã chốt; xuất CSV.

**Trang OPPM của công trình:** dòng **"Chi phí nhân công (đã chốt)"** (tiền, giờ, số tháng).
- Chỉ Owner / kế toán và PM của công trình đó thấy dòng này; không có chi tiết từng người.
- Bản chụp OPPM cũ không đổi.

Không thêm quyền, thư viện hay mục menu. Không đổi cách tính lương hay bảng giờ. Có 1 migration tạo bảng
`labour_cost_allocations`.

**3. Đã kiểm thử** — base `da686ca5`; subject `ed18bad4bdf12643c0d663d8421958d51d88ef1f`; digest
`c035f00da04cb9cfde51f01e16596b62da9affc0de1b1ce36e4af27ffa9e305b`.
- **10 test mới**, hỏng khi chưa có mã, xanh khi có mã.
- **Phân bổ (5):**
  - ít / nhiều hơn giờ chuẩn;
  - giờ không thuộc công trình;
  - người văn phòng;
  - người không tài khoản;
  - giờ chưa có đơn giá;
  - chỉ giờ đã duyệt, đúng tháng;
  - chặn chốt khi còn giờ treo;
  - mở lại xoá, chốt lại tính lại;
  - làm tròn đồng khớp tổng;
  - nhật ký;
  - cách ly công ty.
- **Báo cáo (5):**
  - tạm tính → đã chốt, luỹ kế;
  - danh sách giờ treo trên trang lương;
  - quyền trang và dòng OPPM;
  - CSV, cách ly công ty;
  - số truy vấn không tăng theo số dòng.
- Bộ test lương, OPPM, ghi giờ: 51 test xanh.
- **MySQL 8 thật:**
  - migration lên / gỡ / lên được;
  - 17 test lương (gồm 10 test mới) xanh trên MySQL.
- PHPStan, php-cs-fixer, SSOT lint, deptrac sạch.
- Unit + Feature + Integration, Feature/Api và CI trên đúng head: xem mục 9.

**4. Lưu ý vận hành**
- Cần **nối tài khoản đăng nhập** cho từng nhân sự (trang Nhân sự) thì giờ mới thành tiền.
- PM cần duyệt hết giờ của tháng trước khi Owner chốt lương.
- Các tháng đã chốt trước khi có tính năng này chưa có số. Muốn có thì mở lại tháng đó rồi chốt lại.

**5. Ngoài phạm vi**
- Phân bổ chi phí chung và lời lỗ công trình (phần 4).
- Bút toán kế toán, ghi sổ Ngân quỹ.
- Deploy.

**6. Rủi ro còn lại** — Thấp.
- Chỉ thêm bảng và trang mới.
- Chốt lương thêm điều kiện chặn (H-1) và bước tính.
- Còn một khe hở có sẵn từ GAP-112: kiểm tra khoá tháng khi duyệt giờ không khoá dòng. Nếu ai đó duyệt giờ đúng lúc Owner
  bấm chốt, dòng giờ đó có thể không vào số đã chốt. Mở lại rồi chốt lại sẽ tính đúng.

**7. Hoàn tác**
- Revert PR.
- Migration `down()` xoá bảng `labour_cost_allocations`. Mất số đã phân bổ, nhưng tính lại được từ lương và giờ.

**8. Đề xuất** — Phát hành (merge).

**9. Kết quả chạy toàn bộ** — đang chạy lúc lập hồ sơ; sẽ cập nhật trước khi xin duyệt.

## Decision Needed

Owner chọn: `APPROVE GAP-113 Gate 3` / Correction / Defer.

## What the owner is NOT being asked to decide

Deploy, phát hành production.
