# GAP-029 — Submittal resubmit web UI — Design

Date: 2026-07-25
Status: Approved for planning

## Background

PR#229 (merged 2026-07-24, `6589705b`) built the full resubmit state machine at the API/service layer: `draft → submitted → approved|rejected → revising → submitted`, backed by `SubmittalLifecycleService` (sole writer of `Submittal::status`), an immutable `submittal_revisions` table, and `SubmittalPolicy`. That PR deliberately shipped API-only — the operator web UI was never wired to `startRevision`/resubmit, and was logged as `GAP-029` in `OPERATIONAL_GAP_REGISTER.md`.

Investigating the gap during brainstorming surfaced that the web layer's editing story is smaller than assumed: `SubmittalPageController` has no `update()` method at all, and `resources/views/submittals/` has no edit form for *any* status, not just `rejected`/`revising`. This spec closes that gap end-to-end: an edit form usable in `draft` and `revising`, a "reopen for revision" action, a resubmit action that carries `revision_summary`, and a display of why the submittal was rejected.

## 1. Architecture and request flow

Two new `SubmittalPageController` methods (`update()`, `startRevision()`) call `SubmittalLifecycleService` **directly** — they do not proxy through `ApiSubmittalController` the way `store()`/`submit()`/`approve()`/`reject()` currently do (see §10, Out of scope, for why those four are left alone).

```
Browser (Blade form, PUT/POST)
   │
   ▼
SubmittalPageController::update() / startRevision()
   │  1. submittalForTenant($id)            — tenant-scoped lookup, ModelNotFoundException → 404
   │  2. $this->authorize('update'|'startRevision', $submittal)  — AuthorizationException → 403
   │  3. Validate (shared SubmittalContentRules, update() only)  — ValidationException → 422 back()+errors+old
   │  4. $this->lifecycle->updateContent(...) / ->startRevision(...)
   │       throws SubmittalTransitionNotAllowedException / SubmittalTransitionConflictException
   ▼
SubmittalLifecycleService (unchanged from PR#229 — no changes in this PR)
   │  DB::transaction + lockForUpdate + Submittal::canTransition guard
   ▼
Submittal / SubmittalRevision (unchanged)
```

**Correction after checking the actual file** (`SubmittalPageController::show()`, `app/Http/Controllers/Web/SubmittalPageController.php:106-119`): there is **no** existing `submittalForTenant()` helper on the Web controller today — `show()` inlines its own `Submittal::query()->where('tenant_id', ...)->with([...])->findOrFail($id)`. This spec adds a new private `submittalForTenant(string $id): Submittal` method to `SubmittalPageController` (same shape as the query `show()` already runs, plus the extra `rejectedBy:id,name` eager-load from §5) and refactors `show()` to call it, so `update()`/`startRevision()` share it too instead of each inlining a third copy of the same tenant-scoped lookup. This is a small, behavior-preserving extraction on `show()` (identical query, same eager-loads plus the one addition already required by §5) — not new business logic, and not a change to `ApiSubmittalController`'s identically-named-but-separate helper.

`AuthorizationException` and `ModelNotFoundException` are **not caught** in the new methods — they propagate to Laravel's default exception handler, which renders the standard 403/404 error pages. This is a deliberate difference from the four existing proxied methods (which manually translate an `ApiSubmittalController` JSON response into a redirect); calling the service directly removes the JSON-translation step entirely, so there is nothing to catch.

## 2. Route / controller contract

Added to `routes/web.php`, in the existing `submittals` route group, same middleware-naming convention as the routes already there:

```php
Route::put('/submittals/{id}', [SubmittalPageController::class, 'update'])
    ->middleware('rbac:submittal.edit')->name('submittals.update');
Route::post('/submittals/{id}/start-revision', [SubmittalPageController::class, 'startRevision'])
    ->middleware('rbac:submittal.submit')->name('submittals.start-revision');
```

