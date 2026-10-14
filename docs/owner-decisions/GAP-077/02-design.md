---
work_id: GAP-077
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-14-gap-077-dead-controllers-evidence.md
  plan: docs/superpowers/plans/2026-10-14-gap-077-dead-controllers.md
  branch: docs/GAP-077-dead-controllers
  pr: https://github.com/kha997/zenamanagephp/pull/350
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-14T15:38:16+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-14, verbatim: 'APPROVE GAP-077 Gate 2 Option A'. Reviewed design head: 11f851bffffcc79eff25e58f353bbaf70bc20398. Approves Option A and its exact allowlist (delete app/Http/Controllers/InteractionLogController.php, app/Http/Requests/StoreInteractionLogRequest.php, app/Http/Requests/UpdateInteractionLogRequest.php, app/Http/Controllers/Admin/BasicSidebarController.php, resources/views/admin/simple-sidebar-builder.blade.php; remove only their phpstan-baseline.neon entries); not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-14T15:37:15+07:00"
  updated_at: "2026-10-14T15:38:16+07:00"
generated_by: agent
---

# GAP-077 — Remove unreachable controllers: Gate 2 design

## OWNER GATE 2: APPROVED — OPTION A

Owner approved Option A in-session on 2026-10-14 against reviewed design head
`11f851bffffcc79eff25e58f353bbaf70bc20398`. This authorizes only the bounded implementation defined by this packet;
it does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

- **A — xoá cả 5 file (khuyến nghị):** controller InteractionLog cũ + 2
  Request, `BasicSidebarController` + view của nó; bỏ các dòng PHPStan
  baseline tương ứng.
- **B — chỉ xoá nhóm InteractionLog (3 file):** giữ `BasicSidebarController`
  và view để xử lý cùng 2 controller sidebar còn lại sau này.

Đề xuất **Phương án A**: `BasicSidebarController` không kiểm tra quyền, giữ lại
là rủi ro nếu bị nối route.

## Design: Option A (exact allowlist)

1. Delete:
   - `app/Http/Controllers/InteractionLogController.php`
   - `app/Http/Requests/StoreInteractionLogRequest.php`
   - `app/Http/Requests/UpdateInteractionLogRequest.php`
   - `app/Http/Controllers/Admin/BasicSidebarController.php`
   - `resources/views/admin/simple-sidebar-builder.blade.php`
2. `phpstan-baseline.neon`: remove only the entries whose `path` is one of
   the deleted files; no other change.
3. Files: the above, plan, this packet, 03-release. No route, config, test or
   `src/` change.

## Verification

- `php artisan route:list --json` identical before and after.
- PHPStan clean (no new errors, baseline only shrinks).
- Full Unit, Feature, Integration suites (same root-only exceptions as main);
  `composer dump-autoload` without warnings; SSOT/governance/docs lints.
- Exact-head CI green.

## Rollback

Revert the squash commit.

## Out of scope

`SimpleSidebarBuilderController`, `SidebarBuilderController`, other views,
`src/`, historical scripts, deployment.

## Decision Needed

Owner chọn: `APPROVE GAP-077 Gate 2 Option A` (khuyến nghị) / Option B /
Request changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, release hay deploy.
