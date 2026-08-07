---
work_id: GAP-034
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-034/02-design.md
---

# GAP-034 — Export Tenant Isolation: Gate 2 Design

**Status:** Gate 2 design awaiting Owner decision. Gate 1 is approved. Gate 3 is not started; implementation, merge and release are not authorized.

**Objective:** An authenticated request operating as Tenant A can never cause either legacy bulk export endpoint to emit Tenant B Task/Project records, Tenant B-derived related/aggregate data, or unverified Tenant B-owned scalar reference identifiers, regardless of caller-supplied IDs, filters or requested format.

**Review history:** Round 1 reviewed head `96dc086283e021e007d627d62c0061ecb330f2ab` and returned **CHANGES REQUESTED — Task scalar foreign-reference leakage**; the Task-side direction is now accepted and unchanged. Round 2 reviewed head `08d48bd9c9e02712365f0ad5248c374aa8463d00` and returned **CHANGES REQUESTED — Project scalar foreign-reference inventory/projection incomplete**. This revision closes the Project-side finding and re-presents Gate 2; it does not record approval.

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

### 1.5 Scalar-reference and serialization surfaces

Current Task CSV emits the Project name and constructs `User <assignee_id>` directly from the Task row. Current Task JSON passes `$tasks->toArray()` to the writer. `Src\CoreProject\Models\Task` has no `$hidden` allowlist, so Eloquent serialization includes database attributes beyond `$fillable`, plus loaded relations.

Repository schema/model evidence identifies reference-bearing Task attributes: `project_id`, `component_id`, `phase_id`, `assignee_id`, `assigned_to`, `dependencies_json`, `parent_id`, `created_by`, `updated_by`, `watchers`, `work_instance_id` and `work_instance_step_id`. Project, Component, ProjectPhase, User, Task, WorkInstance and WorkInstanceStep are tenant- or project-owned. These scalar values remain visible even when an eager-loaded relation is absent; relation scoping alone is therefore insufficient.

### 1.6 Project schema and metadata evidence

The full current `projects` migration chain adds `tenant_id`, `client_id`, `pm_id`, `created_by` and `template_id` alongside business fields. Foreign-key migrations bind `client_id`, `pm_id` and `created_by` to `users`; `App\Models\Project` also exposes `client()` and manager relationships through `client_id`/`pm_id`. Users carry `tenant_id`. `template_id` is added as a nullable string but has no Project FK, model relationship or repository usage establishing a target/tenant contract.

`tags` is validated as an array of bounded strings in Project requests and factories generate ordinary word labels. `settings` is validated as an array; the Project factory evidence contains boolean business flags (`notifications`, `auto_assign`, `require_approval`). No repository evidence gives either field an entity-ID-bearing structure. Under GAP-034 they are ordinary business metadata, not reference containers. A future schema/contract that places identifiers inside them requires a new review before those values can be allowlisted.

---

## 2. Threat model and invariant

### 2.1 Adversary capabilities

Assume an authenticated/authorized Tenant A caller can supply arbitrary:

- `task_ids` or `project_ids`, including valid Tenant B ULIDs;
- task filters, especially `filters.project_id`;
- `format=csv|excel|json`;
- body/query/header fields resembling tenant identifiers.

Also assume malformed historical data can violate logical tenant consistency: Task A → Project B, Task B → Project A, an assignment linked across tenant boundaries, or any scalar Task reference pointing to a foreign/missing target.

### 2.2 Security invariant

Tenant isolation covers four surfaces: primary records, loaded related records, aggregate contributions, and tenant-owned identifiers/reference values emitted from a primary record. Every primary/related row and aggregate contribution must independently satisfy the trusted-tenant boundary. No unverified tenant-owned foreign identifier may enter CSV/Excel/JSON writer input.

Primary-row and mandatory structural eligibility filtering occurs in the database query. Optional references are resolved through tenant-scoped lookups and converted into an explicit safe projection before writer dispatch; unrestricted model serialization is prohibited. Retrieving a foreign related entity or its attributes and merely hiding it after serialization is prohibited.

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
    ->whereHas('project', fn ($query) => $query
        ->where('tenant_id', $trustedTenantId))