`PUT`, not `PATCH`: every currently-active `update` route in `routes/web.php` uses `PUT` (grep confirmed 6 live routes: `projects.update`, `tasks.update`, `crm.leads.update`, `dashboards.update`, plus 2 legacy top-level routes; the only `PATCH` routes in the file are commented out with "MOVED TO API"). `submittal.submit` (not a new `submittal.start_revision` permission) for `start-revision`, matching the API layer's existing policy design (`SubmittalPolicy::startRevision()` already reuses `submittal.submit`).

```php
public function update(Request $request, string $id): RedirectResponse
public function startRevision(Request $request, string $id): RedirectResponse
```

Both return `RedirectResponse` only — never `JsonResponse` — from every code path (success, validation failure, business-rule failure).

## 3. Validation matrix

New file `app/Support/SubmittalContentRules.php`:

```php
final class SubmittalContentRules
{
    /** @return array<string, list<mixed>> */
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

This is byte-for-byte the rule *shape* `SubmittalController::update()` (API) already validates today — extracting it is a **behavior-preserving refactor**, not a new rule set. `App\Http\Controllers\Api\SubmittalController::update()` is changed to call `SubmittalContentRules::rules()` instead of its inline array; no rule is added, removed, loosened, or tightened. §8 (item 15) lists the regression test that proves this.

**Field allowlist — final, matches `SubmittalLifecycleService::updateContent()`'s `$allowedFields` exactly**: `title`, `description`, `submittal_type`, `specification_section`, `due_date`, `contractor`, `manufacturer`. `file_url` and `attachments` are in the service's allowlist but are out of scope for this UI (§10 — no upload UI exists anywhere in this app yet, not introduced here).

**`package_no`** is fillable on `Submittal` and present in the DB schema, but is **not** in the service's `updateContent()` allowlist and has no field in `create.blade.php` either. It is immutable through every UI surface today; this spec does not add it to the edit form and does not change that.

**Immutable fields** (never in the edit form, never in `SubmittalContentRules`): `id`, `tenant_id`, `project_id`, `submittal_number`, `package_no`, `status`, `current_revision_no`, `created_by`, `submitted_by`, `submitted_at`, `reviewed_by`, `reviewed_at`, `review_comments`, `review_notes`, `approved_by`, `approved_at`, `approval_comments`, `rejected_by`, `rejected_at`, `rejection_reason`, `rejection_comments`, `file_url`, `attachments`.

**Vendor fields (`contractor`/`manufacturer`) — Web-only extra layer, conditional on change:**

```php
$rules = SubmittalContentRules::rules();
$tenantId = (string) Auth::user()?->tenant_id;

