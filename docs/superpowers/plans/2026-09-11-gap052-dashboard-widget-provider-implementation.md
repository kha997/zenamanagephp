---
work_id: GAP-052
owner_governance_version: 1
# The governance linter derives the canonical Gate-2 path as 02-design.md;
# 02-design-v2.md is the superseding clarification record referenced below.
owner_gate_2_record: docs/owner-decisions/GAP-052/02-design.md
---

# GAP-052 Dashboard Widget Provider/Resolver Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the false role-dashboard widget-data dependency with an explicit provider/resolver contract while preserving both authenticated v1 dashboard surfaces, safe partial degradation, and tenant/RBAC/project isolation.

**Architecture:** `DashboardRoleBasedService` remains the role-oriented orchestrator and role-summary consumer. A new dashboard widget-data resolver receives a typed request context, selects a provider by authoritative widget code/provider capability, and returns a typed per-widget result; the resolver never formats HTTP responses. A catalog object becomes the single role-to-widget authority consumed by the role service and customization metadata path. Existing `DashboardService` generic retrieval is not promoted until its tenant/query/cache behavior is proven; the real-time services are not providers for this contract.

**Tech Stack:** Laravel 12, PHP 8.2, Eloquent, Sanctum Bearer authentication, PHPUnit 11, Mockery only at the explicit provider boundary, SQLite local test database, existing MySQL CI/parity lane where configured.

**Spec:** `docs/superpowers/specs/2026-09-11-gap052-dashboard-widget-contract-design.md`; owner approval and clarification: `docs/owner-decisions/GAP-052/02-design-v2.md` (supersedes `02-design.md` only for the explicit unknown-role rule); Gate-1 evidence: `docs/audits/2026-09-11-gap-052-role-dashboard-widget-contract-evidence.md`; Gate-3 preparation packet: `docs/owner-decisions/GAP-052/03-release.md`.

## Global Constraints

- Retain `GET /api/v1/dashboard/role-based` and `GET /api/v1/dashboard/role-based/widgets`, including their successful outer `success`, `data`, and `meta` shape.
- `DashboardDataAggregationService` remains role-summary-only; do not add `getWidgetData()` to it.
- Generic widget retrieval is owned by the explicit provider/resolver boundary.
- An eligible unsupported widget with `include_data=true` remains in position as `state: degraded`, `error.code: DASHBOARD.WIDGET_UNSUPPORTED`, with no fake data or internal details; safe siblings continue.
- `include_data=false` returns eligible metadata without resolving or requiring providers.
- Use request-level 5xx only when safe response composition is impossible.
- Use real container wiring, all seven configured roles, genuine login-issued Bearer tokens, canonical RBAC, tenant isolation, and project-access checks.
- Remove the Mockery expectation that invents `DashboardDataAggregationService::getWidgetData()`.
- Fix only GAP-052’s undefined-method/internal-detail disclosure on the two affected dashboard paths; do not refactor the global error-envelope system.
- Do not promote `DashboardService`’s generic SQL/query path without proving tenant, query, authorization, and cache safety.
- Do not change schema, migrations, production data, route paths, or unrelated dashboard/error behavior.
- Unknown or invalid dashboard roles fail closed before catalog/provider execution with HTTP 403, safe code `DASHBOARD.ROLE_UNSUPPORTED`, and no implicit aliases or fallback to `client_rep`.

## Verified Inventory and Current Wiring

The implementation worker must preserve this inventory as the baseline and update it only with evidence from tests/source:

| Surface | Verified current contents | Consequence for implementation |
|---|---|---|
| Role catalog | `DashboardRoleBasedService::getRoleConfiguration()` contains exactly `system_admin`, `project_manager`, `design_lead`, `site_engineer`, `qc_inspector`, `client_rep`, `subcontractor_lead`; 53 configured role-widget entries in total | Extract into one catalog authority without changing the seven role names or ordering. |
| Current local handlers | `getWidgetDataForRole()` has exactly 12 explicit codes: `project_overview`, `task_progress`, `rfi_status`, `budget_tracking`, `schedule_timeline`, `team_performance`, `quality_metrics`, `safety_summary`, `inspection_schedule`, `ncr_tracking`, `system_health`, `user_management` | Treat these 12 source cases as authoritative; add a regression assertion for exactly these 12 and do not invent another provider. |
| Configured-but-unhandled role entries | The remaining role-catalog entries include `tenant_overview`, `system_metrics`, `audit_logs`, `backup_status`, `change_requests`, all six design codes, all eight site codes, five additional QC codes, all eight client codes, and all eight subcontractor codes | These are deliberate unsupported/degraded test inputs unless a provider is proven and explicitly admitted. |
| Integration fixture catalog | `tests/Integration/SystemIntegrationTest.php` creates 19 test-only codes, including `project_overview`, `budget_summary`, `task_progress_chart`, `budget_utilization_chart`, `rfi_status_table`, `task_list_table`, `system_alerts`, `project_alerts`, `project_timeline`, `milestone_timeline`, `overall_progress`, `task_completion_progress`, `project_progress`, `budget_utilization`, `total_tasks`, `open_rfis`, `task_duration_distribution`, `budget_variance_distribution`, and `project_summary` | Do not treat these fixture codes as production providers; use them to prove supported sibling plus unsupported behavior. |
| Seeder/catalog reality | `DashboardSeeder` contains legacy widget definitions without `code`; `DatabaseSeeder` does not call it; `DashboardWidget` is tenant-owned and `code` is nullable | No migration/backfill is authorized in this plan. Empty/missing rows remain normal catalog behavior. |
| Generic dashboard path | `DashboardService::getWidgetData(string $widgetId, App\Models\User $user, ?string $projectId, array $params)` loads by ID, uses `data_source` types `static`, `query`, `metric`, and `api`, and performs raw placeholder substitution for query data | Audit only. Adapt only a proven safe subset behind the new boundary; do not expose arbitrary SQL as a provider. |
| Real-time paths | `RealTimeDashboardService` has an unrelated `(string $widgetType, string $userId)` method and an eight-code in-memory config; `DashboardRealTimeService` is injected by customization | Neither is a role-based widget provider. |
| Current dependency wiring | No dashboard provider/resolver interface or binding exists. Laravel auto-resolves `DashboardRoleBasedService(DashboardDataAggregationService, DashboardCustomizationService)`; `AppServiceProvider` has no dashboard binding | Add explicit container bindings in `AppServiceProvider` only after RED tests define the contract. |
| Current false contract | `tests/Unit/Dashboard/DashboardRoleBasedServiceTest.php` mocks `DashboardDataAggregationService` and expects `getWidgetData()` | Delete that expectation and replace the unit dependency with a narrow explicit provider/resolver boundary. |
| Current `include_data` behavior | `/widgets` always calls `getRoleBasedWidgets()` with data, then unsets `data`; root always loads data | Thread `includeData` into the role service so provider resolution is skipped before the loop for metadata-only requests. |

