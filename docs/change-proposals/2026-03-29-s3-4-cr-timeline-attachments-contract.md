# S3.4 CR Timeline + Attachments Contract

Date: 2026-03-29
Status: proposal-only
Story: `S3.4`
Story title: `CR timeline + attachments`

## Why this exists

`S3.4` backlog wording is currently too compressed to safely drive a runtime round.

Current canonical runtime on `/api/zena/change-requests` proves:

- CRUD plus `submit`, `approve`, `reject`, and `apply` on `App\Http\Controllers\Api\ChangeRequestController`
- canonical workflow audit writes through `App\Services\ZenaAuditLogger` for those workflow mutations

Current canonical runtime does not prove:

- a timeline read endpoint for change requests
- a canonical CR attachment endpoint
- that `change_requests.attachments` is the forward attachment contract
- a CR-specific document-link filter contract on `/api/zena/documents`

This proposal narrows the contract so the next runtime round can implement the smallest evidence-backed canonical slice without inventing broader media or workflow-history semantics.

## Context snapshot

Current route truth from `php artisan route:list`:

- canonical CR routes exist on `/api/zena/change-requests`
- canonical document routes exist on `/api/zena/documents`
- there is no canonical CR timeline route today
- there is no canonical CR attachment route today
- `/api/v1/change-requests/*` and `/api/v1/documents/*` exist, but they are compatibility-only and not proof surfaces for this story

Current source truth:

- canonical CR runtime owner: `App\Http\Controllers\Api\ChangeRequestController`
- strongest timeline source: `audit_logs` rows written by `App\Services\ZenaAuditLogger`
- canonical document owner: `App\Http\Controllers\Api\SimpleDocumentController`
- strongest attachment ownership evidence: `documents.linked_entity_type` and `documents.linked_entity_id`
- `App\Models\ChangeRequest` has an `attachments` JSON cast, but current canonical controller/runtime evidence does not prove that field as the owner contract

## Timeline contract gaps

What current evidence supports:

- canonical workflow mutations already write audit entries for `submit`, `approve`, `reject`, and `apply`
- those audit rows are the cleanest evidence-backed source for workflow history

What current evidence does not support:

- embedding a fully defined timeline payload into `GET /api/zena/change-requests/{id}`
- non-workflow history events beyond the currently proved audit-backed workflow mutations
- exact ordering rules beyond "must be derived from audit-backed history"
- exact response schema details for actors, labels, display strings, or grouped events

Therefore the safe planning position is:

- canonical timeline source = audit-backed workflow history
- exact inclusion/order/schema details remain `UNKNOWN` until the runtime round proves them

## Recommended timeline contract

Recommended endpoint shape:

- `GET /api/zena/change-requests/{id}/timeline`

Why this is the best first contract:

- timeline is a derived history surface, not a core persisted CR row contract
- a dedicated endpoint avoids inflating `show()` with unproved history schema assumptions
- it keeps the implementation boundary narrow and testable without redefining the base CR detail payload
- it fits the current evidence pattern where workflow history comes from audit rows rather than first-class timeline columns on `change_requests`

Planning lock:

- do not embed timeline into canonical `show()` in the first runtime round
- do not invent broader event families beyond the audit-backed workflow history already evidenced

## Attachment contract gaps

What current evidence supports:

- Document Center is the strongest canonical ownership surface for file artifacts
- `App\Models\Document` explicitly supports CR linking through `linked_entity_type = 'cr'`
- model-level tests already prove `Document::ENTITY_TYPE_CR` and `forEntity()` scopes exist

What current evidence does not support:

- `change_requests.attachments` as the canonical forward attachment contract
- a current canonical API filter contract for CR-linked documents on `/api/zena/documents`
- delete semantics for CR-linked documents beyond general document delete behavior
- versioning semantics specific to CR attachments beyond existing document versioning capability

Therefore the safe planning position is:

- canonical CR attachments belong to Document Center
- canonical link rule = `documents.linked_entity_type = 'cr'` and `documents.linked_entity_id = {changeRequestId}`
- `change_requests.attachments` must not be used as proof surface for `S3.4`

## Recommended attachment contract

Recommended first endpoint shape:

- reuse `GET /api/zena/documents` with explicit CR-link filters

Recommended filter contract for the next runtime round:

- `linked_entity_type=cr`
- `linked_entity_id={changeRequestId}`

Why this is the best first contract:

- it keeps ownership in the existing canonical Document Center surface
- it avoids introducing a second file-list owner under `/api/zena/change-requests`
- it aligns with the current canonical data model instead of inventing CR-local attachment storage semantics
- it allows later nested convenience routes if needed, without making the first proof depend on duplicated attachment APIs

Planning lock:

- do not use `change_requests.attachments` JSON as canonical proof
- do not claim nested create/delete CR attachment routes in this proposal
- if later convenience nesting is added, it should still resolve to Document Center ownership rather than CR-owned file storage

## Explicitly deferred

- exact timeline inclusion rules beyond currently proved workflow audit mutations
- exact timeline ordering and payload schema
- whether timeline should later include non-workflow events
- attachment create/delete/version semantics beyond existing document ownership and linking rules
- any `/api/v1/*` involvement
- any broad document/media/storage cleanup
- any broad app-vs-src ownership cleanup

## Runtime-round verify target

Minimum verify expectations for the later runtime round:

- `php artisan route:list | grep -E "change-requests|attachment|attachments|timeline|documents|files|media" || true`
- targeted canonical tests proving a dedicated CR timeline endpoint exists
- targeted canonical tests proving CR-linked documents can be queried through the canonical Document Center surface
- database assertions or response assertions proving:
  - timeline data is sourced from audit-backed workflow history
  - CR-linked attachments resolve through `documents.linked_entity_type = 'cr'`
  - CR-linked attachments resolve through `documents.linked_entity_id = {changeRequestId}`
- no proof depending on `/api/v1/*`
- no proof depending on `change_requests.attachments`

## Verdict

This story is ready for a narrow proposal-backed runtime round only if timeline stays audit-backed and attachments stay owned by Document Center.
