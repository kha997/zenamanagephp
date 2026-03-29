# S4.1 Material Catalog + Vendor Owner Contract

Date: 2026-03-29
Status: proved canonical slice
Story: `S4.1`
Story title: `Material catalog + vendor list`

## Why this exists

`S4.1` backlog wording is too broad for the locked snapshot.

Current repo evidence proves:

- a legacy `MaterialRequest` persistence model exists
- a canonical site-engineer dashboard projection exists at `/api/zena/site-engineer/material-requests`
- a canonical submittal owner family exists at `/api/zena/submittals`

Current repo evidence does not prove:

- an active canonical `/api/zena/materials*` owner family
- an active canonical `/api/zena/vendors*` owner family
- that `MaterialRequest` is the canonical owner for material catalog or vendor records
- BOQ, delivery/receipt, approval, notification, or compensation semantics for a first procurement slice

Without this planning lock, the next runtime round would have to guess whether material/vendor ownership belongs to `MaterialRequest`, `Submittal`, or a new canonical surface.

## Current Material/Vendor Evidence

### Materials

What exists now:

- `app/Models/MaterialRequest.php`
- `database/migrations/2025_09_14_110000_create_zena_system_tables.php` creates `zena_material_requests`
- `database/seeders/ZenaRbacSeeder.php` seeds legacy `material.request`, `material.approve`, `material.receive`, and `material.read`
- `database/seeders/ZenaPermissionsSeeder.php` seeds dashboard permission `site-engineer.material-requests`
- `routes/api_zena.php` exposes `GET /api/zena/site-engineer/material-requests`

What that evidence proves:

- the repo contains a real material-request-like persistence residue
- site engineers have a canonical dashboard projection that reads material-request data
- procurement-related permissions exist in mixed legacy forms

What it does not prove:

- a canonical material catalog owner path
- a canonical material CRUD contract
- that material master data should be owned by `MaterialRequest`

### Vendors

What exists now:

- `app/Services/PresetService.php` includes a procurement menu link to `/vendors`
- the same preset requires `vendor.read`
- `database/seeders/SimpleRoleSeeder.php` describes procurement as "Material and vendor management"
- purchase-order sample seed data stores `vendor_name` as a string in `zena_purchase_orders`

What that evidence proves:

- the UI/navigation layer expects a future vendor area
- vendor identity currently appears only as a string field in purchase-order residue

What it does not prove:

- a real `Vendor` model
- a vendor table or migration
- canonical vendor routes, controller, policy, or tests

### Material requests

Strongest current facts:

- `MaterialRequest` belongs to `project`, `requestedBy`, and `approvedBy`
- schema fields are request/workflow-oriented: `request_number`, `description`, `status`, `estimated_cost`, `required_date`, `requested_by`, `approved_by`, `approved_at`
- `SiteEngineerDashboardController::getMaterialRequests()` reads `MaterialRequest` for a role-specific projection

Important limitations:

- the current controller maps fields like `title`, `material_type`, `quantity`, `unit`, `priority`, and `requested_date` that are not present in `app/Models/MaterialRequest.php` fillable/schema evidence
- `getOverview()` counts `pending` material requests even though the schema enum is `draft|submitted|approved|rejected|fulfilled`
- there is no active canonical `MaterialRequestController`
- `routes/api_zena.php.backup` shows a disabled `material-requests` family, which is not runtime truth

Implication:

- `MaterialRequest` is evidence for procurement-domain residue and dashboard drift
- `MaterialRequest` is not safe proof of canonical material or vendor ownership for `S4.1`

## Current Route Truth

From `php artisan route:list | grep -E "material|vendor|submittal|request" || true`:

- canonical route present: `GET /api/zena/site-engineer/material-requests`
- canonical route family present: `/api/zena/submittals*`
- no canonical `/api/zena/materials*`
- no canonical `/api/zena/vendors*`

Planning consequence:

- do not treat dashboard projection or submittal ownership as proof of canonical material/vendor owner surfaces

## Owner Surface Options

### Option A

Reuse `MaterialRequest` as the owner:

- `/api/zena/material-requests`

Pros:

- backed by an existing model and schema

Cons:

- request/workflow shape is not a material catalog shape
- current runtime surface is only a dashboard projection
- existing field drift shows the projection is not a clean contract source
- would incorrectly merge catalog ownership with request workflow semantics