### Seven-role capability matrix

This matrix is binding for the implementation and acceptance tests. “Supported” means one of the exact 12 existing source switch codes is configured for that role; “unsupported” means the configured code remains eligible metadata but degrades only when data is requested. No provider is added solely to make a role have a supported result.

| Approved role | Supported configured codes | Configured codes that must remain unsupported/degraded |
|---|---|---|
| `system_admin` | `system_health`, `user_management` | `tenant_overview`, `system_metrics`, `audit_logs`, `backup_status` |
| `project_manager` | `project_overview`, `task_progress`, `rfi_status`, `budget_tracking`, `schedule_timeline`, `team_performance`, `quality_metrics`, `safety_summary` | `change_requests` |
| `design_lead` | none | `design_progress`, `drawing_status`, `submittal_tracking`, `design_reviews`, `technical_issues`, `coordination_log` |
| `site_engineer` | none | `daily_tasks`, `site_diary`, `inspection_checklist`, `weather_forecast`, `equipment_status`, `safety_alerts`, `progress_photos`, `manpower_tracking` |
| `qc_inspector` | `inspection_schedule`, `ncr_tracking`, `quality_metrics` | `defect_analysis`, `corrective_actions`, `compliance_status`, `inspection_reports`, `quality_trends` |
| `client_rep` | none | `project_summary`, `progress_report`, `milestone_status`, `budget_summary`, `quality_summary`, `schedule_status`, `client_communications`, `approval_queue` |
| `subcontractor_lead` | none | `subcontractor_progress`, `payment_status`, `work_orders`, `quality_issues`, `safety_compliance`, `resource_allocation`, `performance_metrics`, `contract_status` |

## Proposed File and Interface Structure

These are the exact files the implementation plan expects. No production file is changed by this planning session.

### New production files

- `app/Contracts/Dashboard/WidgetDataProvider.php`
  - `public function supports(string $widgetCode): bool`.
  - `public function provide(DashboardWidget $widget, WidgetDataContext $context): WidgetDataResult`.
  - Providers receive an already authenticated user/context and never format HTTP responses.
- `app/Contracts/Dashboard/WidgetDataResolver.php`
  - `public function resolve(DashboardWidget $widget, WidgetDataContext $context): WidgetDataResult`.
  - `public function canResolve(DashboardWidget $widget): bool` for capability inspection without executing data retrieval.
- `app/Services/Dashboard/WidgetDataContext.php`
  - Immutable context containing `App\Models\User $user`, `string $tenantId`, `?string $projectId`, and `array $parameters`.
  - Construction validates that tenant identity comes from the authenticated user and that an optional project context has already passed tenant/project-access authorization.
- `app/Services/Dashboard/WidgetDataResult.php`
  - Immutable result with `state` (`ready` or `degraded`), nullable `data`, nullable safe error `{code,message,retryable?}`, and provider/capability metadata needed by the response composer.
  - Factory methods: `ready(array $data)`, `unsupported()`, and `degraded(string $code, string $message, bool $retryable)`.
- `app/Services/Dashboard/DashboardWidgetDataResolver.php`
  - Concrete registry resolver implementing `WidgetDataResolver`; receives `iterable<WidgetDataProvider>` from the container.
  - Returns `DASHBOARD.WIDGET_UNSUPPORTED` without invoking a provider when no provider supports the code.
  - Catches provider/data-source failures into named safe results and structured logs; it never emits exception text.
- `app/Services/Dashboard/DashboardWidgetCatalog.php`
  - Single seven-role catalog authority and capability metadata source.
  - Exposes `roles(): array`, `forRole(string $role): array`, and `configurationForRole(string $role): array`.
- `app/Services/Dashboard/DashboardRoleValidator.php`
  - Fail-closed role gate exposing `assertSupported(string $role): void`; it runs before catalog lookup and provider resolution.
- `app/Services/Dashboard/RoleBasedWidgetProvider.php`
  - First-party provider for exactly the 12 currently handled role-based codes, moving/adapting the existing role-service calculation methods without changing their data semantics.
  - It must enforce the typed context and use tenant/project-safe queries before returning data.
