---
work_id: GAP-110
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
  spec: docs/audits/2026-11-05-gap-110-oppm-phase-2-3-evidence.md
  plan: docs/superpowers/plans/2026-11-05-gap-110-oppm-phase-2-3.md
  branch: docs/GAP-110-oppm-phase-2-3
  pr: https://github.com/kha997/zenamanagephp/pull/400
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
  created_at: "2026-11-05T07:10:13+07:00"
  updated_at: "2026-11-05T07:10:13+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-110 at subject 1bc7fea895721db52c1105b1ef59c7895838d99b (Gate 2 S-1, A-1, W-1, E-1, D-1, K-1, P-1, Q-1; exact allowlist; one PR). Deviation from S-1 wording: the three parts (overlay, decisions, snapshots) landed in one implementation commit instead of three, because they share the read service, page body, controller and routes; scope and files are exactly the approved ones. One migration creates oppm_workstream_notes (unique project + component; FKs tenant, project, component, owner), oppm_project_notes (unique project), oppm_decisions (index project + status + due) and oppm_snapshots (payload as exact JSON text plus sha256); every row carries tenant_id and project_id; no existing table changes. OppmOverlayService: canEdit = same tenant and (project pm_id or project.update); per root component of the same project only (child or foreign components refused), outcome 500, weight 0-100 display only, next action with same-tenant owner and due date, note 1000, display order 0-999; project-level summary 2000 and next action; row lock, AuditLog with old / new values; overlayFor reads in two queries. ProjectOppmReadService adds overlay (per work group, display order before start-date order, weight total) and decisions; progress, delay and attention are untouched (W-1). OppmDecisionService: create / update while open, decide needs an outcome, cancel needs a reason (kept as outcome), row lock, audit created / updated / decided / cancelled; page block shows five open decisions, overdue first, with the open total. OppmSnapshotService: close needs canEdit and a label; payload = header + full OPPM data normalized through the same JSON encoding as the model cast, sha256 stored; model refuses update and delete; history; previous snapshot by created_at then id; compare subtracts stored numbers only (progress, delay, attention counts, open decisions, finance measures, per work group progress). Pages: OPPM page (summary and decision blocks, inline edit forms and close form for editors, history link), decisions page, history, snapshot view through the shared body with a 'Bản chốt' banner and the existing print layout, compare page. Routes under rbac:project.view with tenant-scoped project lookup (404 across tenants or projects); writes 403 without canEdit. No new permission, no new dependency, no server-side PDF. Tests: ProjectOppmOverlayTest 4, ProjectOppmDecisionTest 3, ProjectOppmSnapshotTest 4, red before (11 errors) and green after; the 12 GAP-104 page tests unchanged and green; all 23 OPPM tests green with DB_CONNECTION=mysql on MySQL 8; migration up, down, up on MySQL 8; OPPM page query count stays constant with 1 vs 11 overlay rows. Unit + Feature + Integration 2952 tests with only the 2 known root-only failures; Feature/Api 824 green. PHPStan, php-cs-fixer, SSOT test lint, ssot:lint, route guard, deptrac pass. Exact-head CI pending at packet time."
technical_evidence:
  base_sha: "bdf1b5fb27e3487ce25bd0fe61d9c63ee8b986eb"
  subject_sha: "1bc7fea895721db52c1105b1ef59c7895838d99b"
  implementation_tree_digest: "c70bc70bafe8f18f517ac2f184fbf01ac228ac79219ec8723389e75c238a7c10"
  verified_pr_head_sha: "1bc7fea895721db52c1105b1ef59c7895838d99b"
  verified_at: "2026-11-05T07:10:13+07:00"
owner_decision_binding: null
---

# GAP-110 — Gate 3 release decision (OPPM Phase 2 overlay, Phase 3 snapshots and decisions)

## Gói quyết định phát hành

**1. Vấn đề là gì?** OPPM chỉ có số liệu tự động. PM / Owner chưa ghi được nhận định, việc cần làm tiếp và quyết định cần
xử lý, cũng chưa lưu được báo cáo từng kỳ để so sánh.

**2. Sau thay đổi (Gate 2 S-1, A-1, W-1, E-1, D-1, K-1, P-1, Q-1)** — subject `1bc7fea8`:

**Trang OPPM**
- Bảng nhóm công việc thêm hai cột:
  - **Kết quả đầu ra / trọng số** (kèm ghi chú);
  - **Hành động tiếp theo** (người làm, hạn).
- Dưới bảng hiện **tổng trọng số**, cảnh báo nếu khác 100. Trọng số chỉ để tham khảo, **% tiến độ không đổi**.
- Nhóm công việc sắp theo **thứ tự hiển thị** nếu có, không thì theo ngày bắt đầu như cũ.
- Khối mới **"Tóm tắt quản trị"**: tóm tắt và hành động tiếp theo của cả dự án, nên dự án chưa có nhóm công việc vẫn ghi
  được.
