# Post-S6.5 Proposed Next Story Unlock: Canonical Material Request Owner

Date: 2026-04-12
Status: proposed docs-only unlock memo

## Why this memo exists

Current SSOT still stops at proved `S6.5`.

That stop-line is real and remains binding:

- no exact post-`S6.5` story is currently locked in SSOT
- no safe runtime frontier is currently unlocked
- continuing runtime work without a new planning artifact would be roadmap invention

This memo does not claim that the next story is already accepted.

This memo exists only to propose one exact next story candidate that clears the unlock memo's required shape more honestly than the remaining blocked adjunct surfaces.

This memo does not patch runtime.

This memo does not patch tests.

## Why the old stop-line is exhausted

The current blocker artifacts already exclude the remaining mounted adjunct/dashboard residue as next-story anchors without a fresh planning artifact:

- `pm`, `designer`, and `site-engineer` adjunct routes remain either residue, hard-blocked, or insufficiently narrowed
- no canonical `/api/zena/notification-rules*` or `/api/zena/event-records*` owner family exists
- broad canonical families such as `projects`, `documents`, `submittals`, `inspections`, `materials`, and `contracts` are either already story-backed or too broad to reopen without a fresh narrowing lock

Those facts still leave one business-relevant procurement gap with stronger domain continuity than the blocked adjunct surfaces:

- a real `MaterialRequest` persistence model exists
- a real production migration exists for `zena_material_requests`
- a role projection exists at `GET /api/zena/site-engineer/material-requests`
- the projection is not safe as owner proof because its payload drifts from schema/model truth
- `S4.1` already established that any later material-request story must be planned separately after material/vendor ownership, which is now done

## Proposed next story

Proposed story key placeholder:

- `S6.6-proposed`

Proposed story title:

- `Canonical material request owner convergence`

This is a proposal only.

It is not current backlog truth.

It is not yet accepted roadmap state.

## Why this is the best next move

This candidate is the strongest next bridge toward the repo's stated goal because it adds a missing operational procurement owner between already-proved master data and later fulfillment/cost flows without reopening already-closed procurement stories.

Why it ranks above the currently blocked alternatives:

- higher business value than dashboard residue because it targets a transactional business object rather than a role projection
- clearer domain continuity than alerts/rules/event-record reads because materials, vendors, receipts, and contract cost summaries are already proved around the procurement lane
- stronger schema footing than designer/site-engineer adjunct routes because `zena_material_requests` has a real production migration and model
- narrower first slice than approvals/receipts/purchase orders because it can start read-only and schema-backed
- safer than reusing the site-engineer projection because the proposal creates a dedicated owner family instead of promoting projection drift into canonical truth

## Proposed owner anchor

Proposed new canonical owner family:

- `/api/zena/material-requests`

Proposed first owner anchor:

- `GET /api/zena/material-requests`

Reason:

- it is the narrowest canonical owner anchor that avoids using `GET /api/zena/site-engineer/material-requests` as forward ownership
- it keeps the first proof surface read-only
- it can be constrained to fields already backed by migration/model truth

This owner family is proposed by this memo only.

It is not currently mounted runtime truth.

## Proposed first proof surface

First proof surface:

- read-only canonical list on `GET /api/zena/material-requests`

Exact first-slice boundary:

- list only
- tenant-safe and anti-enumeration-safe through canonical zena middleware/RBAC expectations
- optional `project_id` filter
- optional `status` filter
- sorted by `created_at desc`
- response fields limited to migration/model-backed facts only:
  - `id`
  - `project_id`
  - `request_number`
  - `description`
  - `status`
  - `estimated_cost`
  - `required_date`
  - `requested_by`
  - `approved_by`
  - `approved_at`
  - `created_at`
  - `updated_at`

Optional relation expansion for the same first slice, if needed and only if directly backed by existing model relations:

- `project.{id,name}`
- `requested_by_user.{id,name}`

The first slice must not depend on any projection-only fields currently emitted by `SiteEngineerDashboardController`, including:

- `title`
- `material_type`
- `quantity`
- `unit`
- `priority`
- `requested_date`

## Supporting repo evidence

Material-request persistence residue that supports a later canonical owner story:

- `app/Models/MaterialRequest.php` defines the business object and relations to `project`, `requestedBy`, and `approvedBy`
- `database/migrations/2025_09_14_110000_create_zena_system_tables.php` creates `zena_material_requests` with request/workflow-oriented columns only
- `database/seeders/ZenaRbacSeeder.php` seeds procurement-facing `material.request|approve|receive|read`

Why the site-engineer projection is not the owner anchor:

- mounted route truth proves only `GET /api/zena/site-engineer/material-requests`
- the controller serializes fields not backed by current model/migration truth
- the projection therefore remains evidence for business relevance, not for canonical owner contract

Why this proposal does not conflict with `S4.1`:

- `S4.1` explicitly excluded `MaterialRequest` from material/vendor owner proof
- `S4.1` also explicitly stated that a later canonical material-request workflow story should be planned separately after material/vendor master-data ownership is established
- materials and vendors are now already implemented, so that precondition has been satisfied

## Out of scope

The proposed first story does not include:

- `POST /api/zena/material-requests`
- `GET /api/zena/material-requests/{id}`
- update/delete
- submit/approve/reject/fulfill actions
- purchase orders
- receipts
- vendor workflows
- approvals
- notifications
- dashboard widgets
- contract/payment/cost-summary semantics
- any dependency on `/api/v1/*`
- any proof based on `GET /api/zena/site-engineer/material-requests`

## Exact unlock conditions for the follow-up implementation round

The next implementation-bound round should begin only if all of the following are accepted as the active planning lock:

- exact story identity:
  - `S6.6-proposed` or maintainer-renamed equivalent, titled `Canonical material request owner convergence`
- exact owner anchor:
  - `GET /api/zena/material-requests`
- exact first proof surface:
  - read-only canonical list only, with schema-backed fields only
- exact owner boundary:
  - dedicated canonical owner family under procurement/materials domain, not site-engineer projection ownership
- exact exclusions:
  - no write actions, no approvals, no receipts, no purchase-order workflow, no vendor workflow expansion in the same round

## Proposed follow-up verification lane

- `php artisan route:list --except-vendor -v --path=api/zena/material-requests`
- migration truth:
  - `database/migrations/2025_09_14_110000_create_zena_system_tables.php`
- model truth:
  - `app/Models/MaterialRequest.php`
- controller truth for the new canonical owner only
- targeted feature test for canonical list contract on `/api/zena/material-requests`
- architecture/route invariant test proving the owner controller mapping
- no positive proof derived from `GET /api/zena/site-engineer/material-requests`

## Explicit non-claims

- this memo does not claim `S6.6` already exists
- this memo does not claim `/api/zena/material-requests` is mounted today
- this memo does not claim the proposal is already accepted by SSOT
- this memo does not claim current `MaterialRequest` schema is sufficient for write semantics beyond the proposed read-only list
- this memo does not claim approvals, receipts, or purchase-order flows are unlocked

## Verdict

If maintainers want one smallest honest candidate to replace the current post-`S6.5` stop-line, the strongest proposal is:

- proposed story: `Canonical material request owner convergence`
- proposed owner anchor: `GET /api/zena/material-requests`
- proposed first proof surface: schema-backed read-only canonical list only

That is the narrowest proposal in the procurement/materials lane that advances the supreme repo goal without using projection residue as owner proof and without widening into workflow/platform creep.
