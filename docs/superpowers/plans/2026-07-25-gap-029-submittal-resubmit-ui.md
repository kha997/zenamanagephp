# GAP-029 Submittal Resubmit Web UI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give operators a web UI path to reopen a rejected `Submittal` for editing and resubmit it, closing `GAP-029` — today this is only reachable via direct API calls (`SubmittalController::update()`/`startRevision()`/`submit()`, all shipped in PR#229).

**Architecture:** Two new `SubmittalPageController` methods (`update()`, `startRevision()`) call `SubmittalLifecycleService` directly instead of proxying through `ApiSubmittalController` (the pattern the four pre-existing Web actions use and keep using — untouched, tracked debt). A shared `SubmittalContentRules` class removes the validation-rule duplication between the API and Web `update()` paths. `show.blade.php` gains an edit card, a reopen-for-revision action, a resubmit form carrying `revision_summary`, and a rejection-detail card, all gated by both `@can` (UX) and the unchanged `SubmittalPolicy` (real enforcement).

**Tech Stack:** Laravel 12 (PHP 8.2), Blade, vanilla JS (no Alpine — not guaranteed loaded by `layouts.operator`), PHPUnit feature tests, Laravel Dusk for the one browser-only assertion.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-07-25-gap-029-submittal-resubmit-ui-design.md` — every numbered section is binding; this plan implements it as written.
- `update()`/`startRevision()` on `SubmittalPageController` call `SubmittalLifecycleService` directly — never `ApiSubmittalController`. `store()`/`submit()`/`approve()`/`reject()` keep proxying through `ApiSubmittalController` exactly as they do on `main` today (spec §10 — do not refactor them beyond the one `handleErrorResponse`/`handleMutationResponse` optional-parameter addition Task 2 makes).
- Field allowlist for content edits: `title`, `description`, `submittal_type`, `specification_section`, `due_date`, `contractor`, `manufacturer`. Never `package_no`, `file_url`, `attachments`, `status`, or any `*_by`/`*_at`/`*_comments`/`*_reason` column.
- `PUT /submittals/{id}` (not `PATCH` — every live Web update route in this repo uses `PUT`), `POST /submittals/{id}/start-revision`, permission `submittal.edit` for the former, `submittal.submit` for the latter (matches `SubmittalPolicy::startRevision()`, which already reuses `submittal.submit` — no new permission).
- Vendor (`contractor`/`manufacturer`) existence validation only re-runs when the submitted value differs from `$submittal->contractor`/`->manufacturer` (spec §3) — never validate an unchanged legacy/renamed vendor reference.
- Every Web response in this feature is `RedirectResponse` — never `JsonResponse`.
- `SubmittalTransitionNotAllowedException`/`SubmittalTransitionConflictException` → flash `error`, not a field error. `AuthorizationException`/`ModelNotFoundException` → not caught, let Laravel's default 403/404 handler render.
- Two separate named error bags: `submittalUpdate` (edit form), `submittalResubmit` (resubmit form) — never the default bag for either, to prevent one form's errors rendering inside the other.
- Rejection-info display reads `Submittal.rejection_reason`/`rejection_comments`/`rejected_by`(via `rejectedBy` relation)/`rejected_at` — **not** `SubmittalRevision.decision_comments` (single merged field, loses fidelity) and **not** `review_comments`/`review_notes`/`reviewed_by`/`reviewed_at` (a different, unrelated pair of columns written by the legacy `review()` endpoint).
- Zero new migrations. Zero changes to `SubmittalLifecycleService`, `SubmittalPolicy`, or any migration.

---

### Task 1: `SubmittalContentRules` shared validation + behavior-preserving API refactor

**Files:**
- Create: `app/Support/SubmittalContentRules.php`
- Modify: `app/Http/Controllers/Api/SubmittalController.php:248-257` (the `update()` method's validator)
- Test: `tests/Feature/Api/SubmittalContentRulesRegressionTest.php`

**Interfaces:**
- Produces: `App\Support\SubmittalContentRules::rules(): array` — a static method returning `['title' => [...], 'description' => [...], 'submittal_type' => [...], 'specification_section' => [...], 'due_date' => [...], 'contractor' => [...], 'manufacturer' => [...]]`, each value a `list<string>` of Laravel validation rule strings. Consumed by Task 3's Web `update()`.

- [ ] **Step 1: Write the failing regression test**

This test proves the *current* (pre-refactor) API `update()` behavior for a representative payload matrix, so that after Task 1's refactor the exact same test still passes unmodified — the test itself is the before/after parity proof required by spec §8.15.

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Role;
use App\Models\Submittal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\RouteNameTrait;

class SubmittalContentRulesRegressionTest extends TestCase
{
    use RefreshDatabase;
    use RouteNameTrait;

    private User $user;
    private Submittal $submittal;
    private array $authHeaders;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $project = Project::factory()->create([
            'created_by' => $this->user->id,
            'tenant_id' => $this->user->tenant_id,
        ]);
        $this->syncZenaProjectRecord($project);
        $this->assignSuperAdminRole($this->user);
        $token = $this->loginZenaUser($this->user);
        $this->authHeaders = [
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID' => (string) $this->user->tenant_id,
            'Accept' => 'application/json',
        ];

        $this->submittal = Submittal::factory()->create([
            'project_id' => $project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->user->tenant_id,
            'status' => 'draft',
        ]);
    }

    public function test_valid_payload_is_accepted(): void
    {
        $response = $this->withHeaders($this->authHeaders)->putJson(
            $this->zena('submittals.update', ['id' => $this->submittal->id]),
            ['title' => 'Updated title', 'description' => 'Updated description']
        );

        $response->assertStatus(200);
    }

    public function test_partial_payload_missing_title_is_accepted_because_title_is_sometimes_not_required(): void
    {
        $response = $this->withHeaders($this->authHeaders)->putJson(
            $this->zena('submittals.update', ['id' => $this->submittal->id]),
            ['description' => 'Only description changes']
        );

        $response->assertStatus(200);
    }

    public function test_invalid_submittal_type_enum_is_rejected(): void
    {
        $response = $this->withHeaders($this->authHeaders)->putJson(
            $this->zena('submittals.update', ['id' => $this->submittal->id]),
            ['submittal_type' => 'not_a_real_type']
        );

        $response->assertStatus(422);
        $this->assertArrayHasKey('submittal_type', $response->json('error.details.data', []));
    }

    public function test_specification_section_over_255_chars_is_rejected(): void
    {
        $response = $this->withHeaders($this->authHeaders)->putJson(
            $this->zena('submittals.update', ['id' => $this->submittal->id]),
            ['specification_section' => str_repeat('x', 256)]
        );

        $response->assertStatus(422);
        $this->assertArrayHasKey('specification_section', $response->json('error.details.data', []));
    }

    public function test_non_date_due_date_is_rejected(): void
    {
        $response = $this->withHeaders($this->authHeaders)->putJson(
            $this->zena('submittals.update', ['id' => $this->submittal->id]),
            ['due_date' => 'not-a-date']
        );

        $response->assertStatus(422);
        $this->assertArrayHasKey('due_date', $response->json('error.details.data', []));
    }

    public function test_status_field_is_still_prohibited_after_refactor(): void
    {
        $response = $this->withHeaders($this->authHeaders)->putJson(
            $this->zena('submittals.update', ['id' => $this->submittal->id]),
            ['title' => 'x', 'status' => 'approved']
        );

        $response->assertStatus(422);
        $this->assertArrayHasKey('status', $response->json('error.details.data', []));
        $this->assertDatabaseHas('submittals', ['id' => $this->submittal->id, 'status' => 'draft']);
    }

    private function syncZenaProjectRecord(Project $project): void
    {
        DB::table('zena_projects')->updateOrInsert(
            ['id' => $project->id],
            [
                'tenant_id' => $project->tenant_id,
                'code' => $project->code,
                'name' => $project->name,
                'description' => $project->description,
                'client_id' => $project->client_id,
                'status' => 'planning',
                'start_date' => $project->start_date,
                'end_date' => $project->end_date,
                'budget' => $project->budget_total ?? 0,
                'settings' => json_encode($project->settings ?? []),
                'created_at' => $project->created_at,
                'updated_at' => $project->updated_at,
            ]
        );
    }

    private function assignSuperAdminRole(User $user): void
    {
        $role = Role::firstOrCreate(['name' => 'super_admin'], [
            'scope' => Role::SCOPE_SYSTEM,
            'allow_override' => true,
            'is_active' => true,
        ]);

        $permissionNames = [
            'submittal.view', 'submittal.create', 'submittal.edit', 'submittal.delete',
            'submittal.submit', 'submittal.review', 'submittal.approve', 'submittal.reject',
        ];

        foreach ($permissionNames as $permissionName) {
            $permission = \App\Models\Permission::firstOrCreate(['name' => $permissionName], [
                'code' => $permissionName,
                'module' => 'submittal',
                'action' => explode('.', $permissionName)[1] ?? '*',
                'description' => ucfirst(str_replace('.', ' ', $permissionName)),
            ]);
            $role->permissions()->syncWithoutDetaching($permission->id);
        }

        $user->roles()->syncWithoutDetaching($role->id);
    }

    private function loginZenaUser(User $user): string
    {
        $response = $this->postJson($this->zena('auth.login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(200);

        return (string) $response->json('data.token');
    }
}
```

