---
work_id: GAP-034
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-034/02-design.md
---

# GAP-034 — Export Tenant Isolation: Gate 2 Design

**Status:** Gate 2 design awaiting Owner decision. Gate 1 is approved. Gate 3 is not started; implementation, merge and release are not authorized.

**Objective:** An authenticated request operating as Tenant A can never cause either legacy bulk export endpoint to emit Tenant B Task/Project records or Tenant B-derived related/aggregate data, regardless of caller-supplied IDs, filters or requested format.

**Endpoints and formats:** `POST /tasks/bulk/export` and `POST /projects/bulk/export`; CSV, Excel and JSON. Isolation is applied to data selection before format generation, so no writer can bypass it.

---

## 1. Verified baseline

### 1.1 Middleware establishes two tenant representations

`TenantIsolationMiddleware` authenticates the user, compares optional `X-Tenant-ID` with `user.tenant_id`, verifies the Tenant exists, then sets both:

```php
app()->instance('current_tenant_id', $tenantId);
$request->attributes->set('tenant_id', $tenantId);
```

The export routes execute behind `auth:sanctum`, `tenant.isolation` and `rbac`. Middleware currently rejects a mismatched header with `TENANT_INVALID`/403 and missing/unresolvable tenant context before the controller.

### 1.2 Export queries are unscoped

`ExportController` currently builds `Task::with(['project', 'assignments'])` and `Project::with(['tasks'])`, applies caller IDs/filters, then calls `get()`. Neither base query has a tenant predicate. Query construction occurs before CSV/Excel/JSON selection.

### 1.3 Tenant IDs are strings

`Src\CoreProject\Models\Task` and `Project` use non-incrementing string keys. `Project` documents `tenant_id` as ULID/string. Its optional local scope currently accepts `int $tenantId`, an incompatible contract:

```php
public function scopeForTenant($query, int $tenantId)
```

The scope is opt-in and unused by `ExportController`.

### 1.4 Related-data and aggregate surfaces

- Task CSV emits `$task->project->name`; JSON serializes loaded relations.
- Current Project export loads `tasks`; JSON can serialize those tasks.
- GAP-010b's approved bounded-memory CSV design replaces Project task hydration with `withCount()` and removes the unused Task `assignments` eager-load.
- `task_assignments` has tenant data in the schema even though the current model does not expose a tenant scope.

---

## 2. Threat model and invariant

### 2.1 Adversary capabilities

Assume an authenticated/authorized Tenant A caller can supply arbitrary:

- `task_ids` or `project_ids`, including valid Tenant B ULIDs;
- task filters, especially `filters.project_id`;
- `format=csv|excel|json`;
- body/query/header fields resembling tenant identifiers.

Also assume malformed historical data can violate logical tenant consistency: Task A → Project B, Task B → Project A, or an assignment linked across tenant boundaries.

### 2.2 Security invariant

Every exported primary row, eager-loaded relation and aggregate contribution must independently satisfy `tenant_id = trustedTenantId`. The database query performs this filtering. Retrieving cross-tenant records and discarding them in PHP is prohibited.

### 2.3 Non-goals

This design does not change who may reach the routes. RBAC, route permissions and role membership are separate concerns. It does not create a global tenant scope or repair tenant behavior application-wide.

---

## 3. Trusted tenant source — settled

### 3.1 Canonical source

Use only the request attribute:

```php
$request->attributes->get('tenant_id')
```

This is authoritative because `tenant.isolation` writes it only after authentication, header/user comparison and Tenant existence verification, on the same Request object passed to the controller. It avoids duplicating middleware selection policy in the export controller.

### 3.2 Inputs explicitly not trusted

Do not use request body/query `tenant_id`, caller filters, supplied record IDs or any record's own tenant as proof of caller tenancy. Do not fall back to caller-supplied values.

`current_tenant_id` remains an application context established by the same middleware and may support other repository code, but GAP-034 deliberately selects the request attribute as the single export-controller source. Having one source prevents precedence drift between two context representations.

### 3.3 Fail-closed behavior

Resolve and cast the attribute to string before creating either model query. Empty/missing context raises an error before database selection or output creation. It must never produce an unscoped query, a success envelope or a downloadable artifact.

Because the routes already require `tenant.isolation`, this is defense in depth for direct-controller tests/misconfiguration; it does not change the normal middleware-visible API. The existing export failure envelope/status may carry the failure. No new public error taxonomy is introduced by GAP-034.

