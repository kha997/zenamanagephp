# S6.4 Project Template Walkthrough Coverage

Date: 2026-04-04
Story: `S6.4`
Story title: `Documentation coverage: module→API→screens→workflows`
Status: runtime proved for the first narrow documentation slice only

## Scope

This document proves only the first narrow `S6.4` slice locked in SSOT:

- owner anchor: `POST /api/zena/projects/{id}/apply-template`
- cluster: `Work Templates -> Project Apply -> Work Instances`
- workflow chain: `draft -> preview -> publish -> apply -> generated work-instance runtime`

This is not a repo-wide documentation sweep.

## Canonical owner cluster

From `docs/architecture/module-ownership-ssot.md`:

- `Work Templates`
  - canonical route family: `/api/zena/work-templates`
  - canonical controller owner: `App\Http\Controllers\Api\WorkTemplateController`
  - canonical model owner: `App\Models\WorkTemplate`
- `Work Instances`
  - canonical route family: `/api/zena/work-instances`
  - canonical controller owner: `App\Http\Controllers\Api\WorkInstanceController`
  - canonical model owner: `App\Models\WorkInstance`
  - project-scoped canonical alias: `/api/zena/projects/{project}/work-instances`
- adjacent cluster owner:
  - `Project Apply` stays on canonical `/api/zena/projects/{id}/apply-template`
  - the action is implemented by `App\Http\Controllers\Api\WorkTemplateController@applyToProject`

## Canonical API walkthrough

The first proved documentation slice covers only this canonical API chain:

1. `draft`
   - `GET /api/zena/work-templates`
   - `POST /api/zena/work-templates`
   - `GET /api/zena/work-templates/{id}`
   - `PUT /api/zena/work-templates/{id}`
2. `preview`
   - `POST /api/zena/work-templates/{id}/preview`
3. `publish`
   - `POST /api/zena/work-templates/{id}/publish`
4. `apply`
   - `POST /api/zena/projects/{id}/apply-template`
5. `generated work-instance runtime`
   - `GET /api/zena/projects/{project}/work-instances`
   - `GET /api/zena/work-instances`
   - `PATCH /api/zena/work-instances/{id}/steps/{stepId}`
   - `POST /api/zena/work-instances/{id}/steps/{stepId}/approve`
   - `GET /api/zena/work-instances/{id}/steps/{stepId}/attachments`
   - `POST /api/zena/work-instances/{id}/steps/{stepId}/attachments`
   - `DELETE /api/zena/work-instances/{id}/steps/{stepId}/attachments/{attachmentId}`
   - `POST /api/zena/work-instances/{id}/export`
   - `POST /api/zena/work-instances/{id}/export-bundle`

## Runtime evidence

### Route truth

From `routes/api_zena.php` and `php artisan route:list`:

- canonical owner anchor exists at `POST /api/zena/projects/{id}/apply-template`
- canonical template lifecycle routes exist on `/api/zena/work-templates*`
- canonical generated work-instance readback exists at `GET /api/zena/projects/{project}/work-instances`
- canonical work-instance runtime exists on `/api/zena/work-instances*`

No `/api/v1/*` route is used as proof in this slice.

### Test truth

From existing feature tests:

- `tests/Feature/Api/WorkTemplateMvpApiTest.php`
  - proves canonical CRUD, preview, publish, project apply, and work-instance runtime on `/api/zena/*`
- `tests/Feature/Api/WorkTemplateBaselineSeederTest.php`
  - proves the narrow walkthrough chain end-to-end for seeded templates:
    - preview
    - dry-run apply without writes
    - apply
    - generated `work_instances`, `work_instance_steps`, `tasks`, and `task_assignments`
- `tests/Feature/Api/InspectionTemplateRuntimeTest.php`
  - proves project apply can generate inspection-linked work-instance artifacts on the canonical path
- `tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php`
  - proves canonical `/api/zena/*` ownership stays on app controllers for the cluster

## Workflow coverage

The first `S6.4` runtime slice documents only the already-proved narrow workflow chain:

- `draft`
  - canonical template create/update/show lives on `/api/zena/work-templates`
- `preview`
  - canonical dry planning check lives on `POST /api/zena/work-templates/{id}/preview`
- `publish`
  - canonical publish action lives on `POST /api/zena/work-templates/{id}/publish`
- `apply`
  - canonical owner anchor lives on `POST /api/zena/projects/{id}/apply-template`
- `generated work-instance runtime`
  - generated runtime is read back on `GET /api/zena/projects/{project}/work-instances`
  - generated runtime is operated through `/api/zena/work-instances*`

This slice does not document any broader workflow engine, reviewer matrix, dashboard flow, or notification semantics.

## Screen coverage

Screen/UI coverage for the first slice is intentionally narrow.

Evidence-backed statement:

- `docs/work-templates-ssot.md` explicitly says `UI for WT/WI management and execution` is out of scope for the Work Templates MVP.

Therefore:

- canonical screen owner for Work Template / Work Instance UI is `UNKNOWN`
- exact user-facing project-template walkthrough screen path is `UNKNOWN`
- no screen map is claimed beyond this `UNKNOWN` boundary

## Explicit non-claims

This document does not claim:

- repo-wide documentation coverage
- dashboard coverage
- notification coverage
- event-record coverage
- generic screen inventory
- `/api/v1/*` compatibility ownership
- template UI ownership beyond the explicit `UNKNOWN` boundary

## Verdict

The first `S6.4` runtime slice is proved at a narrow documentation boundary only:

- canonical owner anchor proved: `POST /api/zena/projects/{id}/apply-template`
- cluster proved: `Work Templates -> Project Apply -> Work Instances`
- narrow workflow coverage proved: `draft -> preview -> publish -> apply -> generated work-instance runtime`
- screen coverage remains evidence-limited and honestly documented as `UNKNOWN`

