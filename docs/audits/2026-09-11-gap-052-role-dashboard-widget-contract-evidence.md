# GAP-052 Gate 1 Evidence — Role-Based Dashboard Widget Contract Mismatch

**Date:** 2026-09-11

**Canonical main/base SHA:** `97e87d216cc2abc45352e3b232c3895c86d0fe37`

**Audit branch:** `audit/GAP-052-dashboard-contract`
**Scope:** Gate 1 investigation and evidence only. No production code, test contract, route, schema, fixture, baseline, or implementation-plan change is authorized or included.

## 1. Executive finding

`GET /api/v1/dashboard/role-based` and `GET /api/v1/dashboard/role-based/widgets` are active production API routes. A genuinely authenticated `client_rep` with the canonical RBAC role can reach both routes. If that tenant has an active, accessible `DashboardWidget` whose code appears in the role configuration but is not one of the 12 locally handled switch cases, `DashboardRoleBasedService` calls:

```php
$this->dataAggregationService->getWidgetData($widget->id, $user, $projectId);
```

The concrete injected class, `DashboardDataAggregationService`, has no such method. PHP throws `Error`, the service and controller `catch (\Exception)` blocks do not catch it, and the route's outer error-envelope middleware converts it into a real HTTP 500.

**Root-cause classification:** confirmed contract mismatch whose primary class is **wrong fallback dependency/call receiver**. The absent method on `DashboardDataAggregationService` is the immediate crash mechanism, not sufficient evidence that this class should gain that method. The exact call shape exists on `DashboardService`; `DashboardDataAggregationService` instead exposes role-wide aggregate methods; `RealTimeDashboardService` exposes an incompatible two-string method. Whether Gate 2 should rewire, replace, retire, or redesign the fallback is intentionally undecided.

## 2. Base and worktree proof

Commands executed before investigation:

```text
$ git rev-parse HEAD
97e87d216cc2abc45352e3b232c3895c86d0fe37

$ git rev-parse origin/main
97e87d216cc2abc45352e3b232c3895c86d0fe37

$ git branch --show-current
audit/GAP-052-dashboard-contract

$ git status --short --branch
## audit/GAP-052-dashboard-contract
```

The installed dependency tree was copied locally from sibling worktree `impl-GAP-051-sanctum-bearer-fidelity` only after proving both `composer.lock` files had identical SHA-1 `c04f127f28680f64527df48446c6f18afc81c466`. `vendor/` is ignored; this created no tracked diff and performed no network install.

## 3. Authoritative route and middleware surface

Required SSOT command:

```text
$ php artisan route:list --except-vendor -v --path='api/v1/dashboard/role-based'
GET|HEAD api/v1/dashboard/role-based
  Api\DashboardRoleBasedController@getRoleBasedDashboard
  api
  App\Http\Middleware\Authenticate:sanctum
  App\Http\Middleware\TenantIsolationMiddleware
  App\Http\Middleware\RoleBasedAccessControlMiddleware
  App\Http\Middleware\InputSanitizationMiddleware
  App\Http\Middleware\ErrorEnvelopeMiddleware

GET|HEAD api/v1/dashboard/role-based/widgets
  Api\DashboardRoleBasedController@getRoleWidgets
  [same middleware stack]
```

All ten role-based dashboard routes are registered under the authenticated `v1` group (`routes/api.php:787`) and the role-based group (`routes/api.php:902-913`). Only the root route and `/widgets` execute widget-data aggregation (`app/Http/Controllers/Api/DashboardRoleBasedController.php:25-70,76-137`). Metrics, alerts, permissions, role configuration, projects, summary, and project-context do not traverse this failing call graph.

No first-party caller of `/api/v1/dashboard/role-based` or `/api/v1/dashboard/role-based/widgets` was found under `resources/`, `public/`, or `app/`. That does not make the active API route dead: external clients and the live production database are unavailable to this source audit, so live caller volume is **UNKNOWN**.

## 4. Exact RED reproduction with genuine authentication

The canonical integration fixture uses `AuthenticationTrait::apiAs()` (`tests/Traits/AuthenticationTrait.php:70-113`), which logs in through `/api/auth/login`, extracts the issued token, sends an actual `Authorization: Bearer ...` header, and calls `AuthManager::forgetGuards()` before dispatch. The scenario also creates the canonical RBAC pivot role, mapping app role `client_rep` to middleware role `client` (`tests/Integration/SystemIntegrationTest.php:710-733`). This is the genuine transport/RBAC path exposed by GAP-051, not `actingAs()` or a cached guard.