- `app/Exceptions/Dashboard/UnsupportedDashboardRole.php`
  - Named fail-closed dashboard-role failure mapped to HTTP 403 and `DASHBOARD.ROLE_UNSUPPORTED`.
- `app/Exceptions/Dashboard/WidgetDataUnavailable.php` and `app/Exceptions/Dashboard/UnsupportedWidget.php`
  - Named internal domain failures used only to classify resolver outcomes; messages never cross the API boundary.

### Existing production files to modify

- `app/Services/DashboardRoleBasedService.php`: depend on `WidgetDataResolver` and `DashboardWidgetCatalog`; preserve role-summary methods; compose eligible metadata/data/degraded results; accept an `includeData` argument; remove the `DashboardDataAggregationService::getWidgetData()` call and the local generic-data switch after provider extraction.
- `app/Http/Controllers/Api/DashboardRoleBasedController.php`: pass `include_data` into the role service; preserve root default data behavior and widgets default metadata-only behavior; replace only the two GAP-052 failure responses with the repository’s stable safe error code/message and request ID, without changing global middleware/envelopes.
- `app/Services/DashboardCustomizationService.php`: consume catalog/provider capability metadata for available-widget presentation, without making customization a data provider and without changing mutation semantics.
- `app/Providers/AppServiceProvider.php`: bind `WidgetDataResolver` to `DashboardWidgetDataResolver` and register the concrete provider list; do not rely on implicit concrete resolution.
- `app/Models/DashboardWidgetDataCache.php` and, only if needed after tests prove the current key unsafe, `app/Services/DashboardService.php`: preserve or harden cache tenancy/context dimensions. No schema change is planned; a cache-key-only change must be justified by a failing isolation test.
- `tests/Unit/Dashboard/DashboardRoleBasedServiceTest.php`: remove the false aggregation mock contract and inject a narrow resolver/provider double.
- `tests/Integration/SystemIntegrationTest.php` or new `tests/Integration/GAP052DashboardWidgetContractTest.php`: replace the expected-500 discovery assertion with concrete route/container/Bearer acceptance coverage.
- `tests/Unit/Dashboard/WidgetDataResolverTest.php`: new resolver/provider registry RED/GREEN suite.
- `tests/Feature/Dashboard/DashboardApiTest.php`: modify only if the existing route assertions need the new metadata/data contract; do not broaden unrelated dashboard coverage.

## TDD Task Sequence

### Task 1: Freeze the inventory and choose the unknown-role policy

**Files:**
- Create: `tests/Unit/Dashboard/DashboardWidgetCatalogTest.php`
- Create: `app/Services/Dashboard/DashboardWidgetCatalog.php`
- Create: `app/Services/Dashboard/DashboardRoleValidator.php`
- Create: `app/Exceptions/Dashboard/UnsupportedDashboardRole.php`
- Modify: `app/Services/DashboardRoleBasedService.php`
- Modify: `app/Services/DashboardCustomizationService.php`

**Interfaces:**
- Produces `DashboardWidgetCatalog::roles(): array`, `forRole(string $role): array`, and `configurationForRole(string $role): array`.
- Produces `DashboardRoleValidator::assertSupported(string $role): void`; it throws `UnsupportedDashboardRole` for any value outside the exact seven-role set before catalog/provider execution.

- [ ] **Step 1: Write RED catalog tests.** Assert the exact seven roles, exact per-role order, no role silently maps to `client_rep`, and that the catalog exposes capability classification for every configured code (`supported`, `unsupported`, or `configuration_error`). Assert exactly the 12 currently handled codes are classified as supported only where their existing calculations are moved into a provider; all other source role entries are not silently mapped to fake data. Keep the catalog test unit-scoped; the real-route unknown-role test belongs to Task 5’s concrete integration suite.

```php
public function test_catalog_has_exactly_the_seven_approved_roles(): void
{
    self::assertSame([
        'system_admin', 'project_manager', 'design_lead', 'site_engineer',
        'qc_inspector', 'client_rep', 'subcontractor_lead',
    ], $this->catalog->roles());
}

public function test_unknown_role_fails_closed_instead_of_inheriting_client_rep_catalog(): void
{
    $this->expectException(UnsupportedDashboardRole::class);
    $this->validator->assertSupported('unknown_role');
}
```

- [ ] **Step 2: Run the focused RED command.**

Run: `./vendor/bin/phpunit tests/Unit/Dashboard/DashboardWidgetCatalogTest.php --testdox --stop-on-failure`

Expected: FAIL because the catalog class and explicit unknown-role policy do not exist.

- [ ] **Step 3: Implement the role gate and catalog.** Move the seven role configuration arrays out of `DashboardRoleBasedService` into the catalog, preserve their order and non-widget metadata, and make `DashboardRoleValidator::assertSupported()` throw `UnsupportedDashboardRole` for unknown values. Invoke the validator before any catalog lookup in both controller entry points and before provider resolution in the role service. Map that named failure to HTTP 403, safe code `DASHBOARD.ROLE_UNSUPPORTED`; do not define aliases such as `admin`, `client`, or `designer`. Update `getRoleConfiguration()` to delegate to the catalog and update customization’s available-widget metadata to consume the same catalog/capability source.

- [ ] **Step 4: Run the focused GREEN command.**

Run: `./vendor/bin/phpunit tests/Unit/Dashboard/DashboardWidgetCatalogTest.php --testdox --stop-on-failure`

