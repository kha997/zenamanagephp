---
work_id: GAP-086
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-086/02-design.md
---

# GAP-086 — npm audit gate at high: implementation plan

Executes approved Gate 2 Option A (`docs/owner-decisions/GAP-086/02-design.md`).

1. **Gate step** — count distinct high/critical GHSA ids from `npm-audit.json` `.vulnerabilities[].via[]`, minus ids in
   an optional `.npm-audit-ignore`; fail and list them when any remain; missing report still fails.
2. **Local check** — run the step's shell against a clean report, a report with a high advisory, and the same with
   the id ignored.
3. **Red/green proof** — temporary commits (high advisory dependency → red; plus ignore entry → green), reverted.
4. **Verify** — lints, exact-head CI.
