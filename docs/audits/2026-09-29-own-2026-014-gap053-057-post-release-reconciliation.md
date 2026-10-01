# OWN-2026-014 — GAP-053/055/056/057 post-release register reconciliation: Gate-1 evidence

**Date:** 2026-09-29 (+07:00)

**Canonical base:** `bd1ced98e3e419febed2d3d788ac0a5afaa1998e`

**Branch:** `docs/OWN-2026-014-gap053-057-post-release-reconciliation`

**Scope:** Read-only investigation and Gate-1 documentation. No register, code,
script, config, CI or deployment change.

## Why a separate Work ID

Four work items were released on 2026-09-28/29. Editing their register rows
under their own Work IDs would change the implementation-tree digests their
Owner Gate-3 approvals are bound to (same situation resolved by OWN-2026-011
for GAP-052 and OWN-2026-013 for GAP-054).

## Candidate-ID audit

`docs/owner-decisions/` holds OWN-2026-001…009, -011, -012, -013; `git grep
OWN-2026-014` over every remote branch: no match. Register tops out at GAP-056
on `origin/main`; `GAP-058`/`GAP-059`: no match on any remote branch.

## Register state on the canonical base

| Item | Register row today | Actual state |
|---|---|---|
| GAP-053 | **none** (never registered) | Released |
| GAP-055 | `OPEN (verified 2026-09-28) — registered by OWN-2026-013; Gate 1 not started` | Released |
| GAP-056 | same as GAP-055 | Released |
| GAP-057 | **none** (opened directly from GAP-055 evidence) | Released |

## Release facts (queried 2026-09-29)

| Item | PR | Merge SHA | Merged (UTC) | Approved subject | Approved digest |
|---|---|---|---|---|---|
| GAP-053 | #317 | `368536793117816417373a3bde2ac636d46b7d42` | 2026-09-28T23:48:49Z | `b6a18f73599622caaee8018908a9b52aea64d550` (Gate 3 **v2**) | `5c323ec663d0dc4199718f6e0a2b970de598078d6ffca4d6e459a8eebf27233d` |
| GAP-055 | #322 | `c32a7ddb31995ac7dd00886ceca94a0c5f63d3ca` | 2026-09-29T00:55:22Z | `af087dcf09626784f6fb59457fb17b0fe656bd85` | `17bf6858e8b85eb7a8c2657c96800d88121dd6b41d9d19b57b5acd4fcb8d76b2` |
| GAP-056 | #323 | `18cc0f796abd6735bd2abe1ebf318b10aec58d7d` | 2026-09-29T05:47:43Z | `1f573a55cc49c70e2bf47002d2124f4b6fd9bf78` | `b1441886ad1d74506a4b37a62ddd35f879f8b430289a65dab1d105d1d72844e5` |
| GAP-057 | #324 | `bd1ced98e3e419febed2d3d788ac0a5afaa1998e` | 2026-09-29T14:00:49Z | `40bd0bd66bd857e002a02d9850be2a32db09f042` | `dc9876bec255f4d83cfa58add4b18058e82e223e83ac64b2ff3aaed01c7be936` |

All four merged by `kha997` with `--squash --match-head-commit`; at merge time
each `origin/main` tree was verified identical to the approved head's tree and
the canonical digest recomputed at the merge commit equalled the Owner-bound
digest. GAP-053's v1 Gate-3 packet (subject `ff825fb9`, digest `8b25a50d…`)
carries `superseded_by: 03-release-v2.md`; the base refresh that forced v2 is
recorded in v2.

### Post-merge CI (push event on each merge SHA)

Auth Guard Lint, Automated Testing, Button Test Suite, CI/CD Pipeline, Code
Quality & Security, Owner Governance Lint, Routes Guardrails, Staging Smoke —
8/8 `success` for all four. On `18cc0f79` (GAP-056) Staging Smoke attempt 1
failed (`artisan serve` stopped after `/api/zena/auth/login`; later requests
`status=000`); attempt 2 on the same SHA succeeded. The tree is identical to PR
#323's head, where the same job had passed.

### Deployment

`production.yml` has no run for any of the four merge SHAs (latest run
2026-09-02 at `0872ac856`). None was deployed.

## Corrections carried by the releases (to record in the rows)

- GAP-055 Gate 2 corrected Gate 1: the maintenance page's "Clear" button is a
  client-side mock and never called the endpoint; also GAP-054's evidence
  over-stated the flush's reach — sessions and scheduler locks live in Redis
  DB 0 under default config, only DB 1 (cache) is flushed.
- GAP-056 Gate 3: 32 sites, not 31 (`scripts/setup-replication.sh:29`,
  lowercase variable); two literal `"password"` defaults also removed.
- GAP-057 Gate 3: readiness in the 409 body lives at
  `error.details.data.readiness` because the `error.envelope` middleware keeps
  only `data` from error bodies.

## New gaps found during these releases (candidates)

- **GAP-058 — sidebar badge API always returns 500.**
  `app/Http/Controllers/Api/BadgeController.php` type-hints `Request` without
  `use Illuminate\Http\Request` (GAP-055 probe: `Class
  "App\Http\Controllers\Api\Request" does not exist`, HTTP 500), and
  `app/Services/BadgeService.php` type-hints `?User` without
  `use App\Models\User` (resolves to `App\Services\User`). Every
  `/api/badges/*` route is affected, including the sidebar fetch
  `resources/views/components/sidebar.blade.php:209` (`GET /api/badges/{id}`).
  Since GAP-055 the badge cache clear is targeted, so fixing these imports no
  longer exposes a global cache flush.
- **GAP-059 — SMTP password on the command line.**
  `scripts/configure-production-smtp.sh:176` passes
  `--password="$SMTP_PASSWORD"` to `php artisan smtp:configure`
  (`app/Console/Commands/ConfigureSMTP.php:21`). Same exposure class as
  GAP-056, different credential; left out of GAP-056 by design.

## Open operational item (not a register change)

GAP-056 Gate 1's question — were `scripts/setup-production.sh` /
`docker-manage.sh` ever run on a real server? — is still unanswered. If yes,
`/usr/local/bin/zenamanage-backup` (plaintext DB password) and
`backups/*/production.env` exist on that host and need cleanup plus credential
rotation outside the repository. Proposed to be recorded in the GAP-056 row.

## Observed, not proposed for registration

The scheduled workflow `Accessibility & Performance Testing`
(`.github/workflows/a11y-perf-testing.yml`) has failed on every daily run since
at least 2026-09-22 (commit `adacc5cc`, before any of these four items): all
five jobs red (exit 127 in two jobs, `CriticalUserFlowsE2ETest` failing, Lighthouse
and WCAG jobs failing). Root causes are not investigated here; recommended as a
separate Gate 1.

## Out of scope for this Gate 1

Any register edit (Gate 2 design); fixing GAP-058/059; investigating the
scheduled workflow; host cleanup; deployment.