Expected: PASS, with no changed production behavior outside catalog ownership and unknown-role handling.

- [ ] **Step 5: Commit the catalog slice.**

```bash
git add app/Services/Dashboard/DashboardWidgetCatalog.php app/Services/Dashboard/DashboardRoleValidator.php app/Exceptions/Dashboard/UnsupportedDashboardRole.php app/Services/DashboardRoleBasedService.php app/Services/DashboardCustomizationService.php tests/Unit/Dashboard/DashboardWidgetCatalogTest.php
git commit -m "refactor: centralize dashboard widget catalog"
```

### Task 2: Define typed provider/resolver contracts and safe result semantics

**Files:**
- Create: `app/Contracts/Dashboard/WidgetDataProvider.php`
- Create: `app/Contracts/Dashboard/WidgetDataResolver.php`
- Create: `app/Services/Dashboard/WidgetDataContext.php`
- Create: `app/Services/Dashboard/WidgetDataResult.php`
- Create: `app/Services/Dashboard/DashboardWidgetDataResolver.php`
- Create: `app/Exceptions/Dashboard/UnsupportedWidget.php`
- Create: `app/Exceptions/Dashboard/WidgetDataUnavailable.php`
- Create: `tests/Unit/Dashboard/WidgetDataResolverTest.php`

**Interfaces:**
- `WidgetDataProvider::supports(string): bool` and `provide(DashboardWidget, WidgetDataContext): WidgetDataResult`.
- `WidgetDataResolver::resolve(DashboardWidget, WidgetDataContext): WidgetDataResult` and `canResolve(DashboardWidget): bool`.

- [ ] **Step 1: Write RED resolver tests at the explicit provider boundary.** Use a narrow fake implementing `WidgetDataProvider`, not a mock of `DashboardDataAggregationService`. Cover supported dispatch, unsupported result, provider exception conversion, and `canResolve()` not executing `provide()`.

```php
public function test_unsupported_code_is_a_named_degraded_result(): void
{
    $resolver = new DashboardWidgetDataResolver([$this->providerFor('supported_code')]);
    $result = $resolver->resolve($this->widget('unknown_code'), $this->context());

    self::assertSame('degraded', $result->state());
    self::assertSame('DASHBOARD.WIDGET_UNSUPPORTED', $result->error()['code']);
    self::assertNull($result->data());
    self::assertStringNotContainsString('class', strtolower($result->error()['message']));
}

public function test_provider_failure_does_not_leak_exception_text(): void
{
    $provider = $this->throwingProvider(new RuntimeException('secret SQL/class detail'));
    $result = (new DashboardWidgetDataResolver([$provider]))
        ->resolve($this->widget('supported_code'), $this->context());

    self::assertSame('degraded', $result->state());
    self::assertNotSame('secret SQL/class detail', $result->error()['message']);
    self::assertNull($result->data());
}
```

- [ ] **Step 2: Run the resolver RED command.**

Run: `./vendor/bin/phpunit tests/Unit/Dashboard/WidgetDataResolverTest.php --testdox --stop-on-failure`

Expected: FAIL because the interfaces, context/result objects, and resolver do not exist.

- [ ] **Step 3: Implement the minimal contract and resolver.** Make `WidgetDataContext` immutable and tenant-bound to the authenticated user. Make `WidgetDataResult` serialize only safe fields. Make unsupported codes deterministic. Catch `Throwable` only inside provider dispatch, log widget code, tenant, role, request ID, and safe reason, and convert to a stable named degraded result. Do not decide HTTP status in this layer.

- [ ] **Step 4: Run the resolver GREEN command.**

Run: `./vendor/bin/phpunit tests/Unit/Dashboard/WidgetDataResolverTest.php --testdox --stop-on-failure`

Expected: PASS.

- [ ] **Step 5: Commit the contract slice.**

```bash
git add app/Contracts/Dashboard app/Services/Dashboard app/Exceptions/Dashboard tests/Unit/Dashboard/WidgetDataResolverTest.php
git commit -m "feat: add dashboard widget provider resolver contract"
```

### Task 3: Extract the currently handled calculations into the first-party provider

**Files:**
- Create: `app/Services/Dashboard/RoleBasedWidgetProvider.php`
- Modify: `app/Services/DashboardRoleBasedService.php`
- Modify: `tests/Unit/Dashboard/DashboardRoleBasedServiceTest.php`
- Modify: `tests/Unit/Dashboard/WidgetDataResolverTest.php`

**Interfaces:**
- `RoleBasedWidgetProvider` implements `WidgetDataProvider` and supports exactly the 12 source switch codes; no additional provider code is admitted by this plan.
- It consumes `WidgetDataContext` and produces `WidgetDataResult::ready(array)`.

- [ ] **Step 1: Write RED provider tests for each existing handled code family.** Use the existing dashboard fixtures and assert the current keys/values for `project_overview`, `task_progress`, `rfi_status`, `budget_tracking`, `schedule_timeline`, `team_performance`, `quality_metrics`, `safety_summary`, `inspection_schedule`, `ncr_tracking`, `system_health`, and `user_management`. Add a data-provider assertion that the provider supports exactly these 12 codes and no role-catalog-only unsupported code.

- [ ] **Step 2: Run the provider RED command.**

Run: `./vendor/bin/phpunit tests/Unit/Dashboard/DashboardRoleBasedServiceTest.php tests/Unit/Dashboard/WidgetDataResolverTest.php --testdox --stop-on-failure`

Expected: FAIL because the role service still owns the switch and the new provider is not registered.

