# Site Diary Autofill Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** When creating a new Site Diary entry, prefill `weather`, `temperature`, `manpower_count`, and `equipment_used` from the same project's most recent diary, while leaving day-specific fields (`work_performed`, `materials_delivered`, `safety_notes`, `visitors`, `delays_issues`) untouched.

**Architecture:** `SiteDiaryPageController@create()` computes a `$autofillByProject` map (project_id → last-known values for the 4 autofill fields) and passes it to the view. `resources/views/site-diaries/create.blade.php` embeds this map via `@js()` and uses plain vanilla JS (no framework) to fill the 4 fields when the project dropdown changes, guarded by a per-field "touched" flag so it never overwrites something the user already typed.

**Tech Stack:** Laravel 12 (Blade, Eloquent Collections), vanilla JS (no Alpine — `layouts.operator` does not load it), PHPUnit feature tests.

## Global Constraints

- No new routes, API endpoints, migrations, or permissions — everything happens inside the existing `create()` action and its view.
- Autofill fields are exactly: `weather`, `temperature`, `manpower_count`, `equipment_used`. Do not autofill `work_performed`, `materials_delivered`, `safety_notes`, `visitors`, `delays_issues`, `diary_date`, or `project_id`.
- "Most recent diary" = ordered by `diary_date` desc, then `created_at` desc, regardless of `status` (draft/submitted/approved all count).
- Do not use Alpine.js. `layouts/operator.blade.php` never loads it and `package.json` has no `alpinejs` dependency — use vanilla JS scoped in a `<script>` block in the view.
- Embed server data into JS via Blade's `@js()` helper, never raw `json_encode()` interpolation.
- Tenant isolation: `autofillByProject` must never contain data from another tenant's `SiteDiary` records.
- Spec: `docs/superpowers/specs/2026-07-21-site-diary-autofill-design.md`

---

### Task 1: Expose `autofillByProject` from the create() controller action

**Files:**
- Modify: `app/Http/Controllers/Web/SiteDiaryPageController.php:53-65` (the `create()` method)
- Test: `tests/Feature/Zena/OperatorSiteOpsUiTest.php`

**Interfaces:**
- Produces: view variable `autofillByProject` — a PHP associative array `[string $projectId => ['weather' => ?string, 'temperature' => ?string, 'manpower_count' => int, 'equipment_used' => ?string]]`. A project with no prior `SiteDiary` has no key in this array. This is the exact shape Task 2's JS consumes (via `@js($autofillByProject)`).

- [ ] **Step 1: Write the failing test**

Add this test method to `tests/Feature/Zena/OperatorSiteOpsUiTest.php` (inside the `OperatorSiteOpsUiTest` class, after `test_site_diary_pages_load_and_full_workflow_executes`):

