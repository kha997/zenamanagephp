---
work_id: GAP-019
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-019/02-design.md
---

# GAP-019 — Remove the universal-frame shell: implementation plan

Executes Gate 2 Option A (`docs/owner-decisions/GAP-019/02-design.md`) once the Owner approves it.

1. **Red:** `tests/Feature/Architecture/UniversalFrameShellRemovedTest.php` fails (the 14 views exist; the launch
   checklist still returns `universal_page_frame`, `smart_tools`, `mobile_optimization`).
2. **Green:** delete the 14 views; drop the 3 entries and their private checks from
   `LaunchChecklistService::runSystemIntegrationChecks()`.
3. **Verify:** `php artisan view:cache`; the new test and the universal-frame / final-integration tests; Unit + Feature +
   Integration and `tests/Feature/Api`; PHPStan, php-cs-fixer, SSOT lint, route guard, deptrac.
4. **Gate 3:** release packet with subject and implementation-tree digest.