- [ ] **Step 3: Move/adapt only the existing calculation methods.** Preserve query predicates and returned keys while adding the typed context. Do not use `DashboardDataAggregationService` for widget retrieval. Do not add generic SQL execution, external API behavior, or new role-specific calculations. Keep `DashboardDataAggregationService` injected only where role-summary methods actually use it; if it is not used after extraction, remove only that constructor dependency.

- [ ] **Step 4: Replace the false Mockery contract.** Delete `shouldReceive('getWidgetData')` from `DashboardRoleBasedServiceTest`. Inject a narrow fake resolver/provider at the explicit boundary and assert the role service asks the resolver for eligible widgets. No test may declare `DashboardDataAggregationService::getWidgetData()`.

- [ ] **Step 5: Run the provider GREEN command.**

Run: `./vendor/bin/phpunit tests/Unit/Dashboard/DashboardRoleBasedServiceTest.php tests/Unit/Dashboard/WidgetDataResolverTest.php --testdox --stop-on-failure`

Expected: PASS, with role-summary tests still calling only real role-summary methods.

- [ ] **Step 6: Commit the provider slice.**

```bash
git add app/Services/Dashboard/RoleBasedWidgetProvider.php app/Services/DashboardRoleBasedService.php tests/Unit/Dashboard/DashboardRoleBasedServiceTest.php tests/Unit/Dashboard/WidgetDataResolverTest.php
git commit -m "refactor: move role dashboard widget data behind provider"
```

### Task 4: Add real container wiring and compose safe widget outcomes

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `app/Services/DashboardRoleBasedService.php`
- Modify: `app/Http/Controllers/Api/DashboardRoleBasedController.php`
- Modify: `tests/Unit/Dashboard/DashboardRoleBasedServiceTest.php`
- Create or modify: `tests/Feature/Dashboard/DashboardApiTest.php`

**Interfaces:**
- Container resolves `WidgetDataResolver` to `DashboardWidgetDataResolver` with `RoleBasedWidgetProvider` registered.
- `DashboardRoleBasedService::getRoleBasedWidgets(App\Models\User $user, array $roleConfig, ?string $projectId = null, bool $includeData = true): array`.
- The root endpoint passes `includeData=true`; `/widgets` passes the request’s boolean, defaulting to `false`.

- [ ] **Step 1: Write RED composition tests.** Cover supported sibling plus unsupported widget, all-unsupported catalog, metadata-only unsupported catalog, empty catalog, inactive row, and role-ineligible row. Assert ordering and the outer item shape. For data requests, assert unsupported entries contain `state=degraded`, `error.code=DASHBOARD.WIDGET_UNSUPPORTED`, and `data=null`; supported siblings retain real data. For metadata-only requests, assert no provider fake records a call.

- [ ] **Step 2: Run the focused RED command.**

Run: `./vendor/bin/phpunit tests/Feature/Dashboard/DashboardApiTest.php tests/Unit/Dashboard/DashboardRoleBasedServiceTest.php --testdox --stop-on-failure`

Expected: FAIL because the resolver is not wired and the controller currently resolves data before stripping it.

- [ ] **Step 3: Wire the concrete graph.** Register the resolver and provider in `AppServiceProvider`; make `DashboardRoleBasedService` depend on `WidgetDataResolver`, not on the invented aggregation method. Compose provider results into the existing `widget`, `data`, `permissions` structure while adding only the approved per-widget `state` and `error` fields. Do not invoke the resolver at all when `includeData=false`.

- [ ] **Step 4: Update both controllers’ narrow request behavior.** Preserve route names, validation, and outer success keys. Call `DashboardRoleValidator::assertSupported($user->role)` before retrieving role configuration; map `UnsupportedDashboardRole` to HTTP 403 with `DASHBOARD.ROLE_UNSUPPORTED` and do not instantiate or invoke the catalog/provider for that request. Root remains data-bearing. `/widgets?include_data=false` is provider-independent; `/widgets?include_data=true` includes typed result state. Keep category filtering after composition. If a named resolver failure prevents safe composition, return the existing stable safe 5xx envelope with request ID; never expose exception text.

- [ ] **Step 5: Run the focused GREEN command.**

Run: `./vendor/bin/phpunit tests/Feature/Dashboard/DashboardApiTest.php tests/Unit/Dashboard/DashboardRoleBasedServiceTest.php --testdox --stop-on-failure`

Expected: PASS.

- [ ] **Step 6: Commit the composition/wiring slice.**

```bash
git add app/Providers/AppServiceProvider.php app/Services/DashboardRoleBasedService.php app/Http/Controllers/Api/DashboardRoleBasedController.php tests/Feature/Dashboard/DashboardApiTest.php tests/Unit/Dashboard/DashboardRoleBasedServiceTest.php
git commit -m "feat: wire dashboard widget resolver and degraded outcomes"
```

### Task 5: Prove tenant, project, RBAC, and cache safety before admitting generic retrieval

**Files:**
- Create: `tests/Integration/GAP052DashboardWidgetContractTest.php`
- Modify only if a test proves a defect: `app/Services/Dashboard/RoleBasedWidgetProvider.php`, `app/Services/Dashboard/DashboardWidgetDataResolver.php`, `app/Models/DashboardWidgetDataCache.php`, or `app/Services/DashboardService.php`

- [ ] **Step 1: Write RED concrete-dependency tests.** Use the existing `AuthenticationTrait` login/token path and a real `Authorization: Bearer <token>` header; do not use `actingAs()`.

