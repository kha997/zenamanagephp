---
work_id: GAP-078
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-10-14-gap-078-unrouted-sidebar-builders-evidence.md
  plan: null
  branch: docs/GAP-078-unrouted-sidebar-builders
  pr: https://github.com/kha997/zenamanagephp/pull/351
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-14T20:36:20+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-14T20:36:20+07:00"
  updated_at: "2026-10-14T20:36:20+07:00"
generated_by: agent
---

# GAP-078 — Remove unrouted sidebar builders: Gate 2 design

## Owner Summary

- **A — xoá 2 controller + 4 view (khuyến nghị):** cùng các dòng PHPStan
  baseline và sửa chú thích trong test.
- **B — chỉ xoá 2 controller:** giữ 4 view lại (sẽ thành view mồ côi, không
  ai dùng).

Đề xuất **Phương án A**.

## Design: Option A (exact allowlist)

1. Delete:
   - `app/Http/Controllers/Admin/SimpleSidebarBuilderController.php`
   - `app/Http/Controllers/Admin/SidebarBuilderController.php`
   - `resources/views/admin/sidebar-builder.blade.php`
   - `resources/views/admin/sidebar-builder-edit.blade.php`
   - `resources/views/admin/sidebar-preview.blade.php`
   - `resources/views/admin/sidebar-builder-content.blade.php`
2. `phpstan-baseline.neon`: remove only entries whose `path` is one of the
   deleted controllers.
3. `tests/Feature/Audit/AudChangeRequestPermissionSeedingTest.php`: comment
   only — the reference to `SimpleSidebarBuilderController.php:126` becomes a
   note that it lived in that controller until GAP-078; no test logic change.
4. Files: the above, plan, this packet, 03-release. No route, config, service
   or `src/` change.

## Verification

- `php artisan route:list --json` identical before and after.
- PHPStan clean; `php artisan view:cache` succeeds.
- Full Unit, Feature, Integration suites; lints; exact-head CI green.

## Rollback

Revert the squash commit.

## Out of scope

The `/admin/sidebar-builder` placeholder route, `PermissionService`
route-name map, `SidebarConfig`/`PresetService` menu links,
`Api\SidebarConfigController`, deployment.

## Decision Needed

Owner chọn: `APPROVE GAP-078 Gate 2 Option A` (khuyến nghị) / Option B /
Request changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, release hay deploy.
