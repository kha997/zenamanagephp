# GAP-034 Export Tenant Isolation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make both legacy bulk export endpoints emit only tenant-eligible Task/Project records and tenant-safe scalar, relation, aggregate, CSV, Excel, and JSON data.

**Architecture:** `ExportController` resolves the middleware-established tenant before constructing any query and applies mandatory database eligibility predicates. A focused `ExportTenantProjectionService` validates optional references in bounded candidate sets and returns explicit logical arrays; every writer consumes those arrays, never unrestricted Eloquent serialization. Project JSON preserves `export_info`, `projects`, and each Project's existing `tasks` key, whose children use the same Task-safe projection.

**Tech Stack:** PHP 8.2, Laravel/Eloquent, PHPUnit/Laravel feature testing, SQLite test database, `Storage::fake()`.

## Global Constraints

- Gate 2 is approved; implementation remains unauthorized until a later explicit Owner decision.
- Trusted tenant source is only `$request->attributes->get('tenant_id')`; missing/empty context fails before model query or output.
- No request body/query fallback, global scope, `TenantScope` rollout, model-wide `scopeForTenant()` repair, schema change, migration, or dependency.
- Caller IDs and filters only narrow an already tenant-scoped query; B-only/mixed IDs are silently filtered without an existence oracle.
- Project JSON always preserves the existing `tasks` key; every child uses the approved Task-safe projection.
- No `$tasks->toArray()`, `$projects->toArray()`, or raw child Task serialization.
- Candidate reference validation operates per bounded collection/chunk and never copies an unverified identifier into writer input.
- GAP-034 owns tenant-safe selection, relation/aggregate isolation, reference validation, and projections. GAP-010b retains Request import, CSV injection mitigation, streaming/chunking, tags CSV serialization, `fputcsv()`, atomic publication, exported-row counting, and removal of the unused assignments eager-load.
- Do not create Gate 3, mark a PR Ready, merge, release, or restore the export routes during this plan.

## File Map

- Modify `app/Http/Controllers/Api/ExportController.php`: trusted-tenant resolution, Task/Project query eligibility, constrained eager loads/counts, projection-service dispatch, and safe-array writer inputs.
- Create `app/Services/ExportTenantProjectionService.php`: bounded allowset queries and explicit Task/Project logical projections.
- Create `tests/Feature/Api/ExportTenantIsolationTest.php`: all 28 approved security scenarios, literal foreign-ULID absence, writer-format matrix, and projection guards.

No model, migration, route, package, or global-scope file is expected.

## Hard Boundary: GAP-010b Composition

Before implementation, compare the selected implementation base with the approved GAP-010b branch:

1. `ExportController` currently lacks `use Illuminate\Http\Request;`; that import belongs to GAP-010b.
2. GAP-010b owns removal of `assignments`, chunking/streaming, CSV field escaping/tag serialization, `fputcsv()`, atomic publication, and exported-row counting.
3. GAP-034 must feed safe logical projections into those writers and add tenant predicates inside GAP-010b's count closures.

If the implementation base does not already contain the approved GAP-010b composition points, stop before Task 1 and return to Owner plan review. Do not add the Request import, remove assignments, or rewrite writers under GAP-034. The intended ordering is GAP-010b implementation/base first, then GAP-034 rebased onto its exact approved head; alternatively Owner must explicitly authorize a coordinated shared-hunk sequence.

---

### Task 1: Establish Red Security Harness and Trusted-Tenant Boundary

**Files:**
- Create: `tests/Feature/Api/ExportTenantIsolationTest.php`
- Modify: `app/Http/Controllers/Api/ExportController.php:16-132`

**Interfaces:**
- Consumes: GAP-010b-provided `Illuminate\Http\Request` import and existing export response/writer contract.
- Produces: `private function trustedTenantId(Request $request): string`; tenant-scoped Task/Project builders used by later tasks.

- [ ] **Step 1: Create endpoint test fixtures and artifact readers**

Add a `RefreshDatabase` feature test with helpers that create two ULID tenants, RBAC-capable users, projects/tasks, authenticate with `X-Tenant-ID`, call `/api/tasks/bulk/export` or `/api/projects/bulk/export`, resolve `data.filename`, and decode the fake-disk artifact:

```php
private function export(string $resource, User $user, Tenant $tenant, array $payload): TestResponse
{
    return $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json', 'X-Tenant-ID' => (string) $tenant->id])
        ->postJson("/api/{$resource}/bulk/export", $payload);
}

private function storedPayload(TestResponse $response): string
{
    $response->assertOk()->assertJsonPath('success', true);
    return Storage::get('exports/' . $response->json('data.filename'));
}
```

Use `Storage::fake(config('filesystems.default'))` in `setUp()`. Reuse the repository's RBAC test helper/role setup; do not bypass `tenant.isolation` for endpoint cases.

- [ ] **Step 2: Write failing trusted-tenant and primary-selection tests**

Add scenarios 1–4, 6–8, 18–20 from the approved matrix:

```php
#[DataProvider('formats')]
public function test_task_export_silently_filters_foreign_and_mixed_ids(string $format): void
{
    // Create Task A and Task B with valid same-tenant Projects.
    // Export B-only and A+B IDs as Tenant A.
    // Assert Task B ULID is absent and Task A is present only for mixed input.
}

public function test_missing_request_tenant_attribute_fails_before_task_or_project_query(): void
{
    $request = Request::create('/api/tasks/bulk/export', 'POST', ['format' => 'json']);
    DB::enableQueryLog();
    $response = app(ExportController::class)->exportTasks($request);
    $this->assertSame(500, $response->status());
    $this->assertSame([], DB::getQueryLog());
    Storage::assertDirectoryEmpty('exports');
}
```

Cover no IDs, B-only IDs, mixed IDs, foreign `filters.project_id`, caller tenant-like input, missing context, and existing header/user mismatch 403 behavior.

- [ ] **Step 3: Run the focused tests and verify red state**

Run:

```bash
php artisan test tests/Feature/Api/ExportTenantIsolationTest.php --filter='trusted|primary|mixed|missing|mismatch'
```

Expected: FAIL because current queries are unscoped and missing context is not rejected by the controller.

- [ ] **Step 4: Implement trusted tenant resolution and base predicates**

In `ExportController`, resolve before either `Task::query()` or `Project::query()`:

```php
private function trustedTenantId(Request $request): string
{
    $tenantId = trim((string) $request->attributes->get('tenant_id', ''));
    if ($tenantId === '') {
        throw new RuntimeException('Trusted tenant context is required for export.');
    }
    return $tenantId;
}
```

Build Task semantics exactly as approved:

```php
$query = Task::query()
    ->where('tenant_id', $trustedTenantId)
    ->whereHas('project', fn ($projectQuery) => $projectQuery
        ->where('tenant_id', $trustedTenantId));
```

Build Project semantics before IDs:

```php
$query = Project::query()->where('tenant_id', $trustedTenantId);
```

Then apply `whereIn`, status, priority, and `filters.project_id` only with `AND` narrowing. Do not use `scopeForTenant()`.

- [ ] **Step 5: Run the focused tests and verify green state**

Run the Step 3 command. Expected: PASS for scenarios 1–4, 6–8, 18–20.

- [ ] **Step 6: Commit the independently testable boundary**

```bash
git add app/Http/Controllers/Api/ExportController.php tests/Feature/Api/ExportTenantIsolationTest.php
git commit -m "fix(exports): enforce tenant-scoped export eligibility"
```

### Task 2: Implement Task-Safe Reference Projection

**Files:**
- Create: `app/Services/ExportTenantProjectionService.php`
- Modify: `app/Http/Controllers/Api/ExportController.php:29-79,137-176,248-264`
- Test: `tests/Feature/Api/ExportTenantIsolationTest.php`

**Interfaces:**
- Consumes: a bounded `Collection<int, Task>` whose rows already satisfy Task and Project eligibility.
- Produces: `projectTasks(Collection $tasks, string $tenantId): Collection<int, array<string,mixed>>`; each array contains only approved Task fields/references and a safe Project subprojection.

- [ ] **Step 1: Write failing mandatory-Project and Task-reference tests**

Add scenarios 5, 10–16, 21–22:

```php
public function test_cross_tenant_project_makes_task_ineligible_in_every_format(): void
{
    // Insert Task A -> Project B directly to create deliberately malformed data.
    // For csv/excel/json, assert Task A and Project B ULID/name are absent.
}

public function test_foreign_assignee_is_unassigned_and_never_emitted(): void
{
    // Task A.assignee_id = User B.
    // Assert CSV/Excel logical row says Unassigned, JSON assignee_id is null,
    // and the complete artifact does not contain User B ULID.
}
```

