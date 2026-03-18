# Slack Notify Step Inventory

This document inventories the remaining Slack notify steps found in:

- `.github/workflows/automated-deployment.yml`
- `.github/workflows/release-management.yml`
- `.github/workflows/production.yml`

Missing runtime or secret evidence is marked `UNKNOWN` per `docs/agent-ssot-rules.md:1-30`.

## Summary findings

- Total notify steps found: 8.
- Steps using `8398a7/action-slack@v3`: 7. `.github/workflows/automated-deployment.yml:124-137`, `.github/workflows/automated-deployment.yml:255-268`, `.github/workflows/automated-deployment.yml:320-333`, `.github/workflows/automated-deployment.yml:461-474`, `.github/workflows/automated-deployment.yml:620-633`, `.github/workflows/release-management.yml:305-318`, `.github/workflows/release-management.yml:370-383`
- Steps using `slackapi/slack-github-action@v2.1.1`: 1. `.github/workflows/production.yml:153-182`
- Steps with explicit `channel:` override in workflow YAML: 7.
- Steps with repo-proven incoming webhook usage: 8.
- Steps with repo-proven bot-token usage: 0.

## Inventory table

| Workflow | Job | Step name | Current action/version | Uses incoming webhook / bot token / UNKNOWN | Channel currently used | Has channel override? | Secret/env related | Migration safety | Specific blocker | Recommended path |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `automated-deployment.yml` | `deploy-staging` | `Notify deployment success` | `8398a7/action-slack@v3` | Incoming webhook via `env.SLACK_WEBHOOK_URL` -> `secrets.SLACK_WEBHOOK_URL` | `#deployments` in YAML override; webhook-bound default channel is `UNKNOWN` | Yes | `SLACK_WEBHOOK_URL`, `GITHUB_TOKEN` | `BLOCKED` | Shared webhook secret plus channel override; final routing design not approved | Hold migration until channel-routing design is approved; likely move to channel-specific webhook secret |
| `automated-deployment.yml` | `deploy-production` | `Notify deployment success` | `8398a7/action-slack@v3` | Incoming webhook via `env.SLACK_WEBHOOK_URL` -> `secrets.SLACK_WEBHOOK_URL` | `#deployments` in YAML override; webhook-bound default channel is `UNKNOWN` | Yes | `SLACK_WEBHOOK_URL`, `GITHUB_TOKEN` | `BLOCKED` | Shared webhook secret plus channel override; final routing design not approved | Hold migration until channel-routing design is approved; likely move to channel-specific webhook secret |
| `automated-deployment.yml` | `rollback` | `Notify rollback` | `8398a7/action-slack@v3` | Incoming webhook via `env.SLACK_WEBHOOK_URL` -> `secrets.SLACK_WEBHOOK_URL` | `#deployments` in YAML override; webhook-bound default channel is `UNKNOWN` | Yes | `SLACK_WEBHOOK_URL`, `GITHUB_TOKEN` | `BLOCKED` | Shared webhook secret plus channel override; final routing design not approved | Hold migration until channel-routing design is approved; likely move to channel-specific webhook secret |
| `automated-deployment.yml` | `blue-green-deployment` | `Notify blue-green deployment success` | `8398a7/action-slack@v3` | Incoming webhook via `env.SLACK_WEBHOOK_URL` -> `secrets.SLACK_WEBHOOK_URL` | `#deployments` in YAML override; webhook-bound default channel is `UNKNOWN` | Yes | `SLACK_WEBHOOK_URL`, `GITHUB_TOKEN` | `BLOCKED` | Shared webhook secret plus channel override; final routing design not approved | Hold migration until channel-routing design is approved; likely move to channel-specific webhook secret |
| `automated-deployment.yml` | `canary-deployment` | `Notify canary deployment success` | `8398a7/action-slack@v3` | Incoming webhook via `env.SLACK_WEBHOOK_URL` -> `secrets.SLACK_WEBHOOK_URL` | `#deployments` in YAML override; webhook-bound default channel is `UNKNOWN` | Yes | `SLACK_WEBHOOK_URL`, `GITHUB_TOKEN` | `BLOCKED` | Shared webhook secret plus channel override; final routing design not approved | Hold migration until channel-routing design is approved; likely move to channel-specific webhook secret |
| `release-management.yml` | `deploy-release` | `Notify release deployment success` | `8398a7/action-slack@v3` | Incoming webhook via `env.SLACK_WEBHOOK_URL` -> `secrets.SLACK_WEBHOOK_URL` | `#releases` in YAML override; webhook-bound default channel is `UNKNOWN` | Yes | `SLACK_WEBHOOK_URL`, `GITHUB_TOKEN` | `BLOCKED` | Shared webhook secret plus channel override; final routing design not approved | Hold migration until channel-routing design is approved; likely move to channel-specific webhook secret |
| `release-management.yml` | `rollback-release` | `Notify rollback` | `8398a7/action-slack@v3` | Incoming webhook via `env.SLACK_WEBHOOK_URL` -> `secrets.SLACK_WEBHOOK_URL` | `#releases` in YAML override; webhook-bound default channel is `UNKNOWN` | Yes | `SLACK_WEBHOOK_URL`, `GITHUB_TOKEN` | `BLOCKED` | Shared webhook secret plus channel override; final routing design not approved | Hold migration until channel-routing design is approved; likely move to channel-specific webhook secret |
| `production.yml` | `notify` | `Notify deployment status` | `slackapi/slack-github-action@v2.1.1` | Incoming webhook via `with.webhook` and `webhook-type: incoming-webhook` | `UNKNOWN` from repo; no channel in payload and webhook-bound destination is not in repo | No | `SLACK_WEBHOOK_URL` env, `secrets.SLACK_WEBHOOK_URL` in `with.webhook` | `SAFE` | No channel override in YAML; only missing evidence is external webhook-to-channel mapping | Leave unchanged in this round; if standardizing later, map it to an explicit per-channel webhook secret |

## Evidence index

### Automated Deployment

- `deploy-staging` notify step: `.github/workflows/automated-deployment.yml:124-137`
- `deploy-production` notify step: `.github/workflows/automated-deployment.yml:255-268`
- `rollback` notify step: `.github/workflows/automated-deployment.yml:320-333`
- `blue-green-deployment` notify step: `.github/workflows/automated-deployment.yml:461-474`
- `canary-deployment` notify step: `.github/workflows/automated-deployment.yml:620-633`

### Release Management

- `deploy-release` notify step: `.github/workflows/release-management.yml:305-318`
- `rollback-release` notify step: `.github/workflows/release-management.yml:370-383`

### Production Deployment

- `notify` step: `.github/workflows/production.yml:153-182`

## UNKNOWNs

- Actual Slack channel currently bound to `secrets.SLACK_WEBHOOK_URL`.
- Whether Slack bot token secrets already exist.
- Whether Slack channel IDs already exist in repo variables or secrets.
- Whether operationally the `channel:` override in `8398a7/action-slack@v3` is required, tolerated, or already deprecated outside repo evidence.