if ($request->input('contractor') !== $submittal->contractor) {
    $rules['contractor'][] = Rule::exists('vendors', 'name')->where('tenant_id', $tenantId);
}
if ($request->input('manufacturer') !== $submittal->manufacturer) {
    $rules['manufacturer'][] = Rule::exists('vendors', 'name')->where('tenant_id', $tenantId);
}
```

**Why conditional, not unconditional (this is the resolution to the legacy-vendor problem — see §10 for the tracked debt)**: `Vendor` has no `SoftDeletes` and no FK from `Submittal.contractor`/`manufacturer` — the columns are plain strings matching `vendors.name`. If a vendor is later renamed, the *old* name string stored on an already-created submittal no longer matches any `vendors.name` row. Validating unconditionally would mean a user editing only `title` on such a submittal gets blocked by a `contractor` error on a field they never touched. Only re-validating when the submitted value differs from the stored value means: touching an unrelated field never fails because of a stale vendor reference; actually changing `contractor`/`manufacturer` still goes through the same tenant-scoped existence check `store()` already enforces.

`Vendor.is_active = false` (deactivated, not renamed) is a separate case: `Rule::exists` has no `is_active` filter (matches `store()`'s existing behavior — not changed here), so a deactivated vendor's name still passes if unchanged or re-submitted as-is. The **display** problem — the edit form's `<select>` filters `where('is_active', true)` like `create.blade.php` does, so a submittal's current (now-inactive) vendor would not appear as a selectable option and the select would silently show blank — is handled in the view: if `$submittal->contractor` is not null and not present in the active `$vendors` collection passed to the view, inject one extra `<option>` for it, disabled-looking but selected, labelled `"{{ $submittal->contractor }} (không còn hoạt động)"`. Same for `manufacturer`. This preserves the current value on page load without forcing the user to pick a new vendor just to edit something else.

## 4. Authorization and visibility matrix

Backend enforcement (unchanged from PR#229, already reviewed and merged):

| Action | Ability | Permission | Enforced by |
|---|---|---|---|
| Edit content | `update` | `submittal.edit` | `SubmittalPolicy::update()` |
| Reopen for revision | `startRevision` | `submittal.submit` | `SubmittalPolicy::startRevision()` |
| Submit / resubmit | `submit` | `submittal.submit` | `SubmittalPolicy::submit()` |

View-level `@can` (UX only — hiding a button a user cannot use; **never** the actual security boundary, which stays server-side in the Policy):

```blade
@can('update', $submittal)
    {{-- "Sửa nội dung" card, only meaningful when status ∈ {draft, revising} --}}
@endcan

@can('startRevision', $submittal)
    {{-- "Mở lại để sửa" button, only meaningful when status === rejected --}}
@endcan

@can('submit', $submittal)
    {{-- "Gửi duyệt" (draft) / "Gửi lại" (revising) button --}}
@endcan
```

A user hitting `PUT /submittals/{id}` or `POST /submittals/{id}/start-revision` directly (bypassing the hidden button) with insufficient permission still gets **403** from the Policy check in the controller — the `@can` wrapper does not weaken this, it only prevents showing a control that would 403 if used.

## 5. Rejection-information display

Verified against the actual model (`app/Models/Submittal.php`, `app/Models/SubmittalRevision.php`) before writing this section — the column names below are the real ones, not assumed:

- `Submittal.rejection_reason`, `Submittal.rejection_comments`, `Submittal.rejected_by` (→ `rejectedBy(): BelongsTo`), `Submittal.rejected_at` **exist** and are already partially rendered in `show.blade.php` today (`rejection_reason`/`rejection_comments`, not yet `rejected_by`/`rejected_at`).
- `Submittal.review_comments` / `Submittal.review_notes` / `Submittal.reviewed_by` / `Submittal.reviewed_at` are a **separate** pair of columns, written by `SubmittalController::review()`'s `forceFill()` (the legacy "review" endpoint that wraps approve/reject) — not the source used for the rejection-reason display in this spec. Not to be confused with `rejection_reason`/`rejection_comments`.
- `SubmittalRevision.decision_comments` (on the immutable revision row) is a **single** merged field — `SubmittalLifecycleService::decide()` sets it to `$decisionComments ?? $comments`, i.e. for a reject it's `rejection_comments` if present, else falls back to `rejection_reason`. It does **not** separately preserve both reason and comments the way the `Submittal` mirror columns do.

**Source-of-truth decision for display**: use the `Submittal` mirror columns (`rejection_reason`, `rejection_comments`, `rejected_by`, `rejected_at`), not `SubmittalRevision.decision_comments`, because the mirror preserves both fields distinctly and is already the pattern `show.blade.php` uses. The mirror columns remain correct for `rejected` and `revising` status: `SubmittalLifecycleService::startRevision()` does not touch or clear them, and `current_revision_no` is not incremented by `startRevision()` either (only `submit()` increments it, at the point of actual resubmission) — so during `revising`, `$submittal->current_revision_no` still correctly identifies *which* revision was rejected.

New card in `show.blade.php`, shown when `status ∈ {rejected, revising}`:

```blade
@if (in_array($submittal->status, ['rejected', 'revising'], true) && $submittal->rejection_reason)
    <x-ui.card title="Lần nộp #{{ $submittal->current_revision_no }} bị từ chối">
        <x-ui.field-value label="Người từ chối" :value="$submittal->rejectedBy?->name" />
        <x-ui.field-value label="Thời gian" :value="optional($submittal->rejected_at)->format('d/m/Y H:i')" />
        <div class="mt-3 whitespace-pre-line text-slate-800">{{ $submittal->rejection_reason }}</div>
        @if ($submittal->rejection_comments)
            <div class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ $submittal->rejection_comments }}</div>
        @endif
    </x-ui.card>
