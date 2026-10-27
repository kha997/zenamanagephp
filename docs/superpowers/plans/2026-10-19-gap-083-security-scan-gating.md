---
work_id: GAP-083
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-083/02-design.md
---

# GAP-083 — Make security scans fail CI: implementation plan

Executes approved Gate 2 Option B (`docs/owner-decisions/GAP-083/02-design.md`).

1. **security-scan job** — composer audit records its exit code (`--abandoned=report`); remove PHPStan Security
   Rules (step, summary section, artifact paths); add npm audit (`--package-lock-only`, JSON report in summary and
   artifact); final gate step fails on a composer advisory or an npm critical.
2. **docker-security job** — final Trivy step on `zenamanage:test`: `exit-code: 1`, `CRITICAL,HIGH`,
   `ignore-unfixed`, `.trivyignore` when present.
3. **Red/green proof** — temporary commits that make each gate red (composer advisory, npm critical, widened Trivy
   scope), each reverted; final head green.
4. **Verify** — YAML parse, governance/SSOT/docs lints, exact-head CI.
