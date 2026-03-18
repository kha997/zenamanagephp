# Slack Channel Routing Migration Proposal

## Context

This round is docs-first only. No workflow runtime behavior changes are included in this round.

The canonical SSOT rules for this round are in `docs/agent-ssot-rules.md`, especially the requirement to mark missing evidence as `UNKNOWN`. `docs/agent-ssot-rules.md:1-30`

Three workflow files were reviewed as read-only input:

- `.github/workflows/automated-deployment.yml`
- `.github/workflows/release-management.yml`
- `.github/workflows/production.yml`

## Current evidence

Evidence found in repository:

- `automated-deployment.yml` has five Slack notify steps using `8398a7/action-slack@v3`, all wired to `secrets.SLACK_WEBHOOK_URL`, and all five explicitly set `channel: '#deployments'`. `.github/workflows/automated-deployment.yml:124-137`, `.github/workflows/automated-deployment.yml:255-268`, `.github/workflows/automated-deployment.yml:320-333`, `.github/workflows/automated-deployment.yml:461-474`, `.github/workflows/automated-deployment.yml:620-633`
- `release-management.yml` has two Slack notify steps using `8398a7/action-slack@v3`, both wired to `secrets.SLACK_WEBHOOK_URL`, and both explicitly set `channel: '#releases'`. `.github/workflows/release-management.yml:305-318`, `.github/workflows/release-management.yml:370-383`
- `production.yml` has one Slack notify step using `slackapi/slack-github-action@v2.1.1` with `webhook-type: incoming-webhook` and `webhook: ${{ secrets.SLACK_WEBHOOK_URL }}`. The payload in repo does not include a channel field. `.github/workflows/production.yml:153-182`
- No repo evidence shows what Slack channel is currently bound to `secrets.SLACK_WEBHOOK_URL`. That mapping is `UNKNOWN`.
- No repo evidence shows whether a bot token secret or Slack channel ID secrets already exist. That is `UNKNOWN`.

## Problem statement

Current workflows mix two routing models:

- `8398a7/action-slack@v3` with a single `SLACK_WEBHOOK_URL` plus per-step `channel:` override.
- `slackapi/slack-github-action@v2.1.1` using an incoming webhook without any channel specified in payload.

This creates a design decision that must be settled before migrating the remaining notify steps:

- If routing continues through incoming webhooks, channel targeting must be made explicit and supportable per channel.
- If routing moves to bot-token based posting, workflows need a new secret model and channel ID configuration.

Without that decision, auto-migrating the remaining steps would risk changing Slack delivery behavior with no repo evidence for the current channel binding behind `SLACK_WEBHOOK_URL`.

## Option A: webhook riêng theo channel

Model:

- Keep incoming-webhook delivery.
- Provision one webhook secret per destination channel, for example `SLACK_WEBHOOK_DEPLOYMENTS` and `SLACK_WEBHOOK_RELEASES`.
- Remove dependence on `channel:` override for routing; each step posts to the webhook that is already bound to the intended channel.

Pros:

- Closest to the current `production.yml` pattern, which already uses `webhook-type: incoming-webhook`. `.github/workflows/production.yml:156-162`
- Smaller secret blast radius per channel.
- Lower workflow complexity than introducing Slack app scopes and channel IDs.

Cons:

- Requires multiple webhook secrets instead of one shared `SLACK_WEBHOOK_URL`.
- Channel changes require webhook re-provisioning or secret remapping outside the repo.
- Repo alone still cannot prove final channel binding; that remains operational configuration.

## Option B: bot token + channel ID

Model:

- Standardize on `slackapi/slack-github-action`.
- Post via bot token and explicit channel ID per step.
- Store bot token and channel IDs as secrets or variables.

Pros:

- Channel routing becomes explicit in workflow config instead of implicit in webhook configuration.
- Easier to reuse one Slack app across multiple channels.
- Better fit if routing needs to become dynamic later.

Cons:

- Requires a Slack app with token scopes and channel membership not evidenced in repo.
- Larger security surface than channel-specific incoming webhooks.
- Requires new secrets and probably channel ID inventory before migration.

