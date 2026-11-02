---
work_id: GAP-102
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-102/02-design.md
---

# GAP-102 — follow-redirects GHSA-mrwp-f3q8-3g9p: implementation plan

Executes the approved Gate 2 option (`docs/owner-decisions/GAP-102/02-design.md`) once the Owner approves it.

1. **Red:** `npm audit --package-lock-only` reports GHSA-mrwp-f3q8-3g9p (high) for `follow-redirects` 1.16.1.
2. **Green:** generate the 1.16.2 entry with `npm update follow-redirects --package-lock-only` and keep only the
   `node_modules/follow-redirects` change (version, resolved, integrity) in `package-lock.json`.
3. **Verify:** `npm audit --package-lock-only` reports 0 high/critical; `npm ci --ignore-scripts` succeeds from the lock;
   `npm run build` succeeds; diff touches only those 3 lines.
4. **Gate 3:** release packet with subject and implementation-tree digest.
