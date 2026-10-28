---
work_id: GAP-018
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-018/02-design.md
---

# GAP-018 — Dead smart-tools components: implementation plan

Executes approved Gate 2 Option A (`docs/owner-decisions/GAP-018/02-design.md`).

1. **Red:** add `tests/Feature/Architecture/DeadSmartToolsComponentsRemovedTest.php` asserting the five views do not
   exist; it fails while they exist.
2. **Green:** delete `resources/views/components/{smart-search,smart-filters,analysis-drawer,export-component}.blade.php`
   and `resources/views/test-smart-tools.blade.php`; the test passes.
3. Verify: `php artisan view:cache`; Architecture, Web and Feature suites; SSOT lint; PHPStan; route guard; governance and
   docs lints.
