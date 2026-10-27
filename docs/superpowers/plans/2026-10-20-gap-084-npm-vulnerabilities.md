---
work_id: GAP-084
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-084/02-design.md
---

# GAP-084 — npm vulnerabilities: implementation plan

Executes approved Gate 2 Option B (`docs/owner-decisions/GAP-084/02-design.md`).

1. **Baseline** — `npm audit`; `npm run build` on the base lock (output kept outside the repo).
2. **In-range** — `npm audit fix` (no `--force`); build output must be byte-identical; commit.
3. **vite 8** — `npm install -D vite@^8.3.4 laravel-vite-plugin@^3.2.0`; `Dockerfile.prod` → `node:22-alpine`;
   build; every manifest entry present; CSS rules equal after normalising the minifier; commit.
4. **Verify** — `npm audit`, lints, exact-head CI.
