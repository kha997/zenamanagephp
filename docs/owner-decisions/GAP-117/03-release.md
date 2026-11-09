---
work_id: GAP-117
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
  spec: docs/audits/2026-11-10-gap-117-project-milestones-evidence.md
  plan: docs/superpowers/plans/2026-11-10-gap-117-project-milestones.md
  branch: docs/GAP-117-project-milestones
  pr: https://github.com/kha997/zenamanagephp/pull/407
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
  created_at: "2026-11-10T02:47:31+07:00"
  updated_at: "2026-11-10T02:47:31+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-117 at subject 1f16e9c24fa6a87420afaeec7036db23be97b96c (Gate 2 L-1, E-1, A-1, T-1, O-1, C-2; exact allowlist, no amendment; one PR, one implementation commit). T-1/O-1: expand migration 2026_11_10_100000_add_tenant_id_to_project_milestones adds a nullable tenant_id with (tenant_id, project_id) index, backfills it from projects and normalizes stored overdue rows to pending (up() re-runnable; classified expand); ProjectMilestone uses TenantScope, fills tenant_id from the project on create, no longer stores overdue, isOverdue() is live (pending and target day before today), markCompleted() stores a date. A-1/E-1: ProjectMilestoneService create / update / complete (date not in the future) / reopen / cancel (reason required), no hard delete, cancelled must be reopened before editing; edits by the project pm_id or project.update in the same tenant (AuthorizationException / 403), milestone must belong to the project and tenant (404), row lock, AuditLog milestone.* with before / after. L-1: ProjectMilestonePageController and six routes app.projects.milestones.* (rbac:project.view, tenant-scoped project lookup), view projects/milestones with live badges, buttons on the project page and a link in the OPPM milestone block. C-2: the broken ProjectMilestoneCompleted event, LogProjectActivity and Project::calculateProgress are untouched. Tests: ProjectMilestoneServiceTest (6) and ProjectMilestonePageTest (3) new, AudTenantScopeCoverageTest gains ProjectMilestone; red before, green after; on MySQL 33 milestone-related tests green and the migration runs up / down / up. Existing milestone readers (OPPM / health, Today, analytics, PM dashboard, architecture guards) 197 tests green. PHPStan, php-cs-fixer, ssot:lint, deptrac, governance lint pass. Local Unit + Feature + Integration 3008 tests: the 2 known root-only failures plus 3 PDF-export failures; Feature/Api 824 with 1 PDF-export failure — the same 4 PDF failures as on main in this container (no Node/Playwright PDF runtime). Exact-head CI recorded in the body."
technical_evidence:
  base_sha: "41daaca199f5fd1f288a42e7c3ebaa6228c73d71"
  subject_sha: "1f16e9c24fa6a87420afaeec7036db23be97b96c"
  implementation_tree_digest: "a75cc959f5a961c9fdd2cc2c662b389c919cd7b5668ab324660be4c0f9d3dd69"
  verified_pr_head_sha: "1f16e9c24fa6a87420afaeec7036db23be97b96c"
  verified_at: "2026-11-10T02:47:31+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

# GAP-117 — Gate 3 release decision (project milestones)

## Gói quyết định phát hành

**1. Vấn đề là gì?** PM không có chỗ nào để tạo mốc tiến độ, nên OPPM và trang "Hôm nay" luôn trống phần mốc.

**2. Sau thay đổi (Gate 2 L-1, E-1, A-1, T-1, O-1, C-2)** — subject `1f16e9c2`:
- **Trang "Mốc tiến độ"** cho từng dự án, mở từ nút trên trang dự án và link "Quản lý mốc" ở ô "Mốc" trên OPPM.
- **Ai làm gì:**
  - ai xem được dự án thì xem được mốc;
  - PM của dự án hoặc người có quyền sửa dự án thì được thêm, sửa, đánh dấu xong, mở lại, huỷ (có lý do);
  - không xoá hẳn; mọi thao tác ghi nhật ký trước / sau.
- **Tình trạng mốc:** Đang chờ / **Quá hạn** (tính theo ngày thực tế, dời ngày thì tự hết) / Xong / Đã huỷ.
- **Tách theo công ty:** mốc có cột công ty, mốc cũ được điền sẵn theo dự án.
- Mốc mới tạo hiện ngay trên OPPM ("x/y hoàn thành · z quá hạn") và trang "Hôm nay".
- Mã cũ hỏng (sự kiện "hoàn thành mốc"...) để nguyên theo C-2.

**3. Đã kiểm thử** — base `41daaca1`; subject `1f16e9c24fa6a87420afaeec7036db23be97b96c`; digest
`a75cc959f5a961c9fdd2cc2c662b389c919cd7b5668ab324660be4c0f9d3dd69`.
- 14 test mới, gồm cả test cách ly công ty: **đỏ** trước khi làm, **xanh** sau.
- 197 test có sẵn đọc mốc (OPPM, Hôm nay, thống kê, PM dashboard): xanh.
- Trên **MySQL**: migration chạy lên, xuống, lên lại được; 33 test liên quan xanh.
- PHPStan, php-cs-fixer, SSOT lint, deptrac, governance lint sạch.

**4. Lưu ý vận hành**
- Có **một migration chỉ thêm cột** (loại `expand`), chạy bằng `deploy:migrate` như thường.
- Mốc cũ đang lưu "quá hạn" được đổi về "đang chờ". Thông tin quá hạn không mất, vì vẫn tính lại được từ ngày mục tiêu.

**5. Ngoài phạm vi**
- Gắn mốc với công việc.
- Gantt.
- Nhắc việc.
- Giao diện điện thoại.
- API JSON.
- Dọn mã cũ hỏng.
- Deploy.

**6. Rủi ro còn lại** — Thấp.
- Mốc giờ tự lọc theo công ty. Nơi nào đọc mốc mà không qua dự án sẽ chỉ thấy mốc của công ty mình, và đó là điều mong
  muốn.
- Các test đọc mốc có sẵn đều xanh.

**7. Hoàn tác** — Revert PR. Migration `down()` bỏ cột công ty; mốc đã tạo vẫn còn.

**8. Đề xuất** — Phát hành (merge).

**9. Kết quả chạy toàn bộ**
- **Unit + Feature + Integration:** 3.008 test, có 5 lỗi:
  - 2 lỗi có sẵn do chạy bằng root (`GrandfatherLoaderTest`, `BackupStorageLocationTest`);
  - 3 lỗi xuất PDF trong `DocumentTemplateRenderTest`.
- **Feature/Api:** 824 test, có 1 lỗi xuất PDF trong `WorkInstanceEndToEndSmokeFlowTest`.
- **4 lỗi PDF do môi trường, không do PR này:**
  - máy làm việc này chưa cài bộ dựng PDF (Node / Playwright);
  - các test đó cũng hỏng y hệt trên `main`;
  - PR này không đụng phần xuất PDF.
- **CI trên đúng head:** ghi nhận trước khi merge.

## Decision Needed

Owner chọn: `APPROVE GAP-117 Gate 3` / Correction / Defer.

## What the owner is NOT being asked to decide

Deploy, phát hành production.
