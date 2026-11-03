---
work_id: GAP-104
gate: 3
gate_status: approved
technical_readiness:
  value: ready
  generated_by: engineering_evidence
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
  recorded_at: "2026-11-03T21:04:29+07:00"
  owner_response_reference: "Owner Gate-3 decision in-session on 2026-11-03, verbatim: 'APPROVE GAP-104 Gate 3'. Given on the packet at PR head f2d8d973047d46a8a6299284bae84164d561ac27; bound to implementation subject 08ddfb9efe206e4adb502ba536badc2805d2829a and implementation-tree digest 76dd5c6e538a22c0178333e498b60eff2f9dca5195ecb6176e0167a22caf7c5d (recomputed at recording time, zero drift). Merge is covered by the Owner's standing in-session instruction of 2026-09-28; no deployment or release authorized."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-03T21:03:19+07:00"
  updated_at: "2026-11-03T21:04:29+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-104 at subject 08ddfb9efe206e4adb502ba536badc2805d2829a (Gate 2 V-1, W-A, T-1, R-1; exact allowlist). New read-only page GET /app/projects/{project}/oppm (rbac:project.view, project loaded inside the user's tenant, 404 otherwise) via Web\\ProjectOppmController and App\\Services\\ProjectOppm\\ProjectOppmReadService: header with Service Lines (unclassified when none, unconfirmed marked); KPIs from ProjectHealthReadService (progress with basis, delay, milestones, attention) called with the new includeTreasuryAlerts=false flag so the Treasury dashboard is never built; work groups = root components ordered by start date, max 12, descendant-component tasks rolled up in memory, CSS timeline relative to project dates; task-status summary when there are no components; blocked tasks / overdue tasks / blocked design items capped at 10 with totals; finance block with budget, contract value, cash received, cost recorded labelled not cash paid, no profit figure; Treasury held/posted expenses only with treasury.view-project; A4 landscape @media print layout; 'OPPM mot trang' button on the project page. Fixed query count independent of task/component count; no migration, no new permission, no writes. Components have no owner column, so the work-group table has no owner column. A user without project.view is redirected back with an error by the existing rbac middleware (web behavior), not shown a 403 page. New ProjectOppmPageTest (12 tests) and one new ProjectHealthReadServiceTest case: red before (13 errors: route and flag missing), green after. Unit + Feature + Integration 2890 tests with only the 2 known root-only failures; Feature/Api 824 green. PHPStan, php-cs-fixer, SSOT test lint, ssot:lint, route guard, deptrac pass. Exact-head CI pending at packet time."
technical_evidence:
  base_sha: "052a5aa39813336ff716919138b8e59945ae8381"
  subject_sha: "08ddfb9efe206e4adb502ba536badc2805d2829a"
  implementation_tree_digest: "76dd5c6e538a22c0178333e498b60eff2f9dca5195ecb6176e0167a22caf7c5d"
  verified_pr_head_sha: "08ddfb9efe206e4adb502ba536badc2805d2829a"
  verified_at: "2026-11-03T21:03:19+07:00"
owner_decision_binding:
  implementation_tree_digest: "76dd5c6e538a22c0178333e498b60eff2f9dca5195ecb6176e0167a22caf7c5d"
  decision_recorded_at: "2026-11-03T21:04:29+07:00"
---

# GAP-104 — Gate 3 release decision (Project OPPM Phase 1, read-only)

## OWNER GATE 3: APPROVED

Owner approved Gate 3 in-session on 2026-11-03 ("APPROVE GAP-104 Gate 3"), bound to implementation subject
`08ddfb9efe206e4adb502ba536badc2805d2829a` and implementation-tree digest `76dd5c6e538a22c0178333e498b60eff2f9dca5195ecb6176e0167a22caf7c5d`. No deployment or release is authorized.

## Gói quyết định phát hành

