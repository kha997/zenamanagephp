# Production Slack Webhook Evidence Audit

## Context

This document records the repo-only evidence for the production Slack notification wiring in `.github/workflows/production.yml`.

This audit is limited to repository evidence. Where the repository does not prove the current Slack destination, the status remains `UNKNOWN`.

## Findings

### 1. Current production workflow wiring

File reviewed: `.github/workflows/production.yml`

- The production notify step is `Notify deployment status`. `.github/workflows/production.yml:153`
- The step is gated by `if: ${{ always() && env.SLACK_WEBHOOK_URL != '' }}`. `.github/workflows/production.yml:154`
- The step maps `env.SLACK_WEBHOOK_URL` from `secrets.SLACK_WEBHOOK_URL`. `.github/workflows/production.yml:157-158`
- The step sends `webhook: ${{ secrets.SLACK_WEBHOOK_URL }}` using `slackapi/slack-github-action@v2.1.1` and `webhook-type: incoming-webhook`. `.github/workflows/production.yml:156-161`
- The payload in repo does not specify a Slack channel override. `.github/workflows/production.yml:162-168`

### 2. Repo has already migrated other workflows to channel-specific webhook secrets

- `automated-deployment.yml` uses `SLACK_WEBHOOK_DEPLOYMENTS`. `.github/workflows/automated-deployment.yml:26`, `.github/workflows/automated-deployment.yml:126-130`, `.github/workflows/automated-deployment.yml:271-275`, `.github/workflows/automated-deployment.yml:350-354`, `.github/workflows/automated-deployment.yml:505-509`, `.github/workflows/automated-deployment.yml:678-682`
- `release-management.yml` uses `SLACK_WEBHOOK_RELEASES`. `.github/workflows/release-management.yml:29`, `.github/workflows/release-management.yml:303-307`, `.github/workflows/release-management.yml:382-386`

### 3. What the repository does not prove

- No repo evidence maps `secrets.SLACK_WEBHOOK_URL` to a specific Slack channel.
- No repo evidence explains whether `SLACK_WEBHOOK_URL` is intentionally reserved for production-only notifications.
- No repo evidence confirms whether the current production webhook should be replaced by `SLACK_WEBHOOK_DEPLOYMENTS`, `SLACK_WEBHOOK_RELEASES`, or another secret.
- Prior metadata visibility for `gh secret list` did not show Slack secrets in the available scope, so repo-adjacent secret metadata remains insufficient to identify the destination channel.

## Risk assessment

- Remapping `production.yml` now could silently change the destination of production deployment notifications.
- Switching to `SLACK_WEBHOOK_DEPLOYMENTS` without confirmation could alter established production alerting behavior if `SLACK_WEBHOOK_URL` currently targets a different channel.
- Creating or adopting a new secret name without confirmation would encode a routing decision that the repo does not justify.

## Conclusion

The repository proves that `production.yml` still posts via `secrets.SLACK_WEBHOOK_URL`, while other workflows have moved to channel-specific webhook secrets.

The repository does not prove which Slack channel currently receives posts from `secrets.SLACK_WEBHOOK_URL`. That destination remains `UNKNOWN`.

Because the target channel is `UNKNOWN`, the repo-only conclusion is to keep the current `production.yml` mapping unchanged until operators provide external confirmation of the webhook's actual destination and intended replacement strategy.

## Operator questions

- Which Slack channel currently receives posts from `secrets.SLACK_WEBHOOK_URL` in the production workflow?
- Is `secrets.SLACK_WEBHOOK_URL` intentionally reserved for production deployment notifications, or is it a legacy shared webhook?
- If the current mapping is legacy, what exact destination should replace it for `production.yml`?
- Should production notifications remain on the current channel after the rest of the repo's channel-specific webhook migration?

## Decision status

- Status: `FROZEN`
- Current action: Keep `.github/workflows/production.yml` unchanged and continue using `SLACK_WEBHOOK_URL`.
- Blocker to unfreeze: External confirmation of the actual Slack channel bound to `secrets.SLACK_WEBHOOK_URL` and explicit operator approval for any remap.