Canonical main currently records the defect as an expected 500 at `tests/Integration/SystemIntegrationTest.php:693-758`. First, the scenario was run unchanged:

```text
$ ./vendor/bin/phpunit tests/Integration/SystemIntegrationTest.php \
    --group performance \
    --filter it_can_handle_role_based_data_filtering \
    --stop-on-failure --testdox

OK, but there were issues!
Tests: 1, Assertions: 55, PHPUnit Deprecations: 11.
```

For the RED proof only, the two local known-defect expectations were temporarily changed from `assertStatus(500)` to `assertStatus(200)`. No fixture, authentication, middleware, route, controller, or production source changed. The exact same command then failed:

```text
Expected response status code [200] but received 500.
Failed asserting that 500 is identical to 200.

Error: Call to undefined method
App\Services\DashboardDataAggregationService::getWidgetData()
in app/Services/DashboardRoleBasedService.php:316

#0 DashboardRoleBasedService.php(257): getWidgetDataForRole(...)
#1 DashboardRoleBasedService.php(47): getRoleBasedWidgets(...)
#2 DashboardRoleBasedController.php(45): getRoleBasedDashboard(...)

FAILURES!
Tests: 1, Assertions: 46, Failures: 1, PHPUnit Deprecations: 11.
```

Both temporary expectations were immediately restored. `git diff --exit-code -- tests/Integration/SystemIntegrationTest.php` passed afterward. The RED evidence therefore proves the intended successful HTTP contract fails on exact canonical main without committing a failing test or modifying production code.

Environment-only warnings: local PHP reported unavailable optional `imagick`/`memcached` shared-library dependencies. They did not prevent the route listing, the 55-assertion current-contract run, or the RED run and are unrelated to the observed application stack.

## 5. Full call graph and failure boundary

### Root dashboard endpoint

1. `routes/api.php:787,902-904` — authenticated, tenant-scoped, RBAC-protected route.
2. `DashboardRoleBasedController::getRoleBasedDashboard()` (`app/Http/Controllers/Api/DashboardRoleBasedController.php:25-70`).
3. `DashboardRoleBasedService::getRoleBasedDashboard()` (`app/Services/DashboardRoleBasedService.php:34-76`).
4. `DashboardRoleBasedService::getRoleBasedWidgets()` (`:247-267`).
5. Tenant filter: active widgets are loaded with `where('tenant_id', $user->tenant_id)` (`:250-252`).
6. Role filter: only configured default widget codes that pass `userCanAccessWidget()` continue (`:254-257`, `:685-694`).
7. `DashboardRoleBasedService::getWidgetDataForRole()` (`:272-325`).
8. Any code outside the 12 explicit cases enters `default:` and dispatches the nonexistent method on `DashboardDataAggregationService` (`:315-316`).
9. PHP `Error` bypasses the service's `catch (\Exception)` (`:318`) and the controller's `catch (\Exception)` (`DashboardRoleBasedController.php:58`).
10. `ErrorEnvelopeMiddleware::handle()` catches `\Throwable` and returns `E500.SERVER_ERROR` (`app/Http/Middleware/ErrorEnvelopeMiddleware.php:26-35`).

### Widgets endpoint

Steps 1 and 4-10 are identical; its controller entry is `DashboardRoleBasedController::getRoleWidgets()` (`app/Http/Controllers/Api/DashboardRoleBasedController.php:76-137`). `include_data=false` does not avoid the crash because the controller always calls `getRoleBasedWidgets()` with data before stripping the returned `data` key (`:98-113`).

### Error disclosure side effect

The outer middleware passes both `$e->getMessage()` and an `exception` detail into `ErrorEnvelopeService::serverError()` (`ErrorEnvelopeMiddleware.php:30-35`), and that service includes them in the JSON response (`app/Services/ErrorEnvelopeService.php:34-58,206-217`) without checking `APP_DEBUG`. Therefore the 500 response can expose the internal class and missing method name. This is directly coupled to the reproduced failure. Broader error-envelope hardening outside this failure remains out of GAP-052 scope unless separately governed.