**1. Vấn đề là gì?** Muốn biết một dự án đang thế nào, Owner/PM phải mở nhiều trang rồi tự ghép thông tin (issue #248).

**2. Sau thay đổi (Gate 2 V-1, W-A, T-1, R-1)** — subject `08ddfb9e`:

Trang dự án có nút **"OPPM một trang"**, mở trang chỉ đọc `/app/projects/{id}/oppm` gồm:
- **Thông tin dự án:** mã, trạng thái, khách hàng, PM, ngày bắt đầu/kết thúc, loại dịch vụ. Dự án chưa phân loại ghi
  "Chưa phân loại"; phân loại chưa xác nhận ghi "(chưa xác nhận)".
- **Chỉ số điều hành:** tiến độ (kèm cơ sở tính), thời hạn so với kế hoạch gốc, mốc, số việc cần chú ý. Tất cả lấy từ bộ
  đọc chung GAP-103; thiếu dữ liệu ghi "Chưa có dữ liệu".
- **Nhóm công việc:** các hạng mục gốc, tối đa 12, mỗi dòng có trạng thái, %, thanh thời gian, chi phí kế hoạch / ghi nhận
  và số việc bị chặn / quá hạn (tính cả việc thuộc hạng mục con). Dự án không có hạng mục: hiện số task theo trạng thái.
- **Vướng mắc và trễ hạn:** việc bị chặn (ghi chú, người chặn, từ ngày), việc quá hạn, hạng mục thiết kế bị chặn. Mỗi
  loại tối đa 10 dòng, kèm tổng số.
- **Hợp đồng và tài chính:** ngân sách, giá trị hợp đồng, đã thu, chi phí ghi nhận ("không phải tiền đã chi"). Không có
  số lãi.
  - Phần Ngân quỹ chỉ hiện với người có quyền Ngân quỹ, kèm nút "Xem Ngân quỹ".
  - Trang không đếm cảnh báo Ngân quỹ, nên không chậm dần theo số khoản tạm ứng.
- **Nút "In":** bố cục A4 ngang, ẩn menu và các nút.

Quyền: phải có `project.view` và cùng tenant. Không migration, không thêm quyền, không ghi dữ liệu.

**3. Khác với bản thiết kế (cần anh/chị biết)**
- Hạng mục không có trường "người phụ trách", nên bảng nhóm công việc không có cột này.
- Người không có quyền `project.view` bị chuyển về trang trước kèm thông báo lỗi. Đây là cách middleware phân quyền hiện
  có xử lý trên web; trang không hiện lỗi 403.

**4. Đã kiểm thử** — base `052a5aa3`; subject `08ddfb9efe206e4adb502ba536badc2805d2829a`;
digest `76dd5c6e538a22c0178333e498b60eff2f9dca5195ecb6176e0167a22caf7c5d`.
- **Đỏ trước:** 13 test mới hỏng (chưa có trang, chưa có tham số bỏ qua cảnh báo).
- **Xanh sau:** 12 test trang OPPM, gồm:
  - Quyền và tenant.
  - Dự án rỗng không hiện 0%.
  - Loại dịch vụ.
  - Thứ tự hạng mục, cộng dồn việc từ hạng mục con, thanh thời gian, giới hạn 12.
  - Danh sách giới hạn 10 kèm tổng.
  - Không có số lãi.
  - Ngân quỹ cần quyền và không dựng bảng Ngân quỹ.
  - Bố cục in và nút trên trang dự án.
  - Cách ly dự án.
  - Số truy vấn không tăng khi dự án lớn.

  Thêm 1 test cho tham số bỏ qua cảnh báo.
- Toàn bộ Unit + Feature + Integration: 2.890 test, chỉ 2 lỗi có sẵn do chạy bằng root. Feature/Api: 824 test xanh.
- PHPStan, php-cs-fixer, SSOT lint, route guard, deptrac sạch.
- CI trên đúng head: đang chạy lúc lập hồ sơ.

**5. Lưu ý dữ liệu** — Hạng mục hiện chỉ tạo được qua API và mốc chưa có chức năng nhập. Vì vậy nhiều dự án sẽ thấy khối
nhóm công việc và mốc ở trạng thái "chưa có". Đây là dữ liệu thật đang thiếu, không phải lỗi; cần Work ID riêng nếu muốn có
màn hình nhập.

**6. Ngoài phạm vi** — nhập liệu quản trị (Phase 2); snapshot / PDF (Phase 3); nhập mốc; màn hình tạo hạng mục; viết lại
phần đếm cảnh báo Ngân quỹ; Control Tower.

**7. Rủi ro còn lại** — Thấp: trang mới chỉ đọc. Thay đổi duy nhất trên mã cũ là tham số tuỳ chọn của GAP-103, mặc định giữ
nguyên hành vi.

**8. Hoàn tác** — revert PR; không có dữ liệu nào bị thay đổi.

**9. Đề xuất** — Phát hành (merge).

## Decision Needed

Owner chọn: `APPROVE GAP-104 Gate 3` / Correction / Defer.

## What the owner is NOT being asked to decide

Deploy, phát hành production.
