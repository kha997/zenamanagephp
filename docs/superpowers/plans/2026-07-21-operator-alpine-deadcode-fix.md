# Operator Alpine Dead-Code Fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix 4 dead Alpine.js usages under `layouts.operator` (a layout that never loads Alpine): 3 always-open "Biểu mẫu ▾" dropdowns become native `<details>` dropdowns, and the invisible "Áp dụng mẫu công việc" card gets rewritten in vanilla JS — plus a guard test and a Dusk browser test so this class of bug cannot silently recur.

**Architecture:** `layouts/operator.blade.php` loads NO Alpine.js (no CDN script, no `alpinejs` in `package.json`, `@vite` only pulls `operator.css` + 4 standalone JS files). Any `x-data`/`x-show`/`x-if` markup rendered under it is inert: `@click`/`x-show` attributes are ignored by the browser (so the dropdown menu `<div x-show="open">` renders **permanently visible**, overlapping the header), and `<template x-if>` content is **never rendered** (so the work-template card shows an empty shell). The fix direction — decided and approved — is vanilla JS, NOT adding Alpine: the operator surface already has an established vanilla-JS pattern (`resources/js/ai-*.js`, and the Site Diary autofill shipped in PR#211), and the other layouts load Alpine from a CDN (unpkg) which we do not want to extend to the operator surface.

**Tech Stack:** Laravel 12 Blade (anonymous components, `@once`), native HTML `<details>/<summary>`, vanilla JS via Vite entries, PHPUnit feature tests, Laravel Dusk (CI job `browser-tests` in `.github/workflows/button-tests.yml`).

## Global Constraints

- **Do NOT add Alpine.js** — not via CDN, not via npm. No `alpinejs` anywhere in `package.json`, `vite.config.js`, or `layouts/operator.blade.php`.
- After this plan completes, **zero** Alpine directives (`x-data`, `x-init`, `x-show`, `x-if`, `x-model`, `x-text`, `x-for`, `x-transition`, `@click`, `@change` in Alpine syntax) may remain in any view rendered under `layouts.operator`. Views under other layouts (`layouts.app`, `layouts.dashboard`, etc.) are out of scope — do not touch them.
- Behavior of the three "Biểu mẫu ▾" dropdowns must be: closed by default, opens on click, closes on outside click, links render one per template. Same visual classes as today (slate borders, white bg, shadow, `w-56` menu).
- Behavior of the "Áp dụng mẫu công việc" card must exactly match the original Alpine intent: on load fetch `GET /app/projects/{id}/work-templates` → populate a `<select>` of published templates (or an empty-state message); "Xem trước" POSTs to `.../work-templates/preview` and renders the summary (phases/tasks/checklists/docs) or the duplicate warning; "Áp dụng" (only visible after a non-duplicate preview) POSTs to `.../work-templates/apply` and reloads on success. All existing routes and permission gates (`template.apply` for the card, `rbac:` middleware on routes) stay unchanged.
- New JS files follow the existing pattern in `resources/js/ai-opportunity-summary.js`: IIFE, `'use strict'`, `data-*` attribute hooks, CSRF from `meta[name="csrf-token"]`, `DOMContentLoaded`-safe init, no framework.
- Every new Vite entry must be added in BOTH `vite.config.js` (`input` array) AND the `@vite([...])` call in `layouts/operator.blade.php`, or the asset 404s in production builds.
- Existing tests that must keep passing unchanged in intent: `tests/Feature/Web/ProjectApplyWorkTemplateUiTest.php` (asserts card visible/hidden by permission via the text "Áp dụng mẫu công việc"), `tests/Feature/Web/ProjectWorkTemplateApplyTest.php` (endpoint behavior), `tests/Feature/Zena/ContractProgressViewTest.php`, `tests/Feature/Zena/ContractFinanceViewTest.php`.
- Test invocation: `./vendor/bin/phpunit <path>` directly. In hybrid-vendor worktrees NEVER `php artisan test` (class-redeclaration crash) and treat local PHPStan as unreliable — CI is the source of truth for PHPStan.
- The repo-wide PHPStan baseline lives in `phpstan-baseline.neon`; if CI's "Code Quality Analysis"/"Security Tests" jobs fail on new magic-property/scope findings, add surgical baseline entries (message + identifier + count + path), remembering single quotes inside single-quoted neon strings must be doubled (`''`).

## Background (read once, it explains every task)

Audit findings (2026-07-21, verified against code):

| Location | Symptom in a real browser |
|---|---|
| `resources/views/contracts/show.blade.php:13-20` | Dropdown menu "Biểu mẫu" renders permanently open over the page header (shown whenever `$contractTemplates` is non-empty) |
| `resources/views/contracts/certificate-show.blade.php:11-21` | Same, gated on `$certificateTemplates` |
| `resources/views/projects/show.blade.php:13-22` | Same, gated on `$projectTemplates` |
| `resources/views/projects/_apply-work-template.blade.php` (whole file) | Card body invisible — everything is inside `<template x-if>` which never renders without Alpine; users only see an empty card titled "Áp dụng mẫu công việc" |

Why tests never caught it: all coverage is PHPUnit `assertSee()` over HTTP — no JS executes. The only Dusk test CI runs is `tests/Browser/Projects/ProjectCreateCanaryTest.php` (see `.github/workflows/button-tests.yml`, step "Run Browser Tests").

---

### Task 1: Native `<details>` dropdown component + replace the 3 Alpine dropdowns

**Files:**
- Create: `resources/views/components/ui/template-dropdown.blade.php`
- Modify: `resources/views/contracts/show.blade.php:12-21`
- Modify: `resources/views/contracts/certificate-show.blade.php:11-21`
- Modify: `resources/views/projects/show.blade.php:13-22`
- Test: `tests/Feature/Web/OperatorTemplateDropdownTest.php` (create)

**Interfaces:**
- Produces: Blade component `<x-ui.template-dropdown :links="[['label' => string, 'href' => string], ...]" />`. Renders nothing when `links` is empty. Later tasks do not consume it, but Task 3's guard test will scan these three views, so they must be Alpine-free after this task.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Web/OperatorTemplateDropdownTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\DeliverableTemplate;
use App\Models\DeliverableTemplateVersion;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperatorTemplateDropdownTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithPermissions(Tenant $tenant, array $codes): User
    {
        $user = User::factory()->create(['tenant_id' => (string) $tenant->id]);
        $role = Role::factory()->create(['name' => 'Test Role ' . uniqid()]);

        $ids = [];
        foreach ($codes as $code) {
            $permission = Permission::where('code', $code)->first()
                ?? Permission::factory()->create(['code' => $code, 'name' => $code]);
            $ids[] = $permission->id;
        }
        $role->permissions()->sync($ids);
        UserRole::query()->create(['user_id' => (string) $user->id, 'role_id' => (string) $role->id]);

        return $user;
    }

    public function test_project_show_template_dropdown_is_details_based_and_alpine_free(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->makeUserWithPermissions($tenant, ['project.view']);
        $project = Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'created_by' => (string) $user->id,
            'pm_id' => (string) $user->id,
        ]);
        // $projectTemplates trong ProjectController::show() lọc
        // `latestPublishedVersion !== null` — nên fixture PHẢI có version
        // với published_at, không chỉ template. (Pattern lấy từ
        // tests/Feature/Zena/DocumentTemplateRenderTest.php.)
        $template = DeliverableTemplate::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'context' => 'project',
            'name' => 'Biên bản bàn giao',
            'status' => 'published',
        ]);
        DeliverableTemplateVersion::create([
            'tenant_id' => (string) $tenant->id,
            'deliverable_template_id' => $template->id,
            'version' => '1.0.0',
            'semver' => '1.0.0',
            'storage_path' => 'deliverable-templates/' . $tenant->id . '/dropdown-test/render.html',
            'checksum_sha256' => hash('sha256', '<h1>x</h1>'),
            'mime' => 'text/html',
            'size' => 10,
            'placeholders_spec_json' => ['schema_version' => '1.0.0', 'placeholders' => []],
            'published_at' => now(),
            'created_by' => (string) $user->id,
            'updated_by' => (string) $user->id,
        ]);

        $response = $this->actingAs($user)->get("/app/projects/{$project->id}");

        $response->assertOk();
        $response->assertSee('data-template-dropdown', false);
        $response->assertSee('Biên bản bàn giao');
        $response->assertDontSee('x-data', false);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `./vendor/bin/phpunit tests/Feature/Web/OperatorTemplateDropdownTest.php`
