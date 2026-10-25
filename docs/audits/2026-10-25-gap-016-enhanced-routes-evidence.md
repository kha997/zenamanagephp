# GAP-016 — Evidence: `/projects-enhanced` and `/admin-dashboard-enhanced` render missing views

Base: `0ac6f6209e3b97c4f1721e3d8e907a14f45797d9` (main after GAP-017).

## The routes

`routes/web.php:226-234`:
- `GET /admin-dashboard-enhanced` → closure `view('admin.dashboard-enhanced')`, middleware `auth`, `tenant.isolation`,
  `rbac:admin`, name `admin-dashboard-enhanced`;
- `GET /projects-enhanced` → closure `view('app.projects-enhanced')`, middleware `auth`, `tenant.isolation`, name
  `projects-enhanced`.

Neither `resources/views/admin/dashboard-enhanced.blade.php` nor `resources/views/app/projects-enhanced.blade.php` exists.
`docs/refactor/duplicates.csv:6` records `app/projects-enhanced.blade.php` as deleted in an earlier duplicate cleanup; the
route stayed.

## Reproduction (feature test on the base above, then removed)

| Request | Result |
|---|---|
| guest `GET /projects-enhanced` | 302 to login |
| guest `GET /admin-dashboard-enhanced` | 302 to login |
| signed-in tenant member `GET /projects-enhanced` | **500** — `View [app.projects-enhanced] not found.` |
| tenant admin `GET /admin-dashboard-enhanced` | **500** — `View [admin.dashboard-enhanced] not found.` |
| tenant member `GET /admin-dashboard-enhanced` | 302 (blocked by `rbac:admin`) |

So any signed-in user who opens `/projects-enhanced`, and any admin who opens `/admin-dashboard-enhanced`, gets a 500.

## Consumers

No view, controller, JavaScript or test in the repo links to either URL or route name. The only references are the routes
themselves and old audit/refactor docs (`docs/audits/2026-03-19-legacy-surface-rationalization-backlog.md:38` marks them
`delete-candidate`).

## Notes for Gate 2

- Options: remove both routes, or redirect them to the canonical pages (`/app/projects`, admin dashboard).
- Add a regression test for whichever option is chosen.
