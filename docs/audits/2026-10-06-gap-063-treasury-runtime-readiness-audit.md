# GAP-063 — Project Treasury runtime: Gate-1 readiness audit (Issue #244)

**Date:** 2026-10-06 (+07:00)

**Canonical base:** `f652ceebfa5f641207138243a4a8e3eba7a2909c`

**Branch:** `docs/GAP-063-treasury-runtime-gate1`

**Scope:** Read-only audit and Gate-1 documentation for Issue #244 ("Audit repo
and prepare implementation plan for Project Treasury"). No code, schema,
permission, route, UI or deployment change.

## 1. Authority chain (binding, not reopened)

| Record | Status | What it fixes |
|---|---|---|
| `docs/superpowers/specs/2026-08-15-zena-one-page-management-canonical-semantics.md` (OWN-2026-009) | Merged | Treasury ≠ duplicate of Contract payment lifecycle; Cost ≠ Cash ≠ Revenue ≠ Profit |
| `docs/superpowers/specs/2026-08-16-gap037-project-treasury-architecture-decisions.md` + `GAP-037/02-design.md` | Approved | A3 (Treasury owns cash side; cost stays in `ContractExpense`/`MaterialReceiptLine`), A4-a (never touch `Component`/`Project` cost rollup), A.5 (many-to-many settlement), B2 + B2-T (`ContractPayment` stays canonical; Treasury only traces custody), C (immutable posting, no double posting), D (no edit to `ReportPageController::cashflow()`) |
| `GAP-037/02-design-v17.md` | Approved schema | 14 tables, posting/reversal/route/advance/reconciliation rules, global lock order 1→6 |
| `GAP-038/03-release.md` (PR #265) | Released | 14 migrations + 15 native CHECK constraints on SQLite and MySQL |
| PR #245 (`cd8b79d8`, Draft, kept open by Owner) | Non-normative evidence | Business scenarios A–F, permission list, UI/report wishes, delivery phases |

## 2. What exists on `main` today

- **Schema:** 14 `treasury_*` migrations (2026-08-17), composite tenant FKs,
  CHECK constraints (`app/Support/Treasury/TreasuryCheckConstraint.php`).
- **Models:** 14 classes under `app/Models/Treasury/` plus
  `Concerns/EnforcesRowInvariants.php` (row-level invariants on Eloquent save).
- **Tests:** 20 files under `tests/Unit/Migrations/Treasury` and
  `tests/Unit/Models` (schema, constraints, invariants), plus the dedicated
  MySQL CI job from GAP-038.
- **Nothing runtime:** no service, policy, permission code, route, controller,
  view, navigation entry, seeder or feature test references Treasury
  (`grep -ri treasury app/Http routes resources database/seeders` → none).
  No user can record or see any Treasury fact.

## 3. Repository conventions the runtime must follow

- **RBAC:** canonical permission codes in `database/seeders/ZenaPermissionsSeeder.php`
  and role map in `ZenaRbacSeeder.php` (roles: `super_admin`, Admin, PM,
  Designer, SiteEngineer, QC, Procurement, Finance, Client); policies check
  `code` (GAP-042/044). No `treasury.*` code exists.
- **Tenant/project isolation:** `tenant.isolation` middleware + policy checks;
  project membership via `project_users`.
- **Web UI:** operator layout (Blade + Tailwind, universal frame); API under
  `routes/api_zena.php`.
- **Tests:** factories required for `User/Tenant/Role/Permission/Project`
  (SSOT lint, GAP-062); MySQL parity jobs exist for concurrency-sensitive code.

## 4. Gap between the approved design and runtime

Every approved rule in v17 is currently enforced only where the database can
(CHECK/FK). The rules that need application code are all unimplemented:
posting-path selection and atomic status flips, the closed status-transition
graph, reversal/replacement, expense-approval gate (§10.1), route custody and
completion, advance/settlement conservation, reconciliation lifecycle, and the
global lock order (§11). Issue #244 also requires permissions, UI, dashboard,
timeline, reports.

## 5. Proposed slicing (each slice = its own Work ID and Gate 2/3)

| Slice | Content | User-visible result |
|---|---|---|
| **S1 Foundation** | 15 `treasury.*` permission codes + role defaults; `TreasuryPolicy` (tenant + project membership); parties and wallets (create/list/edit, no delete of used rows); project "Ngân quỹ" tab skeleton | Set up parties and wallets per project |
| **S2 Ledger engine** | Posting service (`direct` path), funding (posts immediately as `posted_unreconciled`), internal transfer, reversal + replacement, derived wallet/project balances, lock order classes 4–5 | Declare funding, transfer between wallets, see balances, correct mistakes (Scenarios A, C, F) |
| **S3 Expenses & approvals** | Draft → submit → approve/reject → post; self-approval audited and reportable; cost allocations to `ContractExpense`/`MaterialReceiptLine` (lock class 2) | Expense workflow (Scenario E) |
| **S4 Routes & reconciliation** | `via_route` payments, route legs, `ContractPayment` traceability (B2-T, lock classes 1/6), reconciliation | Investor pays through intermediary (Scenario B) |
| **S5 Advances & settlements** | Advance issue, expense settlement, cash return, ageing (lock class 3) | Engineer advance (Scenario D) |
| **S6 Dashboard & reports** | Dashboard cards, transaction register, timeline, reports/exports | Management view |

Order follows the dependency chain of the approved schema (S2 posting is
used by S3–S5; S4 needs S2's reversal coupling). PR #245's own phase order is
the same.

## 6. Business decisions needed before S1's Gate 2

1. **Role mapping** — who is "X" (owner/director, may self-approve), who is
   "accountant", who is "engineer Z" among the canonical roles.
2. **Wallet scope** — v17 allows company/shared wallets (`project_id` NULL):
   are they used from the start, or project wallets only in S1?
3. **First UI** — web screens in the operator app from S1, or API first.

## 7. Out of scope for this Gate 1

Any implementation; re-opening any approved Treasury decision;
`ReportPageController::cashflow()`; `Component`/`Project` cost rollup; AI/OCR/
bank feeds; statutory accounting; PRs #245/#257 (kept open).
