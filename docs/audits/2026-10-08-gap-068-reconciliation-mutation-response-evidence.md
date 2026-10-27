# GAP-068 — Reconciliation API returns `data: null` after a successful write: Gate-1 evidence

**Date:** 2026-10-08 (+07:00)

**Canonical base:** `a534de0982f33c85e936ecb4d2aca67d4eee1aa9` (origin/main, GAP-067 released)

**Branch:** `docs/GAP-068-reconciliation-mutation-response`

**Scope:** Read-only investigation and Gate-1 documentation. One local,
uncommitted reproduction test was run and deleted (`git status` clean
afterwards). No code change.

## Candidate-ID audit

`docs/owner-decisions/` on main tops out at GAP-067; GAP-065 is taken by Draft
PR #338. `git grep GAP-068` over every `origin/*` branch: no match.

## Source

Codex review on PR #340 (posted 2026-10-08T01:42Z, two minutes after the
merge), P2, thread on
`app/Http/Controllers/Api/Treasury/TreasuryReconciliationController.php:160`:
the mutation responses are looked up in a history list capped at 100 rows.

## Finding 1 — the defect (reproduced)

`TreasuryReconciliationController::historyItem()` builds the response of
`store`, `undo` and `undoEntry` by scanning
`TreasuryReconciliationService::history($project)`, which returns at most the
100 newest reconciliations of the project (`orderByDesc('reconciled_at')`,
`orderByDesc('created_at')`, `limit(100)`). When the affected reconciliation
is not among those 100, the method returns `null` and the endpoint answers
`201`/`200` with `data: null`, although the write succeeded.

Reproduction (local SQLite, uncommitted test, deleted afterwards): one wallet,
101 funding entries; 100 reconciliations dated today via
`POST …/wallets/{wallet}/reconciliations`, then one more dated 5 days ago.

```
STATUS=201 DATA=null RECS=101
```

The 101st reconciliation exists (`RECS=101`) but the response body carries no
data. The same lookup serves both undo endpoints, so undoing a reconciliation
outside the 100 newest also answers `200` with `data: null`.

## Finding 2 — impact

- Data is correct: reconciliation rows, document status and audit log are
  written normally; only the response body is empty.
- An API client that reads `data.id` / `data.lines` after the call gets
  nothing and cannot tell what was created without a second request.
- Trigger: a project with more than 100 reconciliations newer (by
  `reconciled_at`) than the affected one — typically a backdated
  reconciliation or an undo of an old one.
- The operator web pages do not use this lookup; they redirect.

## Finding 3 — related observation (same cap, web)

The reconciliation page shows the history from the same capped list, so a
reconciliation older than the 100 newest of the project is not listed and
cannot be undone from the page (the API undo endpoints still work, with the
empty response above). Listed for the Gate-2 scope decision; not reproduced
separately.

## Proposed direction (for Gate 2)

Build the mutation response from the affected reconciliation itself (look it
up by id, uncapped) instead of scanning the capped list; optionally page the
web history. No schema change.
