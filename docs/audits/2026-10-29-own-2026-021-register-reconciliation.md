# OWN-2026-021 — Register and roadmap reconciliation: Gate-1 evidence

**Date:** 2026-10-29 (+07:00)

**Canonical base:** `60c3921d07ebeec9e3c1acc639fd76e6826e56d5` (GAP-015 released)

**Branch:** `docs/OWN-2026-021-register-reconciliation`

**Scope:** Read-only comparison of the planning records with the released state on `main` since OWN-2026-020
(`cc07e77d`). No code change.

## 1. `OPERATIONAL_GAP_REGISTER.md` rows that are stale

Each Work ID below has an approved Gate-3 packet (`docs/owner-decisions/<ID>/03-release.md`, `gate_status: approved`)
and a squash merge on `main`.

| Row | Register says | Released state on `main` |
|---|---|---|
| GAP-021 | UNVERIFIED | PR #378, squash `c1420a3e` |
| GAP-098 | OPEN (verified 2026-10-28) | PR #379, squash `8dd80a84` |
| GAP-018 | OPEN (verified) | PR #381, squash `8a676dcc` |
| GAP-020 | OPEN (verified) | PR #382, squash `01595a7f` |
| GAP-014c | OPEN (verified 2026-08-06) | PR #385, squash `23a90945` |
| GAP-014 (parent) | PARTIALLY RESOLVED, only 014c open | 014a, 014b, 014c all released |
| GAP-030 | OPEN (verified) — intentionally deferred | PR #386, squash `2e3ba4b8` |
| GAP-015 | OPEN (self-declared out of MVP scope) | Option A (execution screens) released: PR #387, squash `60c3921d`; template authoring screens not built |

## 2. Released Work IDs with no register row

- GAP-099 — PR #380, squash `02070628` (v1 project update/delete; v1 change-request routes retired).
- GAP-100 — PR #383, squash `170b7eea` (test DB-leak guard; two leaking classes isolated).
- GAP-101 — PR #384, squash `1b0e4f4d` (symfony/mime 7.4.22, CVE-2026-45070).

## 3. Roadmap

`docs/roadmap/canonical-roadmap.md` "Status as of 2026-10-28" lists GAP-021, 018, 020, the DB-leak work, GAP-014c,
GAP-015 and GAP-030 as open work; all are released. Still open after this reconciliation: GAP-019 (universal-frame
shell), GAP-026 (blocked, external Slack channel), and the optional follow-ups (GAP-015 template authoring screens,
Treasury extensions, Project OPPM / One-Page issue #248 / draft PR #257).

## 4. Deployment notes carried by these releases (not run)

- GAP-014c: one new migration (`deploy:migrate`).
- GAP-030: re-run `ZenaPermissionsSeeder`, `ZenaAdminRolePermissionSeeder`, then `ZenaProjectManagerRolePermissionSeeder`.
- GAP-098: `V1PermissionGapSeeder`.
- GAP-015: re-run `ZenaProjectManagerRolePermissionSeeder`.