Expected: FAIL — `assertSee('data-template-dropdown')` fails (component doesn't exist yet) and/or `assertDontSee('x-data')` fails (Alpine markup still present).

- [ ] **Step 3: Create the component**

Create `resources/views/components/ui/template-dropdown.blade.php`:

```blade
@props(['label' => 'Biểu mẫu ▾', 'links' => []])

@if (count($links) > 0)
    <details class="relative inline-block" data-template-dropdown>
        <summary class="inline-flex cursor-pointer select-none list-none items-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 [&::-webkit-details-marker]:hidden">
            {{ $label }}
        </summary>
        <div class="absolute right-0 z-50 mt-1 w-56 rounded-md border border-slate-200 bg-white shadow-lg">
            @foreach ($links as $link)
                <a href="{{ $link['href'] }}" class="block px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">{{ $link['label'] }}</a>
            @endforeach
        </div>
    </details>

    @once
        <script>
            document.addEventListener('click', function (event) {
                document.querySelectorAll('details[data-template-dropdown][open]').forEach(function (dropdown) {
                    if (!dropdown.contains(event.target)) {
                        dropdown.removeAttribute('open');
                    }
                });
            });
        </script>
    @endonce
@endif
```

- [ ] **Step 4: Replace the three Alpine dropdowns**

In `resources/views/contracts/show.blade.php`, replace lines 12-21 (the whole `@if ($contractTemplates->isNotEmpty()) ... @endif` block containing `x-data`) with:

```blade
        <x-ui.template-dropdown :links="$contractTemplates->map(fn ($tpl) => [
            'label' => $tpl->name,
            'href' => route('operator.contracts.documents.render', [$contract->id, $tpl->id]),
        ])->all()" />
```

In `resources/views/contracts/certificate-show.blade.php`, replace lines 11-21 (the `@if ($certificateTemplates->isNotEmpty()) ... @endif` block) with:

```blade
        <x-ui.template-dropdown :links="$certificateTemplates->map(fn ($tpl) => [
            'label' => $tpl->name,
            'href' => route('operator.contracts.certificates.documents.render', [$contract->id, $certificate->id, $tpl->id]),
        ])->all()" />
```

In `resources/views/projects/show.blade.php`, replace lines 13-22 (the `@if ($projectTemplates->isNotEmpty()) ... @endif` block) with:

```blade
        <x-ui.template-dropdown :links="$projectTemplates->map(fn ($tpl) => [
            'label' => $tpl->name,
            'href' => route('app.projects.documents.render', [$project->id, $tpl->id]),
        ])->all()" />
```

Note the component itself handles the empty case, so the surrounding `@if (...->isNotEmpty())` wrappers are removed — passing an empty collection renders nothing.

- [ ] **Step 5: Run the new test + the existing view tests**

Run: `./vendor/bin/phpunit tests/Feature/Web/OperatorTemplateDropdownTest.php tests/Feature/Zena/ContractProgressViewTest.php tests/Feature/Zena/ContractFinanceViewTest.php tests/Feature/Zena/DocumentTemplateRenderTest.php`
Expected: ALL PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/views/components/ui/template-dropdown.blade.php resources/views/contracts/show.blade.php resources/views/contracts/certificate-show.blade.php resources/views/projects/show.blade.php tests/Feature/Web/OperatorTemplateDropdownTest.php
git commit -m "fix(operator-ui): replace dead Alpine dropdowns with native details element"
```

---

### Task 2: Rewrite the apply-work-template card in vanilla JS

**Files:**
- Modify: `resources/views/projects/_apply-work-template.blade.php` (full rewrite)
- Create: `resources/js/work-template-apply.js`
- Modify: `vite.config.js` (add entry to `input` array)
- Modify: `resources/views/layouts/operator.blade.php:10` (add entry to `@vite([...])`)
- Test: `tests/Feature/Web/ProjectApplyWorkTemplateUiTest.php` (existing — must keep passing; extend with one markup assertion)

**Interfaces:**
- Consumes (unchanged, already live): `GET /app/projects/{project}/work-templates` → `{success: true, data: [{id, name, code, latest_published_version_id}]}`; `POST .../work-templates/preview` and `POST .../work-templates/apply` → on success `{success: true, data: {duplicate: bool, summary?: {phases, tasks, checklists, docs}}}`, on failure `{success: false, message}`. Both POSTs take JSON body `{work_template_id}` and CSRF header.
- Produces: card markup with `data-work-template-apply` root + `data-project-id`, hook attributes `data-wta-loading`, `data-wta-empty`, `data-wta-body`, `data-wta-select`, `data-wta-preview-btn`, `data-wta-apply-btn`, `data-wta-error`, `data-wta-result`. Task 4's Dusk test selects on these attributes — keep them exact.

- [ ] **Step 1: Rewrite the Blade partial**

Replace the entire contents of `resources/views/projects/_apply-work-template.blade.php` with:

```blade
@if (auth()->user()?->hasPermission('template.apply'))
    <div data-work-template-apply data-project-id="{{ $project->id }}">
        <x-ui.card title="Áp dụng mẫu công việc">
            <p data-wta-loading class="text-sm text-slate-500">Đang tải danh sách mẫu...</p>

            <p data-wta-empty class="hidden text-sm text-slate-500">Chưa có mẫu công việc nào được publish. Liên hệ quản trị viên để tạo mẫu.</p>

            <div data-wta-body class="hidden space-y-3">
                <select data-wta-select class="operator-select">
                    <option value="">-- Chọn mẫu công việc --</option>
                </select>

                <div class="flex gap-2">
                    <button type="button" data-wta-preview-btn class="operator-button operator-button-secondary" disabled>Xem trước</button>
                    <button type="button" data-wta-apply-btn class="operator-button operator-button-primary hidden">Áp dụng</button>
                </div>

                <p data-wta-error class="hidden text-sm text-rose-600"></p>

                <div data-wta-result class="hidden rounded border border-slate-200 p-3 text-sm"></div>
            </div>
        </x-ui.card>
    </div>
@endif
```

- [ ] **Step 2: Write the vanilla JS module**

Create `resources/js/work-template-apply.js` (follows the `ai-opportunity-summary.js` pattern):

```js
/**
 * Card "Áp dụng mẫu công việc" trên trang chi tiết dự án: tải danh sách mẫu
 * đã publish, xem trước (dry-run) và áp dụng thật. Vanilla JS — layout
 * operator không có Alpine. Copy pattern từ ai-opportunity-summary.js.
 */
(function () {
    'use strict';

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function postJson(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
            body: JSON.stringify(body),
        }).then(function (response) {
            return response.json().then(function (json) {
                return { ok: response.ok, body: json };
            });
        });
    }

    function renderSummary(resultEl, data) {
        resultEl.textContent = '';
        if (data.duplicate) {
            var warn = document.createElement('p');
            warn.className = 'text-amber-600';
            warn.textContent = 'Mẫu này đã được áp dụng cho dự án trước đó.';
            resultEl.appendChild(warn);
        } else {
            var list = document.createElement('ul');
            list.className = 'space-y-1 text-slate-700';
            [
                ['Giai đoạn', data.summary.phases],
                ['Công việc', data.summary.tasks],
                ['Checklist', data.summary.checklists],
                ['Tài liệu', data.summary.docs],
            ].forEach(function (row) {
                var item = document.createElement('li');
                item.textContent = row[0] + ': ' + row[1];
                list.appendChild(item);
            });
            resultEl.appendChild(list);
        }
        resultEl.classList.remove('hidden');
    }

    function attach(container) {
        if (container.dataset.wtaBound) return;
        container.dataset.wtaBound = '1';

        var projectId = container.dataset.projectId;
        var loadingEl = container.querySelector('[data-wta-loading]');
        var emptyEl = container.querySelector('[data-wta-empty]');
        var bodyEl = container.querySelector('[data-wta-body]');
        var selectEl = container.querySelector('[data-wta-select]');
        var previewBtn = container.querySelector('[data-wta-preview-btn]');
        var applyBtn = container.querySelector('[data-wta-apply-btn]');
        var errorEl = container.querySelector('[data-wta-error]');
        var resultEl = container.querySelector('[data-wta-result]');

        if (!projectId || !selectEl || !previewBtn || !applyBtn) return;

        var base = '/app/projects/' + encodeURIComponent(projectId) + '/work-templates';

        function setError(message) {
            errorEl.textContent = message || '';
            errorEl.classList.toggle('hidden', !message);
        }

        function resetPreviewState() {
            setError('');
            resultEl.classList.add('hidden');
            resultEl.textContent = '';
            applyBtn.classList.add('hidden');
        }

        fetch(base, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) { return response.json(); })
            .then(function (json) {
                var templates = (json && json.data) || [];
                loadingEl.classList.add('hidden');
                if (templates.length === 0) {
                    emptyEl.classList.remove('hidden');
                    return;
                }
                templates.forEach(function (tpl) {
                    var option = document.createElement('option');
                    option.value = tpl.id;
                    option.textContent = tpl.name;
                    selectEl.appendChild(option);
                });
                bodyEl.classList.remove('hidden');
            })
            .catch(function () {
                loadingEl.classList.add('hidden');
                emptyEl.textContent = 'Không tải được danh sách mẫu.';
                emptyEl.classList.remove('hidden');
            });

        selectEl.addEventListener('change', function () {
            previewBtn.disabled = !selectEl.value;
            resetPreviewState();
        });

        previewBtn.addEventListener('click', function () {
            if (!selectEl.value) return;
            previewBtn.disabled = true;
            resetPreviewState();

            postJson(base + '/preview', { work_template_id: selectEl.value })
                .then(function (result) {
                    if (!result.ok || !result.body.success) {
                        setError(result.body.message || 'Không xem trước được.');
                        return;
                    }
                    renderSummary(resultEl, result.body.data);
                    if (!result.body.data.duplicate) {
                        applyBtn.classList.remove('hidden');
                    }
                })
                .catch(function () { setError('Có lỗi xảy ra, thử lại.'); })
                .finally(function () { previewBtn.disabled = false; });
        });

        applyBtn.addEventListener('click', function () {
            applyBtn.disabled = true;
            setError('');

            postJson(base + '/apply', { work_template_id: selectEl.value })
                .then(function (result) {
                    if (result.ok && result.body.success && !result.body.data.duplicate) {
                        window.location.reload();
                    } else if (result.ok && result.body.success && result.body.data.duplicate) {
                        renderSummary(resultEl, result.body.data);
                        applyBtn.classList.add('hidden');
                    } else {
                        setError(result.body.message || 'Áp dụng thất bại.');
                    }
                })
                .catch(function () { setError('Có lỗi xảy ra, thử lại.'); })
                .finally(function () { applyBtn.disabled = false; });
        });
    }

    function init() {
        document.querySelectorAll('[data-work-template-apply]').forEach(attach);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
```

- [ ] **Step 3: Register the Vite entry in both places**

In `vite.config.js`, extend the `input` array (currently ends with `'resources/js/ai-opportunity-summary.js'`) to also include `'resources/js/work-template-apply.js'`.

In `resources/views/layouts/operator.blade.php` line 10, extend the `@vite([...])` array the same way — append `'resources/js/work-template-apply.js'` after `'resources/js/ai-opportunity-summary.js'`.

- [ ] **Step 4: Extend the existing feature test with a markup assertion**

In `tests/Feature/Web/ProjectApplyWorkTemplateUiTest.php`, method `test_apply_template_card_visible_when_user_has_permission()`, add after the existing `$response->assertSee('Áp dụng mẫu công việc');`:

```php
        $response->assertSee('data-work-template-apply', false);
        $response->assertDontSee('x-data', false);
```

- [ ] **Step 5: Run the tests + build assets**

Run: `./vendor/bin/phpunit tests/Feature/Web/ProjectApplyWorkTemplateUiTest.php tests/Feature/Web/ProjectWorkTemplateApplyTest.php`
Expected: ALL PASS.

Run: `npm run build`
Expected: build succeeds and `public/build/manifest.json` contains an entry for `resources/js/work-template-apply.js`. (If npm is unavailable in the environment, note it in the report — CI builds assets and will verify.)

- [ ] **Step 6: Commit**

```bash
git add resources/views/projects/_apply-work-template.blade.php resources/js/work-template-apply.js vite.config.js resources/views/layouts/operator.blade.php tests/Feature/Web/ProjectApplyWorkTemplateUiTest.php
git commit -m "fix(work-template): rewrite apply card in vanilla JS (Alpine never loaded on operator layout)"
```

---

### Task 3: Guard test — no Alpine directives under the operator layout

**Files:**
- Create: `tests/Feature/Architecture/OperatorLayoutAlpineGuardTest.php`

**Interfaces:**
- Consumes: nothing from other tasks at the code level, but it will FAIL until Tasks 1-2 are complete (it scans the same views). Execute this task AFTER Tasks 1 and 2.
- Produces: a permanent architecture guard; nothing downstream consumes it.

- [ ] **Step 1: Write the guard test (it should pass immediately, since Tasks 1-2 already removed all Alpine)**

Create `tests/Feature/Architecture/OperatorLayoutAlpineGuardTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * layouts/operator.blade.php không load Alpine.js — mọi directive Alpine
 * trong view render dưới layout này đều chết lặng (menu hiện thường trực,
 * template x-if không bao giờ render). Guard này chặn tái sinh lỗi đó:
 * quét mọi view @extends('layouts.operator') VÀ mọi partial được @include
 * từ chúng (một cấp), fail nếu còn cú pháp Alpine.
 *
 * Nếu test này fail: viết lại bằng vanilla JS (xem resources/js/ai-*.js,
 * resources/js/work-template-apply.js) hoặc dùng <details> native
 * (xem components/ui/template-dropdown.blade.php). ĐỪNG thêm Alpine vào
 * layout operator để "sửa" — đó là quyết định kiến trúc đã chốt.
 */
class OperatorLayoutAlpineGuardTest extends TestCase
{
    private const ALPINE_PATTERN = '/\bx-(data|init|show|if|for|model|text|cloak|transition)\b|@(click|change|input|submit)(\.[a-z]+)*="/';

    public function test_operator_layout_views_and_their_includes_are_alpine_free(): void
    {
        $viewsPath = resource_path('views');
        $operatorViews = [];

        foreach (File::allFiles($viewsPath) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $contents = $file->getContents();
            if (str_contains($contents, "@extends('layouts.operator')")) {
                $operatorViews[$file->getPathname()] = $contents;
            }
        }

        $this->assertNotEmpty($operatorViews, 'Sanity: expected at least one view extending layouts.operator');

        // Mở rộng một cấp @include: partial include từ view operator cũng render dưới layout đó.
        $toScan = $operatorViews;
        foreach ($operatorViews as $contents) {
            preg_match_all("/@include\\(\\s*'([^']+)'/", $contents, $matches);
            foreach ($matches[1] as $viewName) {
                $includePath = $viewsPath . '/' . str_replace('.', '/', $viewName) . '.blade.php';
                if (is_file($includePath) && !isset($toScan[$includePath])) {
                    $toScan[$includePath] = file_get_contents($includePath);
                }
            }
        }

        // Layout operator + mọi component x-ui.* nó dùng cũng thuộc phạm vi.
        $layoutPath = $viewsPath . '/layouts/operator.blade.php';
        $toScan[$layoutPath] = file_get_contents($layoutPath);
        foreach (File::allFiles($viewsPath . '/components/ui') as $file) {
            $toScan[$file->getPathname()] = $file->getContents();
        }

        $violations = [];
        foreach ($toScan as $path => $contents) {
            if (preg_match(self::ALPINE_PATTERN, $contents, $match)) {
                $violations[] = str_replace($viewsPath . '/', '', $path) . ' → "' . $match[0] . '"';
            }
        }

        $this->assertSame([], $violations, "Alpine directives found in operator-layout views (Alpine is NOT loaded there — see this test's docblock):\n" . implode("\n", $violations));
    }
}
```

- [ ] **Step 2: Run the guard**

Run: `./vendor/bin/phpunit tests/Feature/Architecture/OperatorLayoutAlpineGuardTest.php`
Expected: PASS. If it fails, the failure message lists exact file + matched directive — those are leftovers Tasks 1-2 missed; fix them (per the docblock: vanilla JS or `<details>`, never by adding Alpine) and re-run.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Architecture/OperatorLayoutAlpineGuardTest.php
git commit -m "test(architecture): guard operator-layout views against dead Alpine directives"
```

---

### Task 4: Dusk browser test for the work-template card + wire into CI

**Files:**
- Create: `tests/Browser/Projects/WorkTemplateApplyBrowserTest.php`
- Modify: `.github/workflows/button-tests.yml` — step "Run Browser Tests" (currently `php artisan dusk tests/Browser/Projects/ProjectCreateCanaryTest.php --env=testing --without-tty --stop-on-failure`)

**Interfaces:**
- Consumes: the `data-wta-*` hook attributes produced by Task 2 (exact names listed there) and the seeding helpers `Tests\Feature\Api\Concerns\InteractsWithWorkTemplateV2::seedV2Template()` plus `Database\Seeders\RoleSeeder` / `Database\Seeders\ZenaPermissionsSeeder` (same fixtures `tests/Feature/Web/ProjectWorkTemplateApplyTest.php` uses — mirror its `makeProjectManager` + `publishedTemplate` helpers).
- Produces: CI-executed browser coverage. This is the regression net that would have caught both the dead card AND the always-open dropdowns.

- [ ] **Step 1: Write the Dusk test**

Create `tests/Browser/Projects/WorkTemplateApplyBrowserTest.php` (modeled on `ProjectCreateCanaryTest` for auth/DB conventions and on `ProjectWorkTemplateApplyTest` for fixtures):

```php
<?php

declare(strict_types=1);

namespace Tests\Browser\Projects;

use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRole;
use App\Models\WorkTemplate;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ZenaPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Tests\Feature\Api\Concerns\InteractsWithWorkTemplateV2;

class WorkTemplateApplyBrowserTest extends DuskTestCase
{
    use DatabaseMigrations;
    use InteractsWithWorkTemplateV2;

    protected Tenant $tenant;
    protected User $user;
    protected Project $project;
    protected WorkTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(ZenaPermissionsSeeder::class);

        $this->tenant = Tenant::factory()->create();

        $this->user = User::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'is_active' => true,
        ]);
        $role = Role::factory()->create(['name' => 'Browser PM ' . uniqid()]);
        $permissionIds = Permission::whereIn('code', ['project.view', 'template.view', 'template.apply'])->pluck('id');
        $role->permissions()->sync($permissionIds);
        UserRole::query()->create(['user_id' => (string) $this->user->id, 'role_id' => (string) $role->id]);

        $this->project = Project::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'created_by' => (string) $this->user->id,
            'pm_id' => (string) $this->user->id,
        ]);

        // seedV2Template($tenant, $user, $code) tạo WorkTemplate với
        // name cố định 'Seeded V2 Template' (xem InteractsWithWorkTemplateV2:118).
        [$this->template, $version] = $this->seedV2Template($this->tenant, $this->user, 'WT-BROWSER-1');
        $version->update([
            'published_at' => now(),
            'is_immutable' => true,
            'published_by' => (string) $this->user->id,
        ]);

        // Seed một DeliverableTemplate published để dropdown "Biểu mẫu ▾"
        // THẬT SỰ xuất hiện trên trang — nếu thiếu, test closed-by-default
        // bên dưới pass rỗng vì không có dropdown nào để kiểm tra.
        $docTemplate = \App\Models\DeliverableTemplate::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'context' => 'project',
            'name' => 'Browser Dropdown Template',
            'status' => 'published',
        ]);
        \App\Models\DeliverableTemplateVersion::create([
            'tenant_id' => (string) $this->tenant->id,
            'deliverable_template_id' => $docTemplate->id,
            'version' => '1.0.0',
            'semver' => '1.0.0',
            'storage_path' => 'deliverable-templates/' . $this->tenant->id . '/browser-test/render.html',
            'checksum_sha256' => hash('sha256', '<h1>x</h1>'),
            'mime' => 'text/html',
            'size' => 10,
            'placeholders_spec_json' => ['schema_version' => '1.0.0', 'placeholders' => []],
            'published_at' => now(),
            'created_by' => (string) $this->user->id,
            'updated_by' => (string) $this->user->id,
        ]);
    }

    public function test_apply_work_template_card_loads_templates_and_previews(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/app/projects/' . $this->project->id)
                // Card đã render và JS đã fetch xong danh sách (loading ẩn, body hiện).
                ->waitFor('[data-wta-body]:not(.hidden)', 15)
                ->assertPresent('select[data-wta-select]')
                // Select có đúng template đã publish (option text = name của template).
                ->assertSeeIn('select[data-wta-select]', 'Seeded V2 Template')
                // Chọn template → nút Xem trước bật.
                ->select('select[data-wta-select]', (string) $this->template->id)
                ->pause(200)
                ->click('[data-wta-preview-btn]')
                // Preview trả về summary (dry-run) → khối kết quả hiện.
                ->waitFor('[data-wta-result]:not(.hidden)', 15)
                ->assertSeeIn('[data-wta-result]', 'Giai đoạn')
                // Không phải duplicate → nút Áp dụng hiện.
                ->assertVisible('[data-wta-apply-btn]');
        });
    }

    public function test_template_dropdown_menu_is_closed_by_default(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/app/projects/' . $this->project->id)
                // Dropdown Biểu mẫu PHẢI tồn tại (setUp đã seed DeliverableTemplate
                // published) — nếu selector này absent, fixture hỏng chứ không phải pass.
                ->waitFor('details[data-template-dropdown]', 15);

            // Menu phải ĐÓNG mặc định (regression: Alpine chết từng làm
            // menu hiện thường trực đè lên header).
            $openMenus = $browser->script(
                "return document.querySelectorAll('details[data-template-dropdown][open]').length;"
            )[0];
            $this->assertSame(0, $openMenus);

            // Click mở, click ra ngoài đóng lại.
            $browser->click('details[data-template-dropdown] summary')
                ->pause(150);
            $openAfterClick = $browser->script(
                "return document.querySelectorAll('details[data-template-dropdown][open]').length;"
            )[0];
            $this->assertSame(1, $openAfterClick);

            $browser->click('body')->pause(150);
            $openAfterOutsideClick = $browser->script(
                "return document.querySelectorAll('details[data-template-dropdown][open]').length;"
            )[0];
            $this->assertSame(0, $openAfterOutsideClick);
        });
    }
}
```

**Fixture facts (verified against code, no need to re-derive):** `seedV2Template(Tenant $tenant, User $user, string $code = 'WT-V2-SEED'): array` returns `[$template, $version]` and hardcodes `name => 'Seeded V2 Template'` (`tests/Feature/Api/Concerns/InteractsWithWorkTemplateV2.php:118-126`). The card's `<option>` text is the template `name` (see `WorkTemplateApplyController::templates()`). `ProjectWorkTemplateApplyTest::publishedTemplate()` is the working reference for the publish step.

- [ ] **Step 2: Verify the test runs locally IF a local Chrome/chromedriver is available; otherwise rely on CI**

Run: `php artisan dusk tests/Browser/Projects/WorkTemplateApplyBrowserTest.php --env=testing` from the MAIN checkout (Dusk needs the real app + a running server; in a hybrid-vendor worktree this will not work — note it in the report and let CI verify).
Expected: 2 tests pass. If no local browser stack exists, state that explicitly in the report — do NOT claim local verification that didn't happen.

- [ ] **Step 3: Wire the test into CI**

In `.github/workflows/button-tests.yml`, find the step:

```yaml
    - name: Run Browser Tests
      run: php artisan dusk tests/Browser/Projects/ProjectCreateCanaryTest.php --env=testing --without-tty --stop-on-failure
```

Replace the `run:` line with:

```yaml
      run: php artisan dusk tests/Browser/Projects/ --env=testing --without-tty --stop-on-failure
```

This runs every test in `tests/Browser/Projects/` (currently `ProjectCreateCanaryTest` + the new file), so future canaries dropped in that directory are picked up automatically. Do NOT widen to all of `tests/Browser/` — the legacy tests outside `Projects/` (`TaskEditBrowserTest`, `DashboardTest`, ...) target pre-operator-era routes and are known-broken.

- [ ] **Step 4: Commit**

```bash
git add tests/Browser/Projects/WorkTemplateApplyBrowserTest.php .github/workflows/button-tests.yml
git commit -m "test(browser): dusk coverage for work-template apply card + closed-by-default dropdowns"
```

---

## Final verification (after all tasks)

- [ ] Full targeted suite: `./vendor/bin/phpunit tests/Feature/Web/OperatorTemplateDropdownTest.php tests/Feature/Web/ProjectApplyWorkTemplateUiTest.php tests/Feature/Web/ProjectWorkTemplateApplyTest.php tests/Feature/Architecture/OperatorLayoutAlpineGuardTest.php tests/Feature/Zena/ContractProgressViewTest.php tests/Feature/Zena/ContractFinanceViewTest.php tests/Feature/Zena/DocumentTemplateRenderTest.php` — ALL PASS.
- [ ] `grep -rn "x-data\|x-init\|x-show" resources/views/projects/_apply-work-template.blade.php resources/views/contracts/show.blade.php resources/views/contracts/certificate-show.blade.php resources/views/projects/show.blade.php` returns nothing.
- [ ] Manual browser walkthrough (mandatory before merge — this whole bug class survived because nobody opened a browser): log in as a user with `template.apply`, open a project detail page → card "Áp dụng mẫu công việc" shows the select (not an empty shell); dropdown "Biểu mẫu ▾" (any contract/certificate/project page with published document templates) is closed by default, opens on click, closes on outside click.
- [ ] CI green on the PR, including the `browser-tests` job now running the new Dusk test. Watch "Code Quality Analysis"/"Security Tests" for PHPStan baseline drift (new view/JS code shouldn't trigger any, but the guard test's `File::` facade usage might — baseline surgically if CI says so).

## Out of Scope

- Adding Alpine to `layouts.operator` (explicitly rejected).
- Touching Alpine usage in views under other layouts (`layouts.app`, `layouts.dashboard`, admin, universal-frame...) — those layouts DO load Alpine via CDN; separate debt discussion.
- The legacy Dusk tests outside `tests/Browser/Projects/` (pre-operator routes, known-broken) — do not enable them in CI.
- CDN-to-npm migration of Alpine for the other layouts.
