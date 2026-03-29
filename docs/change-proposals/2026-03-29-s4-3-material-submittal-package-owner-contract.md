# S4.3 Material Submittal Package Owner Contract

Date: 2026-03-29
Status: planning lock for next narrow execution slice
Story: `S4.3`
Story title: `Material submittal package (docs + approvals)`

## Why this exists

`S4.3` backlog wording is currently too broad for the locked snapshot.

Current repo evidence proves:

- a canonical submittal owner family exists at `/api/zena/submittals`
- canonical submittal workflow-like routes exist for `submit`, `review`, `approve`, and `reject`
- canonical Document Center ownership exists separately at `/api/zena/documents`

Current repo evidence does not prove:

- that `submittals.attachments` or `file_url` is the canonical file-owner surface
- that Document Center currently supports canonical submittal linking
- that `review` is a required intermediate step in the first canonical state machine
- that canonical submittal notifications exist on submit/review/approve/reject

Without this planning lock, the next runtime round would have to guess whether submittal packages belong primarily to `Submittal`, `Document Center`, or both.

## Current S4.3 Evidence

### Submittal owner path

What exists now:

- `routes/api_zena.php` exposes canonical `/api/zena/submittals`
- the same route family includes:
  - `POST /api/zena/submittals/{id}/submit`
  - `POST /api/zena/submittals/{id}/review`
  - `POST /api/zena/submittals/{id}/approve`
  - `POST /api/zena/submittals/{id}/reject`
- `app/Http/Controllers/Api/SubmittalController.php` owns CRUD plus those workflow endpoints
- `tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php` locks `api.zena.submittals.index` to `App\Http\Controllers\Api\SubmittalController`
- `tests/Feature/Zena/ZenaRbacTenantSmokeTest.php` proves tenant-safe list/show behavior on the canonical owner path

What that evidence proves:

- `/api/zena/submittals` is a real canonical owner family, not just residue
- package-level workflow mutations are already routed on the submittal owner path
- ownership does not currently sit on `/api/zena/documents`

What it does not prove:

- exact reviewer/approver matrix semantics
- exact status-gate rules for every non-happy-path transition
- notification behavior

### Workflow shape

What exists now:

- `app/Http/Controllers/Api/SubmittalController.php::submit()` explicitly guards `draft -> submitted`
- `tests/Feature/Api/SubmittalApiTest.php` proves `submit`, `review`, `approve`, and `reject` endpoints
- `app/Models/Submittal.php` includes persisted status residue for `draft`, `submitted`, `pending_review`, `approved`, `rejected`, and `revised`

What that evidence proves safely:

- the narrow state chain `draft -> submitted -> approved|rejected` is grounded by runtime routes, controller methods, and tests
- `review` exists in runtime and model residue

What it does not prove safely:

- that `review` must be a required intermediate state before approval or rejection
- that `pending_review` is the canonical first-slice workflow contract
- that `revised` belongs in the first execution slice

Planning consequence:

- lock the first story shape to `draft -> submitted -> approved|rejected`
- treat `review` as optional/intermediate or `UNKNOWN` unless stronger proof is added in the runtime round

### Document linkage boundary

What exists now:

- `app/Http/Controllers/Api/SimpleDocumentController.php` owns canonical `/api/zena/documents`
- Document Center already supports canonical link/query semantics through:
  - `GET /api/zena/documents?linked_entity_type=...&linked_entity_id=...`
  - `POST /api/zena/documents/{id}/link`
  - `DELETE /api/zena/documents/{id}/link`
- `app/Models/Document.php` and `SimpleDocumentController::LINKABLE_MODELS` only prove link targets for `task`, `component`, and `cr`
- `tests/Feature/Api/DocumentManagementTest.php` proves canonical link/query behavior only for those entity types

What that evidence proves:

- Document Center is the canonical file-owner surface already used for entity linkage
- submittal package files should not create a second canonical file owner if that can be avoided

What it does not prove:

- a current canonical submittal link target in Document Center
- whether the later runtime round should use `linked_entity_type=submittal` or another minimal package-link contract

Planning consequence:

- keep file ownership with Document Center
- require the runtime round to add or prove a narrow submittal-compatible Document Center linkage path
- do not use `submittals.attachments` or `file_url` as canonical proof

### Notification boundary

What exists now:

- the repo has a canonical notification module and generic notification capability
- `SubmittalController` writes audit events through `ZenaAuditLogger`

What current evidence does not prove:

- any canonical `Notification::create(...)` call or event dispatch from `SubmittalController`
- any submittal-specific notification test on `/api/zena/submittals`
- any direct-recipient mapping for submit, approve, or reject

