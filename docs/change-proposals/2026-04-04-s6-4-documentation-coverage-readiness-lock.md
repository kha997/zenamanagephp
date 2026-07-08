# S6.4 Documentation Coverage First Slice Readiness Lock

Date: 2026-04-04
Status: docs-only planning adjustment
Story: `S6.4`
Story title: `Documentation coverage: module→API→screens→workflows`

## Why this round exists

`S6.3` is now runtime-proved on the canonical task-owned overdue escalation path.

The exact next roadmap story in SSOT is therefore `S6.4`.

As currently written, `S6.4` is not runtime-ready. "Documentation coverage: module→API→screens→workflows" can drift into at least four different documentation scopes:

- a full cross-module API catalog
- a screen inventory for product UI
- workflow walkthroughs across multiple business modules
- a project-template walkthrough that touches WorkTemplate, Project, and WorkInstance ownership

Current SSOT only guarantees that `S6.4` must include a project template walkthrough. It does not yet lock one exact first owner anchor or one honest first proof surface.

## Current evidence

### Backlog truth

From `docs/roadmap/backlog.yaml`:

- `S6.3` is `done`
- `S6.4` is the exact next unresolved story in `EPIC-6`
- current `S6.4` wording is still one broad acceptance line:
  - `Docs map endpoints/screens/workflows; includes project template walkthrough.`

That wording is too broad to execute honestly without choosing a narrower first slice.

### Module ownership truth

From `docs/architecture/module-ownership-ssot.md`:

- `Work Templates` already have a clean canonical owner family at `/api/zena/work-templates`
- `Work Instances` already have a clean canonical owner family at `/api/zena/work-instances`
- `Deliverable Templates / Deliverables` already have clean canonical ownership on `/api/zena/deliverable-templates` and `/api/zena/work-instances/{id}/export*`

This is one of the cleanest documented canonical ownership clusters in the repo today.

### Project template walkthrough truth

From `routes/api_zena.php`, `app/Http/Controllers/Api/WorkTemplateController.php`, `docs/progress.md`, and `tests/Feature/Api/WorkTemplateMvpApiTest.php`:

- canonical template CRUD exists on `/api/zena/work-templates`
- canonical project apply exists on:
  - `POST /api/zena/projects/{id}/apply-template`
- canonical project-scoped work-instance readback exists on:
  - `GET /api/zena/projects/{project}/work-instances`
- canonical work-instance runtime exists on `/api/zena/work-instances*`
- prior proved stories already cover the `draft -> preview -> publish -> apply` path plus project work-instance generation

This makes the project template walkthrough the narrowest evidence-backed first slice named by current `S6.4` acceptance.

### Screen truth

From `docs/work-templates-ssot.md`:

- `UI for WT/WI management and execution` is explicitly out of scope for the Work Templates MVP

Implication:

- repo evidence is strong for canonical API/workflow ownership in this area
- repo evidence is not yet strong enough to claim a broad stable screen inventory for the same lane
- any screen coverage in the first `S6.4` slice must stay limited to evidence-backed surfaces only, and missing proof must be written as `UNKNOWN`

## What blocks runtime now

`S6.4` is not runtime-ready as currently worded because it still lacks one exact first documentation owner contract.

Current blockers:

- no exact first documentation owner anchor is named in backlog wording
- "screens" is broader than the currently proved UI evidence for the template lane
- the story could easily sprawl into a repo-wide API or screen catalog without proving one honest first slice
- no existing `S6.4` change proposal narrows the first deliverable

## Decision

Choose `Option B`: docs-only readiness lock.

Reason:

- the exact next story is clear
- the project template walkthrough is already explicitly required by backlog wording
- that walkthrough has stronger canonical ownership evidence than the dashboard/notification residue elsewhere in the repo
- the story still needs one narrow first-slice contract before any honest implementation/proof round

## Locked first future slice

### Owner anchor

Use exactly one owner anchor:

- `POST /api/zena/projects/{id}/apply-template`

Why this is the best anchor:

- it is explicitly part of the required project template walkthrough
- it is already mounted on the canonical `/api/zena/*` business surface
- it connects the cleanest current ownership chain across `Work Templates -> Project Apply -> Work Instances`
- it stays narrower than inventing a repo-wide documentation catalog

### Narrow first proof surface

The first future `S6.4` slice should document one canonical walkthrough only:

- module cluster: `Work Templates`, `Work Instances`, and adjacent `Deliverable Templates / Deliverables`
- API chain:
  - `GET|POST|PUT /api/zena/work-templates`
  - `POST /api/zena/work-templates/{id}/preview`
  - `POST /api/zena/work-templates/{id}/publish`
  - `POST /api/zena/projects/{id}/apply-template`
  - `GET /api/zena/projects/{project}/work-instances`
  - adjacent `GET|PATCH|POST /api/zena/work-instances*` only where already proved
- workflow chain:
  - `draft -> preview -> publish -> apply -> generated work-instance runtime`
- screen coverage:
  - document only evidence-backed screen surfaces
  - write `UNKNOWN` where stable screen ownership is not proved

## Explicit non-goals

This readiness lock does not claim:

- repo-wide documentation coverage across every active module
- a complete screen inventory for the whole product
- any `/api/v1/*` compatibility documentation as forward owner proof
- dashboard alert, notification-rule, event-record, or replay platform documentation
- a generic cross-module workflow catalog
- proof that `S6.4` is complete

## Runtime-ready verdict

After this round, `S6.4` is still not runtime-ready.

It is now ready only for one narrow future documentation/proof slice anchored on the canonical project template walkthrough.

## Deferred / UNKNOWN

Deferred:

- any repo-wide module coverage beyond the template walkthrough cluster
- broad screen inventory beyond evidence-backed template-lane surfaces
- documentation of dashboard/notification/event-record lanes under `S6.4`

UNKNOWN:

- which exact user-facing screen path should be the first stable screen reference for the walkthrough lane
- whether later `S6.4` slices should split by module cluster or by artifact type

## Verdict

`S6.4` is the exact next roadmap story after proved `S6.3`, but it still needs one narrowing round before any honest proof round. This lock sets the first future slice to one canonical project-template walkthrough anchored on `POST /api/zena/projects/{id}/apply-template`, keeps the documentation scope inside the already-proved WorkTemplate/WorkInstance ownership cluster, and requires `UNKNOWN` for screen facts that the repo does not yet prove.
