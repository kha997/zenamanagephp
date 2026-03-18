# Production Slack Webhook Freeze Decision

## Context

This document records the docs-only freeze decision for the production Slack notification mapping in `.github/workflows/production.yml`.

The goal of this round is not to migrate or normalize workflow wiring. The goal is to make the current decision explicit while the actual Slack destination behind `secrets.SLACK_WEBHOOK_URL` remains unverified from repository evidence.

## Current evidence

- `production.yml` still uses `slackapi/slack-github-action@v2.1.1` with `webhook: ${{ secrets.SLACK_WEBHOOK_URL }}`. `.github/workflows/production.yml:153-161`
- The production notify step is guarded by `env.SLACK_WEBHOOK_URL != ''`, with the env value mapped from `secrets.SLACK_WEBHOOK_URL`. `.github/workflows/production.yml:154`, `.github/workflows/production.yml:157-158`
- The production payload in repo does not specify a target Slack channel. `.github/workflows/production.yml:162-168`
- Other workflows have already migrated to channel-specific webhook secrets: `SLACK_WEBHOOK_DEPLOYMENTS` in `.github/workflows/automated-deployment.yml` and `SLACK_WEBHOOK_RELEASES` in `.github/workflows/release-management.yml`.
- No repo evidence maps `secrets.SLACK_WEBHOOK_URL` to a specific Slack channel.
- Available repo-adjacent secret metadata did not surface Slack secrets in the visible scope, so the destination channel is still not inferable from current evidence.
- Supporting audit: `docs/audits/production-slack-webhook-evidence.md`

## What is known

- The production workflow currently depends on `secrets.SLACK_WEBHOOK_URL`.
- The repository has already introduced channel-specific secret names for non-production deployment and release notifications.
- A remap of `production.yml` would be behavior-changing unless the replacement secret resolves to the same Slack destination.

## What remains unknown

- Which Slack channel is currently bound to `secrets.SLACK_WEBHOOK_URL`.
- Whether that webhook is intentionally production-specific or is a legacy shared secret.
- Whether the correct long-term replacement for production is `SLACK_WEBHOOK_DEPLOYMENTS`, a new production-specific secret, or no change at all.
- Whether operations expects production deployment notifications to remain distinct from deployment or release routing used elsewhere.

## Risks of remapping now

- Production notifications could move to the wrong Slack channel.
- Operators could lose visibility into production deployment outcomes if they rely on the current undisclosed destination.
- A docs or workflow change could create false certainty around a routing decision that has not been externally confirmed.

## Freeze decision

Keep `.github/workflows/production.yml` unchanged for now and continue using `SLACK_WEBHOOK_URL`.

Do not remap the production notify step to `SLACK_WEBHOOK_DEPLOYMENTS`, `SLACK_WEBHOOK_RELEASES`, or any new secret until there is external confirmation of the current destination and intended future destination.

Reason: the repository does not prove which Slack channel currently receives production notifications from `secrets.SLACK_WEBHOOK_URL`, so remapping now would risk changing production alerting behavior without evidence.

## Exit criteria to unfreeze

- An operator confirms the exact Slack channel currently bound to `secrets.SLACK_WEBHOOK_URL`.
- An operator confirms whether `production.yml` should keep that destination or move to a different one.
- The replacement target is named explicitly, such as `SLACK_WEBHOOK_DEPLOYMENTS` or a new production-specific secret.
- The change is approved as an intentional behavior update rather than an inferred cleanup.

## Exact operator questions

- Which exact Slack channel currently receives posts from `secrets.SLACK_WEBHOOK_URL` used by `.github/workflows/production.yml`?
- Is that current destination intentional for production deployment status notifications?
- If not, which exact destination should replace it?
- Should production notifications share `SLACK_WEBHOOK_DEPLOYMENTS`, or should they use a separate production-specific webhook secret?

## Non-goals

- No workflow YAML edits in this round.
- No changes to `.github/workflows/production.yml`.
- No remap to `SLACK_WEBHOOK_DEPLOYMENTS`, `SLACK_WEBHOOK_RELEASES`, or a new secret in this round.
- No changes to domain logic, RBAC, tenant isolation, or route contracts.
- No attempt to invent or infer Slack channel identity where evidence is missing.
