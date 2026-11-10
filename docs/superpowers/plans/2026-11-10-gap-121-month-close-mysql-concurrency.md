---
work_id: GAP-121
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-121/02-design.md
---

# GAP-121 — Real-MySQL concurrency tests for the month close: implementation plan

Executes Gate 2 S-1, J-1 (`docs/owner-decisions/GAP-121/02-design.md`). Only files in the packet's allowlist change.

1. **Test-support commands** in `app/Console/Commands/Testing/`, following `TreasuryConcurrencyTestTransfer`. Each one:
   - opens a transaction and calls the real service;
   - holds for `--hold` seconds, then commits;
   - prints `OK` or `REFUSED <reason>`.

   Commands:
   - `payroll:concurrency-test-close`
   - `payroll:concurrency-test-reopen`
   - `overhead:concurrency-test-close`
   - `time:concurrency-test-add`
2. **Test** `tests/Feature/Concurrency/MonthCloseConcurrencyTest.php` (group `stress`), covering:
   - 1a: close, then add;
   - 1b: add, then close;
   - 2: two payroll closes;
   - 3a: overhead close, then reopen;
   - 3b: reopen, then overhead close;
   - 4: two overhead closes.

   The test skips when MySQL is not available.
3. **Prove the test bites:** locally, temporarily remove one lock (not committed) and see the matching race fail.
4. **CI:**
   - add `scripts/ci/month-close-concurrency-mysql`;
   - add a new job in `automated-testing.yml`, modeled on the Treasury job, that fails if the test is skipped.
5. **Green:**
   - the new test passes on local MySQL 8;
   - the full suites pass;
   - static checks and `WorkflowReferencesExistTest` pass.