### Option B

Reuse submittals as the owner:

- `/api/zena/submittals`

Pros:

- already a canonical route family
- clearly procurement-adjacent

Cons:

- submittals are document/review packages, not material master data or vendor master data
- this would pull approvals/workflow semantics into `S4.1`
- backlog already separates submittals into `S4.3`

### Option C

Create dedicated owner families:

- `/api/zena/materials`
- `/api/zena/vendors`

Pros:

- matches the backlog title directly
- keeps material catalog and vendor list as master-data CRUD
- avoids overclaiming request, approval, or document workflow semantics
- supports a narrow first slice with tenant-safe CRUD only

Cons:

- requires new runtime implementation in the next round
- current repo has little direct persistence evidence for vendors

## Recommended Owner Contract

Choose Option C for the first canonical slice:

- canonical material owner surface: `/api/zena/materials`
- canonical vendor owner surface: `/api/zena/vendors`
- first slice is tenant-safe CRUD only

Reason:

- this is the only option that cleanly matches the story title without collapsing master data into workflow projections or document-review semantics

## MaterialRequest Boundary

`MaterialRequest` is not an owner of `S4.1`.

What it can safely mean right now:

- downstream procurement/request workflow residue
- dashboard/projection data source

What it must not mean in this planning lock:

- canonical material catalog ownership
- canonical vendor ownership
- approval workflow proof for procurement

Follow-up implication:

- if material-request workflow becomes a later story, it should be planned separately after material/vendor master-data ownership is established

## Minimal S4.1 Story Shape

The first runtime slice should be limited to:

- tenant-safe CRUD for materials
- tenant-safe CRUD for vendors
- RBAC on both owner families
- no dependency on `/api/v1/*`

Safe first-slice payload scope:

- materials: identifying and catalog fields only
- vendors: identifying and contact/profile fields only

Do not include in the first slice:

- BOQ linkage
- submittal package linkage
- delivery or receipt
- approvals
- notifications
- compensation or contract mapping

## Deferred / UNKNOWN

Deferred:

- `S4.2` BOQ ownership and linkage
- `S4.3` submittal packages and any review/approval semantics
- `S4.4` delivery/receipt and acceptance checklist
- `S4.5` compensation linkage and cost mapping

`UNKNOWN` until runtime evidence exists:

- exact material schema for the canonical catalog
- exact vendor schema beyond basic list/profile CRUD
- whether a later canonical material-request workflow should live under `/api/zena/material-requests`
- whether material catalog entries should later link directly to submittals or BOQ lines

## Verify Target For The Next Runtime Round

- `php artisan route:list | grep -E "material|vendor|submittal|request" || true`
- `rg -n "Material|Vendor|material_request|vendor" app tests database docs src`
- targeted canonical tests for `/api/zena/materials*`
- targeted canonical tests for `/api/zena/vendors*`
- no proof depending on `/api/zena/site-engineer/material-requests`
- no proof depending on `/api/v1/*`

## Runtime Outcome

The narrowed runtime round is now proved on the canonical owner paths only:

- `/api/zena/materials`
- `/api/zena/vendors`

What runtime evidence now proves:

- both owner families expose minimal canonical CRUD only
- both owner families are tenant-safe and return `404` across tenant boundaries
- both owner families are RBAC-safe through dedicated canonical permissions:
  - `material.view|create|update|delete`
  - `vendor.view|create|update|delete`
- route ownership stays on `App\Http\Controllers\Api\MaterialController` and `App\Http\Controllers\Api\VendorController`

What this runtime round still does not prove:

- BOQ linkage
- submittal package ownership or approvals
- delivery/receipt
- notifications
- compensation linkage
- any canonical `MaterialRequest` workflow

Runtime evidence:

- `php artisan route:list | grep -E "materials|vendors" || true`
- `php -d pcov.enabled=0 ./vendor/bin/phpunit --filter=Material`
- `php -d pcov.enabled=0 ./vendor/bin/phpunit --filter=Vendor`
- `php -d pcov.enabled=0 ./vendor/bin/phpunit tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php`
- `composer ssot:lint`
- `php artisan optimize:clear`

## Verdict

`S4.1` is now proved at the narrowed canonical master-data slice on `/api/zena/materials` and `/api/zena/vendors`, with `MaterialRequest` explicitly kept out of owner scope.