```php
public function test_tenant_a_cannot_read_tenant_b_widget_or_project_data(): void
{
    $token = $this->loginAndGetToken($this->tenantAUser);
    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->withHeader('X-Tenant-ID', $this->tenantA->id)
        ->getJson('/api/v1/dashboard/role-based/widgets?include_data=true&project_id=' . $this->tenantBProject->id);

    self::assertContains($response->status(), [403, 422]);
    $response->assertJsonMissing(['tenant_b_secret' => true]);
}

public function test_metadata_only_request_does_not_resolve_a_provider(): void
{
    $this->app->instance(WidgetDataResolver::class, new RecordingResolverThatFailsIfCalled());
    $response = $this->authenticatedBearerGet('/api/v1/dashboard/role-based/widgets?include_data=false');

    $response->assertOk()->assertJsonPath('data.widgets.0.data', null);
}
```

Also cover all seven roles with explicit capability expectations: `system_admin` supports `system_health` and `user_management`; `project_manager` supports `project_overview`, `task_progress`, `rfi_status`, `budget_tracking`, `schedule_timeline`, `team_performance`, `quality_metrics`, and `safety_summary`; `qc_inspector` supports `inspection_schedule`, `ncr_tracking`, and `quality_metrics`; `design_lead`, `site_engineer`, `client_rep`, and `subcontractor_lead` have no locally handled configured code and must be tested with explicit unsupported/degraded outcomes plus metadata-only success. Also cover canonical `client_rep` → middleware `client` mapping; insufficient role 403 before provider invocation; an unsupported dashboard role returning HTTP 403 with `DASHBOARD.ROLE_UNSUPPORTED` before catalog/provider execution; inaccessible project; same code in tenant A/B; empty tenant catalog; inactive row; and role-ineligible row.

The unknown-role integration test must use a genuine login-issued Bearer token and the real service container. Give the user `role = 'unknown_role'`, grant only the route-level authentication/RBAC prerequisites needed to reach the dashboard controller, and assert the resolver/provider recorder has zero calls:

```php
public function test_unknown_dashboard_role_fails_closed_before_catalog_or_provider_execution(): void
{
    $user = $this->createTenantUser($this->tenant, ['role' => 'unknown_role'], ['dashboard.view']);
    $token = $this->loginAndGetToken($user);

    $this->app->instance(DashboardWidgetCatalog::class, new RecordingCatalogThatFailsIfCalled());
    $this->app->instance(WidgetDataResolver::class, new RecordingResolverThatFailsIfCalled());

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->withHeader('X-Tenant-ID', $this->tenant->id)
        ->getJson('/api/v1/dashboard/role-based/widgets?include_data=true');

    $response->assertStatus(403)
        ->assertJsonPath('error.code', 'DASHBOARD.ROLE_UNSUPPORTED');
}
```

- [ ] **Step 2: Run the RED integration command.**

Run: `./vendor/bin/phpunit tests/Integration/GAP052DashboardWidgetContractTest.php --testdox --stop-on-failure --group performance`

Expected: FAIL on the old 500/false dependency or on the missing safety assertion.

- [ ] **Step 3: Make the smallest safety fix supported by the failing test.** Every widget lookup remains constrained by authenticated `tenant_id`; project context must be tenant-owned and user-accessible before provider execution. Any cache read/write must include widget, user, tenant, project, and result-affecting parameters. If the existing cache model/key cannot prove this, do not use generic cache retrieval for the role provider; adapt the provider to a safe path and record the generic path as excluded.

- [ ] **Step 4: Run the GREEN integration command.**

Run: `./vendor/bin/phpunit tests/Integration/GAP052DashboardWidgetContractTest.php --testdox --stop-on-failure --group performance`

Expected: PASS with the real route, real container, real DB, real middleware, and genuine Bearer token.

- [ ] **Step 5: Commit the isolation slice.**

```bash
git add tests/Integration/GAP052DashboardWidgetContractTest.php app/Services/Dashboard/RoleBasedWidgetProvider.php app/Services/Dashboard/DashboardWidgetDataResolver.php app/Models/DashboardWidgetDataCache.php app/Services/DashboardService.php
git commit -m "test: prove dashboard widget tenant and project isolation"
```

### Task 6: Apply the narrow GAP-052 error-disclosure fix

**Files:**
- Modify: `app/Http/Controllers/Api/DashboardRoleBasedController.php`
- Modify only if the reproduced two-route path requires it: `app/Http/Middleware/ErrorEnvelopeMiddleware.php` or `app/Services/ErrorEnvelopeService.php`
- Modify: `tests/Integration/GAP052DashboardWidgetContractTest.php`

- [ ] **Step 1: Write RED disclosure tests.** Force a provider/data-source failure through the real role-based root and `/widgets` routes. Assert the response contains the stable safe code/message and request/error ID, and does not contain class names, method names, stack traces, SQL, or raw exception text. Assert structured logs receive widget code, tenant, role, request ID, and failure reason.

- [ ] **Step 2: Run the RED command.**

Run: `./vendor/bin/phpunit tests/Integration/GAP052DashboardWidgetContractTest.php --filter 'does_not_disclose|safe_error|provider_failure' --testdox --stop-on-failure --group performance`

Expected: FAIL because the current two controller catch blocks can return exception text when debug is enabled and the old undefined-method path reaches the broad envelope.

