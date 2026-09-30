# OWN-2026-015 — GAP-058/059 post-release register reconciliation: Gate-1 evidence

**Date:** 2026-09-30 (+07:00)

**Canonical base:** `3e6d55f0cd5c1fc68a9725ef1741bd6708bd0c52`

**Branch:** `docs/OWN-2026-015-gap058-059-post-release-reconciliation`

**Scope:** Read-only investigation and Gate-1 documentation. No register, code,
script, config, CI or deployment change.

## Why a separate Work ID

Editing GAP-058/059 register rows under their own Work IDs would change the
implementation-tree digests their Owner Gate-3 approvals are bound to (same
situation as OWN-2026-011/013/014).

## Candidate-ID audit

`docs/owner-decisions/` tops out at OWN-2026-014; `git grep OWN-2026-015` over
every remote branch: no match.

## Register state on the canonical base

| Item | Row | Actual |
|---|---|---|
| GAP-058 (Tier 6) | `OPEN (verified 2026-09-29) — registered by OWN-2026-014; Gate 1 not started` | Released (retired) |
| GAP-059 (Tier 2) | same | Released |

## Release facts (queried 2026-09-30)

| Item | PR | Squash SHA | Merged (UTC) | Actor | Approved subject | Approved digest |
|---|---|---|---|---|---|---|
| GAP-058 | #326 | `943577f75e8e519dfb42109a094a2632a670fd35` | 2026-09-30T04:57:37Z | `kha997` | `6193aec2d5ed6fc81ab8329c11fb868db189fba5` | `67ab00c990a9de046dcf2255a5ee698107ab38bc4cf36d455c6f3db64edd6c93` |
| GAP-059 | #327 | `3e6d55f0cd5c1fc68a9725ef1741bd6708bd0c52` | 2026-09-30T10:37:47Z | `kha997` | `f96aacad33f3f7effaee3ce669c748891fa7b35c` | `24202c37833e5522d55e9db9f9604685538c0bc955015b795e9073f24e15c3ad` |

Both merged with `--squash --match-head-commit`; at merge time the
`origin/main` tree equalled the approved head's tree and the digest at the
merge commit equalled the Owner-bound digest. Post-merge push CI on
`943577f7`: 8/8 success. `production.yml` has no run for either SHA.

## What each row must carry (from the Gate-3 packets)

- **GAP-058:** Owner product decision at Gate 1 — menu count badges not wanted
  now; the dead badge API was retired (8 routes, `BadgeController`,
  `BadgeService`, unused `Sidebar` component and view); the rest of the legacy
  sidebar system was kept; one disclosed line in
  `scripts/ssot/allow_orphan_routes.txt`.
- **GAP-059:** `smtp:configure --password` refused, `--password-stdin` added,
  exact quoted `.env` writes; script without `sed`/`.env.bak`, 0600 backup,
  rewriting "test" step removed; **open operational item**: if
  `scripts/configure-production-smtp.sh` was ever run on a real server,
  `.env.backup.*`/`.env.bak` copies with all secrets may remain there — clean up
  and rotate the SMTP credential (outside the repository).

## Out of scope

Any code change; the scheduled a11y/perf workflow; host cleanup; deployment.
