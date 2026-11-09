---
work_id: GAP-116
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-116/02-design.md
---

# GAP-116 — Flaky ProjectApiTest (project factory dates): implementation plan

Executes Gate 2 F-1, G-1 (`docs/owner-decisions/GAP-116/02-design.md`).

1. **Red:** add `tests/Unit/Factories/ProjectFactoryDatesTest.php`.
   - Build 200 projects per state (default, `completed()`, `overdue()`) with `make()`, using a fixed Faker seed.
   - Assert that the end calendar day is after the start calendar day.
   - For `overdue()`, also assert that the end is before today.
   - Confirm the test fails on the current factory.
2. **F-1:** change the date ranges in `database/factories/ProjectFactory.php`.
3. **Green:**
   - the guard test passes;
   - `ProjectApiTest` passes when run 50 times in a loop;
   - the full suites pass;
   - php-cs-fixer and SSOT lint are clean.
