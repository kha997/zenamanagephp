---
work_id: GAP-090
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-090/02-design.md
---

# GAP-090 — Dusk browser-tests flakes: implementation plan

Executes approved Gate 2 Option A (`docs/owner-decisions/GAP-090/02-design.md`).

1. **Server** — in `button-tests.yml` job `browser-tests`, replace `php -S` and its supervisor loop with php-fpm
   (pool on `/tmp/dusk-fpm.sock`) behind nginx on `127.0.0.1:8000`. The healthcheck, diagnostics and artifacts
   follow the php-fpm and nginx pid and log files.
2. **Retry** — "Run Browser Tests" runs Dusk once.
3. **Cancel click** — `ProjectCreateTest::test_project_create_cancel_flow` scrolls to the top before clicking
   `@project-cancel`.
4. **Proof** — a temporary commit runs `browser-tests` as an 8-copy matrix (`fail-fast: false`) and, before Dusk,
   kills every php-fpm worker with SIGSEGV and checks port 8000 still accepts connections and returns HTTP 200 again.
   All 8 copies must pass; the commit is reverted.
5. **Verify** — actionlint, `lint-mysql-claim-truthfulness.php`, exact-head CI, governance and docs lints.
