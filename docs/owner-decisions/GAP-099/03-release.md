---
work_id: GAP-099
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
  spec: docs/audits/2026-10-28-gap-099-broken-v1-project-and-change-request-evidence.md
  plan: docs/superpowers/plans/2026-10-28-gap-099-broken-v1-routes.md
  branch: docs/GAP-099-broken-v1-project-and-change-request
  pr: https://github.com/kha997/zenamanagephp/pull/380
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-28T23:04:14+07:00"
  owner_response_reference: "Owner Gate-3 decision in-session on 2026-10-28, verbatim: 'APPROVE GAP-099 Gate 3'. Given on the packet at PR head 0728560bbce0fb72d7454243098cc89a880325a0; bound to implementation subject 3e2bef6495f535b0f8d7e74e4cd32ba5723154a6 and implementation-tree digest 7354a216c1fc2fc0030eaf62ebda4c4588ede8070dc9ecd997fb237527356a0b (recomputed at recording time, zero drift). Merge is covered by the Owner's standing in-session instruction of 2026-09-28; no deployment or release authorized."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-28T22:19:25+07:00"
  updated_at: "2026-10-28T23:04:14+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-099 at subject 3e2bef6495f535b0f8d7e74e4cd32ba5723154a6 (Gate 2 Option A + Owner amendment 1). UpdateProjectRequest status rule uses Project::VALID_STATUSES; v1 ProjectController update()/destroy() dispatch ProjectUpdated with the App\\Models\\Project built from the same row and capture $oldData before deleting; src/ChangeRequest/routes/api.php keeps only POST / (10 never-working routes retired). New V1ProjectAndChangeRequestRoutesTest: red before 3 of 4 (500, 500, retired route names present), green after 4/4. Full Feature + Unit 3594 tests, 5 failures identical to the GAP-021/098 baseline (2 root-only, 3 suite-order leaks); PHPStan clean, php-cs-fixer 0, composer ssot:lint (incl. orphan routes) passes, route guard OK, deptrac 0. Exact-head CI at 3e2bef64: all green except browser-tests still running at packet time."
technical_evidence:
  base_sha: "8dd80a8422a9cc6da945c5fa0db5b0bec3850482"
  subject_sha: "3e2bef6495f535b0f8d7e74e4cd32ba5723154a6"
  implementation_tree_digest: "7354a216c1fc2fc0030eaf62ebda4c4588ede8070dc9ecd997fb237527356a0b"
  verified_pr_head_sha: "3e2bef6495f535b0f8d7e74e4cd32ba5723154a6"
  verified_at: "2026-10-28T22:19:25+07:00"
owner_decision_binding:
  implementation_tree_digest: "7354a216c1fc2fc0030eaf62ebda4c4588ede8070dc9ecd997fb237527356a0b"
  decision_recorded_at: "2026-10-28T23:04:14+07:00"
---

# GAP-099 — Gate 3 release decision (broken `/api/v1` project update and change-request routes)

## OWNER GATE 3: APPROVED

Owner approved Gate 3 in-session on 2026-10-28 ("APPROVE GAP-099 Gate 3"), bound to implementation subject
`3e2bef6495f535b0f8d7e74e4cd32ba5723154a6` and implementation-tree digest `7354a216c1fc2fc0030eaf62ebda4c4588ede8070dc9ecd997fb237527356a0b`. No deployment or release is authorized.

## Gói quyết định phát hành

**1. Vấn đề là gì?** Sửa dự án qua API cũ và 9 / 11 route change request API cũ lỗi 500 với mọi người dùng (Gate 1).

**2. Sau thay đổi (Gate 2 Phương án A + Owner amendment 1)** — subject `3e2bef64`:
- `PUT/PATCH /api/v1/projects/{id}` chạy được, kể cả đổi trạng thái / tiến độ / chi phí; `DELETE /api/v1/projects/{id}`
  chạy được (amendment 1: sửa 2 chỗ phát event).
- Gỡ 10 route change request v1 chưa từng chạy; gọi vào nhận 404 thay vì 500. Giữ route tạo mới. Quản lý change request
  qua `/api/zena/change-requests`.

**3. Bằng chứng** — base `8dd80a84`; subject `3e2bef6495f535b0f8d7e74e4cd32ba5723154a6`;
digest `7354a216c1fc2fc0030eaf62ebda4c4588ede8070dc9ecd997fb237527356a0b`.
- **Đỏ trước:** test mới hỏng 3/4 (đổi tên 500, đổi trạng thái 500, 10 route còn tồn tại).
- **Xanh sau:** 4/4; tạo change request v1 vẫn 201.
- Toàn bộ Feature + Unit: 3594 test, 5 hỏng — **đúng 5 test hỏng sẵn** như GAP-021 / 098.
- PHPStan, php-cs-fixer, SSOT lint (gồm kiểm tra route mồ côi), route guard, deptrac sạch. CI trên `3e2bef64`: xanh hết,
  chỉ còn browser-tests đang chạy lúc lập hồ sơ.

**4. Công khai**
- Owner amendment 1: sửa thêm `ProjectController` v1 (2 chỗ phát event sai kiểu, `destroy()` dùng biến chưa khai báo).
- Xoá dự án qua v1 là **xoá hẳn** (model `Src` không dùng xoá mềm) — giữ nguyên hành vi vốn có, chỉ hết lỗi 500.
- Test kiểm tra 10 route đã gỡ theo **tên route** (thay vì đường dẫn) để không phải thêm ngoại lệ vào danh sách cho phép của
  lint route mồ côi.
- Controller / service change request v1 giữ nguyên; các method không còn route gọi tới là code chết (dọn là việc riêng).

**5. Ngoài phạm vi** — dọn code chết change request v1; kiểm tra vai trò `rbac` không tham số; deploy.

**6. Rủi ro còn lại** — Thấp. Client ngoài (nếu có) gọi 10 route đã gỡ nhận 404 thay vì 500.

**7. Hoàn tác** — Revert squash commit.

**8. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: `APPROVE GAP-099 Gate 3` / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy hay release; không dọn code chết change request v1.