```php
    public function test_create_page_exposes_autofill_from_most_recent_diary_per_project(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        SiteDiary::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'diary_number' => 'SD-OLD-001',
            'diary_date' => '2026-07-01',
            'weather' => 'Mưa',
            'temperature' => '24-26°C',
            'manpower_count' => 10,
            'work_performed' => 'Đổ móng',
            'equipment_used' => 'Máy trộn bê tông',
            'status' => SiteDiary::STATUS_DRAFT,
            'created_by' => (string) $this->user->id,
        ]);

        SiteDiary::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'diary_number' => 'SD-NEW-001',
            'diary_date' => '2026-07-08',
            'weather' => 'Nắng',
            'temperature' => '30-34°C',
            'manpower_count' => 25,
            'work_performed' => 'Lắp dựng cốp pha',
            'equipment_used' => 'Cần cẩu tháp',
            'status' => SiteDiary::STATUS_DRAFT,
            'created_by' => (string) $this->user->id,
        ]);

        $projectWithoutDiary = Project::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'name' => 'Project Without Diary',
            'code' => 'PRJ-ND-001',
        ]);

        $foreignTenant = Tenant::factory()->create();
        $foreignProject = Project::factory()->create([
            'tenant_id' => (string) $foreignTenant->id,
            'name' => 'Foreign Project',
            'code' => 'PRJ-FT-001',
        ]);
        $foreignUser = $this->createTenantUser($foreignTenant, [], ['admin'], ['site_diary.view']);
        SiteDiary::query()->create([
            'tenant_id' => (string) $foreignTenant->id,
            'project_id' => (string) $foreignProject->id,
            'diary_number' => 'SD-FOREIGN-001',
            'diary_date' => '2026-07-08',
            'weather' => 'Foreign weather',
            'work_performed' => 'Foreign work',
            'status' => SiteDiary::STATUS_DRAFT,
            'created_by' => (string) $foreignUser->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('operator.site-diaries.create'), $headers)
            ->assertOk();

        $autofill = $response->viewData('autofillByProject');

        $this->assertSame([
            'weather' => 'Nắng',
            'temperature' => '30-34°C',
            'manpower_count' => 25,
            'equipment_used' => 'Cần cẩu tháp',
        ], $autofill[(string) $this->project->id]);

        $this->assertArrayNotHasKey((string) $projectWithoutDiary->id, $autofill);
        $this->assertArrayNotHasKey((string) $foreignProject->id, $autofill);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit --filter test_create_page_exposes_autofill_from_most_recent_diary_per_project tests/Feature/Zena/OperatorSiteOpsUiTest.php`
Expected: FAIL — `autofillByProject` is undefined in the view data (undefined array key, or `viewData()` returns null and the `assertSame` fails).

- [ ] **Step 3: Implement the controller change**

Replace the `create()` method in `app/Http/Controllers/Web/SiteDiaryPageController.php`:

```php
    public function create(): View
    {
        $this->authorize('create', SiteDiary::class);

        $tenantId = (string) Auth::user()?->tenant_id;

        $autofillByProject = SiteDiary::query()
            ->forTenant($tenantId)
            ->orderByDesc('diary_date')
            ->orderByDesc('created_at')
            ->get(['project_id', 'weather', 'temperature', 'manpower_count', 'equipment_used'])
            ->groupBy('project_id')
            ->map(fn ($diaries) => $diaries->first())
            ->mapWithKeys(fn (SiteDiary $diary, string $projectId) => [
                $projectId => [
                    'weather' => $diary->weather,
                    'temperature' => $diary->temperature,
                    'manpower_count' => $diary->manpower_count,
                    'equipment_used' => $diary->equipment_used,
                ],
            ])
            ->all();

        return view('site-diaries.create', [
            'projects' => Project::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get(['id', 'tenant_id', 'name', 'code']),
            'autofillByProject' => $autofillByProject,
        ]);
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit --filter test_create_page_exposes_autofill_from_most_recent_diary_per_project tests/Feature/Zena/OperatorSiteOpsUiTest.php`
Expected: PASS

- [ ] **Step 5: Run the full test class to check for regressions**

Run: `./vendor/bin/phpunit tests/Feature/Zena/OperatorSiteOpsUiTest.php`
Expected: All tests PASS (5 tests: the 4 pre-existing plus the new one).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Web/SiteDiaryPageController.php tests/Feature/Zena/OperatorSiteOpsUiTest.php
git commit -m "feat(site-diary): expose autofillByProject from create() controller"
```

---

### Task 2: Prefill the 4 fields in the create view with touched-guarded vanilla JS

**Files:**
- Modify: `resources/views/site-diaries/create.blade.php`

**Interfaces:**
- Consumes: `$autofillByProject` from Task 1, an array `[projectId => ['weather' => ?string, 'temperature' => ?string, 'manpower_count' => int, 'equipment_used' => ?string]]`.
- Consumes: existing field IDs already present in the view — `#project_id`, `#weather`, `#temperature`, `#manpower_count`, `#equipment_used` (all already defined with these exact `id` attributes in the current file, no HTML id changes needed).

