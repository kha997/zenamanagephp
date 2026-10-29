---
work_id: GAP-014c
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
  spec: docs/audits/2026-10-29-gap-014c-ncr-capa-link-evidence.md
  plan: docs/superpowers/plans/2026-10-29-gap-014c-ncr-capa-link.md
  branch: docs/GAP-014c-ncr-capa-link
  pr: https://github.com/kha997/zenamanagephp/pull/385
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
  created_at: "2026-10-29T12:40:29+07:00"
  updated_at: "2026-10-29T12:40:29+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-014c at subject 9a8439f1a4c224727c1dd2b922e0b2d0a0f53343 (Gate 2 Option B + Owner amendments 1 and 2). Migration adds nullable tasks.ncr_id (ULID, index, FK to ncrs on delete set null), classified expand; Task::ncr(), Ncr::capaTasks(); POST /api/zena/inspections/{inspection}/ncrs/{ncr}/capa-tasks (rbac inspection.edit + task.create) and the web 'Tạo task CAPA' button create the CAPA task from the NCR handoff through TaskController::store, linked to the NCR, refused for a closed NCR; POST /api/zena/tasks accepts ncr_id only for an NCR of the same tenant and project; NCR API lists capa_tasks and the handoff payload carries ncr_id; NCR page lists CAPA tasks. New NcrCapaTaskLinkTest red 7/7 before, green 7/7 after (incl. migration down/up). Related NCR/inspection/escalation/dashboard/architecture suites green. Unit + Feature + Integration 2843 tests with only the 2 known root-only failures after amendment 2 (TaskTest fillable list); tests/Feature/Api 823/823. PHPStan clean, php-cs-fixer 0, composer ssot:lint and lint_tests pass, route guard OK, deptrac 0. Exact-head CI at 9a8439f1: all green including the MySQL jobs."
technical_evidence:
  base_sha: "170b7eea44a35e9d2be5fe15e50159f1fc3f14d1"
  subject_sha: "9a8439f1a4c224727c1dd2b922e0b2d0a0f53343"
  implementation_tree_digest: "343426d6f978ddc8245e4fb1c242e52b8c84c7be36319048b577ad5aa51318a3"
  verified_pr_head_sha: "9a8439f1a4c224727c1dd2b922e0b2d0a0f53343"
  verified_at: "2026-10-29T12:40:29+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

# GAP-014c — Gate 3 release decision (durable NCR ↔ CAPA task link)

## Gói quyết định phát hành

**1. Vấn đề là gì?** NCR và task khắc phục (CAPA) không lưu liên kết; trang web NCR không tạo được task CAPA (Gate 1).

**2. Sau thay đổi (Gate 2 Phương án B + Owner amendment 1, 2)** — subject `9a8439f1`:
- Task có ô `ncr_id` (có thể trống); một NCR có nhiều task CAPA.
- Trang NCR có mục **"Task CAPA"** (danh sách + trạng thái + hạn) và nút **"Tạo task CAPA"**; API có lệnh tương ứng
  (cần quyền `inspection.edit` và `task.create`). NCR đã đóng thì không tạo được.
- Luồng cũ qua API task vẫn chạy và được gắn NCR; NCR khác công ty hoặc khác dự án → bị từ chối (422).
- API NCR trả `capa_tasks`; API task trả `ncr_id`.

**3. Bằng chứng** — base `170b7eea`; subject `9a8439f1a4c224727c1dd2b922e0b2d0a0f53343`;
digest `343426d6f978ddc8245e4fb1c242e52b8c84c7be36319048b577ad5aa51318a3`.
- **Đỏ trước:** test mới hỏng 7/7. **Xanh sau:** 7/7, gồm chạy migration xuống rồi lên.
- Test NCR, kiểm tra, leo thang task quá hạn, dashboard, kiến trúc: xanh.
- Toàn bộ Unit + Feature + Integration: 2.843 test, chỉ 2 test hỏng sẵn do chạy bằng root; `tests/Feature/Api` 823/823.
- PHPStan, php-cs-fixer, SSOT lint, route guard, deptrac sạch. CI trên `9a8439f1`: xanh hết, gồm các job MySQL.

**4. Công khai**
- **Owner amendment 1:** thêm 1 dòng phân loại "expand" cho migration mới vào `database/migrations/classifications.json`.
- **Owner amendment 2:** thêm `ncr_id` vào danh sách mong đợi trong `tests/Unit/Models/TaskTest.php` (CI đỏ ở lần đẩy đầu).
- Task CAPA tạo từ NCR đi qua đúng đường tạo task hiện có (ghi audit, sự kiện) — không có đường tạo task thứ hai.
- Task CAPA cũ (chỉ có nhãn) không được gắn NCR; không chặn đóng NCR khi task CAPA còn dở (đúng thiết kế).
- Môi trường đã có dữ liệu cần chạy migration (`php artisan deploy:migrate`); PR này không chạy.

**5. Ngoài phạm vi** — chặn đóng NCR; gắn task cũ; thông báo NCR; dashboard; deploy / chạy migration.

**6. Rủi ro còn lại** — Thấp: cột mới có thể trống, không đổi dữ liệu cũ; hoàn tác migration chỉ mất liên kết.

**7. Hoàn tác** — Revert squash commit; nếu đã chạy migration thì `migrate:rollback` đúng migration này.

**8. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: `APPROVE GAP-014c Gate 3` / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy, chạy migration trên môi trường thật hay release; không chặn đóng NCR.
