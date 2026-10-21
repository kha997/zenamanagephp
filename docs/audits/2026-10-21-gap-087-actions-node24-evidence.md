# GAP-087 — Evidence: GitHub Actions still on the Node 20 runtime

Base: `1928f2ae1c85388ce88661f53855a5aba03a6720` (main after GAP-086).

## Symptom

CI jobs log warnings like this one, from the Security Vulnerability Scan on PR #359:

> Node.js 20 is deprecated. The following actions target Node.js 20 but are being forced to run on Node.js 24: actions/upload-artifact@v4.

The runner already forces these actions onto Node 24. They work today, but each one
runs on a runtime its authors did not release it for.

## Actions on the Node 20 runtime

Each runtime below is the `runs.using` value in the action's `action.yml` at the tag
the workflows pin. They were read from the upstream repositories on 2026-10-21.

| Action (pinned) | Uses | Runtime | First Node 24 major | Latest |
|---|---|---|---|---|
| `actions/upload-artifact@v4` | 18 | node20 | v6 | v7.0.2 |
| `actions/download-artifact@v4` | 6 | node20 | v7 | v8.0.2 |
| `slackapi/slack-github-action@v2.1.1` | 9 | node20 | v3 | v4.0.0 |
| `docker/build-push-action@v5` | 8 | node20 | v7 | v7.4.0 |
| `docker/setup-buildx-action@v3` | 7 | node20 | v4 | v4.4.1 |
| `docker/metadata-action@v5` | 5 | node20 | v6 | v6.2.0 |
| `docker/login-action@v3` | 5 | node20 | v4 | v4.6.0 |
| `github/codeql-action/upload-sarif@v3` | 2 | node20 | v4 | v4.39.0 |

The other actions in use already run on Node 24 or are not Node-based:
- **Node 24:** `actions/checkout@v5`, `actions/cache@v5`, `actions/upload-artifact@v6`,
  `actions/github-script@v8`, `actions/setup-node@v5`, `shivammathur/setup-php@v2` and
  `browser-actions/setup-chrome@v2`.
- **Composite:** `appleboy/ssh-action@v1` and `codecov/codecov-action@v5`.
- **Docker:** `appleboy/scp-action@v0.1.7`.

## Where they are used

| Workflow | Triggers | Node 20 uses |
|---|---|---|
| `automated-testing.yml` | pull_request, push, schedule, dispatch | 10 (upload/download-artifact) |
| `code-quality-security.yml` | pull_request, push, schedule, dispatch | 8 (upload-artifact, buildx, build-push, upload-sarif) |
| `staging-smoke.yml` | pull_request, push, dispatch | 3 (upload-artifact) |
| `button-tests.yml` | pull_request, push, schedule, dispatch | 2 (download-artifact) |
| `nightly-matrix.yml` | schedule, dispatch | 1 (upload-artifact) |
| `automated-deployment.yml` | release, dispatch | 21 (docker, slack) |
| `release-management.yml` | push, release, dispatch | 11 (docker, upload-sarif, upload-artifact, slack) |
| `production.yml` | dispatch | 5 (upload/download-artifact, slack) |

PR CI runs the first four workflows, so it can check 23 of the 60 uses. The other 37
are in workflows that run only on a schedule, on release or deploy, or by hand. Of
those, `release-management.yml` also runs on push; Gate 2 will check which branches.

## Notes for Gate 2

- Each upgrade is a major version. Gate 2 must read the breaking changes of each one.
- Known areas to check: artifact download paths in download-artifact, the
  input and payload format of the Slack action, and inputs that the docker actions
  removed.
- Gate 2 must also decide how to verify the workflows that PR CI does not run.
