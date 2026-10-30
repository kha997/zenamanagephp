---
work_id: GAP-026
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-026/02-design.md
---

# GAP-026 — Production Slack notifications: implementation plan

Executes the approved Gate 2 option (`docs/owner-decisions/GAP-026/02-design.md`) once the Owner approves it.

1. **Red:** a new `DeploymentGuardTest` case fails — `SLACK_WEBHOOK_URL` is set only at step level in `production.yml`.
2. **Green:** declare `SLACK_WEBHOOK_URL` in the `deploy` and `notify` job `env`; drop the step-level duplicates; Option B
   also sets `environment: production` on `notify`.
3. **Docs:** freeze decision resolved; evidence updated.
4. **Verify:** YAML parses; `DeploymentGuardTest`; Unit + Feature + Integration; governance and docs lints.
5. **Gate 3:** release packet with subject and implementation-tree digest.