---

## 4. Query construction — tenant predicate first

### 4.1 Required composition

Both resources follow:

```text
trusted tenant predicate
AND optional caller ID predicate
AND optional business filters
```

The base builder is tenant-scoped before evaluating IDs or filters. Every later condition narrows that builder; none may replace, OR around or post-filter the tenant predicate.

### 4.2 Task base query

Semantics equivalent to:

```php
Task::query()
    ->where('tenant_id', $trustedTenantId)
```

Then add `whereIn('id', $taskIds)`, status, priority and `project_id` conditions. `filters.project_id` is not treated as tenant evidence; a Tenant B project ULID combined with the Task A tenant predicate returns no Tenant B task.

### 4.3 Project base query

Semantics equivalent to:

```php
Project::query()
    ->where('tenant_id', $trustedTenantId)
```

Then add `whereIn('id', $projectIds)` if supplied.

Do not call `Src\CoreProject\Models\Project::forTenant()` under GAP-034 because its `int` contract conflicts with string/ULID tenant IDs. Do not repair that model scope in this work item: explicit string-safe predicates in the two export paths are sufficient and keep scope bounded.

### 4.4 Unauthorized ID behavior — settled

- No IDs: all and only current-tenant records matching business filters.
- Tenant-B-only IDs: zero exported rows.
- Mixed A+B IDs: only A rows.

Filtering is silent. Do not return per-ID 403/404 or disclose which rejected ID exists, avoiding a cross-tenant existence oracle. Existing success/empty-export behavior is preserved where the writer supports it.

---

## 5. Related-data isolation

### 5.1 Task → Project

The `project` eager load must independently constrain Project `tenant_id` to the trusted tenant. A malformed Task A → Project B reference therefore resolves to no loaded Project. CSV retains its existing fallback (`N/A`); JSON must not serialize Project B.

This is an eager-load query constraint, not post-retrieval masking. The base Task predicate and Project relation predicate are both required.

### 5.2 Task assignments during composition

GAP-010b owns removal of the unused `assignments` eager-load. In the final combined design it is absent. If GAP-034 is verified temporarily against a branch where assignments are still loaded for JSON, that eager load must be constrained by `task_assignments.tenant_id = trustedTenantId`; no cross-tenant assignment may enter writer input. GAP-034 does not otherwise redesign assignments or retain the eager load.

### 5.3 Project → Task data

Any Task relation retained for JSON/current-format compatibility must add `tasks.tenant_id = trustedTenantId`. A malformed Task B → Project A reference is excluded from serialized Project relations.

No relation constraint may rely only on the parent Project tenant. Child Task tenant is checked independently.

---

## 6. Aggregate isolation and GAP-010b composition

GAP-010b's approved Project CSV design uses database-side `withCount()` to avoid hydrating Task models. GAP-034 composes by adding the trusted tenant predicate inside every Task aggregate closure:

```php
Project::query()
    ->where('tenant_id', $trustedTenantId)
    ->withCount([
        'tasks' => fn ($query) => $query
            ->where('tenant_id', $trustedTenantId),
        'tasks as completed_tasks_count' => fn ($query) => $query
            ->where('tenant_id', $trustedTenantId)
            ->where('status', 'completed'),
    ]);
```

Thus a Tenant B Task incorrectly referencing Project A contributes to neither total nor completed count. This preserves GAP-010b's bounded-memory property: counts remain database aggregates and no Task model is instantiated.

GAP-034 does not reopen chunking, CSV streaming, tags serialization, formula mitigation, explicit `fputcsv()`, atomic publication or response-row counting.

---

## 7. Format-independent isolation

Tenant resolution and base query construction occur before dispatch to CSV, Excel or JSON generation. All formats consume only the tenant-scoped selected dataset/iterator.

Format-specific extensions may choose relations/aggregates needed by that format, but each extension must preserve the base tenant predicate and constrain every included relation/aggregate as specified in §§5–6. A format handler may not issue an independent unscoped Task/Project query.

Current Excel generator defects and format fidelity are outside GAP-034. Tests may instrument writer input/query results so the security matrix is enforceable without claiming unrelated Excel correctness. Regardless of writer success, no cross-tenant record may be selected or passed to a writer.

---

## 8. Failure and compatibility behavior

### 8.1 Missing trusted context

Fail before query/output. No partial or successful artifact. Do not fall back to an untrusted source.

### 8.2 Middleware behavior

