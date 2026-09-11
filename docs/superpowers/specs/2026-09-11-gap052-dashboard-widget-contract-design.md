---
work_id: GAP-052
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-052/02-design.md
---

# GAP-052 Gate 2 Design — Role-Based Dashboard Root/Widgets Contract

**Status:** Gate 2 awaiting owner decision  
**Work ID:** GAP-052  
**Canonical design base:** `26f8579de4782474bb99325f9d2ec23b7c4b3066`  
**Scope:** architecture/design only; no production code, test code, or implementation plan

## 1. Decision boundary and evidence

Gate 1 evidence is the ground truth for the incident. Current main was independently inspected at the exact canonical base above. The active v1 routes are authenticated, tenant-isolated, RBAC-protected surfaces:

- `GET /api/v1/dashboard/role-based`
- `GET /api/v1/dashboard/role-based/widgets`

The root and widgets controllers call `DashboardRoleBasedService::getRoleBasedWidgets()`. An active same-tenant widget whose configured code is not one of the local switch cases reaches a call to `DashboardDataAggregationService::getWidgetData()`, which does not exist. The concrete call throws `\Error`, bypassing `catch (\Exception)` and producing HTTP 500. Gate 1 also established that the failing `client_rep` path is reached using genuine login, Bearer transport, canonical RBAC, and the concrete dependency graph.

Source search found no first-party UI caller, but external callers and production row distribution are unknown. The route is documented, active, and covered by integration consumers; absence of a repository UI caller is not sufficient grounds for retirement.

## 2. Supported-surface decision

**Retain both root and widgets as supported product API surfaces.** They are mounted under the canonical v1 authenticated route group, documented as dashboard APIs, and have role-oriented consumers/tests. They should not be retired solely because current first-party UI usage is absent from the repository.

The older duplicate role-based block that is explicitly disabled is not an additional supported surface. `/api/zena/dashboard/widgets`-style residue is not evidence for expanding this contract.

Compatibility requirement: preserve the route paths and successful response shape at the outer level (`success`, `data`, `meta`) unless a separate API-version/deprecation decision is made. The exact widget data payload becomes the subject of the implementation acceptance contract below.

## 3. Alternatives considered

### A. Rewire the fallback to `DashboardService`

Replace the bad receiver with the existing `DashboardService::getWidgetData($widgetId, User, ?projectId, params)` contract.

**Advantages:** smallest change; an existing method already has the matching first three arguments; it already understands widget `data_source`, cache, role availability, and a project-overview fallback.

**Risks:** it couples a role-oriented orchestrator to a broad compatibility service; the current generic query path performs string substitution and does not by itself establish the full tenant/project authorization invariant; it leaves duplicate widget catalogs and role-specific switch logic; the real-time service remains a similarly named competing contract.

### B. Introduce an explicit widget-data provider/resolver contract — recommended

Define one dashboard-domain contract for resolving a widget definition plus request context (`tenant`, authenticated user, role, optional authorized project context, parameters) into a typed widget result. A resolver selects a concrete provider by stable widget code/data-source kind. Existing `DashboardService` retrieval behavior is extracted or adapted behind this contract for compatibility; `DashboardDataAggregationService` remains responsible for role-wide summary aggregates, not individual widget retrieval. `RealTimeDashboardService` remains a transport/cache/broadcast concern and is not a drop-in provider.

**Advantages:** makes ownership explicit; permits role-specific providers and data-source-backed providers without another switch/fallback mismatch; gives one place to enforce tenant/RBAC/project scope, unsupported-code policy, caching, and stable errors; supports incremental compatibility with existing widget routes.

**Risks:** more design and wiring than a one-line rewire; provider registry and catalog migration must be kept synchronized; existing generic query behavior needs a security review before being admitted as a provider.

### C. Retire or reject the unsupported role-based widget paths

Deprecate the root/widgets routes and move callers to the generic dashboard/customization APIs, or explicitly reject these paths with a governed 410/404 after caller and production-row analysis.

**Advantages:** removes the ambiguous role catalog and failing code path; avoids inventing a new provider abstraction.

**Risks:** breaks documented active API consumers without known volume; does not solve the already-supported role-based dashboard workflow; requires deprecation headers, migration documentation, telemetry, and a business decision based on actual caller evidence. Not justified by source-only absence of first-party callers.

## 4. Recommendation and ownership contract

Choose **B**.