```

The `whereHas`/equivalent correlated `EXISTS` is mandatory Task eligibility, not only eager-load decoration. It requires the Task's structural Project to exist and have the trusted tenant before caller filters or writer selection. Thus Task A → Project B and Task A → missing/stale Project are excluded across every format. No global scope is introduced.

Then add `whereIn('id', $taskIds)`, status, priority and `project_id` conditions. `filters.project_id` is not treated as tenant evidence; a Tenant B project ULID combined with both eligibility predicates returns no Task.

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

The base Task query must enforce the query-level Project eligibility rule in §4.2. The `project` eager load must also independently constrain Project `tenant_id` to the trusted tenant. A malformed Task A → Project B or missing/stale Project excludes the Task itself; it does not produce an exported Task with `N/A`.

The base Task predicate, Project `EXISTS` eligibility and eager-load predicate are all required. The Project ULID, name and attributes must appear nowhere in writer input/output for an ineligible Task.

### 5.2 Task assignments during composition

GAP-010b owns removal of the unused `assignments` eager-load. In the final combined design it is absent. If GAP-034 is verified temporarily against a branch where assignments are still loaded for JSON, that eager load must be constrained by `task_assignments.tenant_id = trustedTenantId`; no cross-tenant assignment may enter writer input. GAP-034 does not otherwise redesign assignments or retain the eager load.

### 5.3 Project → Task data

Any Task relation retained for JSON/current-format compatibility must add `tasks.tenant_id = trustedTenantId`. A malformed Task B → Project A reference is excluded from serialized Project relations.

No relation constraint may rely only on the parent Project tenant. Child Task tenant is checked independently.

### 5.4 Emitted Task reference inventory and settled behavior

Format exposure below describes the actual current writer: Task CSV has explicit columns; the current Excel method receives the same Task collection but is incomplete and emits no usable content; JSON uses unrestricted `toArray()`. Future Excel writer input must use the same safe projection as CSV/JSON even though Excel fidelity remains GAP-010b territory.

| Task field | Repository-backed target/ownership | Current CSV | Current Excel | Current JSON | Current-tenant target | Foreign target | Missing/stale target |
|---|---|---:|---:|---:|---|---|---|
| `project_id` | `projects`; directly tenant-owned | indirectly, Project name | collection received | yes | retain ID/name | exclude Task at query eligibility | exclude Task |
| `assignee_id` | `users`; `users.tenant_id` | yes, raw `User <id>` | collection received | yes | retain ID; current label behavior may use it | Task remains; CSV/Excel `Unassigned`, JSON `null` | same fallback/null |
| `component_id` | `components`; project-owned through `components.project_id` | no | collection received | yes | retain only if Component belongs same eligible Project | JSON `null` | JSON `null` |
| `phase_id` | `project_phases`; project-owned through `project_phases.project_id` | no | collection received | yes | retain only if Phase belongs same eligible Project | JSON `null` | JSON `null` |
| `dependencies_json` | array of `tasks.id`; Task is tenant-owned and dependency rules are project-local | no | collection received | yes | retain each ID only if dependency Task is trusted-tenant, Project-eligible and belongs same Project | remove offending ID | remove stale ID; preserve array shape |
| `assigned_to` | `users`; optional legacy/current assignment reference | no | collection received | yes | retain ID | JSON `null` | JSON `null` |
| `created_by`, `updated_by` | `users`; audit actor references | no | collection received | yes | retain ID | JSON `null` | JSON `null` |
| `watchers` | schema JSON; controller validation treats entries as `users.id`; Task model has no array cast | no | collection received | yes, currently raw JSON scalar | decode and retain only verified same-tenant User IDs, then preserve the current JSON field value type | remove offending IDs | remove stale IDs; preserve current value type |
| `parent_id` | `tasks.id`; structural Task parent | no | collection received | yes | retain only if parent is trusted-tenant, Project-eligible and belongs same Project | JSON `null` | JSON `null` |
| `work_instance_id` | `work_instances`; directly tenant-owned and project-linked | no | collection received | yes | retain only if tenant matches and Project matches eligible Task Project | JSON `null` | JSON `null` |
| `work_instance_step_id` | `work_instance_steps`; directly tenant-owned and belongs to WorkInstance | no | collection received | yes | retain only if step tenant matches and its WorkInstance is the Task's validated instance/project | JSON `null` | JSON `null` |
| `tenant_id` | Task's own tenant boundary | no | collection received | yes | emit trusted tenant value only | impossible after base eligibility | impossible after base eligibility |

Task `id` is the eligible primary record identifier, not a foreign reference, and is safe after Task/Project eligibility. Non-reference fields such as name, dates, status, priority, progress, tags, visibility and timestamps remain in the projection. `conditional_tag` is a business tag rather than an entity identifier. No repository evidence establishes `title`, `spent_hours`, `order`, `deleted_at` or similar scalar values as foreign references.

### 5.5 Optional-reference validation mechanism

Optional references do not make the Task ineligible. Before building writer input, collect candidate IDs per bounded export chunk and resolve only matching targets through tenant-scoped/project-consistent queries. Build allowsets from those verified results; never load foreign User/Component/Phase/Task/WorkInstance attributes. The explicit projection replaces invalid scalar values with the table policy above.

For users, validation is `users.id IN candidates AND users.tenant_id = trustedTenantId`. For Components and ProjectPhases, validation requires target `project_id = task.project_id`, whose Project already passed §4.2. Dependency/parent Task validation requires `tenant_id = trustedTenantId`, the same Project as the source Task, and the target's own tenant-consistent Project eligibility. WorkInstance/Step validation requires matching tenant and Task Project, with the Step chained through the validated WorkInstance.

This optional-reference sanitization is allowed after primary query eligibility because its result is an explicit writer projection, not an attempt to rescue a foreign primary/related row. At no point may an unverified candidate identifier be copied into the projection.

### 5.6 Emitted Project reference inventory and settled behavior

Current Project CSV explicitly emits business fields and database-derived Task counts; it does not emit the optional Project User IDs. Current Project Excel delegates to the CSV field set. Current Project JSON uses unrestricted `$projects->toArray()`, so all loaded database attributes and the loaded `tasks` relation are exposed regardless of `$fillable`.

| Project field/surface | Repository-backed target/ownership | Current CSV | Current Excel | Current JSON | Same-tenant valid behavior | Foreign-target behavior | Missing/stale behavior |
|---|---|---:|---:|---:|---|---|---|
| `tenant_id` | `tenants`; Project's primary ownership boundary | no | no | yes | emit the already-verified trusted tenant value | impossible after base predicate | Project is ineligible if trusted ownership does not match |
| `client_id` | `users`; optional Client relationship, User is tenant-owned | no | no | yes | preserve ID after `users.tenant_id = trustedTenantId` validation | Project remains; JSON `null` | Project remains; JSON `null` |
| `pm_id` | `users`; optional Manager/ProjectManager relationship, User is tenant-owned | no | no | yes | preserve ID after same-tenant validation | Project remains; JSON `null` | Project remains; JSON `null` |
| `created_by` | `users`; nullable creator/audit FK, User is tenant-owned | no | no | yes | preserve ID after same-tenant validation | Project remains; JSON `null` | Project remains; JSON `null` |
| `template_id` | nullable reference-shaped string; no Project FK/relation/target contract found | no | no | yes under current raw serialization | not allowlisted pending a separately reviewed target/ownership contract | omitted | omitted |
| loaded `tasks` collection | `tasks`; Task is tenant/project-owned | counts only | counts only | yes | include only through §§4.2, 5.4–5.5 Task-safe projection | foreign/ineligible Task absent | stale relation absent |

Project `id` is the eligible primary identifier, not a foreign reference. No `updated_by`, `manager_id` or other reference-bearing column exists in the audited `projects` migrations: `manager_id` is only an accessor alias for `pm_id` and is not appended automatically. The remaining migrated columns are business/lifecycle values, not entity identifiers.

Optional Project User IDs use the same bounded allowset pattern as Task User references: query only `users.id IN candidates AND users.tenant_id = trustedTenantId`, preserve matching IDs, and map every nonmatch to `null` without loading User attributes. They never affect Project primary eligibility.

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

### 7.1 Task JSON serialization — settled

Future GAP-034 implementation uses **tenant-safe explicit JSON projection** (option A), not `$tasks->toArray()`, model-wide `$hidden`, or mutation of model attributes. The projection is an allowlisted array assembled only after §4.2 eligibility and §5.5 validation. It preserves the existing top-level `export_info`/`tasks` envelope and, where safe, the existing Task field names and scalar types. Loaded Project/assignment data is included only through its independently constrained projection.

Reference fields retain their existing keys and current value types to minimize response-shape change, but unsafe optional values become `null` and unsafe reference collections are filtered as specified in §5.4. In particular, `dependencies_json` remains an array while the currently uncast `watchers` field remains its existing JSON scalar representation after decode/filter/re-encode. A structurally invalid Project removes the whole Task. This is an intentional security correction: exact preservation of an unauthorized ULID is not a compatibility requirement and no further Owner contract choice is needed.

The same projected logical record is the security boundary for CSV, Excel and JSON. CSV/Excel may select/format fewer fields, but neither receives the raw Eloquent model or an unrestricted attribute array. Required invariant: **no unverified tenant-owned foreign identifier enters any writer input**.

### 7.2 Project JSON serialization

Future GAP-034 implementation uses a **tenant-safe explicit Project projection**, never `$projects->toArray()`, raw model attributes, or raw Task models. The settled allowlist is:

- identity/ownership: `id`, verified `tenant_id`;
- business/lifecycle: `code`, `name`, `description`, `status`, `priority`, `progress`, `start_date`, `end_date`;
- financial/capacity: `budget_total`, `budget_planned`, `budget_actual`, `actual_cost`, `estimated_hours`, `actual_hours`, `completion_percentage`;
- classification/state: `risk_level`, `is_template`, `last_activity_at`;
- confirmed non-reference metadata: `tags`, `settings`;
- timestamps/lifecycle: `created_at`, `updated_at`, `deleted_at` where present in the current selected row;
- optional verified references: `client_id`, `pm_id`, `created_by`, with nonmatching values explicitly set to `null`;
- relations/aggregates: a `tasks` key only when required by the existing JSON contract, populated exclusively with the Task-safe projection; tenant-constrained `tasks_count`/`completed_tasks_count` where selected by the format design.

`template_id` is deliberately absent because repository evidence does not establish its target or tenant ownership. It cannot enter output merely because it exists in the table. Any other newly added/unexpected database attribute is absent by default until explicitly reviewed and allowlisted.

The top-level `export_info`/`projects` envelope remains. The Project itself remains eligible when optional `client_id`, `pm_id` or `created_by` is foreign/stale; only that field becomes `null`. No foreign User attributes are loaded or emitted. This closes the future-attribute and loaded-relation bypass symmetrically with Task JSON.

---

## 8. Failure and compatibility behavior

### 8.1 Missing trusted context

Fail before query/output. No partial or successful artifact. Do not fall back to an untrusted source.

### 8.2 Middleware behavior

Existing missing tenant, unknown tenant and header/user mismatch responses remain owned by `tenant.isolation`; GAP-034 neither duplicates nor weakens them.

### 8.3 API compatibility

Endpoint paths, methods, accepted fields/formats, RBAC behavior and response envelope remain unchanged. Safe JSON field names/types are preserved where possible. Intentional security changes are: structurally invalid Tasks are omitted; unsafe optional scalar references become `null`; unsafe reference arrays are filtered. Cross-tenant IDs no longer return cross-tenant data. Silent filtering avoids a new existence oracle.

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

This rejection applies to primary Task/Project eligibility and related entities. The chosen optional-reference allowset/projection in §5.5 is not post-retrieval primary-row filtering: it queries only verified matching target IDs and prevents every unverified candidate from entering writer input.

### 9.5 Rejected: unrestricted model serialization plus relation scopes

`$tasks->toArray()` and `$projects->toArray()` serialize database attributes independently of relation visibility. A foreign `project_id`, `assignee_id` or dependency can leak even when the corresponding relation is absent. Model-wide `$hidden` would affect unrelated APIs and cannot express per-tenant validation, so explicit export projection is the bounded choice.

---

## 10. Required regression design

Tests must inspect emitted logical records/writer inputs, not only SQL strings.

1. Task A+B, no IDs → only A.
2. A supplies Task B ID → no B row/B-derived data.
3. Mixed Task A+B IDs → only A.
4. `filters.project_id = Project B` → cannot escape Task tenant predicate.
5. Task A → Project B and Task A → stale Project → Task A excluded from CSV/Excel/JSON; Project B ULID/name/data absent everywhere in logical writer input/output.
6. Projects A+B, no IDs → only A.
7. A supplies Project B ID → no B project.
8. Mixed Project A+B IDs → only A.
9. Task B → Project A malformed row → excluded from total/completed aggregates and serialized Task relation.
10. Task A with `assignee_id = User B` → Task follows optional-reference policy; CSV/Excel show `Unassigned`, JSON has `assignee_id: null`, and User B ULID/attributes are absent everywhere.
11. Task A with valid same-tenant assignee → existing logical label/ID is preserved without loading unrelated User attributes.
12. Task A with foreign/stale Component or ProjectPhase → Task remains; `component_id`/`phase_id` is `null` in JSON and foreign ULID is absent everywhere.
13. `dependencies_json` containing current same-project Task, Tenant B Task and stale ID → only verified current same-project Task ID remains; foreign/stale IDs absent everywhere.
14. Foreign/stale `assigned_to`, `created_by`, `updated_by` and watcher User IDs → scalar fields become `null`, array entries are removed, and foreign User ULIDs/attributes are absent everywhere.
15. Foreign/stale/cross-project `parent_id` → `null`; foreign Task ULID absent everywhere.
16. Foreign/stale/project-inconsistent `work_instance_id` or `work_instance_step_id` → `null`; foreign workflow ULIDs absent everywhere.
17. Parameterized CSV/Excel/JSON requests → tenant-scoped selection plus the same tenant-safe logical projection for each; no format-specific query bypass or raw model input.
18. Body/query/header-like tenant override input → request attribute Tenant A remains authoritative.
19. Missing request tenant attribute → fail closed before any Task/Project query or artifact.
20. Header/user mismatch → existing middleware 403 behavior unchanged.
21. If assignments remain during isolated GAP-034 verification, Assignment B linked to Task A is absent from writer input; final GAP-010b composition removes the eager load.
22. Projection contract → Task and Project JSON writers do not receive Eloquent `toArray()` output; an unexpected future reference-bearing model column is absent unless explicitly reviewed and allowlisted.
23. Project A with `client_id = User B` → Project A remains; JSON has `client_id: null`; User B ULID/attributes are absent everywhere.
24. Project A with `pm_id = User B` → Project A remains; JSON has `pm_id: null`; User B ULID/attributes are absent everywhere.
25. Project A with `created_by = User B` → Project A remains; JSON has `created_by: null`; User B ULID/attributes are absent everywhere.
26. Project A with valid same-tenant `client_id`, `pm_id` and `created_by` → all three IDs remain intact without loading User attributes.
27. Project metadata → ordinary string `tags` and boolean/config `settings` round-trip unchanged as non-reference metadata; `template_id` and an injected future/unexpected reference-bearing column are absent from Project writer input unless separately reviewed and allowlisted.
28. Project JSON with loaded Tasks → every child uses the Task eligibility/reference projection; no raw Task model/attribute array enters the Project payload.

Use real string/ULID tenant and record IDs. Include deliberately inconsistent rows using direct database insertion or a narrowly controlled fixture where model protections would otherwise prevent the state. For every Task and Project foreign-reference class, assert the actual `foreignTenantUlid` is not present anywhere in serialized logical writer input or parsed output, not merely that a relation object is absent. Also verify zero Tenant B names/attributes/aggregate influence.

---

## 11. Acceptance criteria

1. Trusted tenant is sourced only from middleware-established request attribute and cast as string.
2. Missing/empty trusted context fails closed before query/output.
3. Both base queries contain mandatory tenant predicates before caller conditions.
4. IDs and filters only narrow the tenant-scoped builder.
5. Task eligibility requires a present trusted-tenant Project at query level; malformed/stale structural Project references exclude the Task.
6. Task Project relation cannot expose a different tenant.
7. Project Task relations and all Task aggregates independently enforce tenant.
8. Every emitted tenant-owned scalar/reference value follows the settled §5.4 policy.
9. Task/Project JSON uses explicit tenant-safe projection; unrestricted model `toArray()` is prohibited.
10. Project optional `client_id`, `pm_id` and `created_by` preserve only verified same-tenant IDs; foreign/stale values become `null` without excluding the Project.
11. Project `tags`/`settings` remain confirmed non-reference metadata; unclassified `template_id` and future columns are excluded by default.
12. Project Task children use only the Task-safe projection and aggregates remain tenant-constrained.
13. Unauthorized primary IDs are silently excluded without existence disclosure.
14. CSV/Excel/JSON share the same eligible rows and tenant-safe projection boundary.
15. GAP-010b bounded-memory aggregate design remains intact with tenant-constrained closures and bounded reference allowsets.
16. No RBAC/global-scope/model-wide scope/schema/dependency change is introduced.
17. All §10 regressions pass in a future authorized implementation.
18. Neither export route is released until both GAP-034 and GAP-010b pass their own governance and verification.

---

## 12. Scope exclusions and governance boundary

Excluded: global model scopes, broad TenantScope rollout, `scopeForTenant()` repair, RBAC/13-role changes, viewer/client policy, migrations, packages, tenant-ID migration, route restoration, GAP-010b implementation and unrelated export controllers.

If implementation evidence shows explicit export-path predicates cannot enforce this design without changing a model-wide/public contract, work stops for a Gate 2 revision. Any separate RBAC flaw or additional tenant surface is recorded and routed as its own security work item.

**Current authority:** Gate 2 awaiting Owner. Implementation authorized: NO. Gate 3: NOT STARTED. Merge/release authorized: NO.