Note: this test file's helpers (`assignSuperAdminRole`, `syncZenaProjectRecord`, `loginZenaUser`) grant real `submittal.*` permissions from the start — this is the pattern established after the PR#229 CI-failure fix (grep `assignSuperAdminRole` in `tests/Feature/Api/SubmittalApiTest.php` if you want to see the identical pattern already in the suite). Do not copy the older bare-role pattern.

- [ ] **Step 2: Run the test to verify it passes against current code (before refactor)**

Run: `./vendor/bin/phpunit tests/Feature/Api/SubmittalContentRulesRegressionTest.php`
Expected: `OK (6 tests, ...)` — this test must pass *before* the refactor too; it's establishing the baseline the refactor must not change.

- [ ] **Step 3: Create `app/Support/SubmittalContentRules.php`**

```php
<?php declare(strict_types=1);

namespace App\Support;

final class SubmittalContentRules
{
    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'submittal_type' => ['sometimes', 'in:shop_drawing,material_sample,product_data,test_report,other'],
            'specification_section' => ['nullable', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
            'contractor' => ['nullable', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
        ];
    }
}
```

- [ ] **Step 4: Refactor `SubmittalController::update()` to use it**

In `app/Http/Controllers/Api/SubmittalController.php`, add the import near the other `use` statements at the top of the file:

```php
use App\Support\SubmittalContentRules;
```

Replace the validator construction inside `update()` (currently lines 248-257):

```php
            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|string|max:255',
                'description' => 'sometimes|string',
                'submittal_type' => 'sometimes|in:shop_drawing,material_sample,product_data,test_report,other',
                'specification_section' => 'nullable|string|max:255',
                'due_date' => 'nullable|date',
                'contractor' => 'nullable|string|max:255',
                'manufacturer' => 'nullable|string|max:255',
                'status' => 'prohibited',
            ]);
```

with:

```php
            $validator = Validator::make(
                $request->all(),
                SubmittalContentRules::rules() + ['status' => 'prohibited']
            );
```

`status => prohibited` is deliberately kept *outside* `SubmittalContentRules::rules()` and merged back in here: it's an API-only guard against payload tampering (the Web form never sends a `status` field at all, so `SubmittalContentRules` — shared with Task 3's Web controller — has no reason to carry it). Dropping this merge would silently regress `test_status_field_is_still_prohibited_after_refactor` (Step 1) and the pre-existing `tests/Feature/Api/SubmittalApiTest.php::test_cannot_mutate_status_via_update`.

- [ ] **Step 5: Run the regression test again to verify the refactor changed nothing**

Run: `./vendor/bin/phpunit tests/Feature/Api/SubmittalContentRulesRegressionTest.php`
Expected: `OK (6 tests, ...)` — identical result to Step 2.

- [ ] **Step 6: Run the full pre-existing Submittal API suite to confirm zero collateral regression**