## Security / secret management impact

Option A:

- Adds multiple incoming webhook secrets.
- Limits each secret to one destination channel if provisioned that way.
- Keeps current auth pattern closer to existing `SLACK_WEBHOOK_URL` usage. `.github/workflows/automated-deployment.yml:128-130`, `.github/workflows/release-management.yml:309-311`, `.github/workflows/production.yml:157-160`

Option B:

- Requires at least one bot token secret.
- Likely requires separate secret or variable storage for channel IDs.
- Introduces token scope management and channel membership administration outside the repo.

In both options, current secret inventory is incomplete from repo evidence. Existing non-`SLACK_WEBHOOK_URL` Slack secrets are `UNKNOWN`.

## Operational impact

Option A:

- Lower migration friction for notify-only use cases.
- Operations team must own webhook provisioning and channel-to-secret mapping.

Option B:

- Higher setup cost.
- Better long-term flexibility if future workflows need explicit routing, richer Slack APIs, or centralized app governance.

## Migration risk

Primary risk evidenced in repo:

- Eight notify steps remain; seven of them rely on `8398a7/action-slack@v3`, and seven of the eight overall rely on a shared `SLACK_WEBHOOK_URL` secret while assuming channel routing behavior. `.github/workflows/automated-deployment.yml:124-137`, `.github/workflows/automated-deployment.yml:255-268`, `.github/workflows/automated-deployment.yml:320-333`, `.github/workflows/automated-deployment.yml:461-474`, `.github/workflows/automated-deployment.yml:620-633`, `.github/workflows/release-management.yml:305-318`, `.github/workflows/release-management.yml:370-383`

Specific risks:

- Current effective channel behind `SLACK_WEBHOOK_URL` is `UNKNOWN`.
- Whether `channel:` override is relied on operationally is implied by YAML but not externally validated.
- Bot-token readiness is `UNKNOWN`.

## Recommendation

Recommend Option A as the default migration target for the next implementation round, with one incoming webhook secret per destination channel.

Reasoning:

- It matches the current `production.yml` incoming-webhook direction without requiring bot-token infrastructure not evidenced in repo. `.github/workflows/production.yml:156-162`
- It removes the channel-routing ambiguity caused by `channel:` override on a shared webhook secret.
- It minimizes change surface for notify-only workflows.

Exception:

- If platform owners already have an approved Slack app, bot token, and channel ID inventory outside repo, Option B can be reconsidered. Current repo evidence for that readiness is `UNKNOWN`.

## Preconditions

- Confirm actual Slack destinations currently receiving posts from `secrets.SLACK_WEBHOOK_URL`; repo evidence is `UNKNOWN`.
- Decide whether separate destination channels must remain `#deployments` and `#releases` as encoded today. `.github/workflows/automated-deployment.yml:133`, `.github/workflows/automated-deployment.yml:264`, `.github/workflows/automated-deployment.yml:329`, `.github/workflows/automated-deployment.yml:470`, `.github/workflows/automated-deployment.yml:629`, `.github/workflows/release-management.yml:314`, `.github/workflows/release-management.yml:379`
- Provision target secrets for the chosen design.
- Decide naming convention for new secrets or variables.

## Rollout plan

1. Confirm channel-routing design decision: Option A or Option B.
2. Inventory and provision required Slack secrets/config outside repo.
3. Migrate blocked workflow steps in a separate implementation round.
4. Validate that each migrated step preserves its intended destination channel.
5. Remove legacy channel override usage only after successful validation.

## Done criteria

- Design choice between webhook-per-channel and bot-token/channel-ID is documented and approved.
- Required Slack secret/config prerequisites are explicitly identified.
- Remaining notify steps are inventoried with migration status and blockers.
- No workflow YAML behavior is changed in this round.

## Explicit non-goals

- No workflow YAML edits in this round.
- No migration of `8398a7/action-slack@v3` steps in this round.
- No appleboy/ssh-action changes.
- No schema, RBAC, tenant isolation, route contract, or CI behavior changes.
- No external Slack workspace verification from this repo-only pass.
