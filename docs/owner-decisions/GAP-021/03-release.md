---
work_id: GAP-021
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
  spec: docs/audits/2026-10-28-gap-021-tasks-api-parity-evidence.md
  plan: docs/superpowers/plans/2026-10-28-gap-021-tasks-api-parity.md
  branch: docs/GAP-021-tasks-api-parity
  pr: https://github.com/kha997/zenamanagephp/pull/378
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
  created_at: "2026-10-28T19:38:20+07:00"
  updated_at: "2026-10-28T19:38:20+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-021 at subject 072336afd22ce8e2d3258912b67c860bf6058e55 (Gate 2 Option A). PUT|PATCH /api/v1/tasks/{task} and the three work-template task routes now require rbac:task.update; the six /api/v1/tasks* routes carry input.sanitization and error.envelope. New V1TaskUpdatePermissionTest (non-super-admin users): red before on unchanged routes (3 of 4 failing: 403, 403, 201 instead of 400), green after (4/4). TasksContractParityAuditInvariantTest's two v1 assertNotContains lines flipped to assertContains. New OPEN register row GAP-098 (32 unseeded permission codes on 69 /api/v1 routes). Local: Feature/Api + Architecture + ProjectTask + TaskTest 905 OK; full Feature + Unit 3587 tests with 5 failures, all reproduced identically with the main routes restored (2 root-only, 3 pass alone: suite-order leaks); PHPStan clean, php-cs-fixer 0, composer ssot:lint passes, route guard OK, deptrac 0 violations."
technical_evidence:
  base_sha: "cc07e77dae62352b144134f6f841d62a7489073f"
  subject_sha: "072336afd22ce8e2d3258912b67c860bf6058e55"
  implementation_tree_digest: "fa9cb97abbf20b5c996c854e7f44c83369510b27477c155359c00ff9192b16f3"
  verified_pr_head_sha: "072336afd22ce8e2d3258912b67c860bf6058e55"
  verified_at: "2026-10-28T19:38:20+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

# GAP-021 — Gate 3 release decision (tasks API permission and middleware drift)

## OWNER GATE 3: AWAITING OWNER

## Gói quyết định phát hành

**1. Vấn đề là gì?** Sửa công việc qua API cũ và API mẫu công việc đòi quyền `task.edit` không tồn tại, nên chỉ super
admin sửa được; API cũ thiếu lớp làm sạch đầu vào và định dạng lỗi chuẩn (Gate 1).

**2. Sau thay đổi (Gate 2 Phương án A)** — subject `072336af`:
- 5 route sửa công việc dùng quyền `task.update` (đã có sẵn), giống API chuẩn.
- 6 route `/api/v1/tasks*` có thêm làm sạch đầu vào và định dạng lỗi chuẩn.
- Sổ thêm dòng **GAP-098 (OPEN)**: 32 mã quyền khác không được tạo, 69 route `/api/v1` bị ảnh hưởng — chưa sửa.

**3. Bằng chứng** — base `cc07e77d`; subject `072336afd22ce8e2d3258912b67c860bf6058e55`;
digest `fa9cb97abbf20b5c996c854e7f44c83369510b27477c155359c00ff9192b16f3`.
- **Đỏ trước:** test mới trên route cũ hỏng 3/4 (403, 403, và 201 thay vì 400 với tên `<script>…`).
- **Xanh sau:** 4/4.
- Api + Architecture + test task / mẫu công việc: 905 test xanh.
- Toàn bộ Feature + Unit: 3587 test, 5 hỏng — **giống hệt khi trả route về như `main`**: 2 chỉ hỏng khi chạy bằng root, 3
  chạy riêng thì xanh (rò dữ liệu do thứ tự test, cùng dạng GAP-065).
- PHPStan, php-cs-fixer, SSOT lint (như CI), route guard, deptrac sạch.

**4. Công khai**
- Thiết kế ghi "31 mã quyền khác"; đếm lại chính xác là **32** (dòng GAP-098 ghi 32).
- Test mới gọi đường dẫn `/api/v1/...` viết cứng vì các route này không có tên; mỗi dòng có dấu `ssot-allow-hardcode` kèm
  lý do (quy ước có sẵn trong repo).
- Lần push đầu làm CI `code-quality` đỏ: baseline orphan-route ghi theo số dòng, tôi đã thêm 1 dòng chú thích vào test kiến
  trúc. Đã bỏ dòng đó (`072336af`).
- Người dùng gửi dữ liệu có mẫu đáng ngờ (vd `<script>`) tới `/api/v1/tasks` giờ nhận 400 như API chuẩn — trước đây được
  lưu.

**5. Ngoài phạm vi** — 32 mã quyền của GAP-098; dạng phản hồi thành công của API cũ; gộp controller giao việc; deploy.

**6. Rủi ro còn lại** — Thấp. Client ngoài (nếu có) gửi dữ liệu có mẫu đáng ngờ tới API cũ sẽ bị 400.

**7. Hoàn tác** — Revert squash commit.

**8. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: `APPROVE GAP-021 Gate 3` / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy hay release; không sửa GAP-098.
