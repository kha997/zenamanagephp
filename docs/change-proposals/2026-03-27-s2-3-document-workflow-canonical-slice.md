# S2.3 Document Workflow Canonical Slice Handoff

Date: 2026-03-28
Scope: narrative cleanup only for the accepted-with-conditions S2.3 slice
Canonical owner: `App\Http\Controllers\Api\SimpleDocumentController`

## Executive Summary

Current worktree evidence supports a narrow S2.3 slice on canonical Zena documents:

- `POST /api/zena/documents/{id}/submit`
- `POST /api/zena/documents/{id}/decision`

## Files Changed For S2.3 Slice

Current worktree changes tied to the S2.3 document slice:

- `app/Http/Controllers/Api/SimpleDocumentController.php`
- `routes/api_zena.php`
- `tests/Feature/Api/DocumentManagementTest.php`
- `tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php`

Not part of this handoff update:

- `docs/roadmap/backlog.yaml`

## Exact Diff Summary For S2.3 Only

From current `git diff`:

- `app/Http/Controllers/Api/SimpleDocumentController.php`
  - adds workflow status constants: `draft`, `submitted`, `approved`, `rejected`
  - adds `submit(string $id)` with `draft -> submitted`
  - adds `decision(Request $request, string $id)` with `submitted -> approved|rejected`
  - persists workflow facts into `status`, `metadata`, and `updated_by`
- `routes/api_zena.php`
  - adds `POST /api/zena/documents/{id}/submit`
  - adds `POST /api/zena/documents/{id}/decision`
  - uses `rbac:document.update` for `decision` so mutate routes do not rely on view-only access
- `tests/Feature/Api/DocumentManagementTest.php`
  - adds canonical submit/decision transition tests
  - adds invalid-transition, tenant-safety, and management-authorization tests
- `tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php`
  - locks canonical owner for `documents.submit` and `documents.decision` to `App\Http\Controllers\Api\SimpleDocumentController`

## Route Before / After

### Before S2.3

- Pre-existing canonical document CRUD routes existed on `/api/zena/documents`.
- Canonical `submit` and `decision` routes were not present in `HEAD` before the current worktree diff.

### After Current S2.3 Worktree

Runtime truth from `php artisan route:list --path=api/zena/documents`:

- `GET|HEAD  api/zena/documents`
- `POST      api/zena/documents`
- `GET|HEAD  api/zena/documents/{id}`
- `PUT       api/zena/documents/{id}`
- `DELETE    api/zena/documents/{id}`
- `POST      api/zena/documents/{id}/decision`
- `POST      api/zena/documents/{id}/submit`

## Verify Commands + Exact Results

### Command

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden
git status --short
```

### Relevant Result

```text
 M app/Http/Controllers/Api/SimpleDocumentController.php
 M docs/roadmap/backlog.yaml
 M routes/api_zena.php
 M tests/Feature/Api/DocumentManagementTest.php
 M tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php
?? docs/change-proposals/2026-03-27-s2-3-document-workflow-canonical-slice.md
```

### Command

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden
php artisan route:list --path=api/zena/documents
```

### Result

```text
GET|HEAD  api/zena/documents
POST      api/zena/documents
GET|HEAD  api/zena/documents/{id}
PUT       api/zena/documents/{id}
DELETE    api/zena/documents/{id}
POST      api/zena/documents/{id}/decision
POST      api/zena/documents/{id}/submit
```

### Command

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden
git diff -- app/Http/Controllers/Api/SimpleDocumentController.php routes/api_zena.php tests/Feature/Api/DocumentManagementTest.php tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php
```

### Exact Result Summary

- `SimpleDocumentController` adds workflow constants plus `submit()` and `decision()`.
- `routes/api_zena.php` adds `submit` and `decision`, with `decision` protected by `rbac:document.update`.
- `DocumentManagementTest` adds canonical workflow coverage for `submit` and `decision`.
- `ModuleOwnershipRouteInvariantTest` adds owner locks for `submit` and `decision`.

## Verdict

S2.3 narrative should describe only the canonical `submit` and `decision` workflow slice as the new change proven by the current diff.

## Planning Lock

As of `2026-03-29`, the recommended planning direction is Option A: narrow `S2.3` itself to the already-proved canonical document workflow slice instead of keeping backlog acceptance broad.

Locked acceptance boundary:

- `POST /api/zena/documents/{id}/submit` proves `draft -> submitted`
- `POST /api/zena/documents/{id}/decision` proves `submitted -> approved|rejected`
- Canonical `decision()` requires management-policy authorization and rejects invalid transitions
- Tenant-safe access is covered on the canonical `/api/zena/documents/*` owner path

Explicitly out of scope for this slice:

- any separate `review` route or review-stage matrix
- any broad reviewer or approver semantics beyond the proved management-policy gate on `decision()`
- any notification behavior on document workflow state changes

If later planning wants review-matrix or notification semantics, it should do so through a separate evidence-backed story or proposal rather than by stretching this proved slice.
