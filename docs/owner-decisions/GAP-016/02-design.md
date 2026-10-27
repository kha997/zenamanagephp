---
work_id: GAP-016
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-25-gap-016-enhanced-routes-evidence.md
  plan: docs/superpowers/plans/2026-10-25-gap-016-enhanced-routes.md
  branch: docs/GAP-016-enhanced-routes
  pr: https://github.com/kha997/zenamanagephp/pull/368
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-25T16:25:44+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-25, verbatim: 'APPROVE GAP-016 Gate 2 Option A'. Reviewed design head: 9bea2107dbe86209bd52a59e14b72fd84ac0e6d4. Approves Option A and its exact allowlist (routes/web.php: remove the /admin-dashboard-enhanced and /projects-enhanced closures and their two comment lines; new regression test; docs); not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-25T16:24:38+07:00"
  updated_at: "2026-10-25T16:25:44+07:00"
generated_by: agent
---

# GAP-016 — Enhanced routes with missing views: Gate 2 design

## OWNER GATE 2: APPROVED — OPTION A

Owner approved Option A in-session on 2026-10-25 against reviewed design head
`9bea2107dbe86209bd52a59e14b72fd84ac0e6d4`. This authorizes only the bounded implementation defined by this packet;
it does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

Hai route `/projects-enhanced` và `/admin-dashboard-enhanced` trả lỗi 500 vì view đã bị xoá. Không có gì trong repo dẫn
tới chúng. Trang chính thức tương ứng là `/app/projects` và `/admin` (dashboard admin).

**Phương án A (khuyến nghị): xoá hai route.**
- Xoá hai route trong `routes/web.php`. Mở link sẽ nhận **404** thay vì **500**.
- Thêm test: hai link trả 404 (khi đã đăng nhập) và hai tên route không còn.
- Giống cách đã làm với GAP-017; audit 2026-03-19 cũng đề xuất xoá.

**Phương án B: chuyển hướng.** `/projects-enhanced` → `/app/projects`, `/admin-dashboard-enhanced` → `/admin`.
Giữ được link cũ nếu ai đó còn lưu, nhưng giữ lại hai URL không ai dùng.

Đề xuất **Phương án A**.

## Design: Option A (exact allowlist)

1. `routes/web.php`: remove the `/admin-dashboard-enhanced` and `/projects-enhanced` route closures and their two comment
   lines (`// Enhanced Admin Dashboard Route`, `// Enhanced Projects Management Route`).
2. New test `tests/Feature/Web/EnhancedRoutesRemovedTest.php`: a signed-in tenant user gets 404 on both URLs;
   `Route::has('projects-enhanced')` and `Route::has('admin-dashboard-enhanced')` are false.
3. Docs: plan, this packet, 03-release.

**Not changed:** `/app/projects`, `/admin`, any view, and old audit/refactor docs that mention these routes.

## Verification

- The new test fails on the base (500, routes exist) and passes after the change.
- `php artisan route:list` differs only by the two removed routes.
- Route and architecture tests, PHPStan, php-cs-fixer, governance and docs lints pass; exact-head CI green.

## Rollback

Revert the squash commit.

## Out of scope

- Updating `OPERATIONAL_GAP_REGISTER.md` (the queued register reconciliation).
- Other routes under `routes/web.php`.
- Deployment.

## Decision Needed

Owner chọn: `APPROVE GAP-016 Gate 2 Option A` (khuyến nghị) / Option B / Request changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, release hay deploy.
