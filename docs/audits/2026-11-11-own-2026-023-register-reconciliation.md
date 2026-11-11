# OWN-2026-023 — Register and roadmap reconciliation: Gate-1 evidence

**Date:** 2026-11-11 (+07:00)

**Canonical base:** `f3066c548211932b7dd29d65e93abbb0dd2dfddd` (GAP-123 released)

**Branch:** `docs/OWN-2026-023-register-reconciliation`

**Scope:** Read-only comparison of the planning records with `main` since OWN-2026-022 (`aefd55f`). No code change.

## 1. Released Work IDs with no register row

All 21 Work IDs below have an approved Gate-3 packet (`docs/owner-decisions/GAP-1xx/03-release.md`, `gate_status:
approved`, binding digest set) and a squash merge on `main`. `OPERATIONAL_GAP_REGISTER.md` has no row for any of them.

| Work ID | PR | Squash on `main` | Title |
|---|---|---|---|
| GAP-103 | #393 | `052a5aa3` | Shared project health read model |
| GAP-104 | #394 | `9e758cb8` | Read-only one-page Project OPPM (Phase 1) |
| GAP-105 | #395 | `71bc0c94` | Treasury BOQ link, fund chains, investor receivable |
| GAP-106 | #396 | `249c8b71` | Treasury evidence attachments |
| GAP-107 | #397 | `08788500` | Treasury period lock and CSV bank statement import |
| GAP-108 | #398 | `f0fad9cc` | Treasury configurable thresholds and per-advance due date |
| GAP-109 | #399 | `bdf1b5fb` | Staff register and monthly payroll cost |
| GAP-110 | #400 | `ce41d610` | OPPM Phase 2 overlay and Phase 3 snapshots / decisions |
| GAP-111 | #401 | `ef278c5c` | Symfony 7.4 security patches |
| GAP-112 | #402 | `da686ca5` | Time logging per task |
| GAP-113 | #403 | `b4a9174f` | Labour cost per project |
| GAP-114 | #404 | `a9ff611a` | Overhead allocation and project P&L |
| GAP-115 | #405 | `9a23ac6a` | Deploy readiness and production RBAC bootstrap |
| GAP-116 | #406 | `41daaca1` | Flaky ProjectApiTest (project factory dates) |
| GAP-117 | #407 | `153709a8` | Project milestones |
| GAP-118 | #408 | `7352fc46` | Phone layout for My Work and Timesheet |
| GAP-119 | #409 | `5ff8671a` | Remove broken project analytics API and dead event code |
| GAP-120 | #410 | `503f92ed` | Month-end reminders |
| GAP-121 | #411 | `41e87a44` | Real-MySQL month-close concurrency tests |
| GAP-122 | #412 | `9f98b208` | Phone layout for the time review page |
| GAP-123 | #413 | `f3066c54` | Authoring work templates in the web app |

## 2. Roadmap

`docs/roadmap/canonical-roadmap.md` is dated 2026-11-03.
- "Released to `main` since 2026-03-19 (not deployed)" stops at GAP-102.
- "Open work" still lists two items that are now released:
  - WorkTemplate authoring screens (GAP-123);
  - the Treasury extensions and Project OPPM (GAP-104 … 108, 110).
- The other two items are still open:
  - business notifications to Slack;
  - Zalo OA / ZNS.

## 3. `docs/progress.md`

- The header says "execution-progress SSOT", "Last updated: 2026-07-08".
- The last round recorded is Round 25 (Laravel 12 upgrade).
- Nothing after July is in it.
- The roadmap status section already names the register as the source of truth for open and released work, so
  `progress.md` now contradicts that by being months stale.

## 4. Packets that still read as open

| Packet | State | Fact on `main` |
|---|---|---|
| `GAP-031/03-release-v2.md` | `awaiting_owner` | GAP-031 is on the legacy allowlist (`docs/owner-governance/legacy-work-ids.txt`) as a worked example. The packet itself says its digest is a placeholder. The document approval workflow it describes is in `main`. |
| `OWN-2026-002/02-design.md` | Gate 2 approved, no Gate 3 | It is a programme design, not an implementation. The waves it ordered were delivered under their own Work IDs (for example GAP-010b, GAP-014b / 014c). |

## 5. Not part of this work

- No code, test, CI or GAP-* packet change.
- `docs/roadmap/backlog.yaml` (29/29 done) is unchanged.
