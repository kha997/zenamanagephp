---
work_id: GAP-075
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-075/02-design.md
---

# GAP-075 — Quiet repeated PR bot comments: implementation plan

Executes approved Gate 2 Option A (`docs/owner-decisions/GAP-075/02-design.md`).

1. **Shared helper** — each of the 5 comment steps in `code-quality-security.yml` finds its marked comment
   (`<!-- zena-ci:<key> -->`), deletes it when there is no problem, otherwise creates it or updates it when the
   body changed.
2. **Conditions** — PHPStan errors or cs-fixer findings in PR-changed files; audit/security-checker findings;
   outdated packages new versus the base lock (`composer outdated --locked` on both); Trivy vulnerabilities;
   non-empty require-checker output.
3. **button-tests.yml** — remove the static "Comment PR" step.
4. **Verify** — clean exact-head CI with no new report comments; positive path with a throw-away mis-formatted
   line (one comment), then revert (comment deleted); both disclosed in Gate 3.
