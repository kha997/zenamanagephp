---
work_id: GAP-015
gate: 3
gate_status: awaiting_owner
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_correction_or_defer
references:
  spec: docs/audits/2026-10-29-gap-015-worktemplate-ui-owner-evidence.md
  plan: docs/superpowers/plans/2026-10-29-gap-015-work-instance-screens.md
  branch: docs/GAP-015-worktemplate-ui-owner
  pr: https://github.com/kha997/zenamanagephp/pull/387
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-29T21:02:48+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-29T20:22:19+07:00"
  updated_at: "2026-10-29T21:02:48+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-015 at subject 7c9ef6ce07d48706a0e3a7fe793bf2233b4a7de7 (Gate 2 Option A plus approved allowlist supplement and Owner amendments 1-2). New WorkInstancePageController with 7 web routes app.projects.work-instances.* (rbac work.view / work.update / work.approve / work.export): project-scoped list with progress, detail with steps, field values and attachments, step update (status, tenant-scoped assignee, deadline, field values), approve/reject, attachment upload/delete and deliverable export, all delegating to the existing work-instance API; other tenant or project instance 404. Project page link gated on work.view. ZenaProjectManagerRolePermissionSeeder grants PM work.view/update/approve/export and Project Member work.view/update. Amendment 1: two @param PHPDoc lines on DelegatesToApiControllers. WorkInstancePagesTest 18 tests red before (routes missing; assignee test and the Codex-fix tests red before their fixes), green after; Codex round 1: status select defaults to keep-current, json/datetime fields read-only and not submitted; Codex round 2: amendment 2 API updateStep 403 for approved/rejected without work.approve, deadline datetime-local keeps time, numeric inputs step=any; Codex round 3: string fields use a textarea and the page forwards only changed field values; Codex round 4: blank input (null after ConvertEmptyStringsToNull) compared and forwarded as empty string; Codex round 5: a cleared deadline is forwarded as null so it is removed; seeder test green. Unit + Feature + Integration 2862 tests at af5b3f11, 2 known root-only failures; tests/Feature/Api 824/824; at the final subject tests/Feature/Api + tests/Feature/Zena + seeders 1134 green. PHPStan 0 errors, php-cs-fixer 0, composer ssot:lint and lint_tests pass, owner-governance and docs lint pass, route guard OK, deptrac 0. Exact-head CI: all green at 47c8e13c; fix head pending at packet time. Full suite rerun on the fix head pending at packet time; tests/Feature/Api + tests/Feature/Zena + seeders 1131 green."
technical_evidence:
  base_sha: "2e3ba4b860f1525aa5712fd477614189c9a101f0"
  subject_sha: "7c9ef6ce07d48706a0e3a7fe793bf2233b4a7de7"
  implementation_tree_digest: "9d69b1c5b2fa81a73d1301b4ef4c9b26db0b5048a786d3be18f13f19e975c2c3"
  verified_pr_head_sha: "7c9ef6ce07d48706a0e3a7fe793bf2233b4a7de7"
  verified_at: "2026-10-29T21:02:48+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

# GAP-015 — Gate 3 release decision (WorkInstance execution screens)

## OWNER GATE 3: AWAITING OWNER (re-presented, round 6)

Earlier approvals ("APPROVE GAP-015 Gate 3" on subject `47c8e13c` / digest `6678bb7f…`, then on subject `9e125e01bffce51a5fd04fbc58d9094d85c0d4e7` /
digest `7c04fb4491d4629d7cf5d1cc87ec404251d4ad38514f7001075d5689f32c0bf8`) are superseded: Codex found more real issues after each and the fixes changed the implementation tree.
A new decision is needed for subject `7c9ef6ce07d48706a0e3a7fe793bf2233b4a7de7`.

## Gói quyết định phát hành

**1. Vấn đề là gì?** Áp dụng mẫu công việc vào dự án tạo ra "công việc theo mẫu" (WorkInstance), nhưng trên web không có
màn hình nào để làm tiếp: xem bước, cập nhật, duyệt, đính kèm, xuất báo cáo — chỉ có API (Gate 1).

**2. Sau thay đổi (Gate 2 Phương án A)** — subject `7c9ef6ce`:
- Trang dự án có nút **"Công việc theo mẫu"** (cần quyền `work.view`).
- **Danh sách** công việc theo mẫu của dự án, kèm tiến độ (số bước xong / tổng).
- **Chi tiết**: từng bước với trạng thái, người thực hiện, hạn, giá trị trường; form cập nhật (trạng thái chờ / đang làm /
  hoàn thành / bị chặn, người thực hiện, hạn, giá trị trường) — `work.update`; nút **Duyệt / Từ chối** kèm ghi chú —
  `work.approve`; tải lên / xoá tệp đính kèm — `work.update`; **xuất báo cáo** HTML / PDF theo mẫu báo cáo đã phát hành —
  `work.export`.
