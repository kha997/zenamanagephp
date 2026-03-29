# S3.1 Affected Scope Contract

Date: 2026-03-29
Status: proposal-only
Story: `S3.1`
Story title: `CR links affected scope`

## Why this exists

`S3.1` backlog wording is still too compressed after `S2.2`, `S3.3`, and `S3.4`.

Current canonical evidence already locks two different ownership paths:

- task and component change-request link-back can safely use `cr_links`
- document ownership already belongs to Document Center through `documents.linked_entity_type` and `documents.linked_entity_id`

Without a planning lock, `S3.1` risks inventing a second document-owner contract through `cr_links(document)`, which would conflict with the accepted `S2.2` and `S3.4` slices.

## Context snapshot

Current canonical runtime and model truth:

- `routes/api_zena.php` exposes canonical `/api/zena/change-requests/{id}` and workflow actions, but no canonical CR `links` mutation route yet
- `App\Models\ChangeRequest` exposes `links()`, `linkedTasks()`, `linkedDocuments()`, and `linkedComponents()`
- `App\Models\CrLink` still allows `task`, `component`, and `document`
- `App\Models\Document` already owns link state through `linked_entity_type` and `linked_entity_id`
- `App\Http\Controllers\Api\SimpleDocumentController` already proves same-tenant and same-project link validation for document attach/detach on the canonical Document Center path
- `App\Http\Controllers\Api\ChangeRequestController::apply()` already writes canonical task link-back through `cr_links` with `linked_type = task`

Strongest already-proved ownership rules:

- affected task = safe on `cr_links`
- affected component = safe on `cr_links`
- affected document = safe on Document Center ownership, not on `cr_links`

## Contract gaps

What current evidence does support:

- a canonical CR-owned link-back table for task and component scope
- a canonical Document Center-owned contract for CR-linked documents
- same-tenant and same-project validation as the minimal cross-entity invariant

What current evidence does not support:

- using `cr_links(document)` as canonical proof
- broad document semantics beyond the already-accepted Document Center ownership contract
- a broad reverse-query/read API family for every affected-scope entity
- any proof surface on `/api/v1/*`

## Recommended canonical split

Lock the first canonical split as:

- task affected scope uses `cr_links` with `linked_type = task`
- component affected scope uses `cr_links` with `linked_type = component`
- document affected scope does not use `cr_links`
- document ownership remains `documents.linked_entity_type = 'cr'` and `documents.linked_entity_id = {changeRequestId}`

Implication:

- `CrLink::LINKED_TYPE_DOCUMENT` may continue to exist as legacy/model residue for now, but it must not be used as canonical proof for `S3.1`
- no planning or runtime evidence for `S3.1` should claim overlapping ownership between `cr_links(document)` and Document Center links

## Recommended API surface

Recommended first mutation surface:

- `POST /api/zena/change-requests/{id}/links`
- `DELETE /api/zena/change-requests/{id}/links`

Recommended request scope for those mutation routes:

- allow `type=task|component` only
- reject `type=document` on this surface

Recommended first read surface:

- `GET /api/zena/change-requests/{id}` should include a minimal `affected_scope_summary`

Recommended summary contract:

- `tasks`: derived from `cr_links` where `linked_type = task`
- `components`: derived from `cr_links` where `linked_type = component`
- `documents`: derived from canonical document query semantics, not from `cr_links`

Recommended minimal document materialization for this summary:

- count-first summary is the safest first slice
- if item previews are later needed, they must still be sourced from `documents.linked_entity_type = 'cr'` and `documents.linked_entity_id = {changeRequestId}`

Why include the summary on `show()`:

- affected scope is core CR detail, unlike the dedicated workflow-history timeline surface
- a minimal summary avoids inventing a broader reverse-query family in the first runtime slice
- it gives the canonical CR owner path one narrow read surface without creating a second document owner

## Validation invariants

Minimum invariants for the first runtime slice:

- same tenant between the change request and the linked task/component
- same project between the change request and the linked task/component
- no canonical proof may depend on `/api/v1/*`

For documents:

- ownership and validation remain on the Document Center path
- any CR document summary/read must be materialized from the canonical document query path and canonical document columns

## Explicitly deferred

- broader document semantics beyond accepted Document Center ownership
- any broad reverse-query shape beyond the minimal `show()` summary
- item-level response schema richness for affected tasks/components/documents
- any cleanup of legacy/web controllers
- any `app` vs `src` ownership cleanup beyond what is required by the canonical proof
- any runtime removal of `CrLink::LINKED_TYPE_DOCUMENT`

## Runtime-round verify target

Minimum verify target for a later runtime round:

- `php artisan route:list | grep -E "change-requests|documents|tasks|components|link" || true`
- targeted canonical tests proving:
  - `POST /api/zena/change-requests/{id}/links` accepts `task|component`
  - `DELETE /api/zena/change-requests/{id}/links` removes `task|component`
  - `type=document` is rejected on the CR link route
  - `GET /api/zena/change-requests/{id}` returns `affected_scope_summary`
  - document scope in that summary resolves from Document Center ownership, not `cr_links`
- database assertions proving same-tenant and same-project guards
- no proof depending on `/api/v1/*`

## Verdict

`S3.1` is ready for a narrow runtime round only if task/component stay on `cr_links`, documents stay on Document Center ownership, and the first read surface remains a minimal summary rather than a broad reverse-query contract.
