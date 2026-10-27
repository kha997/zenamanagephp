# GAP-069 — Reconciliation history page number overflows into HTTP 500: Gate-1 evidence

**Date:** 2026-10-08 (+07:00)

**Canonical base:** `c8a38b8ff5312c2a40b72184858c8c8b7411508c` (origin/main, GAP-068 released)

**Branch:** `docs/GAP-069-reconciliation-page-overflow`

**Scope:** Read-only investigation and Gate-1 documentation. One local,
uncommitted reproduction test was run and deleted (`git status` clean
afterwards). No code change.

## Candidate-ID audit

`docs/owner-decisions/` on main tops out at GAP-068; GAP-065 is taken by Draft
PR #338. `git grep GAP-069` over every `origin/*` branch: no match.

## Source

Codex review on PR #341 (posted 2026-10-08T05:03Z, one minute after the
merge), P2, thread on
`app/Http/Controllers/Web/Treasury/TreasuryPageController.php:373`.

## Finding 1 — web page (reproduced, HTTP 500)

`TreasuryPageController::reconcileWallet()` reads `?page=` as
`max(1, (int) $request->query('page', '1'))` and probes the next page with
`history(..., $page * 50 + 1, 1)`. For a large `page` the multiplication
overflows to a float; `history()` takes `int $page` under `strict_types=1`, so
PHP throws a `TypeError`.

Reproduction (local SQLite, owner user, uncommitted test):

```
WEB page=9223372036854775807  -> 500  TreasuryReconciliationService::history(): Argument #4 ($page) must be of type int, float given
WEB page=99999999999999999999 -> 500  (same; (int) cast saturates to PHP_INT_MAX)
```

## Finding 2 — API (no 500, but unbounded)

`GET /api/zena/projects/{project}/treasury/reconciliations` validates `page`
as `integer|min:1` with no upper bound.

```
API page=9223372036854775807  -> 200
API page=92233720368547758    -> 200
API page=1000000              -> 200
API page=99999999999999999999 -> 422  (not a PHP integer)
```

The service computes `offset((page − 1) × per_page)`; for very large pages
this product also overflows before reaching the query builder. The response
content for those pages was not examined further; the input is accepted
without a sensible bound.

## Impact

- Only authenticated users with `treasury.view` on the project can send the
  request; no data is changed or exposed.
- Result: an error page (500) and an error-log entry instead of an empty page
  or a validation message.

## Proposed direction (for Gate 2)

Bound `page` (and therefore the offset) to a sane maximum on both the web page
and the API, and compute offsets only after bounding. No schema change.