## 6. Service-contract comparison

| Component | Actual public widget/aggregate contract | Relationship to failing call |
|---|---|---|
| `DashboardRoleBasedService` | Locally handles 12 widget codes, then calls `dataAggregationService->getWidgetData(string widgetId, User user, ?string projectId)` | Caller and failing fallback |
| `DashboardDataAggregationService` | Seven role-wide methods: `getSystemAdminData`, `getProjectManagerData`, `getDesignLeadData`, `getSiteEngineerData`, `getQCInspectorData`, `getClientRepData`, `getSubcontractorLeadData`; no `getWidgetData` | Injected concrete type does not satisfy the call |
| `DashboardService` | `getWidgetData(string $widgetId, App\Models\User $user, ?string $projectId = null, array $params = [])` (`app/Services/DashboardService.php:76-112`) | Exact first-three-argument shape exists here; separate generic dashboard routes already inject this service (`DashboardController.php:16-19,385-392`) |
| `RealTimeDashboardService` | `getWidgetData(string $widgetType, string $userId)` (`app/Services/RealTimeDashboardService.php:58-81`) | Same name, incompatible meaning and signature; not a viable drop-in contract |

No dashboard service interface defining `getWidgetData` was found by repository-wide PHP search. The role-based service's two injected properties are assigned in its constructor, but `dataAggregationService` is referenced only by the failing line and `customizationService` is never referenced after assignment (`app/Services/DashboardRoleBasedService.php:20-28,316`).

Additional contract debt: current `DashboardDataAggregationService.php` imports only `Carbon` and `DB`; its unqualified `User` parameter therefore reflects as `App\Services\User`, not `App\Models\User`. Runtime reflection confirmed:

```text
DashboardDataAggregationService getWidgetData=no
DashboardService getWidgetData=yes
RealTimeDashboardService getWidgetData=yes
getClientRepData param0=App\Services\User
```

This makes the aggregation class's advertised role methods independently unusable with normal `App\Models\User` arguments. It reinforces that the injected dependency is not an established widget-level contract, but it is not the immediate reproduced stack because none of those seven methods is called on the affected path.

## 7. Role and widget reachability

The crash is data-dependent. `getRoleBasedWidgets()` silently skips a configured code when the tenant has no matching active row or the user lacks that widget's permission. It crashes only when an active same-tenant, role-accessible row reaches an unhandled code.

Static comparison of `getRoleConfiguration()` (`DashboardRoleBasedService.php:82-198`) against the handled switch (`:278-314`) shows potential exposure for every configured role:

| Role | Handled configured codes | Configured codes that enter the broken fallback if matching rows exist |
|---|---|---|
| `system_admin` | `system_health`, `user_management` | `tenant_overview`, `system_metrics`, `audit_logs`, `backup_status` |
| `project_manager` | 8 handled codes | `change_requests` |
| `design_lead` | none | all 6 configured codes |
| `site_engineer` | none | all 8 configured codes |
| `qc_inspector` | `inspection_schedule`, `ncr_tracking`, `quality_metrics` | remaining 5 configured codes |
| `client_rep` | none | all 8 configured codes |
| `subcontractor_lead` | none | all 8 configured codes |

The exact RED fixture contains active, tenant-owned, `client_rep`-accessible `budget_summary` and other matching codes (`tests/Integration/SystemIntegrationTest.php:87-329`), so `client_rep` is runtime-confirmed on both endpoints. GAP-051's recorded diagnostic found project manager, site engineer, and QC inspector returned 200 under that fixture while `client_rep` returned 500 on root/widgets and 200 on metrics/alerts/permissions (`SystemIntegrationTest.php:693-705`).

Actual production `dashboard_widgets` rows and external API callers were not inspected: **UNKNOWN**. The canonical `DatabaseSeeder` does not call `DashboardSeeder` (`database/seeders/DatabaseSeeder.php:20-55`), and `DashboardSeeder` predates the nullable `code` column and supplies no `code` values (`database/seeders/DashboardSeeder.php:28-377`; migration truth at `database/migrations/2026_02_02_040000_add_code_to_dashboard_widgets_table.php:12-17`). Source alone therefore cannot establish current production row distribution.

## 8. Tenant, RBAC, and production-impact assessment

