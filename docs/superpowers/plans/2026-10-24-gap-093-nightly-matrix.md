---
work_id: GAP-093
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-093/02-design.md
---

# GAP-093 — Nightly Test Matrix: implementation plan

Executes approved Gate 2 Option A (`docs/owner-decisions/GAP-093/02-design.md`).

1. **Workflow** — `nightly-matrix.yml` builds `.env` from `.env.example`, uses `CACHE_DRIVER: array` and
   `QUEUE_CONNECTION: sync`, drops the app server and websocket steps, keeps only the Redis readiness check, and drops
   `APP_URL`, `WEBSOCKET_*` and the server log artifacts.
2. **Script** — `scripts/test/nightly_matrix.sh` drops the `ssot:lint` and `test:fast` stages; the `redis` group runs with
   `CACHE_DRIVER=redis`; the report drops the app and websocket lines.
3. **Proof** — run the workflow with `workflow_dispatch` on the PR branch; it must be green and report the four groups.
4. **Verify** — `bash -n`, actionlint, exact-head CI, governance and docs lints.
