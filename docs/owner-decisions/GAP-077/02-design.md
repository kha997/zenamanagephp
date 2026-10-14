---
work_id: GAP-077
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-10-14-gap-077-dead-controllers-evidence.md
  plan: null
  branch: docs/GAP-077-dead-controllers
  pr: https://github.com/kha997/zenamanagephp/pull/350
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-14T15:37:15+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-14T15:37:15+07:00"
  updated_at: "2026-10-14T15:37:15+07:00"
generated_by: agent
---

# GAP-077 — Remove unreachable controllers: Gate 2 design

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
