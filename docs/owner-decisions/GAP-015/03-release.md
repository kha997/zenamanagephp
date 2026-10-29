---
work_id: GAP-015
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
  spec: docs/audits/2026-10-29-gap-015-worktemplate-ui-owner-evidence.md
  plan: docs/superpowers/plans/2026-10-29-gap-015-work-instance-screens.md
  branch: docs/GAP-015-worktemplate-ui-owner
  pr: https://github.com/kha997/zenamanagephp/pull/387
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-29T20:34:38+07:00"
  owner_response_reference: "Owner Gate-3 decision in-session on 2026-10-29, verbatim: 'APPROVE GAP-015 Gate 3' (second approval, after the Codex fixes). Given on the re-presented packet at PR head a497f03669acdf3221de03a96752d8b9c0eba38a; bound to implementation subject 9e125e01bffce51a5fd04fbc58d9094d85c0d4e7 and implementation-tree digest 7c04fb4491d4629d7cf5d1cc87ec404251d4ad38514f7001075d5689f32c0bf8 (recomputed at recording time, zero drift). Merge is covered by the Owner's standing in-session instruction of 2026-09-28; no deployment, environment seeding or release authorized."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-29T20:22:19+07:00"
  updated_at: "2026-10-29T20:34:38+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-015 at subject 9e125e01bffce51a5fd04fbc58d9094d85c0d4e7 (Gate 2 Option A plus approved allowlist supplement and Owner amendment 1). New WorkInstancePageController with 7 web routes app.projects.work-instances.* (rbac work.view / work.update / work.approve / work.export): project-scoped list with progress, detail with steps, field values and attachments, step update (status, tenant-scoped assignee, deadline, field values), approve/reject, attachment upload/delete and deliverable export, all delegating to the existing work-instance API; other tenant or project instance 404. Project page link gated on work.view. ZenaProjectManagerRolePermissionSeeder grants PM work.view/update/approve/export and Project Member work.view/update. Amendment 1: two @param PHPDoc lines on DelegatesToApiControllers. WorkInstancePagesTest 13 tests red before (routes missing; assignee test and the 2 Codex-fix tests red before their fixes), green after; Codex fixes: status select defaults to keep-current, json/datetime fields read-only and not submitted; seeder test green. Unit + Feature + Integration 2855 tests, 2 known root-only failures; tests/Feature/Api 824/824. PHPStan 0 errors, php-cs-fixer 0, composer ssot:lint and lint_tests pass, owner-governance and docs lint pass, route guard OK, deptrac 0. Exact-head CI: all green at 47c8e13c (fix head pending at packet time)."
technical_evidence:
  base_sha: "2e3ba4b860f1525aa5712fd477614189c9a101f0"
  subject_sha: "9e125e01bffce51a5fd04fbc58d9094d85c0d4e7"
  implementation_tree_digest: "7c04fb4491d4629d7cf5d1cc87ec404251d4ad38514f7001075d5689f32c0bf8"
  verified_pr_head_sha: "9e125e01bffce51a5fd04fbc58d9094d85c0d4e7"
  verified_at: "2026-10-29T20:33:27+07:00"
owner_decision_binding:
  implementation_tree_digest: "7c04fb4491d4629d7cf5d1cc87ec404251d4ad38514f7001075d5689f32c0bf8"
  decision_recorded_at: "2026-10-29T20:34:38+07:00"
---

# GAP-015 — Gate 3 release decision (WorkInstance execution screens)

## OWNER GATE 3: APPROVED

Owner approved Gate 3 again in-session on 2026-10-29 ("APPROVE GAP-015 Gate 3"), bound to implementation subject
`9e125e01bffce51a5fd04fbc58d9094d85c0d4e7` and implementation-tree digest `7c04fb4491d4629d7cf5d1cc87ec404251d4ad38514f7001075d5689f32c0bf8`. No deployment, environment seeding
or release is authorized.

The earlier approval ("APPROVE GAP-015 Gate 3", bound to subject `47c8e13cb177a7653f78f31776b5e7c3b68f5874` and digest `6678bb7fc91075921b5652d587f86f1b02a7b7a6c48586d454fc21c0ea4ac4bd`) no longer
is superseded: Codex found 2 real bugs after it and the fix changed the implementation tree.

## Gói quyết định phát hành

**1. Vấn đề là gì?** Áp dụng mẫu công việc vào dự án tạo ra "công việc theo mẫu" (WorkInstance), nhưng trên web không có
màn hình nào để làm tiếp: xem bước, cập nhật, duyệt, đính kèm, xuất báo cáo — chỉ có API (Gate 1).