- Khối mới **"Quyết định cần xử lý"**: tối đa 5 mục đang chờ, quá hạn lên đầu, kèm "Xem tất cả".
- Người sửa được thấy nút **"Sửa"** tại chỗ và ô **"Chốt báo cáo"**. Người chỉ xem không thấy các nút này.

**Trang "Quyết định"**
- Thêm / sửa quyết định (người quyết, hạn).
- Ghi **đã quyết** phải có kết quả; **huỷ** phải có lý do.
- Quyết định đã xử lý không sửa được nữa.

**Chốt kỳ**
- Bấm "Chốt báo cáo" và đặt tên kỳ. Hệ thống lưu **toàn bộ dữ liệu trang** tại thời điểm đó, kèm mã kiểm tra.
- Bản chốt **không sửa / xoá được**. Dữ liệu thật thay đổi sau đó không làm đổi bản chốt.
- Trang **"Lịch sử báo cáo"**: xem lại từng kỳ, đúng giao diện OPPM, có dải "Bản chốt…", in ngang được.
- **"So với kỳ trước"**: tiến độ, thời hạn, việc bị chặn / quá hạn, quyết định đang chờ, tài chính, % từng nhóm công việc.
  Chỉ lấy số trong hai bản chốt, không tính lại.

**Quyền**
- Xem: `project.view`.
- Sửa / chốt: PM của dự án hoặc người có `project.update`.
- Mọi thay đổi ghi nhật ký. Công ty / dự án khác không truy cập được.

Không thêm quyền mới, thư viện mới hay công thức tiến độ mới; không đổi bảng cũ. Có 1 migration tạo 4 bảng mới.

**Khác so với Gate 2:** S-1 ghi "ba commit". Ba phần dùng chung dịch vụ đọc, phần thân trang, controller và route nên được
gộp vào **một commit**. Nội dung và danh sách file đúng như đã duyệt.

**3. Đã kiểm thử** — base `bdf1b5fb`; subject `1bc7fea895721db52c1105b1ef59c7895838d99b`;
digest `c70bc70bafe8f18f517ac2f184fbf01ac228ac79219ec8723389e75c238a7c10`.
- **11 test mới**, hỏng khi chưa có mã (11 lỗi), xanh khi có mã:
  - **phần nhập (4):**
    - lưu / sửa;
    - chặn hạng mục con hoặc của dự án khác, trọng số ngoài 0–100, người làm khác công ty;
    - quyền PM / `project.update` / người chỉ xem; nhật ký;
    - thứ tự hiển thị, tổng trọng số, **% tiến độ không đổi**;
    - số truy vấn trang không tăng theo số dòng nhập;
  - **quyết định (3):**
    - vòng đời, bắt buộc kết quả / lý do, không sửa sau khi xử lý; nhật ký;
    - khối 5 mục quá hạn lên đầu;
    - trang, quyền, cách ly dự án / công ty;
  - **chốt kỳ (4):**
    - lưu đủ dữ liệu + mã kiểm tra;
    - đổi dữ liệu thật thì bản chốt không đổi; không sửa / xoá được;
    - so sánh đúng chênh lệch;
    - trang, quyền, cách ly.
- **12 test OPPM giai đoạn 1** giữ nguyên, vẫn xanh.
- **MySQL 8 thật:** migration lên / gỡ / lên được; 23 test OPPM xanh với MySQL.
- **Unit + Feature + Integration:** 2.952 test, chỉ 2 lỗi có sẵn do chạy bằng root. **Feature/Api:** 824 test xanh.
- PHPStan, php-cs-fixer, SSOT lint, route guard, deptrac sạch.
- CI trên đúng head: đang chạy lúc lập hồ sơ.

**4. Lưu ý vận hành**
- Nhóm công việc vẫn là hạng mục gốc. Dự án chưa có hạng mục thì dùng khối "Tóm tắt quản trị".
- PDF: dùng nút **In** rồi chọn "Lưu thành PDF" trong trình duyệt.
- Không có nhắc hạn quyết định tự động.

**5. Ngoài phạm vi** — trang tổng nhiều dự án (Control Tower), Contract / Finance Control, giao diện tạo hạng mục / mốc,
tiến độ theo trọng số, PDF phía máy chủ, chốt tự động, thông báo, AI, deploy.

**6. Rủi ro còn lại** — Thấp:
- chỉ thêm dữ liệu quản trị và bản chốt;
- không đổi cách tính tiến độ / trễ / cảnh báo;
- bản chốt chặn sửa / xoá ở tầng ứng dụng (chưa chặn ở mức cơ sở dữ liệu).

**7. Hoàn tác** — revert PR. Migration `down()` xoá 4 bảng mới (mất phần nhập, quyết định và bản chốt). Trang OPPM trở về
giai đoạn 1.

**8. Đề xuất** — Phát hành (merge).

## Decision Needed

Owner chọn: `APPROVE GAP-110 Gate 3` / Correction / Defer.

## What the owner is NOT being asked to decide

Deploy, phát hành production.
