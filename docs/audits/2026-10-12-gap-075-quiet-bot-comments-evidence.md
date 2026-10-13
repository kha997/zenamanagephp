# GAP-075 — Evidence: PR bot comments repeat on every push

Base: `805a93e5e9d3e8e66d4373f581a1ce7af00bfcee` (main after GAP-074).

## Comment steps found

`.github/workflows/code-quality-security.yml` (runs on every PR push to `main`
that touches non-doc files), each step calls `github.rest.issues.createComment`
unconditionally — a new comment every run, even when nothing changed:

| Job | Step | Posts even when clean? |
|---|---|---|
| code-quality | Comment Code Quality on PR | yes (currently "1702 files need fixing", same as main) |
| security-scan | Comment Security Issues on PR | yes ("No security vulnerabilities found!") |
| dependency-scan | Comment Dependency Issues on PR | yes (outdated list, same as main) |
| docker-security | Comment Docker Security Issues on PR | yes ("Vulnerabilities Found: 0 total") |
| license-scan | Comment License Issues on PR | yes ("All packages comply…") |

`.github/workflows/button-tests.yml` "Comment PR" posts a fixed success text
with a hard-coded "Coverage: 95.1%" on every PR run (not a measured value).

## Observed effect

PR #347 (GAP-074) received these 5 reports on each of about 8 pushes (40+
comments). None of them reported a regression; all repeated the main-branch
baseline. Each comment also wakes every subscribed agent session.

## What is not affected

The checks themselves (pass/fail, artifacts, Trivy SARIF upload) do not depend
on the comment steps (`continue-on-error: true`).