- [ ] **Step 3: Replace only the GAP-052 response detail.** Map the named dashboard provider failure to the existing safe error contract with request ID. If middleware/service adjustment is unavoidable for these two endpoints, guard it by the dashboard-specific named failure/request path and leave unrelated routes unchanged. Do not change `ErrorEnvelopeService` globally or alter unrelated status/error codes.

- [ ] **Step 4: Run the GREEN disclosure command.**

Run: `./vendor/bin/phpunit tests/Integration/GAP052DashboardWidgetContractTest.php --filter 'does_not_disclose|safe_error|provider_failure' --testdox --stop-on-failure --group performance`

Expected: PASS.

- [ ] **Step 5: Commit the narrow disclosure slice.**

```bash
git add app/Http/Controllers/Api/DashboardRoleBasedController.php app/Http/Middleware/ErrorEnvelopeMiddleware.php app/Services/ErrorEnvelopeService.php tests/Integration/GAP052DashboardWidgetContractTest.php
git commit -m "fix: hide dashboard widget provider internals"
```

### Task 7: Replace discovery expectations with the full seven-role acceptance contract

**Files:**
- Modify: `tests/Integration/SystemIntegrationTest.php`
- Modify or create: `tests/Integration/GAP052DashboardWidgetContractTest.php`
- Modify: `tests/Unit/Dashboard/DashboardRoleBasedServiceTest.php`

- [ ] **Step 1: Write/replace RED acceptance assertions.** Remove the two `assertStatus(500)` expectations that preserve the defect. Assert both root and `/widgets` under genuine Bearer authentication for all seven roles. For `system_admin`, `project_manager`, and `qc_inspector`, assert their listed provider-backed codes return real data alongside an unsupported configured code. For `design_lead`, `site_engineer`, `client_rep`, and `subcontractor_lead`, assert their configured codes are explicitly degraded with `DASHBOARD.WIDGET_UNSUPPORTED` when data is requested and that metadata-only requests succeed without provider resolution; do not invent a supported provider for these roles. Assert exact role-specific catalog, no cross-role fallback, and `include_data=false` provider independence.

- [ ] **Step 2: Run the concrete RED-to-GREEN command against the old base as an evidence checkpoint.**

Run before implementation is merged: `./vendor/bin/phpunit tests/Integration/SystemIntegrationTest.php tests/Integration/GAP052DashboardWidgetContractTest.php --group performance --testdox --stop-on-failure`

Expected on the old base: the known GAP-052 success assertions fail with the undefined-method 500. After the implementation sequence: PASS; record both outputs in the Gate-3 evidence packet.

- [ ] **Step 3: Implement only fixture/test-contract changes needed for the approved scenarios.** Preserve the existing authentication helper and tenant/RBAC setup. Do not convert the test to `actingAs()`, introduce a fake aggregation method, or broaden the performance suite.

- [ ] **Step 4: Run the focused acceptance command.**

Run: `./vendor/bin/phpunit tests/Integration/SystemIntegrationTest.php tests/Integration/GAP052DashboardWidgetContractTest.php --group performance --testdox --stop-on-failure`

Expected: PASS for all seven roles, both retained routes, provider-independent metadata requests, safe partial degradation, project isolation, and safe disclosure.

- [ ] **Step 5: Commit the acceptance slice.**

```bash
git add tests/Integration/SystemIntegrationTest.php tests/Integration/GAP052DashboardWidgetContractTest.php tests/Unit/Dashboard/DashboardRoleBasedServiceTest.php
git commit -m "test: enforce GAP-052 dashboard widget contract"
```

### Task 8: Run focused regressions and complete implementation self-review

**Files:**
- No new production files; only corrections to files listed above if a focused regression identifies an in-scope defect.

- [ ] **Step 1: Run unit and feature regressions.**

```bash
./vendor/bin/phpunit tests/Unit/Dashboard/DashboardWidgetCatalogTest.php tests/Unit/Dashboard/WidgetDataResolverTest.php tests/Unit/Dashboard/DashboardRoleBasedServiceTest.php tests/Feature/Dashboard/DashboardApiTest.php --testdox --stop-on-failure
```

- [ ] **Step 2: Run concrete integration regressions.**

```bash
./vendor/bin/phpunit tests/Integration/GAP052DashboardWidgetContractTest.php tests/Integration/SystemIntegrationTest.php --group performance --testdox --stop-on-failure
```

- [ ] **Step 3: Run route/container and static contract checks.**

```bash
php artisan route:list --except-vendor -v --path='api/v1/dashboard/role-based'
php -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $kernel=$app->make(Illuminate\Contracts\Console\Kernel::class); $kernel->bootstrap(); $r=$app->make(App\Contracts\Dashboard\WidgetDataResolver::class); echo get_class($r), PHP_EOL;'
rg -n "DashboardDataAggregationService::getWidgetData|shouldReceive\(['\"]getWidgetData|dataAggregationService->getWidgetData" app tests
```

Expected: both retained routes show the authenticated tenant/RBAC middleware stack; the container reports `DashboardWidgetDataResolver`; the final `rg` returns no false contract hit.

- [ ] **Step 4: Run repository-configured quality commands without inventing replacements.** Inspect `composer.json` and run the applicable existing commands: `composer test:fast`, `composer ssot:lint`, `./vendor/bin/phpstan analyse` if the repository’s current validation lane requires it, and the focused PHP-CS-Fixer/Pint command used by CI. Record baseline failures separately from GAP-052 failures.

- [ ] **Step 5: Run `git diff --check`, inspect the diff, and review the scope.** Confirm no schema/migration, route retirement, broad error-envelope refactor, deployment, production data, or unrelated role-summary rewrite entered the change.

