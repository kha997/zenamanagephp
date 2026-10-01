# OWN-2026-016 — GAP-060/061 post-release reconciliation and GAP-056/059 operational closure: Gate-1 evidence

**Date:** 2026-10-01 (+07:00)

**Canonical base:** `032f121b747dfcae6c83851bebe6d819a5dc704d`

**Branch:** `docs/OWN-2026-016-gap060-061-post-release-reconciliation`

**Scope:** Read-only investigation and Gate-1 documentation. No register, code,
workflow or deployment change.

## Why a separate Work ID

Editing register rows under GAP-056/059/060/061 would change the
implementation-tree digests their Owner Gate-3 approvals are bound to (same
situation as OWN-2026-011/013/014/015).

## Candidate-ID audit

`docs/owner-decisions/` tops out at OWN-2026-015; `git grep OWN-2026-016` over
every remote branch: no match.

## Register state on the canonical base

| Item | Row today | Actual |
|---|---|---|
| GAP-060 | **none** (opened directly) | Released |
| GAP-061 | **none** (opened from GAP-060 Gate-2 v2) | Released |
| GAP-056 (Tier 2) | RESOLVED, with "**Open operational item** … not done" (host cleanup + DB password rotation if old scripts ran on a real server) | Owner confirmed the scripts never ran on a real server |
| GAP-059 (Tier 2) | RESOLVED, with "**Open operational item** … not done" (remove `.env` copies + rotate SMTP credential if the script ran on a real server) | same confirmation |

## Release facts (queried 2026-10-01)

| Item | PR | Squash SHA | Merged (UTC) | Actor | Approved subject | Approved digest |
|---|---|---|---|---|---|---|
| GAP-060 | #329 | `aac94caf890d7229baa3f514860786088e252ee8` | 2026-09-30T14:51:54Z | `kha997` | `59d44a7ad6afd1ecf738dfb5397cbbafbb7324fd` | `ba873b4a8fe27e830f477e0a5275ae6cecae45f18f2d80ec82adca713af8cca8` |
| GAP-061 | #330 | `032f121b747dfcae6c83851bebe6d819a5dc704d` | 2026-09-30T16:32:38Z | `kha997` | `a406fc4f22bc7dc6dc1ae594937f03bbadf57acb` | `17f3f73ae8617d78721d44dc108815d7c6fa89469b3e49238e13175a79589f91` |

Both squashed with `--match-head-commit`; tree at merge equalled the approved
head's tree; digest at merge equalled the Owner-bound digest. Post-merge push
CI: 8/8 `success` on both SHAs. `production.yml`: no run for either SHA.

Nightly workflow after GAP-060: `workflow_dispatch` on `main` at `aac94caf`
(run `36732396645`) **success**, GAP-040 proof `2 passed (15 assertions)`. The
first *scheduled* run after the fix had not yet occurred at query time
(`0 3 * * *` UTC; query at 2026-10-01T00:50Z).

## Owner operational confirmation (in-session, 2026-10-01)

"các script cũ (setup-production.sh, docker-manage.sh,
configure-production-smtp.sh) chưa từng chạy trên máy chủ thật." — so no
`/usr/local/bin/zenamanage-backup`, `backups/*/production.env`,
`.env.backup.*` or `.env.bak` artefacts exist on a real server, and no database
or SMTP credential rotation is needed on that account.

## What each row must carry

- **GAP-060** (Tier 1 with CI/test-integrity items): never-green nightly
  workflow (200/200 failures) reduced to the GAP-040 cold-start proof; removed
  jobs and why; Gate 2 v1→v2 correction; guard
  `tests/Architecture/WorkflowReferencesExistTest.php`.
- **GAP-061** (Tier 1): Owner product choice "retire now"; 15 never-working E2E
  tests deleted; GAP-040 proof kept.
- **GAP-056 / GAP-059**: replace "Open operational item … not done" with the
  Owner's confirmation and date.

## Out of scope

Any code change; new E2E journeys; deployment.
