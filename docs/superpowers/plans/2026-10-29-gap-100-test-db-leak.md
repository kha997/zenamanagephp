---
work_id: GAP-100
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-100/02-design.md
---

# GAP-100 — Test classes that leak DB rows: implementation plan

Executes approved Gate 2 Option A (`docs/owner-decisions/GAP-100/02-design.md`).

1. **Red:** add `tests/Unit/Support/DatabaseLeakGuardTest.php` (applies by trait, snapshot counts, leak diff, null on
   non-SQLite); it fails while `Tests\Support\DatabaseLeakGuard` does not exist.
2. **Green:** add `tests/Support/DatabaseLeakGuard.php`.
3. Hook the guard into `tests/TestCase.php` (snapshot at the end of `setUp()` on the captured connection; compare and
   `fail()` at the start of `tearDown()`, always calling `parent::tearDown()`). Before step 4, confirm the guard fails
   exactly the 3 known leaking tests (`ProjectManagementTest` ×1, `DeployMigrateCommandTest` ×2).
4. Add `use RefreshDatabase;` to `tests/Feature/ProjectManagementTest.php` and
   `tests/Feature/Deployment/DeployMigrateCommandTest.php`. Gate-1 reproduction command goes red 3 → green.
5. Verify: Unit + Feature + Integration and `tests/Feature/Api` (no new failures vs the known baseline), timing, PHPStan,
   php-cs-fixer, `composer ssot:lint`, governance and docs lints; then Gate 3 packet.
