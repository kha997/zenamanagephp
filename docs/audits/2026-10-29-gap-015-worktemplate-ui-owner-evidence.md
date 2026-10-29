# GAP-015 — WorkTemplate → WorkInstance has no owned screens beyond "apply": Gate-1 evidence

**Date:** 2026-10-29 (+07:00)

**Canonical base:** `2e3ba4b860f1525aa5712fd477614189c9a101f0` (origin/main)

**Branch:** `docs/GAP-015-worktemplate-ui-owner`

**Scope:** Read-only investigation and Gate-1 documentation. A scratch SQLite database (outside the repository) was
seeded with `php artisan migrate:fresh --seed` to read default role grants; nothing in the repository changed.

## Candidate-ID audit

GAP-015 is the existing register row (`OPERATIONAL_GAP_REGISTER.md:101`, "OPEN (tự khai là ngoài phạm vi MVP)"). It is a
strategic product decision, not a defect. `docs/owner-decisions/GAP-015/` does not exist on any `origin/*` ref.

## Finding 1 — what the API offers (`routes/api_zena.php`)

| Lifecycle step | API | Permission |
|---|---|---|
| List / view template, version | `GET work-templates`, `/{id}`, `/{id}/versions/{versionId}` | `template.view` |
| Create / edit draft | `POST work-templates`, `PUT /{id}` | `template.edit_draft` |
| Publish | `POST /{id}/publish` | `template.publish` |
| Delete | `DELETE /{id}` | `template.delete` |
| Preview / apply to project or component | `POST /{id}/preview`, `projects/{id}/apply-template`, `components/{id}/apply-template` | `template.view` / `template.apply` |
| Import / export package | `import-template-package`, `export-template-package/{wtId}` | `template.edit_draft` / `template.view` |
| Work instances: list, metrics, update step, approve step, step attachments, export | `work-instances/*`, `projects/{project}/work-instances` | `work.view` / `work.update` / `work.approve` / `work.export` |

## Finding 2 — what the web UI offers

Only the project page: `resources/views/projects/_apply-work-template.blade.php` (included by `projects/show`),
`routes/web.php:361-363` (`projects.work-templates.index|preview|apply`), `resources/js/work-template-apply.js`. A user can
list published templates, preview and apply one to a project. There is **no web screen** to author, edit, publish or
delete a template, import/export a package, or work a WorkInstance (see steps, update, approve, attach evidence, export).

## Finding 3 — default seeded grants

| Role | template.* | work.* |
|---|---|---|
| System Admin | view, create, edit, edit_draft, publish, apply, delete | view, update, approve, export |
| Project Manager | view, create, edit, apply | **none** |
| Project Member | none | none |

PM holds `template.create` / `template.edit`, but the authoring routes require `template.edit_draft`, so a seeded PM
cannot create or edit a template through the API; PM and members cannot see or work instances at all.

## Decisions Gate 2 needs from the Owner

1. Which screens to build, if any: (a) template authoring (list, draft edit, publish, delete, import/export);
   (b) WorkInstance execution (steps, update, approve, attachments, export); (c) both; (d) none — record the API-only
   scope as the intended product and close GAP-015.
2. Which roles use each screen (who authors, who executes / approves), which decides the permission grants.
3. Order and size: each screen set is a separate release; Gate 2 would scope the first one.