Add separate malformed-reference cases for `component_id`, `phase_id`, `dependencies_json`, `assigned_to`, `created_by`, `updated_by`, `watchers`, `parent_id`, `work_instance_id`, and `work_instance_step_id`. Add valid same-tenant controls so sanitization cannot erase authorized IDs.

- [ ] **Step 2: Run Task-reference cases and verify red state**

```bash
php artisan test tests/Feature/Api/ExportTenantIsolationTest.php --filter='project_makes_task|assignee|component|phase|dependencies|assigned_to|created_by|updated_by|watchers|parent|work_instance|projection_guard'
```

Expected: FAIL because current CSV prints raw `assignee_id` and JSON serializes unrestricted Task attributes.

- [ ] **Step 3: Create the bounded projection service**

Define:

```php
final class ExportTenantProjectionService
{
    public function projectTasks(Collection $tasks, string $tenantId): Collection;
    public function projectProjects(Collection $projects, string $tenantId): Collection;

    /** @return array<string, true> */
    private function sameTenantUserIds(Collection $candidateIds, string $tenantId): array;

    /** @return array<string, true> */
    private function sameProjectComponentIds(Collection $tasks): array;

    /** @return array<string, true> */
    private function sameProjectPhaseIds(Collection $tasks): array;

    /** @return array<string, true> */
    private function eligibleReferencedTaskIds(Collection $tasks, string $tenantId): array;

    /** @return array<string, true> */
    private function validWorkInstanceIds(Collection $tasks, string $tenantId): array;

    /** @return array<string, true> */
    private function validWorkInstanceStepIds(Collection $tasks, string $tenantId, array $instanceIds): array;
}
```

Normalize candidates with `filter()->map(fn ($id) => (string) $id)->unique()->values()`. Query only matching same-tenant/same-project targets and key verified IDs into allowsets. Do not hydrate foreign target attributes.

- [ ] **Step 4: Implement explicit Task arrays**

Return an allowlisted Task array preserving safe business fields and applying these exact policies:

```php
'project_id' => (string) $task->project_id,
'project' => ['id' => (string) $task->project->id, 'name' => $task->project->name],
'assignee_id' => isset($validUsers[(string) $task->assignee_id]) ? (string) $task->assignee_id : null,
'component_id' => isset($validComponents[(string) $task->component_id]) ? (string) $task->component_id : null,
'phase_id' => isset($validPhases[(string) $task->phase_id]) ? (string) $task->phase_id : null,
'dependencies_json' => array_values(array_filter(
    $task->dependencies_json ?? [],
    fn ($id) => isset($eligibleTaskIds[(string) $id])
)),
```

Apply the same verified-User policy to `assigned_to`, `created_by`, and `updated_by`; decode/filter/re-encode uncast `watchers` while preserving its current JSON scalar type; null invalid `parent_id`, `work_instance_id`, and `work_instance_step_id`. Include only approved non-reference Task fields. Never spread `$task->getAttributes()`.

- [ ] **Step 5: Constrain eager loads and route safe arrays to Task writers**

Constrain `project` by trusted tenant. If the implementation base still loads `assignments`, stop at the GAP-010b boundary rather than removing it here; any temporary load must be tenant-constrained. Inject the projection service and pass its result to CSV, Excel, and JSON writer methods. CSV derives Project name from the safe `project` array and renders a null `assignee_id` as `Unassigned`.

- [ ] **Step 6: Run Task-reference tests and verify green state**

Run the Step 2 command. Expected: PASS, including literal foreign ULID absence across all selected formats.

- [ ] **Step 7: Commit Task projection**

```bash
git add app/Services/ExportTenantProjectionService.php app/Http/Controllers/Api/ExportController.php tests/Feature/Api/ExportTenantIsolationTest.php
git commit -m "fix(exports): sanitize tenant-owned task references"
```

### Task 3: Implement Project-Safe Projection, Child Tasks, and Aggregates

**Files:**
- Modify: `app/Services/ExportTenantProjectionService.php`
- Modify: `app/Http/Controllers/Api/ExportController.php:84-132,182-225,269-285`
- Test: `tests/Feature/Api/ExportTenantIsolationTest.php`

**Interfaces:**
- Consumes: bounded tenant-scoped Projects with constrained Task children and tenant-constrained count columns.
- Produces: `projectProjects(Collection $projects, string $tenantId): Collection<int, array<string,mixed>>` preserving `tasks` on every Project record.

- [ ] **Step 1: Write failing Project-reference and child-projection tests**

Add scenarios 9, 23–28:

```php
public function test_project_optional_foreign_users_become_null_without_excluding_project(): void
{
    // Set Project A client_id, pm_id, created_by to User B.
    // Assert Project A remains, all three fields are null, and User B ULID is absent.
}

public function test_project_json_always_preserves_tasks_key_with_safe_children(): void
{
    // Include valid Task A, malformed Task A->Project B, and Task B->Project A.
    // Assert tasks key exists; only eligible Task A appears through safe field allowlist.
}
```

Add same-tenant User controls, `tags/settings` round-trip, `template_id` exclusion, unexpected-column guard, and foreign Task exclusion from relations/counts.

- [ ] **Step 2: Run Project cases and verify red state**

```bash
php artisan test tests/Feature/Api/ExportTenantIsolationTest.php --filter='project_optional|project_json|project_metadata|template_id|aggregate|unexpected_project'
```

Expected: FAIL because Project JSON currently uses raw model serialization and counts raw loaded Tasks.

- [ ] **Step 3: Constrain Project children and aggregate closures**

Keep the base Project tenant predicate and add:

```php
->with(['tasks' => fn ($taskQuery) => $taskQuery
    ->where('tenant_id', $trustedTenantId)
    ->whereHas('project', fn ($projectQuery) => $projectQuery
        ->where('tenant_id', $trustedTenantId))])
->withCount([
    'tasks' => fn ($taskQuery) => $taskQuery->where('tenant_id', $trustedTenantId),
    'tasks as completed_tasks_count' => fn ($taskQuery) => $taskQuery
        ->where('tenant_id', $trustedTenantId)
        ->where('status', 'completed'),
]);
```

If GAP-010b already supplies count closures, add only the tenant predicates inside those closures; do not replace its bounded-memory design.

- [ ] **Step 4: Implement exact Project allowlist**

In `projectProjects()`, validate candidate `client_id`, `pm_id`, and `created_by` against same-tenant Users, then emit:

```php
[
    'id' => (string) $project->id,
    'tenant_id' => $tenantId,
    'code' => $project->code,
    'name' => $project->name,
    'description' => $project->description,
    'status' => $project->status,
    'priority' => $project->priority,
    'progress' => $project->progress,
    // approved budget/cost/capacity/date/classification/metadata/timestamp fields
    'client_id' => isset($validUsers[(string) $project->client_id]) ? (string) $project->client_id : null,
    'pm_id' => isset($validUsers[(string) $project->pm_id]) ? (string) $project->pm_id : null,
    'created_by' => isset($validUsers[(string) $project->created_by]) ? (string) $project->created_by : null,
    'tasks' => $this->projectTasks($project->tasks, $tenantId)->values()->all(),
    'tasks_count' => (int) $project->tasks_count,
    'completed_tasks_count' => (int) $project->completed_tasks_count,
]
```

Use explicit null checks that avoid undefined-index notices. Include approved safe fields from §7.2 of the design; omit `template_id` and every unknown attribute. Preserve `tags` and `settings` unchanged as confirmed business metadata.

- [ ] **Step 5: Route Project writers through safe arrays**

Make Project CSV/Excel read count keys from the safe projection. Make JSON retain the `export_info` and `projects` envelope; every Project array must contain `tasks`, even when it is `[]`. Never pass a Project or Task model to a writer.

- [ ] **Step 6: Run Project tests and verify green state**

Run the Step 2 command. Expected: PASS for scenarios 9 and 23–28.

- [ ] **Step 7: Commit Project projection**

```bash
git add app/Services/ExportTenantProjectionService.php app/Http/Controllers/Api/ExportController.php tests/Feature/Api/ExportTenantIsolationTest.php
git commit -m "fix(exports): sanitize project references and child tasks"
```

### Task 4: Prove the Complete 28-Scenario Format Matrix

**Files:**
- Modify: `tests/Feature/Api/ExportTenantIsolationTest.php`
- Modify only if a failing approved case requires it: `app/Services/ExportTenantProjectionService.php`, `app/Http/Controllers/Api/ExportController.php`

**Interfaces:**
- Consumes: safe query/projection/writer interfaces from Tasks 1–3.
- Produces: one executable regression suite mapping every approved scenario to an assertion.

- [ ] **Step 1: Add the complete scenario map**

Ensure named tests cover all approved cases:

```text
1–4 Task primary/no-ID/B-only/mixed/filter isolation
5 malformed/stale structural Project excludes Task
6–8 Project primary/no-ID/B-only/mixed isolation
9 foreign Task excluded from Project relation and aggregates
10–11 foreign/valid assignee
12 Component/Phase
13 dependencies
14 User audit/watchers
15 parent Task
16 WorkInstance/Step
17 CSV/Excel/JSON common safe projection
18 tenant-like override
19 missing context fail closed
20 header mismatch
21 temporary assignment isolation (only until GAP-010b removes load)
22 Task/Project future-column projection guard
23–25 Project client/PM/creator
26 valid Project Users
27 Project metadata/template/future-column policy
28 Project tasks-key child projection
```

- [ ] **Step 2: Add a reusable literal foreign-ID assertion**

```php
private function assertForeignIdsAbsent(string $payload, string ...$foreignIds): void
{
    foreach ($foreignIds as $foreignId) {
        $this->assertStringNotContainsString($foreignId, $payload);
    }
}
```

Use it on the complete logical artifact/writer payload, not only decoded relation keys.

- [ ] **Step 3: Run the entire GAP-034 suite**

```bash
php artisan test tests/Feature/Api/ExportTenantIsolationTest.php
```

Expected: all tests PASS, zero failures/errors/skips for the 28 approved cases.

- [ ] **Step 4: Run route and middleware regressions**

```bash
php artisan test tests/Feature/RouteHygieneTest.php tests/Feature/TenantIsolationProjectsTest.php tests/Feature/RouteMiddleware/V1LegacyRouteHardeningContractTest.php
```

Expected: PASS; endpoint paths/middleware/header mismatch remain unchanged.

- [ ] **Step 5: Run static analysis and diff hygiene**

```bash
./vendor/bin/phpstan analyse app/Http/Controllers/Api/ExportController.php app/Services/ExportTenantProjectionService.php tests/Feature/Api/ExportTenantIsolationTest.php
git diff --check
```

Expected: PHPStan exits 0 and `git diff --check` prints nothing.

- [ ] **Step 6: Commit final regression coverage**

```bash
git add app/Http/Controllers/Api/ExportController.php app/Services/ExportTenantProjectionService.php tests/Feature/Api/ExportTenantIsolationTest.php
git commit -m "test(exports): cover GAP-034 tenant isolation matrix"
```

### Task 5: Governance and Owner Handoff

**Files:**
- No production/test file changes unless verification exposes a defect inside the approved GAP-034 scope.

**Interfaces:**
- Consumes: completed implementation commits and terminal verification evidence.
- Produces: an Owner report only; it does not create Gate 3 without a separate directive.

- [ ] **Step 1: Verify exact changed-file scope**

```bash
git diff --name-only origin/plan/GAP-034-export-tenant-isolation...HEAD
git diff --stat origin/plan/GAP-034-export-tenant-isolation...HEAD
```

Expected files are only the three files in the File Map unless Owner separately authorizes a GAP-010b shared-hunk file change.

- [ ] **Step 2: Re-run fresh complete verification**

```bash
php artisan test tests/Feature/Api/ExportTenantIsolationTest.php
php artisan test tests/Feature/RouteHygieneTest.php tests/Feature/TenantIsolationProjectsTest.php tests/Feature/RouteMiddleware/V1LegacyRouteHardeningContractTest.php
./vendor/bin/phpstan analyse app/Http/Controllers/Api/ExportController.php app/Services/ExportTenantProjectionService.php tests/Feature/Api/ExportTenantIsolationTest.php
git diff --check
```

Expected: every command exits 0.

- [ ] **Step 3: Report without advancing governance**

Report exact head, files, diff stat, test counts, PHPStan, CI, PR Draft/Ready state, mergeability, GAP-010b exclusions, unresolved findings, and the next action not performed. Stop for Owner direction; do not create Gate 3, mark Ready, merge, or release.

## Self-Review Result

- Spec coverage: trusted tenant, Task/Project eligibility, all Task and Project reference policies, mandatory Project `tasks`, aggregates, all formats, fail-closed behavior, 28 scenarios, and GAP-010b ownership are mapped to Tasks 1–5.
- Completeness scan: no unfinished marker, delegated duplicate instruction, or unspecified error/test step remains.
- Type consistency: `projectTasks(Collection, string): Collection` and `projectProjects(Collection, string): Collection` are defined once and consumed consistently.
- Hard boundary: implementation must not start until the GAP-010b Request/writer composition base or an explicit coordinated shared-hunk authorization exists.