- **Real 500:** confirmed by executed HTTP test through genuine login, Bearer token, `auth:sanctum`, tenant middleware, RBAC middleware, controller, and concrete services.
- **Affected endpoints:** confirmed `GET /api/v1/dashboard/role-based` and `GET /api/v1/dashboard/role-based/widgets`. Other role-based endpoints do not traverse the failing widget call.
- **Affected roles:** `client_rep` is runtime-confirmed with current fixtures. All seven configured roles are statically reachable when their tenant has an accessible active row for an unhandled configured code.
- **Tenant isolation:** the failing lookup starts from widgets filtered by the authenticated user's `tenant_id`; the crash occurs before fallback data retrieval. No cross-tenant disclosure was reproduced. Any Gate-2 design must preserve this invariant and must test same-tenant success plus cross-tenant denial/absence.
- **RBAC:** the reproduction grants the middleware's canonical `client` role and still reaches the failure. This is not an RBAC denial disguised as a 500. No authorization bypass was reproduced.
- **Information exposure:** the error envelope can return the internal undefined-method text to an authenticated caller, as described in §5.
- **Frequency/severity:** request failure is deterministic once the qualifying widget row exists. Live row distribution, request volume, and observed production logs are **UNKNOWN** because production access was neither requested nor used.

## 9. Test-double and coverage audit

### Confirmed false contract

`tests/Unit/Dashboard/DashboardRoleBasedServiceTest.php:69-79` creates a Mockery mock of the concrete `DashboardDataAggregationService`, then declares:

```php
$this->aggregationService->shouldReceive('getWidgetData')->andReturn([]);
```

Mockery accepts this method even though the real class has no method. The unit file's fixtures include only locally handled `project_overview` and `task_progress`, so the fallback is not required to prove a real concrete contract. The complete unit class passes:

```text
$ ./vendor/bin/phpunit tests/Unit/Dashboard/DashboardRoleBasedServiceTest.php --testdox --stop-on-failure
Tests: 20, Assertions: 179, PHPUnit Deprecations: 20.
```

This is the principal false-double finding.

### Integration contract normalizes the defect

`SystemIntegrationTest::it_can_handle_role_based_data_filtering` now documents and expects the two `client_rep` 500s. This preserves discovery but makes current main green on the broken success contract. The entire class is tagged `@group performance` (`tests/Integration/SystemIntegrationTest.php:21-24`), and `phpunit.xml:19-23` excludes that group by default. The exact reproduction therefore requires `--group performance` and is absent from the default suite.

### Other suites miss the path

- `DashboardApiTest` authenticates only a project manager for the successful role-based root/widgets assertions, and its widget fixtures use handled codes (`tests/Feature/Dashboard/DashboardApiTest.php:26-105,459-497`).
- `FinalSystemTest` loops all roles, but every widget it creates is permissioned only for project manager/site engineer; roles such as `client_rep` see no qualifying widget and return 200 without exercising fallback (`tests/Integration/FinalSystemTest.php:81-125,983-1006`). The class is also in the excluded performance group.
- `DashboardE2ETest` contains a `client_rep` 200 assertion (`tests/E2E/DashboardE2ETest.php:665-688`), but an executed targeted run failed during fixture setup first on missing required `rfis.title` (`:194`), with zero assertions. It cannot currently protect this contract.
- Repository-wide search found no other mock/stub that explicitly grants `getWidgetData()` to `DashboardDataAggregationService`.

## 10. History

