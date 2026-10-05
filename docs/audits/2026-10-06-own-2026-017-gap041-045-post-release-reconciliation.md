# OWN-2026-017 — GAP-041/045 post-release reconciliation: Gate-1 evidence

**Date:** 2026-10-06 (+07:00)

**Canonical base:** `ec487a48c1196b4219f9e4504598f4b8104ff267`

**Branch:** `docs/OWN-2026-017-gap041-045-post-release-reconciliation`

**Scope:** Read-only investigation and Gate-1 documentation. No register, code,
workflow or deployment change.

## Why a separate Work ID

Editing the GAP-041/045 register rows under their own Work IDs would change the
implementation-tree digests their Owner Gate-3 approvals are bound to (same
situation as OWN-2026-011/013/014/015/016).

## Candidate-ID audit

`docs/owner-decisions/` tops out at OWN-2026-016; `git grep OWN-2026-017` over
every remote branch: no match.

## Register state on the canonical base

| Item | Row today | Actual |
|---|---|---|
| GAP-041 (Tier 1) | `**IMPLEMENTED ON BRANCH — AWAITING GATE 3 (NOT MERGED)**` | Released |
| GAP-045 (Tier 1) | `**UNVERIFIED (LIVE assertion observed 2026-08-21)**`, "Gate 1 chưa bắt đầu" | Gate 1 evidence done, Option A released |

## Release facts (queried 2026-10-06)

| Item | PR | Squash SHA | Merged (UTC) | Actor | Approved subject | Approved digest |
|---|---|---|---|---|---|---|
| GAP-045 | #332 | `49c84e3705d5de03c481204e1917e9b1307ce10f` | 2026-10-05T15:11:12Z | `kha997` | `25df79395a83c5d9fe6d106d2c6e5f67184466b1` | `04ceb1e05fdd069034e6eb0c3a0d4f95693cc2721ba38c12a5987b20b6898fe5` |
| GAP-041 | #316 | `ec487a48c1196b4219f9e4504598f4b8104ff267` | 2026-10-05T16:49:15Z | `kha997` | `a9e7fe7e8110aac8801030628a58ea1f6ca5b81a` | `1859db39134df36e004710dbca1235228d3cd37b881a1e59b363cead3d6bd278` |

Both squashed with `--match-head-commit`; tree at merge equalled the approved
head's tree; digest at merge equalled the Owner-bound digest. Post-merge push
CI: 8/8 `success` on both SHAs. `production.yml`: no run for either SHA
(latest run 2026-09-02).

On `ec487a48` push CI, the performance jobs executed for the first time on
`main`: Dashboard 19 passed / 161 assertions, Monitoring 10 passed / 45
assertions (before GAP-041: `INFO  No tests found.`, exit 0).

## Related housekeeping already done

PRs #276 (GAP-041 Gate 1) and #277 (GAP-041 Gate 2 v3) closed as superseded by
#316 after verifying their records are on `main` (only the documented
schema normalization of `02-design.md` differs); branches deleted.

## What each row must carry

- **GAP-041:** RESOLVED; Option D; `--group=performance
  --fail-on-empty-test-suite`; the phantom-tier part landed via GAP-060; the
  two blockers fixed separately (GAP-053, GAP-045); LIVE evidence (exact-head
  run `37331118265`, zero-selection proof v4 run `37335745972`).
- **GAP-045:** RESOLVED; Gate-1 finding (runner-CPU-dependent timing, 10x
  distribution 265–521ms, no regression); Option A (query-count/result gate,
  timings reported as warnings, thresholds unchanged); 10x proof run
  `36897403420`; Option C (alerts pagination) not taken.

## Out of scope

Any code change; GAP-045 Option C; deployment.
