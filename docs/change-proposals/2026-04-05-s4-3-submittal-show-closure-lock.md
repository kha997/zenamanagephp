# S4.3 Canonical Submittal Show Closure Lock

Date: 2026-04-05
Decision: `DOCS-LOCKED`
Status: docs-only closure memo
Planning anchor: `S4.3`

## Why

The repo already has an explicit planning anchor for the canonical submittal family at `S4.3`.
This round only locks docs wording for the bounded canonical show slice proved on `GET /api/zena/submittals/{id}`.

## Exact anchor mapping

Mapped anchor:

- `docs/roadmap/backlog.yaml` `S4.3 Material submittal package (docs + approvals)`

Bounded mapping only:

- canonical owner family remains `/api/zena/submittals`
- this memo locks closure-ready narrative only for `GET /api/zena/submittals/{id}`
- this memo does not widen `S4.3` to claim fresh closure for list, create, update, submit, review, approve, or reject

## Locked statements

- bounded canonical `GET /api/zena/submittals/{id}` is closure-ready as a docs statement under existing `S4.3`
- file ownership remains on Document Center, not on `submittals.attachments` or `file_url`
- unresolved physical migration provenance for `submittals` remains debt, not a blocker for this bounded show slice

## Explicit non-claims

- no docs claim that approve/reject semantics changed
- no docs claim that `review` is required
- no docs claim that list/create/update are closure-ready because of this memo
- no docs claim that migration provenance debt is resolved
- no roadmap/story/epic invention

## Evidence anchors re-read for this memo

- `docs/roadmap/backlog.yaml`
- `docs/progress.md`
- `docs/architecture/module-ownership-ssot.md`
- `docs/agent-ssot-rules.md`
- `docs/change-proposals/2026-03-29-s4-3-material-submittal-package-owner-contract.md`