The authoritative owner of generic widget-data retrieval should be an explicit dashboard widget-data provider/resolver boundary. The role-based service owns orchestration: role configuration selection, widget eligibility, ordering, response composition, partial/degraded widget outcomes, and project-context handoff. It must not own every widget query and must not call a role-summary service for generic data. A provider failure is a per-widget outcome whenever the outer response can be safely composed; request-level 5xx is reserved for cases where safe composition itself is impossible.

Responsibilities:

| Component | Long-term responsibility |
|---|---|
| `DashboardRoleBasedService` | Resolve the user’s canonical role, select the authoritative catalog, enforce widget eligibility, pass an already-authorized context to the provider, and compose root/widgets responses. |
| `DashboardDataAggregationService` | Produce role-wide aggregates for the seven role summaries. It does not own `getWidgetData()` and must not be made the generic fallback merely because the missing method caused the incident. |
| Widget-data provider/resolver | Authoritatively resolve supported widget codes/data-source kinds and return a stable widget result or named per-widget unsupported/data failure. It owns provider dispatch, not HTTP response formatting or the decision to discard safe partial results. |
| `DashboardService` | Preserve existing generic dashboard/customization compatibility. Its current per-widget retrieval logic is the initial compatibility implementation to extract/adapt behind the explicit provider boundary, after tenant/query/cache review. |
| `RealTimeDashboardService` | Keep real-time cache/analytics semantics separate. Its `(widgetType, userId)` API is incompatible with the role-based provider context and is not a fallback. |
| `DashboardCustomizationService` | Own layout/configuration mutations and available-widget presentation. It must consume the same authoritative catalog/provider capability metadata, not define a second data contract. |
| Controllers | Validate request syntax, invoke the role service, and map named domain failures to the existing safe error envelope. They do not select providers or query data. |
| Config/catalog | One authoritative role-to-widget catalog and provider capability mapping. Existing duplicated arrays are migration inputs, not parallel authorities. |

The explicit contract must carry `User` as `App\Models\User`, tenant identity, optional project context, and request parameters. It must return a stable result shape separating metadata from data and must never require callers to know provider classes.

## 5. Unsupported widget behavior

The contract distinguishes catalog availability from data retrieval and uses per-widget outcomes whenever the dashboard can still be represented safely:

1. No active widget row for a configured code, or the row is not eligible for the authenticated role: omit it from the returned widget list. This is normal catalog availability behavior.
2. An active, eligible row has a code with no registered provider: when `include_data=true`, retain that widget’s position and return a safe per-widget degraded/error result with `state: degraded`, `error.code: DASHBOARD.WIDGET_UNSUPPORTED`, a generic user-safe message, and no `data` (or an explicit `data: null`). Do not fail the whole root/widgets request merely because one widget is unsupported; return the supported widgets and their real data in the same safe response. If all configured widgets are unsupported, a safe response containing only degraded widget entries is still preferable to a request-level 5xx.
3. When `include_data=false`, do not invoke or require a provider. Return the eligible catalog/metadata successfully even if a configured code has no provider; provider absence must not fail this metadata-only request and must not manufacture data.
4. A provider exists but its data source is temporarily unavailable: return a per-widget degraded/error result when the rest of the response can be safely composed, using the named stable data-source error and retryability metadata without leaking internals. Use a request-level 5xx/503 only when the request cannot be safely completed or represented at all; never convert an unsupported code into fake data or silently into an empty success item.

For every degraded widget, log the widget code, tenant, role, request ID, and provider-resolution/data-source reason in structured logs. Do not place class names, method names, stack traces, or raw exception text in the response. The outer response retains the existing successful shape (`success`, `data`, `meta`) for safe partial results; the per-widget result separates `state`, `data`, and `error` so clients can distinguish real data from degradation.

This policy makes bad configuration observable and deterministic while preserving empty-catalog success for tenants with no matching active widgets.

## 6. Role, tenant, and RBAC invariants

The authoritative role catalog must cover exactly these seven configured roles: `system_admin`, `project_manager`, `design_lead`, `site_engineer`, `qc_inspector`, `client_rep`, and `subcontractor_lead`. Each role must have an explicit capability result for every configured widget: supported provider, intentionally unavailable, or configuration error. Unknown user-role values must not silently inherit `client_rep`; they require the repository’s defined role-normalization/default policy to be made explicit before implementation.

For every role and provider:

- Every widget definition query is constrained to the authenticated tenant; global/system authority does not bypass the route’s tenant boundary unless a separately governed system-wide route exists.
- RBAC is enforced before provider execution using the canonical middleware role mapping and widget permissions. A valid Bearer token with insufficient role receives 403, never a provider result or 500.
- An optional `project_id` is accepted only when the project belongs to the authenticated tenant and the user has the role-appropriate project access. A foreign or inaccessible project is denied/treated as unavailable according to the existing API policy, never queried by a provider.
- User, tenant, project, widget, cache, and broadcast keys remain tenant-safe. Cached data must include all authorization/context dimensions that affect the result.
- `system_admin` remains tenant-scoped on these routes because the current route stack is tenant-isolated; a true cross-tenant view requires a separate explicitly authorized surface.
- Empty tenant catalogs return a valid empty widget list and do not invoke a provider.

## 7. Test strategy required for Gate 3

The implementation must replace the current false contract, not merely make its mock pass.

Required concrete-dependency integration coverage:

- Use the real route, controller, service container wiring, database, `auth:sanctum`, and a genuine `Authorization: Bearer` token issued through the login/token path. Do not use `actingAs()` or a mock of `DashboardDataAggregationService` for the end-to-end contract.
- Exercise both root and widgets endpoints with the same qualifying active widget rows and `include_data=true/false`; `include_data=false` must not execute or require an absent provider and must still return the eligible catalog/metadata successfully.
- Exercise all seven roles with at least one handled/supported widget and one unhandled/unsupported configured code. Assert the role-specific catalog and provider result, not just HTTP 200.
- Assert the concrete dependency graph: no test may declare `getWidgetData()` on `DashboardDataAggregationService`; the real resolver/provider must be bound and invoked.
- Assert unsupported-code behavior as a safe partial response: one unsupported widget does not fail supported siblings, each unsupported entry is explicitly degraded with `DASHBOARD.WIDGET_UNSUPPORTED`, has no fake data/internal details, and an all-unsupported catalog remains safely representable. Assert `include_data=false` succeeds without provider resolution. Also cover empty catalog, inactive widget, role-ineligible widget, provider failure, the request-level 5xx boundary when safe composition is impossible, and stable safe error envelopes.
- Assert tenant A cannot read tenant B’s widget definition, data, project, or cache; assert foreign/inaccessible project context is not executed.
- Assert RBAC denial with a genuine token and canonical role pivot, including the `client_rep` → `client` middleware mapping where applicable.
- Keep focused unit tests for the resolver/provider registry, but use narrow fakes only at the explicit provider boundary and verify the concrete integration separately.

The quarantined/current test that expects the known 500 is discovery evidence, not the final acceptance contract. It must be replaced or superseded only during implementation, with the production dependency graph covered first.

## 8. Error-disclosure scope

Fix the exact GAP-052 disclosure in the implementation of this gap: the two affected dashboard paths must return the stable safe error code/message and retain diagnostic detail only in structured logs/error ID. This is required because the exposed undefined-method text is directly coupled to the reproduced failure.

Do not undertake broad error-envelope refactoring in GAP-052. A separate gap should govern global `ErrorEnvelopeService`/middleware hardening, consistency across unrelated routes, and any API-wide envelope migration.

## 9. Migration and compatibility risks

- Existing role arrays contain many codes not implemented by the current switch; a catalog/provider inventory is a prerequisite to implementation.
- `DashboardService`, `DashboardCustomizationService`, role-based configuration, seeders, docs, and tests contain overlapping or divergent widget definitions. Migration must establish one source and preserve response compatibility deliberately.
- `DashboardService`’s generic query data source performs runtime SQL substitution and currently has incomplete authorization semantics; it must not be promoted blindly to the authoritative provider.
- Existing clients may depend on omission of absent rows, current outer response keys, or current error status. Route telemetry and production data remain unknown; deprecation/compatibility decisions must be evidence-based.
- The current aggregation role methods use an incorrectly resolved `User` type in the inspected source and contain mock/stub calculations. This is adjacent contract debt, not a reason to broaden GAP-052 into a role-summary rewrite.
- The current root controller’s validation uses a generic `exists:projects,id`; implementation must preserve tenant/RBAC invariants without broad validation refactoring.

## 10. Gate-3 acceptance boundary

Gate 2 approval would authorize a separate implementation plan. Gate 3 must not be requested until the planned change demonstrates: both retained routes work for all seven roles under genuine Bearer authentication; supported providers return tenant/RBAC-safe data; one unsupported widget degrades without failing safe siblings; every degraded entry carries `DASHBOARD.WIDGET_UNSUPPORTED` with no fake data or internal details; `include_data=false` succeeds without provider resolution; request-level 5xx occurs only when safe composition is impossible; the false mock contract is removed; concrete-dependency integration coverage is green; and only the narrow GAP-052 error-disclosure fix is included.

No implementation plan is included in this document.