- Commit `70b20699db8bdded4d4b0f23646f507c23b56ff5` (2025-09-18) introduced `DashboardRoleBasedService`, `DashboardDataAggregationService`, `DashboardService`, and `RealTimeDashboardService` together. The bad receiver call and aggregation constructor type were present at introduction; `DashboardService::getWidgetData` already had the matching call shape in that same commit.
- The original unit test nevertheless constructed `DashboardRoleBasedService` with mocks of `DashboardService` and `DashboardRealTimeService`, evidence that the test and production constructor disagreed about dependency ownership from inception.
- Commit `9a0b43a277d5cc17d88b8ee0fcfa772c3defdad0` removed the model imports from `DashboardDataAggregationService`, creating the additional `App\Services\User` type mismatch.
- Commit `4b06594eaa199f40093a40b6d4685946c658ae5a` (2026-02-20, PR #31) activated the named `/api/v1/dashboard/role-based/*` routes and changed the unit setup to mock `DashboardDataAggregationService::getWidgetData()` even though the concrete method did not exist. Route activation and the permissive false double therefore entered canonical history together.
- Commit `f6166d5e20e29e8ec9318d1f34848585f1849fef` (GAP-051 Gate 3) made the system integration path use genuine authentication and explicitly record the resulting GAP-052 500s instead of allowing stale identity to mask them.

## 11. Gate-2 decision boundary — recommendation only, no design

If Owner approves Gate 1, Gate 2 should answer exactly these questions before implementation planning:

1. Is the role-based root/widgets API an intended supported product surface, or should an unused/legacy surface be retired through the governed deprecation path?
2. If retained, which component owns generic per-widget data: the role service itself, `DashboardService`, a repaired aggregation abstraction, or a deliberately different contract?
3. Should unrecognized configured widget codes be supported, rejected as configuration errors, or omitted—and what response is user-visible?
4. Which of the seven role configurations and widget catalogs are authoritative, given current duplication across role-based and customization services?
5. What tests must bind the concrete production dependency graph, all seven roles, genuine Bearer authentication, same-tenant filtering, RBAC, empty-catalog behavior, and at least one unhandled configured widget?
6. Must GAP-052 also prevent this exact error response from exposing internal class/method text, while leaving broader error-envelope hardening to a separate Work ID?

Gate 2 must **not** start from “add `getWidgetData()` to `DashboardDataAggregationService`” or “inject `DashboardService`” as a pre-approved answer. Both are hypotheses requiring contract, tenant, cache, and data-source analysis. No implementation plan belongs in Gate 1.

## 12. Facts, recommendations, and unknowns

### Verified facts

- Exact base and `origin/main`: `97e87d216cc2abc45352e3b232c3895c86d0fe37`.
- Active authenticated routes and middleware: verified by `route:list`.
- Genuine `client_rep` HTTP 500 and undefined-method stack: reproduced RED.
- Concrete aggregation class lacks the method; generic dashboard class has the matching method; real-time class has an incompatible method.
- Unit mock grants a method absent from the real class and the unit suite passes.
- No production code or persistent test change was made.

### Recommendation

Approve Gate 1 and authorize a separate Gate-2 design investigation bounded by §11.

### UNKNOWN

- Current production `dashboard_widgets` code/permission distribution.
- Current production call volume, logs, and external API consumers.
- Whether the active role-based API is an intended retained business surface or legacy exposure.
- The correct future owner of the generic fallback contract; this is the central Gate-2 decision, not a Gate-1 conclusion.

## 13. Gate-1 validation status

Focused checks on the unchanged application code and the evidence packet:

```text
$ git diff --check
PASS

$ php scripts/ssot/owner_governance_lint.php \
    docs/owner-decisions/GAP-052/01-request.md
PASS (1 file checked)

$ php scripts/ssot/owner_governance_lint.php \
    --enforce-gate-ordering \
    --changed-files-json=/tmp/gap052-changed-files.json
PASS (111 files checked; gate ordering passed)

$ ./vendor/bin/phpunit tests/Unit/Dashboard/DashboardRoleBasedServiceTest.php \
    --testdox --stop-on-failure
PASS — Tests: 20, Assertions: 179, PHPUnit Deprecations: 20

$ ./vendor/bin/phpunit tests/Integration/SystemIntegrationTest.php \
    --group performance \
    --filter it_can_handle_role_based_data_filtering \
    --stop-on-failure --testdox
PASS under canonical main's known-defect expectations —
Tests: 1, Assertions: 55, PHPUnit Deprecations: 11
```

The unconfigured local default-suite baseline is not green and is not caused
by this evidence-only diff:

```text
$ ./vendor/bin/phpunit
ERRORS!
Tests: 2545, Assertions: 15736, Errors: 528, Failures: 7,
PHPUnit Deprecations: 510, Skipped: 47.
```

The dominant error is `MissingAppKeyException` because this fresh worktree
has no application encryption key. The seven reported failures include local
optional-extension warning contamination and existing authentication
expectation failures. No production or test file was changed to influence
that baseline. The focused route-level reproduction and tests above completed
independently and are the authoritative GAP-052 evidence.