@endif
```

This **replaces** the existing unconditional `@if ($submittal->rejection_reason)` block in `show.blade.php` (currently shows regardless of status, and lacks `rejected_by`/`rejected_at`) — same data, tighter condition, more fields. `SubmittalController::update()` requires `$submittal->rejectedBy` to be eager-loaded in `SubmittalPageController::show()`'s existing `with([...])` list; add `'rejectedBy:id,name'` alongside the `project`/`submittedBy`/`reviewedBy` already there.

## 6. Dirty-form behavior

**This is a UX safeguard only — it is not a backend invariant.** `SubmittalLifecycleService::submit()` does not know or care whether the browser's edit form has unsaved changes; nothing server-side is added or changed to detect this. If dirty-checking JS fails to load or is disabled, the *worst* outcome is a user submits stale content via `submit()` without their unsaved edits — which is exactly what happens today with no dirty-check at all, not a new failure mode. Do not confuse the client-side warning with a state-machine guarantee.

Vanilla JS (no Alpine — `operator.blade.php` layout does not guarantee Alpine is loaded, per prior session's finding that `_apply-work-template.blade.php`'s `x-data` usage was dead for this reason), inline `<script>` in `show.blade.php`, only rendered when both the edit card and the resubmit control are present (`revising` status):

```js
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
```

Dirty comparison explicitly **excludes** `_token` (CSRF) and `_method` (Laravel's PUT-spoofing hidden field) and any `type=submit`/`type=button` element, and **normalizes** via `.trim()` so whitespace-only edits (e.g. a stray trailing space, or the browser re-rendering `old('due_date')` with different formatting) don't produce a false-positive dirty state. `resubmit-button` is `disabled` (not hidden) plus a visible inline warning line — a disabled-but-visible control communicates *why* better than hiding it outright.

**Because this is JS-only behavior, and the test suite (PHPUnit + Dusk config in this repo) does not execute arbitrary inline `<script>` assertions on DOM state via PHPUnit HTTP tests**, this specific behavior is **not** covered by a PHPUnit feature test. It is covered by:
- One Dusk browser test (`tests/Browser/SubmittalResubmitDirtyStateTest.php`, extends `DuskTestCase` per existing convention in this repo) that edits a field, asserts the resubmit button becomes `disabled`, then asserts it re-enables after a successful save.
- A manual acceptance checklist item (§9).

## 7. Success / error response behavior

| Outcome | Response |
|---|---|
| `update()` succeeds | `redirect()->route('operator.submittals.show', $id)->with('success', 'Đã lưu thay đổi')` |
| `startRevision()` succeeds | `redirect()->route('operator.submittals.show', $id)->with('success', 'Đã mở lại để sửa')` |
| Field validation fails (`ValidationException` from `Validator::make(...)->validate()`) | Laravel's default: `back()->withErrors($validator)->withInput()` — Laravel does this automatically when using `$request->validate($rules)` / `Validator::make()->validate()`; explicit call not hand-rolled |
| `SubmittalTransitionNotAllowedException` | `back()->with('error', 'Không thể thực hiện thao tác này ở trạng thái hiện tại.')` — a flash message, not a field error, since it is not about any specific input |
| `SubmittalTransitionConflictException` | `back()->with('error', 'Hồ sơ đã được xử lý bởi người khác, vui lòng tải lại trang.')` |
| `AuthorizationException` | not caught — Laravel's default handler renders the app's standard 403 page |
| `ModelNotFoundException` (wrong id or cross-tenant) | not caught — Laravel's default handler renders the app's standard 404 page |
| Any response from a Web route in this spec | **never** `JsonResponse` — every branch above returns `RedirectResponse` or lets Laravel's exception handler render an HTML error page |

**Separate error bags.** `update()`'s form and the resubmit form (`submit()`, unchanged route, now also carrying `revision_summary`) can both be on the same `show.blade.php` page. Using Laravel's default (unnamed) error bag for both would let a validation failure from one form incorrectly render inside the other. `update()` validates with a **named error bag**:

```php
$validator = Validator::make($request->all(), $rules);
if ($validator->fails()) {
    return back()->withErrors($validator, 'submittalUpdate')->withInput();
}
```

The resubmit path (existing `submit()` route, unchanged controller *logic*) already returns to the same page on failure; its `revision_summary` required-when-revising validation already exists in `ApiSubmittalController::submit()`'s validator from PR#229 (`Rule::requiredIf($submittal->status === Submittal::STATUS_REVISING)`), surfaced via `SubmittalPageController`'s shared `handleErrorResponse()`.

**Bag-collision analysis, precisely**: `handleErrorResponse()`/`handleMutationResponse()` are shared by all four proxied methods (`store`, `submit`, `approve`, `reject`). Of those, only `submit()`'s card (`draft`/`revising`) can ever be rendered on the same page load as the new `update()` card (`draft`/`revising`) — `approve()`/`reject()`'s card only renders at `submitted`, which is mutually exclusive with `draft`/`revising`, so there is no real collision risk for those two and no reason to change their bag. Rather than hardcode a bag name inside the shared `handleErrorResponse()` (which would silently change `store()`/`approve()`/`reject()` too), add an optional parameter, defaulting to the current unnamed-bag behavior so the other three call sites are untouched:

```php
private function handleErrorResponse(JsonResponse $response, ?string $errorBag = null): RedirectResponse
{
    $payload = $response->getData(true);

    if ($response->getStatusCode() === 422 && isset($payload['data']) && is_array($payload['data'])) {
        return back()->withErrors($payload['data'], $errorBag ?? 'default')->withInput();
    }

    return back()->withInput()->with('error', (string) ($payload['message'] ?? 'Không thể xử lý yêu cầu.'));
}