**2. Sau thay đổi (Gate 2 Phương án A)** — subject `9e125e01`:
- Trang dự án có nút **"Công việc theo mẫu"** (cần quyền `work.view`).
- **Danh sách** công việc theo mẫu của dự án, kèm tiến độ (số bước xong / tổng).
- **Chi tiết**: từng bước với trạng thái, người thực hiện, hạn, giá trị trường; form cập nhật (trạng thái chờ / đang làm /
  hoàn thành / bị chặn, người thực hiện, hạn, giá trị trường) — `work.update`; nút **Duyệt / Từ chối** kèm ghi chú —
  `work.approve`; tải lên / xoá tệp đính kèm — `work.update`; **xuất báo cáo** HTML / PDF theo mẫu báo cáo đã phát hành —
  `work.export`.
- Mọi thay đổi đi qua API hiện có (không đổi luật); công việc của tenant hoặc dự án khác trả 404.
- Seed mặc định: **Project Manager** có `work.view`, `work.update`, `work.approve`, `work.export`; **Project Member** có
  `work.view`, `work.update`.

**3. Bằng chứng** — base `2e3ba4b8`; subject `9e125e01bffce51a5fd04fbc58d9094d85c0d4e7`;
digest `7c04fb4491d4629d7cf5d1cc87ec404251d4ad38514f7001075d5689f32c0bf8`.
- **Đỏ trước:** `WorkInstancePagesTest` hỏng toàn bộ (route chưa có); test người thực hiện hỏng trước khi thêm trường này.
  **Xanh sau:** 13/13 (danh sách, chi tiết, link trên trang dự án, cập nhật bước, người thực hiện chỉ trong tenant, duyệt,
  tệp đính kèm, xuất báo cáo, thiếu quyền duyệt, 404 tenant / dự án khác, chưa đăng nhập). Test seeder xanh.
- Toàn bộ Unit + Feature + Integration (tại `47c8e13c`): 2.855 test, chỉ 2 test hỏng sẵn do chạy bằng root; `tests/Feature/Api` 824/824. Sau sửa: `tests/Feature/Zena` + seeder 305 test xanh.
- PHPStan 0 lỗi, php-cs-fixer, SSOT lint, owner-governance lint, docs lint, route guard, deptrac sạch.
- CI trên `47c8e13c`: xanh. CI trên `9e125e01` đang chạy lúc lập hồ sơ.

**3b. Sửa theo Codex (sau lần duyệt trước)** — 2 lỗi thật, mỗi lỗi có test đỏ trước / xanh sau:
- Bước đã duyệt / bị từ chối: ô trạng thái trước đây tự chọn "chờ xử lý", nên lưu hạn / người thực hiện sẽ đưa bước về
  chờ xử lý. Nay mặc định **"Giữ nguyên"**; chỉ đổi khi người dùng chọn.
- Trường kiểu JSON / ngày-giờ: trước đây hiện ô trống và khi lưu bị ghi đè thành rỗng. Nay chỉ hiển thị (không gửi lên),
  dữ liệu giữ nguyên.

**4. Công khai (khác so với bản thiết kế, đã ghi ở 02-design)**
- **Owner amendment 1:** thêm 2 dòng PHPDoc `@param` vào trait `DelegatesToApiControllers` (không đổi logic) để PHPStan
  sạch mà không thêm baseline.
- **Tên route** là `app.projects.work-instances.*` (không phải `operator.`), vì nằm trong nhóm route dự án sẵn có; đường
  dẫn, quyền và xử lý đúng như đã duyệt.
- **Thiếu quyền trên web:** cơ chế phân quyền sẵn có đưa người dùng về trang trước kèm thông báo lỗi, thay vì trang 403;
  bước không bị đổi.
- **Người thực hiện:** chỉ chọn được người cùng tenant; trang web chặn mã người dùng khác (API gốc chỉ kiểm tra người dùng
  tồn tại).
- Môi trường đã có dữ liệu cần chạy lại `ZenaProjectManagerRolePermissionSeeder` (chỉ thêm quyền) để PM / Member thấy màn
  hình; PR này không chạy.

**5. Ngoài phạm vi** — đổi API hay luật bước / duyệt; soạn mẫu công việc; role khác; dữ liệu cũ; deploy / seed môi trường
thật.

**6. Rủi ro còn lại** — Thấp: màn hình mới chỉ gọi API hiện có, giới hạn theo tenant và dự án; seed chỉ thêm quyền.

## Decision Needed

Owner chọn: `APPROVE GAP-015 Gate 3` / Correction / Defer.

## What the owner is NOT being asked to decide

Deploy, phát hành, seed môi trường thật.
