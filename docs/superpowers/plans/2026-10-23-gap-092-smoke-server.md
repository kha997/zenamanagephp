---
work_id: GAP-092
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-092/02-design.md
---

# GAP-092 — staging-smoke server: implementation plan

Executes approved Gate 2 Option A (`docs/owner-decisions/GAP-092/02-design.md`).

1. **Script** — `scripts/smoke_staging.sh` gains `SMOKE_SERVER` (`artisan` default, `fpm`). `start_fpm_server` writes
   php-fpm and nginx configs under `/tmp`, starts `php-fpm -F` (tracked as `PHP_PID`) and nginx (`NGINX_PID`);
   `cleanup` stops both; the report names the server.
2. **Workflow** — `staging-smoke.yml` sets `SMOKE_SERVER: fpm`, installs nginx only if missing, runs the script once,
   and uploads the nginx logs.
3. **Local proof** — fpm run passes; a run with every php-fpm worker killed by SIGSEGV mid-run passes; the default
   artisan run still passes.
4. **CI proof** — a temporary commit runs staging-smoke as a 16-copy matrix, one attempt each; all must pass; reverted.
5. **Verify** — `bash -n`, actionlint, exact-head CI, governance and docs lints.