Planning consequence:

- keep notification claims out of the owner contract unless execution proves one direct-recipient path
- broader fan-out, watcher, or reviewer-matrix notification semantics remain deferred

## Owner Surface Options

### Option A

Make submittal own package and files directly:

- `/api/zena/submittals`
- `submittals.attachments`
- `submittals.file_url`

Pros:

- route family already exists
- avoids immediate Document Center changes

Cons:

- duplicates canonical file ownership already established in Document Center
- current repo does not prove attachment JSON or `file_url` as a forward file API contract
- would conflict with the owner split already used for CR attachments

### Option B

Move package ownership fully into Document Center:

- `/api/zena/documents`

Pros:

- Document Center already owns files and workflow

Cons:

- current repo already proves a real submittal owner family on `/api/zena/submittals`
- would collapse package/workflow ownership into the wrong module
- would ignore existing submittal CRUD and workflow routes

### Option C

Split package workflow from file ownership:

- package owner: `/api/zena/submittals`
- file owner: `/api/zena/documents`

Pros:

- matches current runtime truth on both owner families
- avoids duplicate file ownership
- aligns with the Document Center attachment pattern already used elsewhere
- keeps `S4.3` narrow enough to execute without inventing broad approval or notification semantics

Cons:

- the later runtime round still has to prove a minimal submittal-compatible Document Center link contract

## Recommended Owner Contract

Choose Option C for the first canonical slice:

- canonical package owner surface: `/api/zena/submittals`
- canonical file owner surface: `/api/zena/documents`
- first workflow contract: `draft -> submitted -> approved|rejected`

Reason:

- this is the only option that respects existing runtime truth while preserving Document Center as the canonical owner of files and links

## Review / Approval Contract

Safe first-slice workflow claims:

- `submit()` proves `draft -> submitted`
- `approve()` and `reject()` exist on the canonical submittal owner path
- the first planning contract should target `approved|rejected` as decisions after submission

Explicit non-claims:

- `review` is not yet a required stage in the planning lock
- `pending_review` is not yet locked as the canonical first-slice state
- no broad reviewer/approver matrix is claimed

Implication:

- if the execution round can prove `review` as a real intermediate stage without ambiguity, that can be added then
- if not, `review` should remain optional/intermediate or `UNKNOWN`

## Document Linkage Contract

Planning lock:

- submittal package files must be queried and linked through Document Center semantics
- the execution round must not treat `submittals.attachments` JSON or `file_url` as canonical file-owner proof

Safe minimum target for the runtime round:

- extend or prove Document Center linkage/query semantics for submittal packages narrowly
- keep canonical file CRUD/versioning on `/api/zena/documents`

Not locked by this proposal:

- exact `linked_entity_type` name
- nested convenience routes under `/api/zena/submittals`
- document versioning semantics specific to submittals beyond existing Document Center capability

## Minimal S4.3 Story Shape

The first runtime slice should be limited to:

- canonical package ownership on `/api/zena/submittals`
- narrow workflow proof for `draft -> submitted -> approved|rejected`
- Document Center-backed file linkage/query semantics for submittal packages
- tenant-safe RBAC/anti-enumeration on the canonical owner paths

Do not include in the first slice:

- `submittals.attachments` or `file_url` as canonical file ownership
- broad review matrix semantics
- watcher/fan-out notification semantics
- delivery/receipt
- compensation linkage
- any `/api/v1/*` proof

## Deferred / UNKNOWN

Deferred:

- whether `review` becomes required before decision
- exact submittal-compatible `linked_entity_type` contract in Document Center
- direct-recipient notifications on submit/approve/reject
- broad reviewer/approver/stakeholder semantics
- delivery/receipt follow-on work
- compensation linkage

UNKNOWN:

- whether `pending_review` deserves first-slice canonical status
- whether a separate submittal-specific document convenience route is worth adding later
- whether any notification behavior can be claimed at all without new runtime proof

## Runtime-Round Verify Target

Minimum verify expectations for the later runtime round:

- `php artisan route:list | grep -E "submittal|document|submit|review|approve|reject" || true`
- `rg -n "Submittal|submittal|document center|review|approve|reject|notification" app tests docs src`
- targeted canonical tests proving package workflow on `/api/zena/submittals`
- targeted canonical tests proving submittal package files resolve through Document Center semantics
- no proof depending on `submittals.attachments`
- no proof depending on `/api/v1/*`

## Verdict

`S4.3` is ready for a narrow execution round once planning is locked to split package ownership from file ownership: `/api/zena/submittals` owns the package contract, Document Center owns the files, and notifications stay deferred unless direct evidence appears.