Existing missing tenant, unknown tenant and header/user mismatch responses remain owned by `tenant.isolation`; GAP-034 neither duplicates nor weakens them.

### 8.3 API compatibility

Endpoint paths, methods, accepted fields/formats, RBAC behavior and response envelope remain unchanged. The intentional security change is reduced visibility: cross-tenant IDs no longer return cross-tenant data. Silent filtering avoids a new existence oracle.

### 8.4 Rollback

No schema, package or data migration. GAP-034 can be reverted as controller/query/test changes through normal git history. Release remains blocked until both GAP-034 and GAP-010b are implemented and verified, so rollback of either must prevent route restoration rather than expose the other work item alone.

---

## 9. Alternatives evaluated

### 9.1 Chosen: explicit predicates in export query paths

Smallest safe boundary; string/ULID-safe; directly testable; no application-wide behavior change. Relation and aggregate predicates remain visible beside the export query.

### 9.2 Rejected: use/fix `Project::scopeForTenant()` globally

The current `int` signature is incompatible. Repairing model-wide scope semantics is unnecessary for two endpoints and could affect unrelated callers. It may be a separate work item if broader evidence requires it.

### 9.3 Rejected: global model tenant scope / TenantScope rollout

Would change every Task/Project query across the application, including console/background/admin behavior. That is a material architecture/security scope expansion requiring separate Owner approval.

### 9.4 Rejected: post-retrieval filtering

Cross-tenant rows would already be retrieved/hydrated, related data could leak, memory cost grows and future writers might bypass masking. It violates the query-level invariant.

---

## 10. Required regression design

Tests must inspect emitted logical records/writer inputs, not only SQL strings.

1. Task A+B, no IDs → only A.
2. A supplies Task B ID → no B row/B-derived data.
3. Mixed Task A+B IDs → only A.
4. `filters.project_id = Project B` → cannot escape Task tenant predicate.
5. Task A → Project B malformed relation → Project B name/data absent; CSV fallback is `N/A`.
6. Projects A+B, no IDs → only A.
7. A supplies Project B ID → no B project.
8. Mixed Project A+B IDs → only A.
9. Task B → Project A malformed row → excluded from total/completed aggregates and serialized Task relation.
10. Parameterized CSV/Excel/JSON requests → tenant-scoped selection/writer input for each; no format-specific query bypass.
11. Body/query/header-like tenant override input → request attribute Tenant A remains authoritative.
12. Missing request tenant attribute → fail closed before any Task/Project query or artifact.
13. Header/user mismatch → existing middleware 403 behavior unchanged.
14. If assignments remain during isolated GAP-034 verification, Assignment B linked to Task A is absent from writer input; final GAP-010b composition removes the eager load.

Use real string/ULID tenant and record IDs. Include deliberately inconsistent rows using direct database insertion or a narrowly controlled fixture where model protections would otherwise prevent the state. Verify zero Tenant B identifiers/names/aggregate influence in parsed output or captured writer input.

---

## 11. Acceptance criteria

1. Trusted tenant is sourced only from middleware-established request attribute and cast as string.
2. Missing/empty trusted context fails closed before query/output.
3. Both base queries contain mandatory tenant predicates before caller conditions.
4. IDs and filters only narrow the tenant-scoped builder.
5. Task Project relation cannot expose a different tenant.
6. Project Task relations and all Task aggregates independently enforce tenant.
7. Unauthorized IDs are silently excluded without existence disclosure.
8. CSV/Excel/JSON share the same isolation boundary.
9. GAP-010b bounded-memory aggregate design remains intact with tenant-constrained closures.
10. No RBAC/global-scope/model-wide scope/schema/dependency change is introduced.
11. All §10 regressions pass in a future authorized implementation.
12. Neither export route is released until both GAP-034 and GAP-010b pass their own governance and verification.

---

## 12. Scope exclusions and governance boundary

Excluded: global model scopes, broad TenantScope rollout, `scopeForTenant()` repair, RBAC/13-role changes, viewer/client policy, migrations, packages, tenant-ID migration, route restoration, GAP-010b implementation and unrelated export controllers.

If implementation evidence shows explicit export-path predicates cannot enforce this design without changing a model-wide/public contract, work stops for a Gate 2 revision. Any separate RBAC flaw or additional tenant surface is recorded and routed as its own security work item.

**Current authority:** Gate 2 awaiting Owner. Implementation authorized: NO. Gate 3: NOT STARTED. Merge/release authorized: NO.
