# GAP-099 — Broken `/api/v1` project update and change-request routes: Gate-1 evidence

**Date:** 2026-10-28 (+07:00)

**Canonical base:** `8dd80a8422a9cc6da945c5fa0db5b0bec3850482` (GAP-098 released)

**Branch:** `docs/GAP-099-broken-v1-project-and-change-request`

**Scope:** read-only. Found while implementing GAP-098 (reported in its Gate-3 packet). No code change.

## Reproduction

A temporary test (not committed) called each route as a **super admin** (RBAC bypassed, so every result below is the
route's own behaviour) on one tenant and project:

| Request | Result | Cause |
|---|---|---|
| `GET /api/v1/projects/{project}` | 200 | — |
| `PUT` / `PATCH /api/v1/projects/{project}` | **500** | `src/CoreProject/Requests/UpdateProjectRequest.php:54` reads `Project::STATUSES`, which does not exist (the model defines `VALID_STATUSES`, as `StoreProjectRequest.php:61` uses) |
| `GET /api/v1/change-requests` | **500** | `ChangeRequestController::index(Request, string $projectId)`; the route passes no project id |
| `GET /api/v1/change-requests/pending-approval` | **500** | method `getPendingApproval` does not exist |
| `GET /api/v1/change-requests/project/{projectId}` | **500** | method `getByProject` does not exist |
| `GET /api/v1/change-requests/statistics/{projectId}` | **500** | method `getStatistics` does not exist (the controller has `stats`) |
| `GET /api/v1/change-requests/{id}` | **500** | `show(string $projectId, string $id)`; one argument passed |
| `PUT /api/v1/change-requests/{id}` | **500** | `update(…, $projectId, $id)`; two arguments passed |
| `DELETE /api/v1/change-requests/{id}` | **500** | `destroy($projectId, $id)`; one argument passed |
| `POST /api/v1/change-requests/{id}/approve`, `/reject` | **500** | `ChangeRequestService::approveChangeRequest(int $id)` / `rejectChangeRequest(int $id)` receive a string |
| `POST /api/v1/change-requests` | 201 | works |
| `POST /api/v1/change-requests/{id}/submit` | 422 (validation) | reachable |

Further, every `{id}` / `{projectId}` in `src/ChangeRequest/routes/api.php` is constrained to `[0-9]+`, while
`change_requests.id` and `projects.id` are ULID strings (`ChangeRequest` uses `HasUlids`): a real record can never
match those routes. The group also runs the bare `rbac` role check (`handleGeneralAccess()`), which accepts role slugs
such as `project_manager` but not the seeded display names (`Project Manager`).

So 9 of the 11 `/api/v1/change-requests*` routes and both project update routes fail for every caller.

## Callers and alternatives

- No mounted page calls these routes (`resources/views/change-requests/form.blade.php` is not rendered by any route).
- Canonical, working equivalents: `/api/zena/change-requests` (12 routes, `App\Http\Controllers\Api\ChangeRequestController`)
  and `/api/zena/projects/{id}` update.
- External clients: UNKNOWN — but none can be using the failing routes successfully today.