Run: `./vendor/bin/phpunit tests/Feature/Api/SubmittalApiTest.php tests/Feature/Api/SubmittalShowApiTest.php tests/Feature/Api/SubmittalResubmitLifecycleTest.php`
Expected: all green, same pass counts as on `main` before this task (46 tests across these three files as of PR#229's last commit — confirm the number matches, don't just check "no failures", since a silently-skipped test would also show 0 failures).

- [ ] **Step 7: Commit**

```bash
git add app/Support/SubmittalContentRules.php app/Http/Controllers/Api/SubmittalController.php tests/Feature/Api/SubmittalContentRulesRegressionTest.php
git commit -m "refactor(submittal): extract SubmittalContentRules, shared by API and (upcoming) Web update()"
```

---

### Task 2: Web controller plumbing — `submittalForTenant()`, named error bags, `show()` eager-loads

**Files:**
- Modify: `app/Http/Controllers/Web/SubmittalPageController.php`
- Test: `tests/Feature/Zena/OperatorSubmittalUiTest.php` (run existing, no new file yet)

**Interfaces:**
- Produces: `SubmittalPageController::submittalForTenant(string $id): Submittal` (private) — consumed by Task 3 and Task 4.
- Produces: `SubmittalPageController::handleErrorResponse(JsonResponse $response, ?string $errorBag = null): RedirectResponse` and `handleMutationResponse(JsonResponse $response, string $successUrl, string $successMessage, ?string $errorBag = null): RedirectResponse` — both backward compatible (`$errorBag` defaults to `null`, preserving the current unnamed-bag behavior for every existing call site that doesn't pass one).

This task is a pure refactor — no new route, no new user-visible behavior. Its own "test" is that the *existing* Dusk-free feature suite for this controller stays green with identical assertions.

- [ ] **Step 1: Confirm the baseline passes before touching anything**

Run: `./vendor/bin/phpunit tests/Feature/Zena/OperatorSubmittalUiTest.php`
Expected: `OK (7 tests, ...)` (7 tests as of PR#229's last commit on `main` — confirm this number before proceeding, it's what Step 4 must reproduce).

- [ ] **Step 2: Add `submittalForTenant()`, refactor `show()` to use it, add `rejectedBy` eager-load and `$vendors`**

In `app/Http/Controllers/Web/SubmittalPageController.php`, replace the `show()` method:

```php
    public function show(string $id): View
    {
        $submittal = $this->submittalForTenant($id);
        $tenantId = (string) Auth::user()?->tenant_id;

        return view('submittals.show', [
            'submittal' => $submittal,
            'vendors' => Vendor::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'tenant_id', 'code', 'name']),
        ]);
    }
```

Add the new private helper method (place it near `buildApiRequest()`, before `handleMutationResponse()`):

```php
    private function submittalForTenant(string $id): Submittal
    {
        $tenantId = (string) Auth::user()?->tenant_id;

        return Submittal::query()
            ->where('tenant_id', $tenantId)
            ->with([
                'project:id,tenant_id,name,code',
                'submittedBy:id,name',
                'reviewedBy:id,name',
                'rejectedBy:id,name',
            ])
            ->findOrFail($id);
    }
```

This is the exact query `show()` ran before (same `where('tenant_id', ...)`, same `findOrFail`), plus one new eager-load (`rejectedBy:id,name`, required by Task 4's rejection-info card) and `$vendors` now passed to the view (required by Task 3's edit form and Task 4's legacy-vendor option). No behavior change to what `show()` already returned for `project`/`submittedBy`/`reviewedBy`.

- [ ] **Step 3: Add the optional `$errorBag` parameter**

Replace `handleErrorResponse()` and `handleMutationResponse()`:

```php
    private function handleMutationResponse(JsonResponse $response, string $successUrl, string $successMessage, ?string $errorBag = null): RedirectResponse
    {
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            return redirect($successUrl)->with('success', $successMessage);
        }

        return $this->handleErrorResponse($response, $errorBag);
    }

    private function handleErrorResponse(JsonResponse $response, ?string $errorBag = null): RedirectResponse
    {
        $payload = $response->getData(true);

        if ($response->getStatusCode() === 422 && isset($payload['data']) && is_array($payload['data'])) {
            return back()->withErrors($payload['data'], $errorBag ?? 'default')->withInput();
        }

        return back()
            ->withInput()
            ->with('error', (string) ($payload['message'] ?? 'Không thể xử lý yêu cầu.'));
    }
```

`'default'` is Laravel's own name for the unnamed bag — passing it explicitly when `$errorBag` is `null` is behaviorally identical to the current `withErrors($payload['data'])` call with no bag argument (Laravel defaults to `'default'` internally either way); writing it explicitly here just documents that the fallback is intentional, not an oversight.

- [ ] **Step 4: Change `submit()`'s call site to pass the `submittalResubmit` bag**

In `submit()`, change:

```php
        return $this->handleMutationResponse($response, route('operator.submittals.show', $id), 'Đã gửi duyệt');
```

to:

```php
        return $this->handleMutationResponse($response, route('operator.submittals.show', $id), 'Đã gửi duyệt', 'submittalResubmit');
```

Do **not** change the call sites in `store()`, `approve()`, or `reject()` — they keep calling `handleMutationResponse()`/`handleErrorResponse()` with no fourth argument, so `$errorBag` is `null` there and their behavior is provably unchanged (spec §7's bag-collision analysis: their cards never render on the same page as the new edit card, so they don't need a named bag).

- [ ] **Step 5: Run the full existing test file to confirm no regression**

Run: `./vendor/bin/phpunit tests/Feature/Zena/OperatorSubmittalUiTest.php`
Expected: `OK (7 tests, ...)` — same count as Step 1. If any test that inspects error-bag content on `store()`/`approve()`/`reject()` fails, that indicates the default-bag fallback in Step 3 is wrong — stop and investigate rather than adjusting the test.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Web/SubmittalPageController.php
git commit -m "refactor(submittal): extract submittalForTenant(), add optional named error bag to Web mutation responses"
```

---

### Task 3: `SubmittalPageController::update()` — content edit, calls the service directly

**Files:**
- Modify: `app/Http/Controllers/Web/SubmittalPageController.php`
- Modify: `routes/web.php:849-856` (submittals route group)
- Test: `tests/Feature/Zena/SubmittalUpdatePageTest.php`

**Interfaces:**
- Consumes: `Task 1`'s `SubmittalContentRules::rules()`; `Task 2`'s `submittalForTenant()`; `App\Services\SubmittalLifecycleService::updateContent(Submittal $submittal, array $data, array $context): Submittal` (existing, from PR#229, unchanged); `App\Exceptions\SubmittalTransitionNotAllowedException` (existing).
- Produces: `SubmittalPageController::update(Request $request, string $id): RedirectResponse`, route name `operator.submittals.update`.

- [ ] **Step 1: Write the failing tests**

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Project;
use App\Models\Submittal;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class SubmittalUpdatePageTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenant = Tenant::factory()->create();
        $this->user = $this->createTenantUser(
            $this->tenant,
            [],
            ['admin'],
            ['submittal.view', 'submittal.edit', 'submittal.submit']
        );
        $this->project = Project::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'name' => 'Update Test Project',
            'code' => 'PRJ-UPD-001',
        ]);
    }

    private function makeSubmittal(array $overrides = []): Submittal
    {
        return Submittal::query()->create(array_merge([
            'id' => (string) Str::ulid(),
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'title' => 'Original title',
            'description' => 'Original description',
            'submittal_type' => 'shop_drawing',
            'status' => 'draft',
            'submitted_by' => (string) $this->user->id,
            'submittal_number' => 'SUB-UPD-' . Str::random(4),
        ], $overrides));
    }

    public function test_update_saves_allowed_fields_while_draft(): void
    {
        $submittal = $this->makeSubmittal();
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $response = $this->actingAs($this->user)->put(route('operator.submittals.update', $submittal->id), [
            'title' => 'New title',
            'description' => 'New description',
            'submittal_type' => 'material_sample',
            'specification_section' => 'Sec 5',
            'due_date' => '2026-08-01',
        ], $headers);

        $response->assertRedirect(route('operator.submittals.show', $submittal->id));
        $response->assertSessionHas('success');

        $submittal->refresh();
        $this->assertSame('New title', $submittal->title);
        $this->assertSame('New description', $submittal->description);
        $this->assertSame('material_sample', $submittal->submittal_type);
    }

    public function test_update_ignores_immutable_fields_even_if_submitted(): void
    {
        $submittal = $this->makeSubmittal();
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)->put(route('operator.submittals.update', $submittal->id), [
            'title' => 'New title',
            'status' => 'approved',
            'package_no' => 'HACKED-PKG',
            'submittal_number' => 'HACKED-NUM',
        ], $headers);

        $submittal->refresh();
        $this->assertSame('draft', $submittal->status);
        $this->assertNotSame('HACKED-PKG', $submittal->package_no);
        $this->assertNotSame('HACKED-NUM', $submittal->submittal_number);
    }

    public function test_update_blocked_when_status_is_submitted(): void
    {
        $submittal = $this->makeSubmittal(['status' => 'submitted']);
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $response = $this->actingAs($this->user)->put(route('operator.submittals.update', $submittal->id), [
            'title' => 'Should not apply',
        ], $headers);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $submittal->refresh();
        $this->assertSame('Original title', $submittal->title);
    }

    public function test_update_blocked_when_status_is_approved(): void
    {
        $submittal = $this->makeSubmittal(['status' => 'approved']);
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $response = $this->actingAs($this->user)->put(route('operator.submittals.update', $submittal->id), [
            'title' => 'Should not apply',
        ], $headers);

        $response->assertSessionHas('error');
        $submittal->refresh();
        $this->assertSame('Original title', $submittal->title);
    }

    public function test_validation_failure_returns_old_input_in_named_bag(): void
    {
        $submittal = $this->makeSubmittal();
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $response = $this->actingAs($this->user)->put(route('operator.submittals.update', $submittal->id), [
            'submittal_type' => 'not-a-real-type',
        ], $headers);

        $response->assertSessionHasErrorsIn('submittalUpdate', ['submittal_type']);
        $response->assertSessionHasInput('submittal_type', 'not-a-real-type');
    }

    public function test_vendor_validation_only_fires_when_contractor_changes(): void
    {
        Vendor::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'code' => 'VD-001',
            'name' => 'Renamed Away Vendor',
            'is_active' => true,
        ]);
        $submittal = $this->makeSubmittal(['contractor' => 'A Vendor That No Longer Exists By This Name']);
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        // Editing only the title, leaving the stale contractor value untouched, must succeed.
        $response = $this->actingAs($this->user)->put(route('operator.submittals.update', $submittal->id), [
            'title' => 'Edited title only',
            'contractor' => 'A Vendor That No Longer Exists By This Name',
        ], $headers);

        $response->assertSessionDoesntHaveErrors('submittalUpdate');
        $submittal->refresh();
        $this->assertSame('Edited title only', $submittal->title);
    }

    public function test_vendor_validation_fires_when_contractor_actually_changes_to_unknown_name(): void
    {
        $submittal = $this->makeSubmittal(['contractor' => null]);
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $response = $this->actingAs($this->user)->put(route('operator.submittals.update', $submittal->id), [
            'title' => 'x',
            'contractor' => 'A Vendor That Was Never Created',
        ], $headers);

        $response->assertSessionHasErrorsIn('submittalUpdate', ['contractor']);
    }

    public function test_cross_tenant_returns_404(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherSubmittal = Submittal::query()->create([
            'id' => (string) Str::ulid(),
            'tenant_id' => (string) $otherTenant->id,
            'project_id' => (string) $this->project->id,
            'title' => 'Other tenant submittal',
            'description' => 'x',
            'submittal_type' => 'other',
            'status' => 'draft',
            'submitted_by' => (string) $this->user->id,
            'submittal_number' => 'SUB-OTHER-001',
        ]);
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $response = $this->actingAs($this->user)->put(route('operator.submittals.update', $otherSubmittal->id), [
            'title' => 'x',
        ], $headers);

        $response->assertStatus(404);
    }

    public function test_missing_submittal_edit_permission_returns_403(): void
    {
        $viewer = $this->createTenantUser($this->tenant, [], ['submittal_viewer'], ['submittal.view']);
        $submittal = $this->makeSubmittal();
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $response = $this->actingAs($viewer)->put(route('operator.submittals.update', $submittal->id), [
            'title' => 'x',
        ], $headers);

        $response->assertStatus(403);
        $submittal->refresh();
        $this->assertSame('Original title', $submittal->title);
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `./vendor/bin/phpunit tests/Feature/Zena/SubmittalUpdatePageTest.php`
Expected: FAIL — route `operator.submittals.update` doesn't exist yet.

- [ ] **Step 3: Add the route**

In `routes/web.php`, in the `// Submittals` group (after the `submittals.show` line, currently line 853):

```php
    Route::put('/submittals/{id}', [App\Http\Controllers\Web\SubmittalPageController::class, 'update'])->middleware('rbac:submittal.edit')->name('submittals.update');
```

- [ ] **Step 4: Add `update()` to `SubmittalPageController`**

Add the imports at the top of `app/Http/Controllers/Web/SubmittalPageController.php`:

```php
use App\Exceptions\SubmittalTransitionNotAllowedException;
use App\Services\SubmittalLifecycleService;
use App\Support\SubmittalContentRules;
use Illuminate\Support\Facades\Validator;
```

Add the service to the constructor (this controller currently has no constructor — add one):

```php
    public function __construct(private SubmittalLifecycleService $lifecycle)
    {
    }
```

Add the `update()` method (place it after `show()`, before `submit()`):

```php
    public function update(Request $request, string $id): RedirectResponse
    {
        $submittal = $this->submittalForTenant($id);
        $this->authorize('update', $submittal);

        $tenantId = (string) Auth::user()?->tenant_id;
        $rules = SubmittalContentRules::rules();

        if ($request->input('contractor') !== $submittal->contractor) {
            $rules['contractor'][] = Rule::exists('vendors', 'name')->where('tenant_id', $tenantId);
        }
        if ($request->input('manufacturer') !== $submittal->manufacturer) {
            $rules['manufacturer'][] = Rule::exists('vendors', 'name')->where('tenant_id', $tenantId);
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return back()->withErrors($validator, 'submittalUpdate')->withInput();
        }

        $data = $request->only([
            'title', 'description', 'submittal_type', 'specification_section',
            'due_date', 'contractor', 'manufacturer',
        ]);

        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            $this->lifecycle->updateContent($submittal, $data, ['actor_user_id' => $user->id]);
        } catch (SubmittalTransitionNotAllowedException $e) {
            return back()->with('error', 'Không thể sửa nội dung ở trạng thái hiện tại.');
        }

        return redirect()->route('operator.submittals.show', $id)->with('success', 'Đã lưu thay đổi');
    }
```

`$this->authorize('update', $submittal)` throws `AuthorizationException` when the user lacks `submittal.edit` — deliberately not caught here (spec §7), so it propagates to Laravel's default 403 handler. `submittalForTenant()`'s `findOrFail()` throws `ModelNotFoundException` the same way for a cross-tenant id, propagating to the default 404 handler.

- [ ] **Step 5: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Feature/Zena/SubmittalUpdatePageTest.php`
Expected: `OK (9 tests, ...)`

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Web/SubmittalPageController.php routes/web.php tests/Feature/Zena/SubmittalUpdatePageTest.php
git commit -m "feat(submittal): add Web update() calling SubmittalLifecycleService directly"
```

---

### Task 4: `SubmittalPageController::startRevision()` — reopen a rejected submittal

**Files:**
- Modify: `app/Http/Controllers/Web/SubmittalPageController.php`
- Modify: `routes/web.php` (same submittals group, after Task 3's new route)
- Test: `tests/Feature/Zena/SubmittalStartRevisionPageTest.php`

**Interfaces:**
- Consumes: `Task 2`'s `submittalForTenant()`; `App\Services\SubmittalLifecycleService::startRevision(Submittal $submittal, array $context): Submittal` (existing, PR#229, unchanged).
- Produces: `SubmittalPageController::startRevision(Request $request, string $id): RedirectResponse`, route name `operator.submittals.start-revision`.

- [ ] **Step 1: Write the failing tests**

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Project;
use App\Models\Submittal;
use App\Models\SubmittalRevision;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class SubmittalStartRevisionPageTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenant = Tenant::factory()->create();
        $this->user = $this->createTenantUser(
            $this->tenant,
            [],
            ['admin'],
            ['submittal.view', 'submittal.edit', 'submittal.submit']
        );
        $this->project = Project::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'name' => 'Start Revision Test Project',
            'code' => 'PRJ-SR-001',
        ]);
    }

    private function makeRejectedSubmittalWithRevision(): Submittal
    {
        $submittal = Submittal::query()->create([
            'id' => (string) Str::ulid(),
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'title' => 'Rejected title',
            'description' => 'Rejected description',
            'submittal_type' => 'shop_drawing',
            'status' => 'rejected',
            'current_revision_no' => 1,
            'submitted_by' => (string) $this->user->id,
            'submittal_number' => 'SUB-SR-' . Str::random(4),
            'rejected_by' => (string) $this->user->id,
            'rejected_at' => now(),
            'rejection_reason' => 'Missing details',
        ]);

        SubmittalRevision::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'submittal_id' => $submittal->id,
            'revision_no' => 1,
            'title' => 'Rejected title',
            'description' => 'Rejected description',
            'submitted_by' => (string) $this->user->id,
            'submitted_at' => now(),
            'decision' => 'rejected',
            'decided_by' => (string) $this->user->id,
            'decided_at' => now(),
            'decision_comments' => 'Missing details',
            'created_at' => now(),
        ]);

        return $submittal;
    }

    public function test_start_revision_moves_status_to_revising_without_creating_new_revision(): void
    {
        $submittal = $this->makeRejectedSubmittalWithRevision();
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $response = $this->actingAs($this->user)->post(route('operator.submittals.start-revision', $submittal->id), [], $headers);

        $response->assertRedirect(route('operator.submittals.show', $submittal->id));
        $response->assertSessionHas('success');

        $submittal->refresh();
        $this->assertSame('revising', $submittal->status);
        $this->assertSame(1, SubmittalRevision::query()->where('submittal_id', $submittal->id)->count());
    }

    public function test_start_revision_blocked_from_draft(): void
    {
        $submittal = Submittal::query()->create([
            'id' => (string) Str::ulid(),
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'title' => 'Draft',
            'description' => 'x',
            'submittal_type' => 'other',
            'status' => 'draft',
            'submitted_by' => (string) $this->user->id,
            'submittal_number' => 'SUB-SR-DRAFT',
        ]);
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $response = $this->actingAs($this->user)->post(route('operator.submittals.start-revision', $submittal->id), [], $headers);

        $response->assertSessionHas('error');
        $submittal->refresh();
        $this->assertSame('draft', $submittal->status);
    }

    public function test_cross_tenant_returns_404(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherSubmittal = Submittal::query()->create([
            'id' => (string) Str::ulid(),
            'tenant_id' => (string) $otherTenant->id,
            'project_id' => (string) $this->project->id,
            'title' => 'Other',
            'description' => 'x',
            'submittal_type' => 'other',
            'status' => 'rejected',
            'submitted_by' => (string) $this->user->id,
            'submittal_number' => 'SUB-SR-OTHER',
        ]);
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $response = $this->actingAs($this->user)->post(route('operator.submittals.start-revision', $otherSubmittal->id), [], $headers);

        $response->assertStatus(404);
    }

    public function test_missing_permission_returns_403(): void
    {
        $viewer = $this->createTenantUser($this->tenant, [], ['submittal_viewer'], ['submittal.view']);
        $submittal = $this->makeRejectedSubmittalWithRevision();
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $response = $this->actingAs($viewer)->post(route('operator.submittals.start-revision', $submittal->id), [], $headers);

        $response->assertStatus(403);
        $submittal->refresh();
        $this->assertSame('rejected', $submittal->status);
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `./vendor/bin/phpunit tests/Feature/Zena/SubmittalStartRevisionPageTest.php`
Expected: FAIL — route doesn't exist.

- [ ] **Step 3: Add the route**

In `routes/web.php`, right after Task 3's new `submittals.update` route:

```php
    Route::post('/submittals/{id}/start-revision', [App\Http\Controllers\Web\SubmittalPageController::class, 'startRevision'])->middleware('rbac:submittal.submit')->name('submittals.start-revision');
```

- [ ] **Step 4: Add `startRevision()` to `SubmittalPageController`**

Place after `update()`:

```php
    public function startRevision(Request $request, string $id): RedirectResponse
    {
        $submittal = $this->submittalForTenant($id);
        $this->authorize('startRevision', $submittal);

        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            $this->lifecycle->startRevision($submittal, ['actor_user_id' => $user->id]);
        } catch (SubmittalTransitionNotAllowedException $e) {
            return back()->with('error', 'Chỉ hồ sơ bị từ chối mới mở lại để sửa được.');
        }

        return redirect()->route('operator.submittals.show', $id)->with('success', 'Đã mở lại để sửa');
    }
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Feature/Zena/SubmittalStartRevisionPageTest.php`
Expected: `OK (4 tests, ...)`

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Web/SubmittalPageController.php routes/web.php tests/Feature/Zena/SubmittalStartRevisionPageTest.php
git commit -m "feat(submittal): add Web startRevision() calling SubmittalLifecycleService directly"
```

---

### Task 5: View — edit card, rejection-info card, Thao tác card updates, `@can` gating

**Files:**
- Modify: `resources/views/submittals/show.blade.php`
- Test: `tests/Feature/Zena/SubmittalShowPageViewTest.php`

**Interfaces:**
- Consumes: routes from Tasks 3/4 (`operator.submittals.update`, `operator.submittals.start-revision`), `$vendors` and `rejectedBy` from Task 2's `show()`.

- [ ] **Step 1: Write the failing tests**

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Project;
use App\Models\Submittal;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class SubmittalShowPageViewTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenant = Tenant::factory()->create();
        $this->user = $this->createTenantUser(
            $this->tenant,
            [],
            ['admin'],
            ['submittal.view', 'submittal.edit', 'submittal.submit']
        );
        $this->project = Project::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'name' => 'Show View Test Project',
            'code' => 'PRJ-SV-001',
        ]);
    }

    private function makeSubmittal(array $overrides = []): Submittal
    {
        return Submittal::query()->create(array_merge([
            'id' => (string) Str::ulid(),
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'title' => 'A title',
            'description' => 'A description',
            'submittal_type' => 'shop_drawing',
            'status' => 'draft',
            'submitted_by' => (string) $this->user->id,
            'submittal_number' => 'SUB-SV-' . Str::random(4),
        ], $overrides));
    }

    public function test_draft_shows_edit_card_and_submit_button_not_resubmit(): void
    {
        $submittal = $this->makeSubmittal();
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $response = $this->actingAs($this->user)->get(route('operator.submittals.show', $submittal->id), $headers);

        $response->assertOk();
        $response->assertSee('Sửa nội dung');
        $response->assertSee('Gửi duyệt');
        $response->assertDontSee('Mở lại để sửa');
        $response->assertDontSee('Tóm tắt thay đổi');
    }

    public function test_rejected_shows_reopen_button_and_rejection_reason_with_revision_number(): void
    {
        $submittal = $this->makeSubmittal([
            'status' => 'rejected',
            'current_revision_no' => 1,
            'rejected_by' => (string) $this->user->id,
            'rejected_at' => now(),
            'rejection_reason' => 'Missing calcs',
            'rejection_comments' => 'Please redo section 3',
        ]);
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $response = $this->actingAs($this->user)->get(route('operator.submittals.show', $submittal->id), $headers);

        $response->assertOk();
        $response->assertSee('Mở lại để sửa');
        $response->assertSee('Lần nộp #1 bị từ chối');
        $response->assertSee('Missing calcs');
        $response->assertSee('Please redo section 3');
        $response->assertSee($this->user->name);
        $response->assertDontSee('Sửa nội dung');
    }

    public function test_revising_shows_edit_card_and_resubmit_form_with_revision_summary(): void
    {
        $submittal = $this->makeSubmittal([
            'status' => 'revising',
            'current_revision_no' => 1,
            'rejection_reason' => 'Missing calcs',
        ]);
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $response = $this->actingAs($this->user)->get(route('operator.submittals.show', $submittal->id), $headers);

        $response->assertOk();
        $response->assertSee('Sửa nội dung');
        $response->assertSee('Tóm tắt thay đổi');
        $response->assertSee('Gửi lại');
        $response->assertSee('Lần nộp #1 bị từ chối');
    }

    public function test_submitted_shows_neither_edit_nor_reopen_nor_resubmit(): void
    {
        $submittal = $this->makeSubmittal(['status' => 'submitted']);
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $response = $this->actingAs($this->user)->get(route('operator.submittals.show', $submittal->id), $headers);

        $response->assertOk();
        $response->assertDontSee('Sửa nội dung');
        $response->assertDontSee('Mở lại để sửa');
        $response->assertDontSee('Tóm tắt thay đổi');
    }

    public function test_viewer_without_edit_or_submit_permission_sees_no_action_buttons(): void
    {
        $viewer = $this->createTenantUser($this->tenant, [], ['submittal_viewer'], ['submittal.view']);
        $submittal = $this->makeSubmittal(['status' => 'draft']);
        $rejected = $this->makeSubmittal(['status' => 'rejected', 'rejection_reason' => 'x']);
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $draftResponse = $this->actingAs($viewer)->get(route('operator.submittals.show', $submittal->id), $headers);
        $draftResponse->assertDontSee('Sửa nội dung');
        $draftResponse->assertDontSee('Gửi duyệt');

        $rejectedResponse = $this->actingAs($viewer)->get(route('operator.submittals.show', $rejected->id), $headers);
        $rejectedResponse->assertDontSee('Mở lại để sửa');
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `./vendor/bin/phpunit tests/Feature/Zena/SubmittalShowPageViewTest.php`
Expected: FAIL on every assertion looking for new content (`Sửa nội dung`, `Mở lại để sửa`, `Tóm tắt thay đổi`, `Lần nộp #1 bị từ chối`) — none of it exists in the view yet.

- [ ] **Step 3: Rewrite `resources/views/submittals/show.blade.php`**

Replace the file's full content:

```blade
@extends('layouts.operator')

@section('title', 'Submittal ' . $submittal->submittal_number)
@section('page_title', 'Submittal ' . $submittal->submittal_number)

@section('content')
    <x-ui.page-header
        :title="'Submittal ' . $submittal->submittal_number"
        :description="$submittal->title"
    >
        <x-ui.button-link :href="route('operator.submittals.index')" variant="secondary">Quay lại</x-ui.button-link>
    </x-ui.page-header>

    @if (session('error'))
        <div class="operator-error-list">{{ session('error') }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <x-ui.card title="Mô tả">
                <div class="whitespace-pre-line text-slate-800">{{ $submittal->description }}</div>
            </x-ui.card>

            @if ($submittal->approval_comments)
                <x-ui.card title="Ý kiến phê duyệt">
                    <div class="whitespace-pre-line text-slate-800">{{ $submittal->approval_comments }}</div>
                </x-ui.card>
            @endif

            @if (in_array($submittal->status, ['rejected', 'revising'], true) && $submittal->rejection_reason)
                <x-ui.card title="Lần nộp #{{ $submittal->current_revision_no }} bị từ chối">
                    <div class="space-y-3">
                        <x-ui.field-value label="Người từ chối" :value="$submittal->rejectedBy?->name" />
                        <x-ui.field-value label="Thời gian" :value="optional($submittal->rejected_at)->format('d/m/Y H:i')" />
                        <div class="whitespace-pre-line text-slate-800">{{ $submittal->rejection_reason }}</div>
                        @if ($submittal->rejection_comments)
                            <div class="whitespace-pre-line text-sm text-slate-600">{{ $submittal->rejection_comments }}</div>
                        @endif
                    </div>
                </x-ui.card>
            @endif

            @can('update', $submittal)
                @if (in_array($submittal->status, ['draft', 'revising'], true))
                    <x-ui.card title="Sửa nội dung">
                        @if ($errors->submittalUpdate->any())
                            <div class="operator-error-list">
                                <ul class="space-y-1 text-sm">
                                    @foreach ($errors->submittalUpdate->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form id="submittal-edit-form" method="POST" action="{{ route('operator.submittals.update', $submittal->id) }}" class="space-y-5">
                            @csrf
                            @method('PUT')

                            <div class="operator-form-grid">
                                <div class="operator-field">
                                    <label for="submittal_type">Loại hồ sơ</label>
                                    <select id="submittal_type" name="submittal_type" class="operator-select">
                                        <option value="shop_drawing" @selected(old('submittal_type', $submittal->submittal_type) === 'shop_drawing')>Shop drawing</option>
                                        <option value="material_sample" @selected(old('submittal_type', $submittal->submittal_type) === 'material_sample')>Mẫu vật liệu</option>
                                        <option value="product_data" @selected(old('submittal_type', $submittal->submittal_type) === 'product_data')>Tài liệu sản phẩm</option>
                                        <option value="test_report" @selected(old('submittal_type', $submittal->submittal_type) === 'test_report')>Báo cáo thí nghiệm</option>
                                        <option value="other" @selected(old('submittal_type', $submittal->submittal_type) === 'other')>Khác</option>
                                    </select>
                                </div>

                                <div class="operator-field">
                                    <label for="specification_section">Mục spec</label>
                                    <input id="specification_section" name="specification_section" type="text" class="operator-input" value="{{ old('specification_section', $submittal->specification_section) }}">
                                </div>

                                <div class="operator-field">
                                    <label for="due_date">Hạn duyệt</label>
                                    <input id="due_date" name="due_date" type="date" class="operator-input" value="{{ old('due_date', optional($submittal->due_date)->format('Y-m-d')) }}">
                                </div>

                                <div class="operator-field">
                                    <label for="contractor">Nhà thầu</label>
                                    <select id="contractor" name="contractor" class="operator-select">
                                        <option value="">— Chọn nhà cung cấp —</option>
                                        @php $currentContractor = old('contractor', $submittal->contractor); @endphp
                                        @if ($currentContractor && !$vendors->pluck('name')->contains($currentContractor))
                                            <option value="{{ $currentContractor }}" selected>{{ $currentContractor }} (không còn hoạt động)</option>
                                        @endif
                                        @foreach ($vendors as $vendor)
                                            <option value="{{ $vendor->name }}" @selected($currentContractor === $vendor->name)>{{ $vendor->name }}{{ $vendor->code ? ' (' . $vendor->code . ')' : '' }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="operator-field">
                                    <label for="manufacturer">Nhà sản xuất</label>
                                    <select id="manufacturer" name="manufacturer" class="operator-select">
                                        <option value="">— Chọn nhà cung cấp —</option>
                                        @php $currentManufacturer = old('manufacturer', $submittal->manufacturer); @endphp
                                        @if ($currentManufacturer && !$vendors->pluck('name')->contains($currentManufacturer))
                                            <option value="{{ $currentManufacturer }}" selected>{{ $currentManufacturer }} (không còn hoạt động)</option>
                                        @endif
                                        @foreach ($vendors as $vendor)
                                            <option value="{{ $vendor->name }}" @selected($currentManufacturer === $vendor->name)>{{ $vendor->name }}{{ $vendor->code ? ' (' . $vendor->code . ')' : '' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="operator-field">
                                <label for="title">Tiêu đề <span class="text-rose-600">*</span></label>
                                <input id="title" name="title" type="text" class="operator-input" value="{{ old('title', $submittal->title) }}" maxlength="255" required>
                            </div>

                            <div class="operator-field">
                                <label for="description">Mô tả <span class="text-rose-600">*</span></label>
                                <textarea id="description" name="description" class="operator-textarea" required>{{ old('description', $submittal->description) }}</textarea>
                            </div>

                            <button type="submit" class="operator-button operator-button-primary">Lưu thay đổi</button>
                        </form>
                    </x-ui.card>
                @endif
            @endcan

            @if (in_array($submittal->status, ['submitted', 'pending_review'], true))
                <x-ui.card title="Xét duyệt">
                    @if ($errors->any())
                        <div class="operator-error-list">
                            <ul class="space-y-1 text-sm">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="space-y-6">
                        <form method="POST" action="{{ route('operator.submittals.approve', $submittal->id) }}" class="space-y-4">
                            @csrf
                            <div class="operator-field">
                                <label for="approval_comments">Ý kiến phê duyệt (không bắt buộc)</label>
                                <textarea id="approval_comments" name="approval_comments" class="operator-textarea">{{ old('approval_comments') }}</textarea>
                            </div>
                            <button type="submit" class="operator-button operator-button-primary">Phê duyệt</button>
                        </form>

                        <hr class="border-gray-200">

                        <form method="POST" action="{{ route('operator.submittals.reject', $submittal->id) }}" class="space-y-4">
                            @csrf
                            <div class="operator-field">
                                <label for="rejection_reason">Lý do từ chối <span class="text-rose-600">*</span></label>
                                <textarea id="rejection_reason" name="rejection_reason" class="operator-textarea" required>{{ old('rejection_reason') }}</textarea>
                            </div>
                            <button type="submit" class="operator-button operator-button-secondary">Từ chối</button>
                        </form>
                    </div>
                </x-ui.card>
            @endif
        </div>

        <div class="space-y-6">
            <x-ui.card title="Thông tin">
                <div class="space-y-4">
                    <x-ui.field-value label="Trạng thái">
                        <x-ui.status-badge :status="$submittal->status" />
                    </x-ui.field-value>
                    <x-ui.field-value label="Dự án" :value="($submittal->project?->name ?? '—') . ($submittal->project?->code ? ' (' . $submittal->project->code . ')' : '')" />
                    <x-ui.field-value label="Loại hồ sơ" :value="match($submittal->submittal_type) { 'shop_drawing' => 'Shop drawing', 'material_sample' => 'Mẫu vật liệu', 'product_data' => 'Tài liệu sản phẩm', 'test_report' => 'Báo cáo thí nghiệm', default => 'Khác' }" />
                    <x-ui.field-value label="Mục spec" :value="$submittal->specification_section" />
                    <x-ui.field-value label="Nhà thầu" :value="$submittal->contractor" />
                    <x-ui.field-value label="Nhà sản xuất" :value="$submittal->manufacturer" />
                    <x-ui.field-value label="Người trình" :value="$submittal->submittedBy?->name" />
                    <x-ui.field-value label="Hạn duyệt" :value="optional($submittal->due_date)->format('d/m/Y')" />
                    <x-ui.field-value label="Ngày tạo" :value="optional($submittal->created_at)->format('d/m/Y H:i')" />
                </div>
            </x-ui.card>

            <x-ui.card title="Thao tác">
                @can('submit', $submittal)
                    @if ($submittal->status === 'draft')
                        <form method="POST" action="{{ route('operator.submittals.submit', $submittal->id) }}">
                            @csrf
                            <button type="submit" class="operator-button operator-button-primary w-full">Gửi duyệt</button>
                        </form>
                    @endif

                    @if ($submittal->status === 'revising')
                        @if ($errors->submittalResubmit->any())
                            <div class="operator-error-list">
                                <ul class="space-y-1 text-sm">
                                    @foreach ($errors->submittalResubmit->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('operator.submittals.submit', $submittal->id) }}" class="space-y-3">
                            @csrf
                            <div class="operator-field">
                                <label for="revision_summary">Tóm tắt thay đổi <span class="text-rose-600">*</span></label>
                                <textarea id="revision_summary" name="revision_summary" class="operator-textarea" required>{{ old('revision_summary') }}</textarea>
                            </div>
                            <p id="unsaved-changes-warning" class="hidden text-sm text-rose-600">Bạn có thay đổi chưa lưu — bấm "Lưu thay đổi" trước.</p>
                            <button id="resubmit-button" type="submit" class="operator-button operator-button-primary w-full">Gửi lại</button>
                        </form>
                    @endif
                @endcan

                @can('startRevision', $submittal)
                    @if ($submittal->status === 'rejected')
                        <form method="POST" action="{{ route('operator.submittals.start-revision', $submittal->id) }}">
                            @csrf
                            <button type="submit" class="operator-button operator-button-primary w-full">Mở lại để sửa</button>
                        </form>
                    @endif
                @endcan
            </x-ui.card>
        </div>
    </div>

    @if ($submittal->status === 'revising')
        <script>
        (function () {
          var editForm = document.getElementById('submittal-edit-form');
          var resubmitBtn = document.getElementById('resubmit-button');
          var warning = document.getElementById('unsaved-changes-warning');
          if (!editForm || !resubmitBtn) return;

          var IGNORED_FIELDS = ['_token', '_method'];

          function snapshot(form) {
            var data = {};
            Array.prototype.forEach.call(form.elements, function (el) {
              if (!el.name || IGNORED_FIELDS.indexOf(el.name) !== -1) return;
              if (el.type === 'submit' || el.type === 'button') return;
              data[el.name] = (el.value || '').trim();
            });
            return data;
          }

          var initial = snapshot(editForm);

          function isDirty() {
            var current = snapshot(editForm);
            for (var key in initial) {
              if (initial[key] !== current[key]) return true;
            }
            return false;
          }

          function refresh() {
            var dirty = isDirty();
            resubmitBtn.disabled = dirty;
            resubmitBtn.classList.toggle('opacity-50', dirty);
            resubmitBtn.classList.toggle('cursor-not-allowed', dirty);
            if (warning) warning.classList.toggle('hidden', !dirty);
          }

          editForm.addEventListener('input', refresh);
          editForm.addEventListener('change', refresh);
          refresh();
        })();
        </script>
    @endif
@endsection
```

Notes on what changed versus the old file: the old unconditional `@if ($submittal->rejection_reason)` "Lý do từ chối" card is replaced by the status-gated, richer version (spec §5). The old unconditional `@if ($submittal->status === 'draft')` submit button is now inside `@can('submit', $submittal)`. The `Thao tác` card now branches on three states (`draft`, `revising`, `rejected`) instead of one. `$errors->submittalUpdate` / `$errors->submittalResubmit` are Laravel's `ViewErrorBag` magic-property access for named bags — both resolve to an empty `MessageBag` (never null, never an error) when that bag has no errors, so `->any()` and `->all()` are always safe to call even before either form has ever been submitted.

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Feature/Zena/SubmittalShowPageViewTest.php`
Expected: `OK (5 tests, ...)`

- [ ] **Step 5: Run every other Submittal-related test file to confirm nothing else broke**

Run: `./vendor/bin/phpunit tests/Feature/Api/SubmittalApiTest.php tests/Feature/Api/SubmittalShowApiTest.php tests/Feature/Api/SubmittalResubmitLifecycleTest.php tests/Feature/Api/SubmittalContentRulesRegressionTest.php tests/Feature/Zena/OperatorSubmittalUiTest.php tests/Feature/Zena/SubmittalUpdatePageTest.php tests/Feature/Zena/SubmittalStartRevisionPageTest.php tests/Feature/Zena/SubmittalShowPageViewTest.php tests/Feature/Services/SubmittalLifecycleServiceSubmitTest.php tests/Feature/Services/SubmittalLifecycleServiceDecisionTest.php tests/Unit/Models/SubmittalStateMachineTest.php tests/Unit/Migrations/SubmittalRevisionsSchemaTest.php tests/Unit/Policies/SubmittalPolicyTest.php`
Expected: all green.

- [ ] **Step 6: Commit**

```bash
git add resources/views/submittals/show.blade.php tests/Feature/Zena/SubmittalShowPageViewTest.php
git commit -m "feat(submittal): wire show.blade.php to update/startRevision, add rejection-info card and dirty-form JS"
```

---

### Task 6: Full end-to-end reject → reopen → edit → resubmit → approve flow + revision immutability

**Files:**
- Test: `tests/Feature/Zena/SubmittalResubmitWebFlowTest.php` (new)

**Interfaces:**
- Consumes: everything from Tasks 1–5 through the actual HTTP routes (no direct service calls) — this is the end-to-end proof that the pieces work together, covering spec §8 items 1, 3, 4, 5, 6 precisely.

- [ ] **Step 1: Write the failing test**

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Project;
use App\Models\Submittal;
use App\Models\SubmittalRevision;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class SubmittalResubmitWebFlowTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    public function test_full_reject_reopen_edit_resubmit_approve_flow_via_web_routes(): void
    {
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser(
            $tenant,
            [],
            ['admin'],
            ['submittal.view', 'submittal.create', 'submittal.edit', 'submittal.submit', 'submittal.approve', 'submittal.reject']
        );
        $project = Project::factory()->create(['tenant_id' => (string) $tenant->id, 'code' => 'PRJ-FLOW-001']);
        $headers = ['X-Tenant-ID' => (string) $tenant->id];

        // 1. Create as draft, submit (revision 1).
        $create = $this->actingAs($user)->post(route('operator.submittals.store'), [
            'project_id' => (string) $project->id,
            'title' => 'Steel connection detail',
            'description' => 'Initial submission for review.',
            'submittal_type' => 'shop_drawing',
        ], $headers);
        $submittal = Submittal::query()->where('title', 'Steel connection detail')->firstOrFail();
        $create->assertRedirect(route('operator.submittals.show', $submittal->id));

        $this->actingAs($user)->post(route('operator.submittals.submit', $submittal->id), [], $headers)
            ->assertRedirect(route('operator.submittals.show', $submittal->id));

        // 2. Reject.
        $this->actingAs($user)->post(route('operator.submittals.reject', $submittal->id), [
            'rejection_reason' => 'Missing weld callouts',
        ], $headers)->assertRedirect(route('operator.submittals.show', $submittal->id));

        $submittal->refresh();
        $this->assertSame('rejected', $submittal->status);
        $revisionOneSnapshotBefore = SubmittalRevision::query()->where('submittal_id', $submittal->id)->where('revision_no', 1)->firstOrFail()->toArray();

        // 3. Reopen for revision — must NOT create a second revision row yet.
        $this->actingAs($user)->post(route('operator.submittals.start-revision', $submittal->id), [], $headers)
            ->assertRedirect(route('operator.submittals.show', $submittal->id));

        $submittal->refresh();
        $this->assertSame('revising', $submittal->status);
        $this->assertSame(1, SubmittalRevision::query()->where('submittal_id', $submittal->id)->count());

        // 4. Edit content while revising — must not touch the rejected revision row.
        $this->actingAs($user)->put(route('operator.submittals.update', $submittal->id), [
            'title' => 'Steel connection detail (revised)',
            'description' => 'Added weld callouts per comments.',
        ], $headers)->assertRedirect(route('operator.submittals.show', $submittal->id));

        $submittal->refresh();
        $this->assertSame('Steel connection detail (revised)', $submittal->title);
        $revisionOneSnapshotAfterEdit = SubmittalRevision::query()->where('submittal_id', $submittal->id)->where('revision_no', 1)->firstOrFail()->toArray();
        unset($revisionOneSnapshotBefore['updated_at'], $revisionOneSnapshotAfterEdit['updated_at']);
        $this->assertSame($revisionOneSnapshotBefore, $revisionOneSnapshotAfterEdit, 'Editing while revising must not mutate the rejected revision row.');

        // 5. Rejection reason must still display after the edit (still revising).
        $show = $this->actingAs($user)->get(route('operator.submittals.show', $submittal->id), $headers);
        $show->assertSee('Missing weld callouts');

        // 6. Resubmit with revision_summary — creates revision 2, revision 1 stays untouched.
        $this->actingAs($user)->post(route('operator.submittals.submit', $submittal->id), [
            'revision_summary' => 'Added weld callouts to all connection points',
        ], $headers)->assertRedirect(route('operator.submittals.show', $submittal->id));

        $submittal->refresh();
        $this->assertSame('submitted', $submittal->status);
        $this->assertSame(2, $submittal->current_revision_no);
        $this->assertSame(2, SubmittalRevision::query()->where('submittal_id', $submittal->id)->count());

        $revisionTwo = SubmittalRevision::query()->where('submittal_id', $submittal->id)->where('revision_no', 2)->firstOrFail();
        $this->assertSame('Steel connection detail (revised)', $revisionTwo->title);
        $this->assertSame('Added weld callouts to all connection points', $revisionTwo->revision_summary);
        $this->assertNull($revisionTwo->decision);

        $revisionOneFinal = SubmittalRevision::query()->where('submittal_id', $submittal->id)->where('revision_no', 1)->firstOrFail()->toArray();
        unset($revisionOneFinal['updated_at']);
        $this->assertSame($revisionOneSnapshotBefore, $revisionOneFinal, 'Revision 1 must remain byte-identical through the entire resubmit flow.');

        // 7. Approve the resubmission.
        $this->actingAs($user)->post(route('operator.submittals.approve', $submittal->id), [], $headers)
            ->assertRedirect(route('operator.submittals.show', $submittal->id));

        $submittal->refresh();
        $this->assertSame('approved', $submittal->status);

        $eventKeys = DB::table('event_records')
            ->where('aggregate_type', 'submittal')
            ->where('aggregate_id', $submittal->id)
            ->orderBy('occurred_at')
            ->pluck('event_key')
            ->all();
        $this->assertSame(
            ['submittal.submitted', 'submittal.rejected', 'submittal.revision_started', 'submittal.content_updated', 'submittal.resubmitted', 'submittal.approved'],
            $eventKeys
        );
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `./vendor/bin/phpunit tests/Feature/Zena/SubmittalResubmitWebFlowTest.php`
Expected: FAIL at whichever assertion hits code Tasks 1-5 haven't added yet — if Tasks 1-5 are already done (this task runs after them), this should actually mostly pass already; if any single assertion fails, it indicates one of the earlier tasks has a gap this end-to-end test catches that the per-task tests didn't. Do not skip straight to "it must be a test bug" — investigate against the actual `SubmittalLifecycleService`/`SubmittalPageController` code first.

- [ ] **Step 3: Fix whatever the failure reveals, or confirm it already passes**

If Tasks 1-5 were implemented exactly as specified, this test should pass without further code changes — it is an integration proof, not a driver of new functionality. If it fails, the fix belongs in whichever Task's file is implicated (do not weaken this test's assertions to make it pass).

- [ ] **Step 4: Run to verify it passes**

Run: `./vendor/bin/phpunit tests/Feature/Zena/SubmittalResubmitWebFlowTest.php`
Expected: `OK (1 test, ...)`

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/Zena/SubmittalResubmitWebFlowTest.php
git commit -m "test(submittal): add full reject-reopen-edit-resubmit-approve web flow integration test"
```

---

### Task 7: Dirty-state Dusk test + manual acceptance checklist

**Files:**
- Create: `tests/Browser/SubmittalResubmitDirtyStateTest.php`
- Modify: `docs/superpowers/plans/2026-07-25-gap-029-submittal-resubmit-ui.md` (this file — append the manual checklist as a completed deliverable, see Step 3)

**Interfaces:**
- Consumes: the `revising`-status page from Task 5, specifically element ids `submittal-edit-form`, `resubmit-button`, `unsaved-changes-warning`.

- [ ] **Step 1: Write the Dusk test**

```php
<?php declare(strict_types=1);

namespace Tests\Browser;

use App\Models\Project;
use App\Models\Submittal;
use App\Models\SubmittalRevision;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Tests\Traits\TenantUserFactoryTrait;

class SubmittalResubmitDirtyStateTest extends DuskTestCase
{
    use TenantUserFactoryTrait;

    public function test_resubmit_button_disables_while_edit_form_is_dirty_and_reenables_after_save(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser(
            $tenant,
            ['password' => \Illuminate\Support\Facades\Hash::make('password')],
            ['admin'],
            ['submittal.view', 'submittal.edit', 'submittal.submit']
        );
        $project = Project::factory()->create(['tenant_id' => (string) $tenant->id]);

        $submittal = Submittal::query()->create([
            'id' => (string) Str::ulid(),
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'title' => 'Dusk dirty-state submittal',
            'description' => 'Original description',
            'submittal_type' => 'shop_drawing',
            'status' => 'revising',
            'current_revision_no' => 1,
            'submitted_by' => (string) $user->id,
            'submittal_number' => 'SUB-DUSK-001',
            'rejection_reason' => 'Fix the title',
        ]);

        SubmittalRevision::query()->create([
            'tenant_id' => (string) $tenant->id,
            'submittal_id' => $submittal->id,
            'revision_no' => 1,
            'title' => 'Dusk dirty-state submittal',
            'description' => 'Original description',
            'submitted_by' => (string) $user->id,
            'submitted_at' => now(),
            'decision' => 'rejected',
            'decided_by' => (string) $user->id,
            'decided_at' => now(),
            'created_at' => now(),
        ]);

        $this->browse(function (Browser $browser) use ($user, $submittal) {
            $browser->loginAs($user)
                ->visit(route('operator.submittals.show', $submittal->id))
                ->assertVisible('#resubmit-button')
                ->assertNotDisabled('#resubmit-button')
                ->type('#title', ' edited')
                ->assertDisabled('#resubmit-button')
                ->assertVisible('#unsaved-changes-warning')
                ->press('Lưu thay đổi')
                ->waitForLocation('/submittals/' . $submittal->id)
                ->assertNotDisabled('#resubmit-button');
        });
    }
}
```

- [ ] **Step 2: Run it**

Run: `php artisan dusk --filter=SubmittalResubmitDirtyStateTest`
Expected: `OK (1 test, ...)`. If Dusk's Chromedriver isn't running locally, this is exactly what the existing `browser-tests` CI job (confirmed present and green as of PR#229 — `.github/workflows/*` job named `browser-tests`) will run instead; don't block on local Dusk setup if the project's usual pattern is CI-verified Dusk.

- [ ] **Step 3: Append the manual acceptance checklist to this plan file's end (documentation, not code)**

Add this section at the very end of `docs/superpowers/plans/2026-07-25-gap-029-submittal-resubmit-ui.md`:

```markdown
## Manual acceptance checklist (spec §9)

- [ ] Open a `rejected` submittal as a user with `submittal.submit`: "Mở lại để sửa" is visible and clickable; after clicking, status shows `revising`, "Sửa nội dung" card appears pre-filled with the last-submitted (rejected) content.
- [ ] Edit a field in "Sửa nội dung", observe "Gửi lại" become disabled and the warning line appear, without reloading the page.
- [ ] Click "Lưu thay đổi", observe redirect back to the same page with a success flash, "Gửi lại" re-enabled, warning gone.
- [ ] Type into `revision_summary`, click "Gửi lại": status returns to `submitted`.
- [ ] As a user with only `submittal.view` (no `submittal.edit`/`submittal.submit`): open the same submittal, confirm none of "Sửa nội dung"/"Mở lại để sửa"/"Gửi lại" render.
- [ ] Select a vendor from the `contractor` dropdown, save, then deactivate that vendor (`is_active = false`) directly in DB/admin, reload the edit page: confirm the current vendor still shows selected (as a synthesized "(không còn hoạt động)" option) rather than blank.
```

- [ ] **Step 4: Commit**

```bash
git add tests/Browser/SubmittalResubmitDirtyStateTest.php docs/superpowers/plans/2026-07-25-gap-029-submittal-resubmit-ui.md
git commit -m "test(submittal): add Dusk dirty-state test, record manual acceptance checklist"
```

---

## Post-plan verification

Run the complete Submittal-related suite one final time across everything this plan and PR#229 touch:

```bash
./vendor/bin/phpunit tests/Feature/Api/SubmittalApiTest.php tests/Feature/Api/SubmittalShowApiTest.php tests/Feature/Api/SubmittalResubmitLifecycleTest.php tests/Feature/Api/SubmittalContentRulesRegressionTest.php tests/Feature/Zena/OperatorSubmittalUiTest.php tests/Feature/Zena/SubmittalUpdatePageTest.php tests/Feature/Zena/SubmittalStartRevisionPageTest.php tests/Feature/Zena/SubmittalShowPageViewTest.php tests/Feature/Zena/SubmittalResubmitWebFlowTest.php tests/Feature/Services/SubmittalLifecycleServiceSubmitTest.php tests/Feature/Services/SubmittalLifecycleServiceDecisionTest.php tests/Unit/Models/SubmittalStateMachineTest.php tests/Unit/Migrations/SubmittalRevisionsSchemaTest.php tests/Unit/Policies/SubmittalPolicyTest.php
grep -rn "ApiSubmittalController" app/Http/Controllers/Web/SubmittalPageController.php
```

Expected: full suite green; the `grep` should show exactly 4 remaining usages (`store`, `submit`, `approve`, `reject` — the untouched proxied methods per spec §10), confirming `update()`/`startRevision()` never reference `ApiSubmittalController`.

## Manual acceptance checklist (spec §9)

- [ ] Open a `rejected` submittal as a user with `submittal.submit`: "Mở lại để sửa" is visible and clickable; after clicking, status shows `revising`, "Sửa nội dung" card appears pre-filled with the last-submitted (rejected) content.
- [ ] Edit a field in "Sửa nội dung", observe "Gửi lại" become disabled and the warning line appear, without reloading the page.
- [ ] Click "Lưu thay đổi", observe redirect back to the same page with a success flash, "Gửi lại" re-enabled, warning gone.
- [ ] Type into `revision_summary`, click "Gửi lại": status returns to `submitted`.
- [ ] As a user with only `submittal.view` (no `submittal.edit`/`submittal.submit`): open the same submittal, confirm none of "Sửa nội dung"/"Mở lại để sửa"/"Gửi lại" render.
- [ ] Select a vendor from the `contractor` dropdown, save, then deactivate that vendor (`is_active = false`) directly in DB/admin, reload the edit page: confirm the current vendor still shows selected (as a synthesized "(không còn hoạt động)" option) rather than blank.
