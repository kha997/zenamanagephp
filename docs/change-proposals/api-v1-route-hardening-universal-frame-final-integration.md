# Change Proposal: `api/v1` Route Hardening for `universal-frame` and `final-integration`

## Scope

- In scope: route hardening and contract guard expansion for:
  - `api/v1/universal-frame/*`
  - `api/v1/final-integration/*`
- Out of scope:
  - `production.yml`
  - Slack secrets / workflow routing
  - CI DB lane realignment
  - bulk ULID cleanup
  - domain logic / tenant isolation / RBAC behavior changes made only to satisfy tests

## Route Truth

- Runtime route truth comes from `php artisan route:list --json --except-vendor`.
- Both families are currently mounted from [`routes/web.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/routes/web.php), so effective stacks are `web` + listed middleware.
- Current highest-risk drift:
  - `api/v1/final-integration/*` routes currently only have `auth`.
  - Several `api/v1/universal-frame/*` write and export routes currently only have `auth`.

## Proposal Summary

- Keep both families on their current paths for this round.
- Apply the canonical hardened stack to both families:
  - `auth`
  - `tenant.isolation`
  - `rbac:admin`
  - `input.sanitization`
  - `error.envelope`
- Do not change controller/service logic in this round.
- Expand contract tests to lock:
  - tenant isolation requirement
  - RBAC denial for non-admin callers
  - middleware stack hygiene
  - error envelope shape where the family is hardened with the envelope middleware

## Inventory

### `api/v1/universal-frame/*`

| Current path | Controller@method | Current middleware stack | Proposed middleware stack | Risk / behavior note |
| --- | --- | --- | --- | --- |
| `GET api/v1/universal-frame/kpis` | `KpiController@index` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | no change | Already hardened; use as family baseline. |
| `GET api/v1/universal-frame/kpis/preferences` | `KpiController@preferences` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | no change | Already hardened. |
| `POST api/v1/universal-frame/kpis/preferences` | `KpiController@savePreferences` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | no change | Already hardened. |
| `POST api/v1/universal-frame/kpis/refresh` | `KpiController@refresh` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | no change | Already hardened. |
| `GET api/v1/universal-frame/kpis/stats` | `KpiController@stats` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | no change | Already hardened. |
| `GET api/v1/universal-frame/alerts` | `AlertController@index` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | no change | Already hardened. |
| `POST api/v1/universal-frame/alerts/acknowledge` | `AlertController@acknowledge` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | no change | Already hardened. |
| `POST api/v1/universal-frame/alerts/mute` | `AlertController@mute` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | no change | Already hardened. |
| `POST api/v1/universal-frame/alerts/dismiss-all` | `AlertController@dismissAll` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | no change | Already hardened. |
| `POST api/v1/universal-frame/alerts/create` | `AlertController@create` | `web`, `auth` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | Safe to harden now: sibling alert routes already use the target stack. |
| `GET api/v1/universal-frame/alerts/stats` | `AlertController@stats` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | no change | Already hardened. |
| `GET api/v1/universal-frame/activities` | `ActivityController@index` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | no change | Already hardened. |
| `GET api/v1/universal-frame/activities/by-type` | `ActivityController@byType` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | no change | Already hardened. |
| `GET api/v1/universal-frame/activities/stats` | `ActivityController@stats` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | no change | Already hardened. |
| `POST api/v1/universal-frame/search` | `SearchController@search` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | no change | Already hardened. |
| `GET api/v1/universal-frame/search/suggestions` | `SearchController@suggestions` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | no change | Already hardened. |
| `GET api/v1/universal-frame/search/recent` | `SearchController@recent` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | no change | Already hardened. |
| `POST api/v1/universal-frame/search/recent` | `SearchController@saveRecent` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | no change | Already hardened. |
| `GET api/v1/universal-frame/filters/presets` | `FilterController@presets` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | no change | Already hardened. |
| `GET api/v1/universal-frame/filters/deep` | `FilterController@deepFilters` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | no change | Already hardened. |
| `GET api/v1/universal-frame/filters/saved-views` | `FilterController@savedViews` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | no change | Already hardened. |
| `POST api/v1/universal-frame/filters/saved-views` | `FilterController@saveView` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | no change | Already hardened. |
| `DELETE api/v1/universal-frame/filters/saved-views/{viewId}` | `FilterController@deleteView` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | no change | Already hardened. |
| `POST api/v1/universal-frame/filters/apply` | `FilterController@applyFilters` | `web`, `auth` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | Safe to harden now: save/delete/list siblings already use target stack. |
| `POST api/v1/universal-frame/analysis` | `AnalysisController@index` | `web`, `auth` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | Safe to harden now: same controller family already uses target stack for read endpoints. |
| `GET api/v1/universal-frame/analysis/{context}` | `AnalysisController@context` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | no change | Already hardened. |
| `GET api/v1/universal-frame/analysis/{context}/metrics` | `AnalysisController@metrics` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | no change | Already hardened. |
| `GET api/v1/universal-frame/analysis/{context}/charts` | `AnalysisController@charts` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | no change | Already hardened. |
| `GET api/v1/universal-frame/analysis/{context}/insights` | `AnalysisController@insights` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | no change | Already hardened. |
| `POST api/v1/universal-frame/export` | `ExportController@index` | `web`, `auth` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | Safe to harden now: export history sibling already hardened; controller contains admin-only branches already. |
| `POST api/v1/universal-frame/export/projects` | `ExportController@projects` | `web`, `auth` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | Safe to harden now: same family baseline. |
| `POST api/v1/universal-frame/export/tasks` | `ExportController@tasks` | `web`, `auth` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | Safe to harden now. |
| `POST api/v1/universal-frame/export/documents` | `ExportController@documents` | `web`, `auth` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | Safe to harden now. |
| `POST api/v1/universal-frame/export/users` | `ExportController@users` | `web`, `auth` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | Safe to harden now; controller already enforces stronger super-admin check internally. |
| `POST api/v1/universal-frame/export/tenants` | `ExportController@tenants` | `web`, `auth` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | Safe to harden now; controller already enforces stronger super-admin check internally. |
| `GET api/v1/universal-frame/export/history` | `ExportController@history` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | no change | Already hardened. |
| `DELETE api/v1/universal-frame/export/{filename}` | `ExportController@delete` | `web`, `auth` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | Safe to harden now: destructive export endpoint should not be weaker than history. |
| `POST api/v1/universal-frame/export/clean-old` | `ExportController@cleanOld` | `web`, `auth` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | Safe to harden now: destructive maintenance endpoint. |

### `api/v1/final-integration/*`

`rbac:admin` for this family is an inference from route purpose and controller behavior, not a directly documented contract. Evidence supporting the inference:

- family name and UI are launch/admin oriented
- several actions can clear caches, cache routes, optimize config, and run migrations via `LaunchChecklistService`
- there is no in-repo evidence that non-admin users should execute these operations

| Current path | Controller@method | Current middleware stack | Proposed middleware stack | Risk / behavior note |
| --- | --- | --- | --- | --- |
| `GET api/v1/final-integration/launch-status` | `FinalIntegrationController@getLaunchStatus` | `web`, `auth` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | Compatibility-sensitive but acceptable now; in-repo consumer evidence for non-admin access is UNKNOWN. |
| `POST api/v1/final-integration/system-integration-checks` | `FinalIntegrationController@runSystemIntegrationChecks` | `web`, `auth` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | Compatibility-sensitive; launch-check family should not bypass tenant/RBAC. |
| `POST api/v1/final-integration/production-readiness-checks` | `FinalIntegrationController@runProductionReadinessChecks` | `web`, `auth` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | Compatibility-sensitive; same rationale. |
| `POST api/v1/final-integration/launch-preparation-tasks` | `FinalIntegrationController@runLaunchPreparationTasks` | `web`, `auth` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | Compatibility-sensitive; task execution surface. |
| `GET api/v1/final-integration/go-live-checklist` | `FinalIntegrationController@getGoLiveChecklist` | `web`, `auth` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | Compatibility-sensitive; read endpoint in same sensitive family. |
| `POST api/v1/final-integration/pre-launch-actions` | `FinalIntegrationController@executePreLaunchActions` | `web`, `auth` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | High-sensitivity action; service can clear/optimize/cache/migrate. |
| `POST api/v1/final-integration/launch-actions` | `FinalIntegrationController@executeLaunchActions` | `web`, `auth` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | High-sensitivity action. |
| `POST api/v1/final-integration/validate-integration` | `FinalIntegrationController@validateIntegration` | `web`, `auth` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | Compatibility-sensitive; no repo evidence of non-admin callers. |
| `POST api/v1/final-integration/run-production-check` | `FinalIntegrationController@runProductionCheck` | `web`, `auth` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | Compatibility-sensitive. |
| `POST api/v1/final-integration/complete-launch-task` | `FinalIntegrationController@completeLaunchTask` | `web`, `auth` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | Compatibility-sensitive. |
| `POST api/v1/final-integration/toggle-checklist-item` | `FinalIntegrationController@toggleChecklistItem` | `web`, `auth` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | Compatibility-sensitive write surface. |
| `POST api/v1/final-integration/execute-action` | `FinalIntegrationController@executeAction` | `web`, `auth` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | Compatibility-sensitive write surface. |
| `GET api/v1/final-integration/launch-metrics` | `FinalIntegrationController@getLaunchMetrics` | `web`, `auth` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | Compatibility-sensitive read aggregation endpoint. |
| `GET api/v1/final-integration/launch-report` | `FinalIntegrationController@generateLaunchReport` | `web`, `auth` | `web`, `auth`, `tenant.isolation`, `rbac:admin`, `input.sanitization`, `error.envelope` | Compatibility-sensitive report endpoint. |

## Safe Now vs Compatibility Handling

### Safe to harden now

- All currently weak `api/v1/universal-frame/*` routes listed above.
- Reason: sibling routes in the same family already enforce the target stack, so this is stack convergence rather than a new access model.

### Harden now, but treat as compatibility-sensitive

- All `api/v1/final-integration/*` routes.
- Reason: the family is currently weak across the board, and in-repo evidence points to an admin/launch surface, but external consumer assumptions are not fully proven.
- Handling in this round:
  - add contract tests first
  - apply the minimal route-only hardening
  - do not rename or move paths
  - do not change controller logic

## Test Plan

- Route contract tests:
  - expand `TenantIsolationV1ContractTest` anchors to both families
  - add a family-specific hardening contract test that asserts exact expected middleware for all routes in both families
- Runtime behavior tests:
  - `universal-frame` validation failure returns JSON error envelope shape
  - `final-integration` tenant mismatch returns `TENANT_INVALID`
  - `final-integration` non-admin caller is denied with JSON error envelope shape
- Verification commands:
  - `php artisan test tests/Feature/RouteMiddleware/TenantIsolationV1ContractTest.php`
  - `php artisan test tests/Feature/RouteMiddleware/V1LegacyRouteHardeningContractTest.php`
  - `php artisan test tests/Feature/Api/ApiSecurityMiddlewareGateTest.php`

## Risk Assessment

- Primary risk: compatibility break for any existing `final-integration` consumer that currently relies on plain session auth without admin rights.
- Mitigation:
  - no path changes
  - no controller/service behavior changes
  - contract tests lock route truth and failure-mode truth
- Secondary risk: `web`-mounted API family still exists in `routes/web.php`.
  - Not addressed in this round; migration to canonical API files remains separate debt.
- Secondary risk: runtime semantics of `rbac:admin` appear broader/narrower than the name suggests.
  - Evidence from contract execution: representative allow-path testing was stable with `super_admin`.
  - This round does not change RBAC internals; it only hardens route stacks.

## UNKNOWN

- UNKNOWN: whether any external or browser consumer outside the repo depends on non-admin access to `api/v1/final-integration/*`.
- UNKNOWN: whether documentation should declare `rbac:admin` for `final-integration` explicitly in a later docs-alignment round.
- UNKNOWN: whether `rbac:admin` is intended to authorize plain `admin` role holders for these routes under the current middleware implementation.
- UNKNOWN: whether stale frontend references still exist for `/api/universal-frame/*` non-`v1` paths; this round does not reconcile route/docs/frontend drift beyond the two target families.
