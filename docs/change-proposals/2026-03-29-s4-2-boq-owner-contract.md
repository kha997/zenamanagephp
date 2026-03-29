# S4.2 BOQ Owner Contract

Date: 2026-03-29
Status: planning lock
Story: `S4.2`
Story title: `BOQ line items linked to Project/Component`

## Why this exists

`S4.2` backlog wording is still too broad for the locked snapshot.

Current repo evidence proves:

- canonical project ownership exists at `/api/zena/projects`
- canonical material master-data ownership exists at `/api/zena/materials`
- canonical vendor master-data ownership exists at `/api/zena/vendors`
- canonical submittal ownership exists at `/api/zena/submittals`
- canonical component linkage exists in the project domain, but there is no active canonical `/api/zena/components*` CRUD family beyond template-apply

Current repo evidence does not prove:

- any active canonical `/api/zena/boqs*` route family
- any `Boq` or `BoqLineItem` model/table/test contract
- any direct BOQ persistence residue
- that `MaterialRequest`, `Submittal`, `Project`, `Component`, `Material`, or `Vendor` already owns BOQ CRUD
- pricing rollups, approvals, notifications, delivery/receipt, compensation, or submittal-package semantics as part of a first BOQ slice

Without this planning lock, the next runtime round would have to guess whether BOQ should be hidden inside project/component/material workflow surfaces or introduced as its own canonical owner family.

## Current BOQ Evidence

What exists now:

- `routes/api_zena.php` exposes canonical `/api/zena/projects`, `/api/zena/materials`, `/api/zena/vendors`, and `/api/zena/submittals`
- `app/Models/Project.php` owns project aggregates and component/task relationships
- `app/Models/Component.php` belongs to a project and already expresses same-project hierarchy
- `app/Models/Material.php` and `app/Models/Vendor.php` are tenant-safe master-data records only
- `tests/Feature/MaterialApiTest.php` and `tests/Feature/VendorApiTest.php` prove only master-data CRUD
- `app/Models/MaterialRequest.php` plus `database/migrations/2025_09_14_110000_create_zena_system_tables.php` prove material-request workflow residue
- `database/migrations/2025_09_14_110000_create_zena_system_tables.php` also creates `zena_purchase_orders` with `vendor_name` as a string

What that evidence proves:

- the repo has project and component identifiers suitable to anchor future BOQ ownership
- material and vendor master data are now canonical, but only as standalone catalogs
- procurement-adjacent workflow residue exists outside the canonical BOQ surface

What it does not prove:

- a BOQ table, BOQ line-item table, or BOQ controller
- any existing owner route for BOQ CRUD
- any direct material-to-BOQ or vendor-to-BOQ foreign-key contract
- any task-linked BOQ line-item owner contract

Implication:

- BOQ currently has no direct owner evidence and must be introduced deliberately as a new canonical surface
- project/component should be treated as linkage targets, not as proof that they already own BOQ

## Current Route Truth

From `php artisan route:list | grep -E "boq|project|component|material|vendor" || true`:

- canonical route family present: `/api/zena/projects*`
- canonical route family present: `/api/zena/materials*`
- canonical route family present: `/api/zena/vendors*`
- no canonical `/api/zena/boqs*`
- no canonical `/api/zena/components*` CRUD family; only `/api/zena/components/{id}/apply-template`

Planning consequence:

- BOQ should not be hidden under a component-owned API because component does not currently have an equivalent canonical CRUD owner family

## Owner Surface Options

### Option A

Reuse projects as the owner:

- `/api/zena/projects/{project}/boqs`

Pros:

- project is the strongest existing procurement-adjacent aggregate anchor
- same-project validation is straightforward

Cons:

- BOQ would become a nested project subdomain even though the story is about BOQ CRUD itself
- nested-only ownership makes cross-BOQ route naming and policy boundaries harder to isolate
- component linkage would still need a second-level nested rule set

### Option B

Reuse components as the owner:

- `/api/zena/components/{component}/boqs`

Pros:

- sounds close to the story wording

Cons:

- current route truth does not prove a canonical component CRUD owner family on `/api/zena/components*`
- would force component to become the top-level owner when BOQ still needs project scope
- breaks BOQs that span multiple components inside one project

### Option C

Reuse materials or vendors as the owner:

- `/api/zena/materials/{material}/boqs`
- `/api/zena/vendors/{vendor}/boqs`

Pros:

- both owner families already exist canonically

Cons:

- materials and vendors are master-data catalogs, not project-scoped quantity plans
- would incorrectly collapse project planning into catalog ownership
- would imply pricing/supplier semantics that are not evidenced

### Option D

Create a dedicated BOQ owner family:

- `/api/zena/boqs`
- `/api/zena/boqs/{boq}/line-items`

Pros:

- matches the story title directly
- keeps BOQ ownership separate from material/vendor catalogs and submittal workflow
- allows project to remain the aggregate anchor while component stays an optional line-item link
- supports a narrow first slice without inventing pricing or approval semantics

Cons:

- requires new runtime implementation in the next round
- current repo has no direct BOQ residue to reuse

## Recommended Owner Contract

Choose Option D for the first canonical slice:

- canonical BOQ owner surface: `/api/zena/boqs`
- canonical BOQ line-item owner surface: `/api/zena/boqs/{boq}/line-items`
- every BOQ must belong to exactly one project
- every line item may optionally reference one component in the same project

Reason:

- this is the only option that keeps BOQ as its own business owner while using project/component strictly as linkage targets instead of overloading existing procurement or workflow modules

## Project/Component Linkage Contract

Safe first-slice linkage rules:

- BOQ carries required `project_id`
- line item may carry nullable `component_id`
- if `component_id` is present, it must belong to the same `project_id` as the parent BOQ

Not part of this planning lock:

- task linkage
- material foreign key requirement
- vendor foreign key requirement
- auto-rollup from component/task/material changes

Reason:

- current evidence proves project and component identity surfaces, but it does not prove that tasks, materials, or vendors should be mandatory parts of the first BOQ owner contract

## MaterialRequest / Submittal Boundary

`MaterialRequest` is not an owner of `S4.2`.

What it can safely mean right now:

- procurement request/workflow residue
- dashboard projection source

What it must not mean in this planning lock:

- canonical BOQ ownership
- line-item ownership
- proof for component/material/vendor linkage

`Submittal` is also not an owner of `S4.2`.

What it can safely mean right now:

- document/review package ownership for `S4.3`

What it must not mean in this planning lock:

- BOQ CRUD ownership
- quantity/planning owner proof

## Minimal S4.2 Story Shape

The first runtime slice should be limited to:

- tenant-safe CRUD for BOQs on `/api/zena/boqs`
- nested tenant-safe CRUD for BOQ line items on `/api/zena/boqs/{boq}/line-items`
- required project linkage at the BOQ level
- optional component linkage at the line-item level with same-project validation
- dedicated BOQ RBAC on the canonical owner path

Safe first-slice payload scope:

- BOQs: identifying fields plus `project_id`
- line items: identifying/description fields plus quantity/unit and optional `component_id`

Do not include in the first slice:

- task linkage
- pricing rollups or cost summaries
- approvals
- notifications
- submittal package linkage
- delivery or receipt
- compensation

## Deferred / UNKNOWN

Deferred:

- task linkage on line items
- pricing rollups and cost summaries
- approvals
- submittal package linkage
- delivery/receipt
- compensation
- notifications

`UNKNOWN` until runtime evidence exists:

- exact BOQ header schema beyond minimal identifying fields
- exact BOQ line-item schema beyond quantity/unit/description and optional component linkage
- whether line items should later link directly to `materials`
- whether line items should later link directly to `vendors`
- whether a later read model should expose project-scoped BOQ summaries under `/api/zena/projects/{project}`

## Verify Target For The Next Runtime Round

- `php artisan route:list | grep -E "boq|project|component|material|vendor" || true`
- `rg -n "BOQ|BillOfQuant|quantity|line item|component|material|vendor" app tests database docs src`
- targeted canonical tests for `/api/zena/boqs*`
- targeted canonical tests for `/api/zena/boqs/{boq}/line-items*`
- no proof depending on `MaterialRequest`
- no proof depending on `/api/zena/submittals`
- no proof depending on `/api/v1/*`

## Verdict

`S4.2` is ready for a narrow planning-locked runtime round only if BOQ ownership is introduced as a dedicated canonical surface on `/api/zena/boqs`, with project as the required anchor and component as an optional same-project line-item link.
