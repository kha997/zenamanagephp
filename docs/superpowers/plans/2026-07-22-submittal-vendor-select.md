# Submittal Vendor Select Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the two free-text inputs `contractor`/`manufacturer` on the Submittal create form with plain `<select>` dropdowns listing the tenant's active Vendors, plus an "+ Thêm nhà cung cấp" link, with tenant-scoped server-side validation.

**Architecture:** `SubmittalPageController::create()` passes `$vendors` (active, tenant-scoped, ordered by name) to the view; the view renders two selects whose option **value is the vendor NAME** (DB columns stay `string` — zero migration, legacy free-text rows unaffected, show page unchanged); `store()` validates both fields against the tenant's vendor names via `Rule::exists` with a tenant scope. No JavaScript.

**Tech Stack:** Laravel 12 Blade, `Illuminate\Validation\Rule::exists`, PHPUnit feature tests.

**Spec:** `docs/superpowers/specs/2026-07-22-submittal-vendor-select-design.md`

## Global Constraints

- Submit and store the vendor **name** (string), never the id. No migration, no new columns, no changes to `submittals/show.blade.php` or the `Submittal` model.
- Both selects share one vendor list: `Vendor` where `tenant_id` = current user's tenant AND `is_active` = true, ordered by `name`. Option label format: `{{ $vendor->name }}{{ $vendor->code ? ' (' . $vendor->code . ')' : '' }}`; option value: `{{ $vendor->name }}` only.
- Both fields remain `nullable` — empty option `— Chọn nhà cung cấp —` with `value=""` first.
- The "+ Thêm nhà cung cấp" link points to `route('operator.vendors.create')`, `target="_blank"`, and renders ONLY when `auth()->user()?->hasPermission('vendor.create')`.
- No JavaScript, no Alpine (operator layout does not load Alpine — guarded by `OperatorLayoutAlpineGuardTest`).
- RFI is out of scope — it has no contractor/manufacturer fields.
- Tests go in the existing `tests/Feature/Zena/OperatorSubmittalUiTest.php` (matches its conventions: `TenantUserFactoryTrait`, `rbac` alias in setUp, `X-Tenant-ID` headers).
- Test invocation: `./vendor/bin/phpunit <path>` directly, never `php artisan test`. CI is the source of truth for PHPStan; if "Code Quality Analysis"/"Security Tests" fail on new findings, add surgical entries to `phpstan-baseline.neon` (single quotes inside single-quoted neon strings must be doubled `''`).
- `Vendor` has NO factory — create rows with `Vendor::query()->create([...])` (precedent: `OperatorSiteOpsUiTest::test_global_search_finds_records_across_modules`). Fillable: `tenant_id, code, name, contact_name, email, phone, address, is_active`.

---

### Task 1: Vendor list in create() + selects in the view

**Files:**
- Modify: `app/Http/Controllers/Web/SubmittalPageController.php:46-56` (the `create()` method)
- Modify: `resources/views/submittals/create.blade.php:65-74` (the two text-input fields)
- Test: `tests/Feature/Zena/OperatorSubmittalUiTest.php`

