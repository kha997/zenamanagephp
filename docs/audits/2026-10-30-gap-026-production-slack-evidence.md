# GAP-026 — Production Slack notification routing: Gate-1 evidence

**Date:** 2026-10-30 (+07:00)

**Canonical base:** `c8938b6e6ef7b9c4cf5cd0ba509df0a278ef9ad7`

**Branch:** `docs/GAP-026-production-slack`

**Scope:** Read-only investigation. No code change.

## 1. The external blocker is resolved

`docs/change-proposals/production-slack-webhook-freeze-decision.md` froze `.github/workflows/production.yml` on
`secrets.SLACK_WEBHOOK_URL` until an operator confirmed which Slack channel that secret targets. Owner answer
in-session on 2026-10-30 (AskUserQuestion), verbatim: "SLACK_WEBHOOK_URL → Zena", for the Zena channel
`https://app.slack.com/client/T0BBEHJE17T/C0BBAL5SZM4` (workspace `T0BBEHJE17T`, channel `C0BBAL5SZM4`). Production keeps
the same secret. The secret value itself is not readable from the repository.

## 2. New finding: the production Slack steps never run

`.github/workflows/production.yml` has two Slack steps:

- `deploy` job, "Escalate breaking-migration recovery requirement" (around line 375).
- `notify` job, "Notify truthful deployment state" (around line 408).

Both are guarded by `if: ${{ ... env.SLACK_WEBHOOK_URL != '' }}`, but `SLACK_WEBHOOK_URL` is defined only in each step's
own `env:` block. A step's own `env` is not available when that step's `if` is evaluated; the workflow `env:` (line 25)
holds only `PHP_VERSION`, and neither job sets it. So `env.SLACK_WEBHOOK_URL` is empty in both conditions and both steps
are skipped on every run, whatever the secret holds.

By contrast, `automated-deployment.yml` (line 23) and `release-management.yml` (line 29) declare their webhook secret
in the workflow-level `env:`, so their identical guards work.

Also: the `notify` job has no `environment: production`. If `SLACK_WEBHOOK_URL` is stored as a `production`
environment secret (rather than a repository secret), it is not available to that job.

## 3. Related records

- Register row GAP-026: **BLOCKED (external)**.
- `docs/audits/production-slack-webhook-evidence.md`, `docs/audits/slack-routing-secret-inventory.md`: destination
  recorded as `UNKNOWN`.
