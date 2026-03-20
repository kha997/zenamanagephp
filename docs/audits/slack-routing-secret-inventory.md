# Slack Routing And Secret Inventory

## Scope

This is an evidence-only inventory for current Slack routing, secret usage, and workflow notify wiring in this repository.

This round is `docs-only`. It does not change workflow behavior, production routing, or secret names.

Where repository evidence is missing, the status is `UNKNOWN`.

## SSOT guardrails for this round

- Treat `main` as green after merge `#142 -> #146` per round SSOT input. This document does not re-prove CI state.
- Keep `.github/workflows/production.yml` locked on `SLACK_WEBHOOK_URL`.
- Do not infer secret-to-channel mapping from naming alone.
- Do not use docs examples, templates, or monitoring scripts as proof of GitHub production Slack routing.

## Findings

### 1. Active GitHub workflows

#### Migrated notify wiring

- `.github/workflows/automated-deployment.yml` uses `slackapi/slack-github-action@v2.1.1` with `secrets.SLACK_WEBHOOK_DEPLOYMENTS` for all five notify steps. [automated-deployment.yml](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/.github/workflows/automated-deployment.yml#L26) [automated-deployment.yml](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/.github/workflows/automated-deployment.yml#L125) [automated-deployment.yml](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/.github/workflows/automated-deployment.yml#L270) [automated-deployment.yml](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/.github/workflows/automated-deployment.yml#L349) [automated-deployment.yml](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/.github/workflows/automated-deployment.yml#L504) [automated-deployment.yml](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/.github/workflows/automated-deployment.yml#L677)
- `.github/workflows/release-management.yml` uses `slackapi/slack-github-action@v2.1.1` with `secrets.SLACK_WEBHOOK_RELEASES` for both notify steps. [release-management.yml](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/.github/workflows/release-management.yml#L29) [release-management.yml](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/.github/workflows/release-management.yml#L302) [release-management.yml](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/.github/workflows/release-management.yml#L381)
- No active workflow currently uses `8398a7/action-slack`. Repo hits for that action are docs-only and describe prior or proposed states. [slack-notify-step-inventory.md](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/docs/audits/slack-notify-step-inventory.md#L14) [slack-channel-routing-migration-proposal.md](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/docs/change-proposals/slack-channel-routing-migration-proposal.md#L19)

#### Locked / frozen production notify wiring

- `.github/workflows/production.yml` still uses `slackapi/slack-github-action@v2.1.1` with `webhook: ${{ secrets.SLACK_WEBHOOK_URL }}` and `webhook-type: incoming-webhook`. [production.yml](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/.github/workflows/production.yml#L153)
- The notify step is gated by `env.SLACK_WEBHOOK_URL != ''` and maps `env.SLACK_WEBHOOK_URL` from `secrets.SLACK_WEBHOOK_URL`. [production.yml](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/.github/workflows/production.yml#L154) [production.yml](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/.github/workflows/production.yml#L157)
- The payload does not specify a Slack channel. The effective destination remains `UNKNOWN` from repo evidence. [production.yml](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/.github/workflows/production.yml#L162)

#### appleboy/ssh-action inventory in active workflows

- `appleboy/ssh-action@v1.2.4` is active in `.github/workflows/production.yml`, `.github/workflows/automated-deployment.yml`, `.github/workflows/release-management.yml`, and `.github/workflows/deploy.yml`. [production.yml](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/.github/workflows/production.yml#L121) [automated-deployment.yml](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/.github/workflows/automated-deployment.yml#L94) [release-management.yml](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/.github/workflows/release-management.yml#L271) [deploy.yml](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/.github/workflows/deploy.yml#L37)
- These steps are deployment transport, not Slack routing. Changing them would be out of scope for this round.

### 2. Docs / change proposals / audit docs

#### Current docs aligned with SSOT

- `docs/audits/production-slack-webhook-evidence.md` correctly records that production remains on `SLACK_WEBHOOK_URL` and that the target channel is `UNKNOWN`. [production-slack-webhook-evidence.md](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/docs/audits/production-slack-webhook-evidence.md#L16)
- `docs/change-proposals/production-slack-webhook-freeze-decision.md` correctly records the freeze: do not remap production until external confirmation exists. [production-slack-webhook-freeze-decision.md](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/docs/change-proposals/production-slack-webhook-freeze-decision.md#L11) [production-slack-webhook-freeze-decision.md](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/docs/change-proposals/production-slack-webhook-freeze-decision.md#L40)

#### Docs that are now stale or historical

- `docs/audits/slack-notify-step-inventory.md` is stale versus current workflows. It still claims seven active `8398a7/action-slack@v3` notify steps and only one `slackapi/slack-github-action` step, which no longer matches workflow YAML. [slack-notify-step-inventory.md](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/docs/audits/slack-notify-step-inventory.md#L14)
- `docs/change-proposals/slack-channel-routing-migration-proposal.md` is historical proposal material, not current runtime truth. It describes a pre-migration state where seven notify steps still used `8398a7/action-slack@v3`. [slack-channel-routing-migration-proposal.md](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/docs/change-proposals/slack-channel-routing-migration-proposal.md#L19)
- `docs/DEPLOYMENT_GUIDE.md` and `PRODUCTION_DEPLOYMENT_GUIDE.md` contain example snippets using `appleboy/ssh-action@v0.1.5`. These are docs examples, not evidence of active workflow runtime. [DEPLOYMENT_GUIDE.md](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/docs/DEPLOYMENT_GUIDE.md#L385) [PRODUCTION_DEPLOYMENT_GUIDE.md](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/PRODUCTION_DEPLOYMENT_GUIDE.md#L405)

### 3. Scripts / monitoring / infra templates

- `scripts/monitor.sh` uses shell env `SLACK_WEBHOOK_URL` for local or server-side monitoring alerts. This is not GitHub Actions secret evidence and does not prove any production workflow Slack mapping. [monitor.sh](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/scripts/monitor.sh#L12) [monitor.sh](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/scripts/monitor.sh#L256)
- `manage-monitoring.sh` can send a test message to shell env `SLACK_WEBHOOK_URL`. This is operational tooling, not GitHub secret inventory proof. [manage-monitoring.sh](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/manage-monitoring.sh#L143)
- `docker/prometheus/alertmanager.yml` contains `YOUR_SLACK_WEBHOOK_URL` placeholders and explicit channels such as `#alerts-critical` and `#alerts-warning`. This is an infra template placeholder, not live secret evidence. [alertmanager.yml](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/docker/prometheus/alertmanager.yml#L26)
- `env.example` exposes `SLACK_WEBHOOK_URL=` as an application environment example. This is a placeholder and does not identify any GitHub secret binding. [env.example](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/env.example#L72)

## Classification Summary

| Group | File(s) | Current classification | Notes |
| --- | --- | --- | --- |
| Active GitHub workflows | `.github/workflows/automated-deployment.yml` | `MIGRATED` | Uses `SLACK_WEBHOOK_DEPLOYMENTS` with `slackapi/slack-github-action@v2.1.1` |
| Active GitHub workflows | `.github/workflows/release-management.yml` | `MIGRATED` | Uses `SLACK_WEBHOOK_RELEASES` with `slackapi/slack-github-action@v2.1.1` |
| Active GitHub workflows | `.github/workflows/production.yml` | `LOCKED / FROZEN` | Still uses `SLACK_WEBHOOK_URL`; destination channel is `UNKNOWN` |
| Active GitHub workflows | `.github/workflows/deploy.yml` | `NO SLACK` | Has `appleboy/ssh-action` only |
| Docs / proposals / audits | `docs/audits/production-slack-webhook-evidence.md` | `CURRENT EVIDENCE` | Matches current freeze state |
| Docs / proposals / audits | `docs/change-proposals/production-slack-webhook-freeze-decision.md` | `CURRENT DECISION` | Matches current freeze state |
| Docs / proposals / audits | `docs/audits/slack-notify-step-inventory.md` | `STALE` | Describes pre-migration notify inventory |
| Docs / proposals / audits | `docs/change-proposals/slack-channel-routing-migration-proposal.md` | `HISTORICAL / STALE` | Proposal from pre-migration state |
| Docs / proposals / audits | `docs/DEPLOYMENT_GUIDE.md`, `PRODUCTION_DEPLOYMENT_GUIDE.md` | `EXAMPLE ONLY` | Not active workflow evidence |
| Scripts / monitoring / infra templates | `scripts/monitor.sh`, `manage-monitoring.sh` | `OPERATIONAL / NON-GITHUB` | Independent shell env usage |
| Scripts / monitoring / infra templates | `docker/prometheus/alertmanager.yml`, `env.example` | `PLACEHOLDER / TEMPLATE` | Not secret proof |

## Risk inventory

- Highest Slack-routing risk remains any change to `.github/workflows/production.yml` before external confirmation of what `secrets.SLACK_WEBHOOK_URL` actually targets. [production.yml](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/.github/workflows/production.yml#L153)
- Renaming or remapping `SLACK_WEBHOOK_URL` in production would be behavior-changing because repo evidence does not prove equivalence with `SLACK_WEBHOOK_DEPLOYMENTS` or `SLACK_WEBHOOK_RELEASES`.
- Treating stale docs as runtime truth is a documentation risk. Current workflow YAML shows migrations already completed for deployment and release notify steps.
- Monitoring scripts and infra templates may also use a variable named `SLACK_WEBHOOK_URL`, but that name collision is not evidence that they share the same secret value or destination as GitHub Actions. That mapping is `UNKNOWN`.

## UNKNOWN

- Which exact Slack channel currently receives posts from `secrets.SLACK_WEBHOOK_URL` in `.github/workflows/production.yml`.
- Whether `secrets.SLACK_WEBHOOK_URL` is intentionally production-only or is a leftover shared webhook.
- Whether GitHub secret scope for `SLACK_WEBHOOK_DEPLOYMENTS` and `SLACK_WEBHOOK_RELEASES` is repository-wide, environment-specific, or mixed.
- Whether any shell/runtime `SLACK_WEBHOOK_URL` used by monitoring scripts points to the same Slack destination as GitHub Actions.
- Whether operators want production notifications to stay separate from deployment or release notifications after external confirmation.

## External questions for operator or GitHub admin

- Which exact Slack channel is bound to GitHub `secrets.SLACK_WEBHOOK_URL` used by `.github/workflows/production.yml`?
- Is that binding intentionally reserved for production deployment status, or is it a legacy shared webhook?
- What are the exact scopes and locations of `SLACK_WEBHOOK_URL`, `SLACK_WEBHOOK_DEPLOYMENTS`, and `SLACK_WEBHOOK_RELEASES` in GitHub: repository secrets, environment secrets, or both?
- Should production continue on its current destination, move to `SLACK_WEBHOOK_DEPLOYMENTS`, or move to a separate production-specific webhook secret?
- Do monitoring-script `SLACK_WEBHOOK_URL` values intentionally share the same Slack destination as GitHub workflow notifications, or are they separate operational channels?

## Safe conclusion

Repo evidence shows that deployment and release notify workflows have already migrated to channel-specific webhook secret names, while `production.yml` remains frozen on `SLACK_WEBHOOK_URL`.

Because the production webhook destination is still `UNKNOWN`, the safe SSOT-preserving action is to leave production notify wiring unchanged and treat this round as evidence-only.
