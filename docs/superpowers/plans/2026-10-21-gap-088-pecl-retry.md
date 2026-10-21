---
work_id: GAP-088
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-088/02-design.md
---

# GAP-088 — Docker builds survive flaky pecl.php.net: implementation plan

Executes approved Gate 2 Option A (`docs/owner-decisions/GAP-088/02-design.md`).

1. **Dockerfiles** — in `Dockerfile.prod`, `Dockerfile.websocket` and `Dockerfile`, replace `pecl install redis`
   with a 5-attempt retry loop around `pecl install redis-6.3.0` (waits 15/30/45/60 s, logs each failed attempt,
   exits non-zero after the fifth).
2. **Local check** — run the loop's shell with a stub `pecl` that fails N times, for N = 0, 2 and 5.
3. **Proof** — a temporary `pull_request` workflow builds `Dockerfile.websocket` and `Dockerfile` and checks
   `php -m` lists `redis`; a temporary commit asks `Dockerfile.prod` for a missing version and must show five
   attempts before going red. Both are reverted.
4. **Verify** — exact-head CI green; governance and docs lints pass.