- [ ] **Step 6: Commit only the verified corrections.** Use one focused commit per correction; do not squash or amend the plan commit during implementation without recording the new exact SHA in evidence.

## Gate-3 Evidence Strategy

Gate 3 is not requested by this plan. Once implementation is complete, prepare evidence for later review only:

1. Create/update `docs/owner-decisions/GAP-052/03-release.md` from `gate_status: preparing`; never change it to `awaiting_owner` in this plan or claim readiness here.
2. Record the exact implementation branch HEAD SHA and implementation-tree digest according to `docs/owner-governance/packet-schema.yml`; the digest must cover production/test/config/script changes and exclude only the active Gate-3 record as governance requires.
3. Bind evidence to the exact approved Gate-2 design and this plan path.
4. Include the inventory table’s source findings, then the post-change provider capability matrix for all 53 configured role-widget entries and all seven roles, including the fact that only three roles currently have supported configured codes.
5. Include focused command output for:
   - unit catalog/resolver/provider tests;
   - both retained routes under genuine login-issued Bearer tokens;
   - all seven roles;
   - unsupported dashboard role returning HTTP 403 with `DASHBOARD.ROLE_UNSUPPORTED` before catalog/provider execution;
   - supported sibling plus unsupported degraded widget;
   - all-unsupported safe response;
   - `include_data=false` with a resolver-call sentinel proving no provider resolution;
   - empty/inactive/ineligible catalog behavior;
   - provider failure and the safe-composition/request-level-5xx boundary;
   - tenant A/B, foreign project, inaccessible project, RBAC denial, and cache isolation;
   - static proof that the false aggregation mock contract is gone;
   - safe response body plus structured-log/request-ID evidence showing no internal disclosure.
6. Include exact baseline-vs-post-change results for the known Gate-1 RED reproduction. The old 500 is discovery evidence only; the post-change acceptance must prove the concrete graph is green.
7. Run the repository’s Gate-3 freshness/check scripts only after all implementation commits and CI checks exist. Do not merge, deploy, or request owner release approval from this implementation-plan session.

## Migration and Security Risks

- The role service currently has duplicated role configuration, permissions, and default-layout maps. The catalog extraction must not silently alter non-widget role-summary behavior.
- `DashboardService::executeQuery()` uses runtime string substitution and catches errors as empty arrays; admitting it as a provider could turn authorization/query failures into false success. The plan therefore excludes it unless a failing test and security review prove a safe parameterized, tenant-scoped path.
- Existing cache keys omit tenant and role dimensions. User ID may be sufficient for some current records, but the plan requires an isolation test before relying on it; otherwise the role provider must bypass/adapt that cache without a migration.
- `DashboardRoleBasedController` currently validates `project_id` only by existence. Provider execution must use tenant ownership and project-user access checks; broad request-validation refactoring remains out of scope.
- `DashboardDataAggregationService` has an unrelated `User` type/import defect in its role-summary methods. Do not repair it unless an in-scope role-summary test proves the implementation cannot function; otherwise record it as adjacent debt.
- The real-time dashboard catalogs and customization templates are divergent inventories. This plan unifies role-widget capability metadata only where the approved contract requires it; it does not migrate real-time semantics.
- Existing clients may depend on omitted absent rows or current outer response keys. Preserve outer compatibility and use explicit per-widget fields only for the approved degraded/data contract.

## Self-Review Against Gate-2 Requirements

- Root and `/widgets` retained: Task 4 and Task 7.
- Role-summary service remains role-summary-only: Tasks 3 and 8 static checks.
- Explicit provider/resolver owns generic retrieval: Tasks 2–4.
- Unsupported provider degrades per widget with `DASHBOARD.WIDGET_UNSUPPORTED`; siblings continue: Task 4 and Task 7.
- `include_data=false` does not resolve providers: Tasks 4, 5, and 7.
- Request-level 5xx only when safe composition is impossible: Tasks 2, 4, 6, and Gate-3 evidence item 5.
- Genuine Bearer auth and real container wiring: Tasks 5 and 7.
- Exactly seven roles and role-specific capability outcomes, without inventing providers for four roles: Tasks 1, 5, and 7.
- Unknown dashboard roles fail closed with HTTP 403 and `DASHBOARD.ROLE_UNSUPPORTED` before catalog/provider execution: Tasks 1, 4, 5, and the Gate-3 evidence checklist.
- Tenant/RBAC/project/cache isolation: Task 5.
- False Mockery contract removed: Tasks 3 and 8.
- Narrow GAP-052 internal-error disclosure fix only: Task 6 and scope checks in Task 8.
- No broad dashboard/error-envelope refactor: Global constraints, Task 6, and Task 8 scope review.
- No blind generic SQL/query promotion: Task 5 and migration/security risks.
- Gate-3 evidence strategy present without claiming readiness: dedicated section and explicit stop boundary.

## Owner-Clarified Unknown-Role Contract

The owner clarification resolves the prior ambiguity. Any authenticated user whose dashboard role is outside the exact seven approved values fails closed before catalog/provider execution with HTTP 403 and safe stable code `DASHBOARD.ROLE_UNSUPPORTED`. GAP-052 introduces no aliases or implicit normalization (`admin`, `client`, `designer`, or any other alias remain unsupported); future aliases require separate evidence and governance. No approved design requirement remains ambiguous, and the task sequence contains no contradictory supported-provider requirement for the four roles without locally handled configured codes.