**Interfaces:**
- Produces: view variable `$vendors` — Eloquent collection of `Vendor` models (`id, tenant_id, code, name` selected), active + tenant-scoped + name-ordered. Task 2's validation is independent of this variable but relies on the same semantic (tenant's vendor names are the only valid values).

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/Zena/OperatorSubmittalUiTest.php` (inside the class, after the existing test methods). Also add `use App\Models\Vendor;` to the imports at the top of the file.

```php
    public function test_create_page_lists_active_tenant_vendors_in_selects(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        Vendor::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'code' => 'VD-001',
            'name' => 'Công ty Thép Hòa Phát',
            'is_active' => true,
        ]);
        Vendor::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'code' => 'VD-002',
            'name' => 'Vendor Ngừng Hoạt Động',
            'is_active' => false,
        ]);

        $foreignTenant = Tenant::factory()->create();
        Vendor::query()->create([
            'tenant_id' => (string) $foreignTenant->id,
            'code' => 'VD-F01',
            'name' => 'Vendor Tenant Khác',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('operator.submittals.create'), $headers)
            ->assertOk();

        $response->assertSee('Công ty Thép Hòa Phát (VD-001)');
        $response->assertSee('— Chọn nhà cung cấp —');
        $response->assertDontSee('Vendor Ngừng Hoạt Động');
        $response->assertDontSee('Vendor Tenant Khác');
        // 2 field đã là select, không còn input text tự do.
        $response->assertSee('<select id="contractor"', false);
        $response->assertSee('<select id="manufacturer"', false);
        $response->assertDontSee('<input id="contractor"', false);
        $response->assertDontSee('<input id="manufacturer"', false);
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `./vendor/bin/phpunit --filter test_create_page_lists_active_tenant_vendors_in_selects tests/Feature/Zena/OperatorSubmittalUiTest.php`
Expected: FAIL — `assertSee('Công ty Thép Hòa Phát (VD-001)')` fails (view has no vendor list yet) and `assertSee('<select id="contractor"')` fails (still a text input).

- [ ] **Step 3: Update the controller**

In `app/Http/Controllers/Web/SubmittalPageController.php`, add `use App\Models\Vendor;` to the imports, then replace `create()`:

```php
    public function create(): View
    {
        $tenantId = (string) Auth::user()?->tenant_id;

        return view('submittals.create', [
            'projects' => Project::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get(['id', 'tenant_id', 'name', 'code']),
            'vendors' => Vendor::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'tenant_id', 'code', 'name']),
        ]);
    }
```

- [ ] **Step 4: Update the view**

In `resources/views/submittals/create.blade.php`, replace the two field blocks (currently lines 65-74):

```blade
                <div class="operator-field">
                    <label for="contractor">Nhà thầu</label>
                    <input id="contractor" name="contractor" type="text" class="operator-input" value="{{ old('contractor') }}">
                </div>

                <div class="operator-field">
                    <label for="manufacturer">Nhà sản xuất</label>
                    <input id="manufacturer" name="manufacturer" type="text" class="operator-input" value="{{ old('manufacturer') }}">
                </div>
```

with:

```blade
                <div class="operator-field">
                    <label for="contractor">Nhà thầu</label>
                    <select id="contractor" name="contractor" class="operator-select">
                        <option value="">— Chọn nhà cung cấp —</option>
                        @foreach ($vendors as $vendor)
                            <option value="{{ $vendor->name }}" @selected(old('contractor') === $vendor->name)>{{ $vendor->name }}{{ $vendor->code ? ' (' . $vendor->code . ')' : '' }}</option>
                        @endforeach
                    </select>
                    @if (auth()->user()?->hasPermission('vendor.create'))
                        <a href="{{ route('operator.vendors.create') }}" target="_blank" class="text-sm text-teal-700 hover:underline">+ Thêm nhà cung cấp</a>
                    @endif
                    @error('contractor')<span class="text-sm text-rose-600">{{ $message }}</span>@enderror
                </div>

                <div class="operator-field">
                    <label for="manufacturer">Nhà sản xuất</label>
                    <select id="manufacturer" name="manufacturer" class="operator-select">
                        <option value="">— Chọn nhà cung cấp —</option>
                        @foreach ($vendors as $vendor)
                            <option value="{{ $vendor->name }}" @selected(old('manufacturer') === $vendor->name)>{{ $vendor->name }}{{ $vendor->code ? ' (' . $vendor->code . ')' : '' }}</option>
                        @endforeach
                    </select>
                    @if (auth()->user()?->hasPermission('vendor.create'))
                        <a href="{{ route('operator.vendors.create') }}" target="_blank" class="text-sm text-teal-700 hover:underline">+ Thêm nhà cung cấp</a>
                    @endif
                    @error('manufacturer')<span class="text-sm text-rose-600">{{ $message }}</span>@enderror
                </div>
```

- [ ] **Step 5: Run the test to verify it passes, plus the whole existing class**

Run: `./vendor/bin/phpunit tests/Feature/Zena/OperatorSubmittalUiTest.php`
Expected: ALL PASS — including the pre-existing full-flow test (its `store` POST omits contractor/manufacturer entirely, which stays valid since both remain nullable).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Web/SubmittalPageController.php resources/views/submittals/create.blade.php tests/Feature/Zena/OperatorSubmittalUiTest.php
git commit -m "feat(submittals): vendor select for contractor/manufacturer on create form"
```

---

### Task 2: Tenant-scoped exists validation in store()

**Files:**
- Modify: `app/Http/Controllers/Web/SubmittalPageController.php:60-68` (the `store()` validation rules)
- Test: `tests/Feature/Zena/OperatorSubmittalUiTest.php`

**Interfaces:**
- Consumes: the semantic from Task 1 — valid values for both fields are exactly the current tenant's vendor `name`s.
- Produces: nothing downstream; this closes the server-side gap (form bypass, stale option lists).

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Zena/OperatorSubmittalUiTest.php`:

```php
    public function test_store_accepts_valid_tenant_vendor_and_rejects_unknown_name(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        Vendor::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'code' => 'VD-010',
            'name' => 'Nhà Thầu Hợp Lệ',
            'is_active' => true,
        ]);

        $valid = $this->actingAs($this->user)
            ->post(route('operator.submittals.store'), [
                'project_id' => (string) $this->project->id,
                'title' => 'Submittal có vendor hợp lệ',
                'description' => 'Kiểm tra select vendor.',
                'submittal_type' => 'material_sample',
                'contractor' => 'Nhà Thầu Hợp Lệ',
            ], $headers);

        $submittal = Submittal::query()->where('title', 'Submittal có vendor hợp lệ')->firstOrFail();
        $valid->assertRedirect(route('operator.submittals.show', $submittal->id));
        $this->assertSame('Nhà Thầu Hợp Lệ', (string) $submittal->contractor);

        $invalid = $this->actingAs($this->user)
            ->post(route('operator.submittals.store'), [
                'project_id' => (string) $this->project->id,
                'title' => 'Submittal vendor lạ',
                'description' => 'Tên không có trong danh sách.',
                'submittal_type' => 'material_sample',
                'manufacturer' => 'Vendor Không Tồn Tại',
            ], $headers);

        $invalid->assertSessionHasErrors('manufacturer');
        $this->assertFalse(
            Submittal::query()->where('title', 'Submittal vendor lạ')->exists(),
            'Submittal không được tạo khi manufacturer không khớp vendor nào của tenant.'
        );
    }

    public function test_store_rejects_vendor_name_belonging_to_another_tenant(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $foreignTenant = Tenant::factory()->create();
        Vendor::query()->create([
            'tenant_id' => (string) $foreignTenant->id,
            'code' => 'VD-F99',
            'name' => 'Vendor Của Tenant Khác',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('operator.submittals.store'), [
                'project_id' => (string) $this->project->id,
                'title' => 'Submittal vendor tenant khác',
                'description' => 'Tên vendor thuộc tenant khác phải bị chặn.',
                'submittal_type' => 'material_sample',
                'contractor' => 'Vendor Của Tenant Khác',
            ], $headers);

        $response->assertSessionHasErrors('contractor');
        $this->assertFalse(Submittal::query()->where('title', 'Submittal vendor tenant khác')->exists());
    }
```

- [ ] **Step 2: Run the tests to verify the rejection cases fail**

Run: `./vendor/bin/phpunit --filter "test_store_accepts_valid_tenant_vendor_and_rejects_unknown_name|test_store_rejects_vendor_name_belonging_to_another_tenant" tests/Feature/Zena/OperatorSubmittalUiTest.php`
Expected: FAIL — `assertSessionHasErrors('manufacturer')` / `assertSessionHasErrors('contractor')` fail because the current rules accept any string (submittals get created).

- [ ] **Step 3: Add the validation rules**

In `app/Http/Controllers/Web/SubmittalPageController.php`, add `use Illuminate\Validation\Rule;` to the imports, then in `store()` replace the two rules:

```php
            'contractor' => ['nullable', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
```

with:

```php
            'contractor' => ['nullable', 'string', 'max:255',
                Rule::exists('vendors', 'name')->where('tenant_id', (string) Auth::user()?->tenant_id),
            ],
            'manufacturer' => ['nullable', 'string', 'max:255',
                Rule::exists('vendors', 'name')->where('tenant_id', (string) Auth::user()?->tenant_id),
            ],
```

Note: the exists rule intentionally does NOT filter `is_active` — a vendor deactivated after a form was opened should still be storable from an in-flight submission; the select (Task 1) already hides inactive vendors for new entries.

- [ ] **Step 4: Run the whole test class**

Run: `./vendor/bin/phpunit tests/Feature/Zena/OperatorSubmittalUiTest.php`
Expected: ALL PASS (the pre-existing full-flow test omits both fields — nullable path unaffected).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Web/SubmittalPageController.php tests/Feature/Zena/OperatorSubmittalUiTest.php
git commit -m "feat(submittals): tenant-scoped vendor validation for contractor/manufacturer"
```

---

## Final verification (after all tasks)

- [ ] `./vendor/bin/phpunit tests/Feature/Zena/OperatorSubmittalUiTest.php tests/Feature/Api/SubmittalApiTest.php` — ALL PASS (`SubmittalApiTest` guards the API layer this Web flow delegates to; its requests bypass the new Web validation and must be unaffected).
- [ ] Manual browser walkthrough (mandatory before merge): open Tạo submittal → both fields render as dropdowns listing active vendors as `Tên (MÃ)`; "+ Thêm nhà cung cấp" opens vendor create in a new tab (link visible only with `vendor.create` permission); submitting with a chosen vendor stores its name (check the show page); leaving both empty still creates the submittal.
- [ ] CI green. Watch PHPStan drift (the `Rule::exists` closure and `Vendor` property access may need surgical baseline entries — take exact messages from the CI artifact `code-quality-reports`, file `phpstan-report.json`).

## Out of Scope

- RFI (has no contractor/manufacturer fields — the original research note over-scoped).
- `vendor_id` FK migration; vendor role classification (contractor vs manufacturer); Submittal edit form (does not exist).