private function handleMutationResponse(JsonResponse $response, string $successUrl, string $successMessage, ?string $errorBag = null): RedirectResponse
{
    if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
        return redirect($successUrl)->with('success', $successMessage);
    }

    return $this->handleErrorResponse($response, $errorBag);
}
```

Only `submit()`'s call site changes, to pass `'submittalResubmit'`; `store()`/`approve()`/`reject()`'s call sites are unchanged (call with the default, no new argument), so their behavior is provably identical to `main` today. This is the only edit to the four pre-existing proxied methods this spec makes — it does not violate §10's "leave the proxy pattern alone," since the proxy *mechanism* (building a synthetic request, calling `ApiSubmittalController`, translating its JSON) is untouched; only an optional parameter is added to a shared private helper, with every existing call site preserving its exact current behavior.

In `show.blade.php`, each card reads its own bag:

```blade
@if ($errors->submittalUpdate->any())
    {{-- render inside "Sửa nội dung" card --}}
@endif

@if ($errors->submittalResubmit->any())
    {{-- render inside the resubmit control in "Thao tác" card --}}
@endif
```

Because Laravel redirects `back()` to the referring page (this same `show.blade.php` route) and both `withErrors()` calls carry `withInput()`, the user lands back on the same page, in the card whose bag has errors, with their typed values preserved via `old('title')` etc. — no navigation away from the page that failed.

## 8. Test matrix

All 12 items from the design review, phrased against real field/relation names verified in §5:

| # | Case | Type |
|---|---|---|
| 1 | `start-revision` copies latest `SubmittalRevision` snapshot into `Submittal`'s working-copy columns but does **not** create a new `submittal_revisions` row (row count unchanged) | Feature |
| 2 | Rejection reason (`rejection_reason`, `rejection_comments`, `rejectedBy.name`, `rejected_at`) still renders after `status` moves from `rejected` to `revising` | Feature |
| 3 | `update()` while `revising` does not modify the `rejected` `SubmittalRevision` row's `title`/`description`/`decision`/`decision_comments` — only the parent `Submittal`'s working-copy columns change | Feature |
| 4 | Resubmit (`submit()` while `revising`, with `revision_summary`) creates `submittal_revisions` row with `revision_no = 2`, `decision = null`, content matching the just-saved edit, `revision_summary` matching what was typed | Feature |
| 5 | Revision 1 (the rejected one) is byte-identical before and after step 4 — asserted via `assertDatabaseHas` on its original snapshot columns | Feature |
| 6 | After step 4, `$submittal->fresh()->current_revision_no === 2` | Feature |
| 7 | `@can` hides "Sửa nội dung"/"Mở lại để sửa"/"Gửi lại" controls for a user missing `submittal.edit`/`submittal.submit` respectively (`assertDontSee` on the button text/form action) | Feature |
| 8 | Same missing-permission user hitting `PUT /submittals/{id}` or `POST /submittals/{id}/start-revision` directly gets `403` (bypassing the hidden button) | Feature |
| 9 | Cross-tenant `PUT`/`POST start-revision`/`GET show` on another tenant's submittal id returns `404` | Feature |
| 10 | Validation failure on `update()` returns to `show.blade.php` with `old('title')` etc. populated and errors present in the `submittalUpdate` bag specifically (not the default bag) | Feature |
| 11 | `update()` and `start-revision` both 400/error (via `SubmittalTransitionNotAllowedException` → flash) when `status ∈ {submitted, approved}` — these are read-only relative to this UI | Feature |
| 12 | Attempting resubmit is blocked client-side when the edit form is dirty (see §6 — Dusk, not PHPUnit) | Browser |

Additional tests required by review points not in the original 12:

| # | Case | Type |
|---|---|---|
| 13 | Editing `title` only, when `contractor` holds a vendor name that no longer exists in `vendors` (renamed away), succeeds — proves the "only validate vendor when changed" rule (§3) | Feature |
| 14 | Editing `contractor` to a name that does not exist in `vendors` for the tenant fails validation, in the `submittalUpdate` bag | Feature |
| 15 (regression) | `SubmittalContentRules::rules()` extraction from `SubmittalController::update()` (API) does not change API behavior: table-driven test asserting the same accept/reject outcome for representative payloads (valid; missing `title`; missing `description`; invalid `submittal_type` enum value; `specification_section` over 255 chars; `due_date` non-date string) both **before** conceptually (documented via existing `SubmittalApiTest`/`SubmittalResubmitLifecycleTest` coverage already in the suite) and **after** this refactor — the plan must run the full existing `SubmittalApiTest`/`SubmittalResubmitLifecycleTest` suite unmodified post-refactor and confirm zero behavior change, not just add a new test | Feature |

## 9. Manual / browser acceptance checks

Checklist for the implementer to run once against a live `php artisan serve` instance (or record as a Dusk test per §6), beyond the automated Dusk test:

- [ ] Open a `rejected` submittal as a user with `submittal.submit`: "Mở lại để sửa" is visible and clickable; after clicking, status shows `revising`, "Sửa nội dung" card appears pre-filled with the last-submitted (rejected) content.
- [ ] Edit a field in "Sửa nội dung", observe "Gửi lại" become disabled and the warning line appear, without reloading the page.
- [ ] Click "Lưu thay đổi", observe redirect back to the same page with a success flash, "Gửi lại" re-enabled, warning gone.
- [ ] Type into `revision_summary`, click "Gửi lại": status returns to `submitted`.
- [ ] As a user with only `submittal.view` (no `submittal.edit`/`submittal.submit`): open the same submittal, confirm none of "Sửa nội dung"/"Mở lại để sửa"/"Gửi lại" render.
- [ ] Select a vendor from the `contractor` dropdown, save, then deactivate that vendor (`is_active = false`) directly in DB/admin, reload the edit page: confirm the current vendor still shows selected (as a synthesized "(không còn hoạt động)" option) rather than blank.

## 10. Out of scope

- **File/attachment upload** — no upload UI exists anywhere in this app today (confirmed: neither `create.blade.php` nor `SubmittalPageController` reference `file_url`/`attachments`/`<input type="file">`). Not introduced by this spec. Tracked as a separate, pre-existing gap if/when prioritized — not filed as a new register entry by this spec, since it predates GAP-029 and isn't a dead-end this PR creates.
- **Refactoring `store()`/`submit()`/`approve()`/`reject()` off the `ApiSubmittalController` proxy pattern.** These four methods continue to build a synthetic `Request`, call `ApiSubmittalController`, and translate its `JsonResponse` back into a redirect — exactly as they do on `main` today. This is architecture debt (the two *new* methods in this spec deliberately do not add to it, per review point 6) but fixing the four existing ones is a separate, larger change touching already-shipped, already-tested PR#229 code, with its own regression risk. Not done here.
- **A new `submittal.start_revision` permission.** `startRevision` reuses `submittal.submit`, matching the decision already made and shipped in PR#229's `SubmittalPolicy`.
- **`package_no` editability.** Confirmed absent from both the service allowlist and every existing form; stays immutable.
- **Any change to `SubmittalLifecycleService`, `SubmittalPolicy`, migrations, or the API controller's business logic.** The only API-layer change is the mechanical `SubmittalContentRules` extraction (§3), which is asserted behavior-preserving by test §8.15.
- **Tracked technical debt (not fixed by this spec): `Submittal.contractor`/`manufacturer` reference `vendors.name` by string value, not `vendors.id` by foreign key.** This is why §3's vendor validation has to be conditional-on-change rather than a straightforward existence check, and why a renamed vendor silently orphans the string on every submittal that referenced the old name (no error, no cascade, no way to detect it happened short of a manual audit query). Fixing this properly means adding `contractor_id`/`manufacturer_id` FK columns, a migration to backfill them from the current name strings, and updating every read path (`create.blade.php`, the new edit form, `show.blade.php`, `SubmittalController::store()`/`update()`) — a schema change well beyond a UI-only spec. This spec works around the symptom (conditional validation, a synthesized "(không còn hoạt động)" option for the current view) without touching the root cause.

## 11. Rollback considerations

- All schema is unchanged — this spec adds zero migrations. Rollback is a pure code revert (routes, one new controller with two methods, one new support class, view changes).
- The `SubmittalContentRules` extraction touches `SubmittalController::update()` (API, already in production since PR#229). If a regression is suspected, first check whether §8.15's before/after parity test still passes — if it does, the extraction is not the cause; if it doesn't, revert `SubmittalController::update()` to its inline rules array (git revert of that one file is safe in isolation, since `SubmittalContentRules` is additive and nothing else depends on it being present).
- The two new routes (`PUT /submittals/{id}`, `POST /submittals/{id}/start-revision`) are net-new — removing them removes the feature with no effect on any other route, since nothing else calls `SubmittalPageController::update()`/`startRevision()`.
- The rejection-info card replaces an existing unconditional block in `show.blade.php` (§5) rather than adding a parallel one — a revert of `show.blade.php` alone restores the exact PR#229 behavior (unconditional rejection-reason display, no status gating) without touching any other file.