This task has no automated test (per spec: no JS test infrastructure exists in this repo). Verification is the full existing test suite (to confirm the view still renders) plus a manual browser check.

- [ ] **Step 1: Add the autofill script to the view**

In `resources/views/site-diaries/create.blade.php`, insert a `<script>` block immediately before the closing `</x-ui.card>` tag (i.e., right after the `</form>` closing tag, still inside the card, before line 96's `</x-ui.card>`):

```blade
        </form>

        <script>
            (function () {
                var autofillByProject = @js($autofillByProject);
                var fieldIds = ['weather', 'temperature', 'manpower_count', 'equipment_used'];
                var touched = {};

                fieldIds.forEach(function (fieldId) {
                    touched[fieldId] = false;
                    var el = document.getElementById(fieldId);
                    if (!el) {
                        return;
                    }
                    el.addEventListener('input', function () {
                        touched[fieldId] = true;
                    });
                });

                var projectSelect = document.getElementById('project_id');
                if (!projectSelect) {
                    return;
                }

                projectSelect.addEventListener('change', function () {
                    var data = autofillByProject[projectSelect.value];
                    if (!data) {
                        return;
                    }

                    fieldIds.forEach(function (fieldId) {
                        if (touched[fieldId]) {
                            return;
                        }
                        if (!(fieldId in data) || data[fieldId] === null) {
                            return;
                        }

                        var el = document.getElementById(fieldId);
                        if (el) {
                            el.value = data[fieldId];
                        }
                    });
                });
            })();
        </script>
    </x-ui.card>
```

(The final `</x-ui.card>` line replaces the original standalone `</x-ui.card>` on line 96 — there should still be exactly one closing tag for the card, immediately after the new `<script>` block.)

- [ ] **Step 2: Run the full existing test suite for this feature to confirm no regressions**

Run: `./vendor/bin/phpunit tests/Feature/Zena/OperatorSiteOpsUiTest.php`
Expected: All 5 tests PASS (the view still renders correctly with the new script block; `assertSee('Thông tin nhật ký')` and the full site-diary workflow test still succeed).

- [ ] **Step 3: Manual browser verification**

Start the app locally and, logged in as a user with `site_diary.create` permission:

1. Navigate to a project's site diary create page. Confirm all 4 autofill fields (`weather`, `temperature`, `manpower_count`, `equipment_used`) start empty (no project selected yet, or project has no prior diary).
2. Create one diary for a project (fill weather="Nắng", temperature="30-34°C", manpower_count=20, equipment_used="Máy đào", work_performed="test") and submit.
3. Return to the create page, select the same project from the dropdown. Confirm the 4 fields auto-populate with the values from step 2.
4. Manually edit `weather` to a different value, then reselect the same project from the dropdown (trigger `change` again). Confirm `weather` keeps your manually-typed value (not overwritten), while the other 3 untouched fields still reflect the autofill data.
5. Select a different project with no prior diary. Confirm the 4 fields are left as-is (not cleared, not overwritten) since there's no data for that project.
6. Submit the create form with a validation error (e.g. leave `work_performed` empty). Confirm on the redisplayed form that your typed values in the autofill fields are preserved via `old()` and not clobbered by the autofill script.

- [ ] **Step 4: Commit**

```bash
git add resources/views/site-diaries/create.blade.php
git commit -m "feat(site-diary): autofill weather/temperature/manpower/equipment from last diary"
```

---

## Out of Scope (confirmed in spec, not part of this plan)

- Data-entry priorities #3 (Vendor select for RFI/Submittal), #4 (auto-generated codes), #5 (cleaning up dead `/templates` page) — separate future plans.
- The suspected dead Alpine.js code in `resources/views/projects/_apply-work-template.blade.php` (PR#210) — flagged as a separate technical debt item, not touched here.
- No changes to `site_diaries` schema or `App\Http\Controllers\Api\SiteDiaryController`.