- Mọi thay đổi đi qua API hiện có (không đổi luật); công việc của tenant hoặc dự án khác trả 404.
- Seed mặc định: **Project Manager** có `work.view`, `work.update`, `work.approve`, `work.export`; **Project Member** có
  `work.view`, `work.update`.

**3. Bằng chứng** — base `2e3ba4b8`; subject `7c9ef6ce07d48706a0e3a7fe793bf2233b4a7de7`;
digest `9d69b1c5b2fa81a73d1301b4ef4c9b26db0b5048a786d3be18f13f19e975c2c3`.
- **Đỏ trước:** `WorkInstancePagesTest` hỏng toàn bộ (route chưa có); test người thực hiện hỏng trước khi thêm trường này.
  **Xanh sau:** 18/18 (danh sách, chi tiết, link trên trang dự án, cập nhật bước, người thực hiện chỉ trong tenant, duyệt,
  tệp đính kèm, xuất báo cáo, thiếu quyền duyệt, 404 tenant / dự án khác, chưa đăng nhập). Test seeder xanh.
- Toàn bộ Unit + Feature + Integration tại `af5b3f11` (trước sửa lần 5, chỉ khác 5 dòng controller): 2.862 test, chỉ 2 test hỏng sẵn do chạy bằng root; `tests/Feature/Api` 824/824. Tại subject cuối `7c9ef6ce`: `tests/Feature/Api` + `tests/Feature/Zena` + seeder 1.134 test xanh.
- PHPStan 0 lỗi, php-cs-fixer, SSOT lint, owner-governance lint, docs lint, route guard, deptrac sạch.
- CI trên `47c8e13c`: xanh. CI trên `7c9ef6ce` đang chạy lúc lập hồ sơ.

**3b. Sửa theo Codex (sau lần duyệt trước)** — 2 lỗi thật, mỗi lỗi có test đỏ trước / xanh sau:
- Bước đã duyệt / bị từ chối: ô trạng thái trước đây tự chọn "chờ xử lý", nên lưu hạn / người thực hiện sẽ đưa bước về
  chờ xử lý. Nay mặc định **"Giữ nguyên"**; chỉ đổi khi người dùng chọn.
- Trường kiểu JSON / ngày-giờ: trước đây hiện ô trống và khi lưu bị ghi đè thành rỗng. Nay chỉ hiển thị (không gửi lên),
  dữ liệu giữ nguyên.

**3c. Sửa theo Codex lần 2** — mỗi lỗi có test đỏ trước / xanh sau:
- **Bảo mật (Owner amendment 2):** API cập nhật bước cũ cho người chỉ có `work.update` đặt trạng thái "đã duyệt / bị từ
  chối" mà không cần quyền duyệt; PR này cấp `work.update` cho Project Member nên Member có thể tự duyệt qua API. Nay API
  trả 403 cho 2 trạng thái này nếu thiếu `work.approve`; người có quyền duyệt dùng như cũ.
- **Hạn có giờ:** ô hạn trước đây chỉ có ngày, lưu bước bất kỳ làm mất giờ (16:30 → 00:00). Nay là ô ngày-giờ.
- **Số thập phân:** ô số trước đây chặn giá trị như 1,25 và chặn cả form. Nay cho nhập số thập phân.

**3d. Sửa theo Codex lần 3** — test đỏ trước / xanh sau:
- **Văn bản nhiều dòng:** ô một dòng làm mất xuống dòng, và lưu bước bất kỳ ghi đè giá trị đó. Nay trường chữ là ô nhiều
  dòng, và trang **chỉ gửi những trường người dùng thực sự sửa** — trường không đổi được giữ nguyên như đã lưu.

**3e. Sửa theo Codex lần 4** — test đỏ trước / xanh sau:
- **Trường trống:** ô trống được Laravel đổi thành `null`, trang coi là "đã sửa" và API lưu thành chữ "null". Nay ô trống
  được coi là chuỗi rỗng: trường trống không đổi thì không ghi; xoá nội dung thì lưu rỗng.

**3f. Sửa theo Codex lần 5** — test đỏ trước / xanh sau:
- **Xoá hạn:** xoá ô hạn rồi lưu trước đây báo thành công nhưng hạn cũ vẫn còn. Nay xoá ô hạn thì hạn bị bỏ.

**4. Công khai (khác so với bản thiết kế, đã ghi ở 02-design)**
- **Owner amendment 2:** chặn duyệt / từ chối qua API cập nhật bước khi thiếu `work.approve` (sửa API, ngoài allowlist
  ban đầu).
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

**5. Ngoài phạm vi** — đổi API hay luật bước / duyệt (ngoài amendment 2); soạn mẫu công việc; role khác; dữ liệu cũ; deploy / seed môi trường
thật.

**6. Rủi ro còn lại** — Thấp: màn hình mới chỉ gọi API hiện có, giới hạn theo tenant và dự án; seed chỉ thêm quyền.

## Decision Needed

Owner chọn: `APPROVE GAP-015 Gate 3` / Correction / Defer.

## What the owner is NOT being asked to decide

Deploy, phát hành, seed môi trường thật.
