# Submittal resubmit (rejected → revising → submitted) — Design

Date: 2026-07-24
Status: Approved for planning

## Background

Audit trace (2026-07-24) of the project lifecycle (`Lead → Opportunity → Project/Contract/Submittal/SiteDiary/Milestone`) found several states with no exit path or no owning actor. This spec fixes the highest-priority one: `Submittal.status` `rejected` and `approved` are dead ends — `SubmittalController::update()` (`app/Http/Controllers/Api/SubmittalController.php:228`) prohibits changing `status`, so the only way to "fix and resend" a rejected submittal today is `destroy()` + create a new record from scratch, losing all history.

The model already declares an unused `STATUS_REVISED` value with a transition `revised → submitted` (`app/Models/Submittal.php:66,74`) that was never reachable because `submit()` hardcodes `status !== 'draft'` as its only guard (`SubmittalController.php:309`). This design replaces that dead, ambiguously-named status with a single-purpose `revising` status and builds a real state machine around it.

Three other dead-end/orphan issues were found in the same audit (Contract has no ownership over its status field, Lead `discarded` has no exit, CRM→Contract transitions send no notifications). Those are out of scope for this spec and will get their own design docs.

## 1. State machine

```
draft     → submitted   [submit()]
submitted → approved    [approve()]
submitted → rejected    [reject()]
rejected  → revising    [start-revision()]
revising  → submitted   [submit()]   (revision_summary required)
approved  → ∅ (terminal)
```

```php
public const TRANSITIONS = [
    self::STATUS_DRAFT     => [self::STATUS_SUBMITTED],
    self::STATUS_SUBMITTED => [self::STATUS_APPROVED, self::STATUS_REJECTED],
    self::STATUS_REJECTED  => [self::STATUS_REVISING],
    self::STATUS_REVISING  => [self::STATUS_SUBMITTED],
    self::STATUS_APPROVED  => [],
];
```

`STATUS_PENDING_REVIEW` is left untouched — it is already dead (no code path ever sets it; `review()`/`approve()`/`reject()` only *accept* it as an input state for backward compatibility). Not in scope here; flagged as separate debt.

`STATUS_REVISED` is removed entirely. Data-fix migration step: `UPDATE submittals SET status = 'rejected' WHERE status = 'revised'` before the new enum/validators are deployed (this path was unreachable in production code, so this is a safety net, not an expected no-op).

## 2. Status semantics

| Status | Meaning | Working-copy content editable? |
|---|---|---|
| `draft` | Being authored for the first time, never submitted | Yes |
| `submitted` | Sent, awaiting decision; corresponds to one `submittal_revisions` row with `decision = null` | No |
| `approved` | Reviewer approved the current revision — **absolute terminal**, no path out | No |
| `rejected` | Reviewer rejected the current revision; that revision's content snapshot is permanently locked | No (must go through `revising`) |
| `revising` | Author is editing after a rejection, not yet resubmitted; the *only* status that means "being edited for resubmission" | Yes |

## 3. Data model

### `submittals` (parent) — identity columns + working-copy

Working-copy columns (`title`, `description`, `file_url`, `attachments`) are only meaningful while `status ∈ {draft, revising}`; they're the form the author edits before snapshotting into a revision at submit time.

Existing decision columns (`approved_by/at`, `rejected_by/at`, `rejection_reason`, `review_comments`, `reviewed_by/at`, `approval_comments`) are **kept but downgraded to denormalized convenience** — the lifecycle service still mirrors the latest decision into them so existing views/dashboards keep working, but they are no longer the source of truth. Not dropped, to avoid touching every existing reader.

`current_revision_no` (nullable int) added — null until first submit.

### `submittal_revisions` (new table)

```php
Schema::create('submittal_revisions', function (Blueprint $t) {
    $t->ulid('id')->primary();
    $t->ulid('tenant_id');
    $t->foreignUlid('submittal_id')->constrained('submittals')->cascadeOnDelete();
    $t->unsignedInteger('revision_no');
    $t->text('revision_summary')->nullable();       // required when revision_no > 1
    // immutable content snapshot at submit time
    $t->string('title');
    $t->text('description');
    $t->string('file_url')->nullable();
    $t->json('attachment_manifest')->nullable();     // [{document_id, version, checksum}]
    $t->ulid('submitted_by');
    $t->timestamp('submitted_at');
    // decision — the only part of a row that mutates, exactly once
    $t->string('decision')->nullable();               // approved|rejected
    $t->ulid('decided_by')->nullable();
    $t->timestamp('decided_at')->nullable();
    $t->text('decision_comments')->nullable();
    $t->timestamp('created_at');
    $t->unique(['submittal_id', 'revision_no']);
});
```

No separate review-events table: the business has exactly one decision per revision (approve or reject is terminal for that revision), so a single mutable-once `decision` column is sufficient.

Relations: `Submittal::revisions(): HasMany`, `Submittal::currentRevision(): HasOne` (latest by `revision_no`).

## 4. Lifecycle service

`app/Services/SubmittalLifecycleService.php` — the **only** place that writes `Submittal::status`:

```php
class SubmittalLifecycleService
{
    public function submit(Submittal $submittal, array $context): Submittal;        // draft|revising → submitted, creates a revision row
    public function approve(Submittal $submittal, array $context): Submittal;       // submitted → approved
    public function reject(Submittal $submittal, array $context): Submittal;        // submitted → rejected
    public function startRevision(Submittal $submittal, array $context): Submittal; // rejected → revising
    public function updateContent(Submittal $submittal, array $data, array $context): Submittal; // no transition; only when draft|revising
}
```

Every method: guards via `Submittal::TRANSITIONS`, row-locks, writes an `EventRecord`, all inside one `DB::transaction`. `SubmittalController::review()` stops calling `->update(['status' => ...])` directly — it becomes a thin adapter that maps `review_status` (`approved`|`rejected` only — `revised` removed from the accepted enum, its old meaning conflicts with the new `revising`) to `approve()`/`reject()`.

## 5. Endpoint contract

| Method & path | Service call | Valid incoming status | Required body |
|---|---|---|---|
| `POST /submittals` | creates `draft`, no revision yet | — | as today |
| `PATCH /submittals/{id}` | `updateContent()` | `draft`, `revising` | descriptive fields; `status` stays `prohibited` |
| `POST /submittals/{id}/submit` | `submit()` | `draft`, `revising` | `revision_summary` required iff current status is `revising` |
| `POST /submittals/{id}/start-revision` | `startRevision()` | `rejected` | — (copies latest revision's snapshot back into the working-copy fields) |
| `POST /submittals/{id}/approve` | `approve()` | `submitted` | `approval_comments?` |
| `POST /submittals/{id}/reject` | `reject()` | `submitted` | `rejection_reason` required |
| `DELETE /submittals/{id}` | — | **`draft` only** (new guard; today `destroy()` has none, which directly contradicts preserving the audit trail this design introduces) | — |

## 6. Transaction / concurrency strategy

Every lifecycle method:

```php
DB::transaction(function () use (...) {
    $submittal = Submittal::query()
        ->where('id', $id)->where('tenant_id', $tenantId)
        ->lockForUpdate()->firstOrFail();

    if (!Submittal::canTransition($submittal->status, $target)) {
        throw ValidationException::withMessages(['status' => 'Invalid transition.']);
    }
    // ... update parent, create/lock revision
});
```

`approve()`/`reject()` add a second protection layer — a conditional update on the revision row itself:

```php
$affected = SubmittalRevision::where('id', $revisionId)->whereNull('decision')->update([...]);
if ($affected === 0) { throw new ConflictException('Revision already decided.'); }
```

Notification dispatch happens **after** the `DB::transaction` closure returns (i.e., after commit), wrapped in try/catch, but the catch **logs**, it does not swallow silently:

```php
try {
    Notification::query()->create([...]);
} catch (\Throwable $e) {
    Log::error('submittal.notification_failed', ['submittal_id' => $submittal->id, 'error' => $e->getMessage()]);
}
```

## 7. Authorization matrix

New `app/Policies/SubmittalPolicy.php` (none exists today — `SubmittalController` currently has zero `$this->authorize()` calls, relying solely on route `rbac:` middleware), registered in `AuthServiceProvider`:

| Action | Permission | Extra condition |
|---|---|---|
| `view` | `submittal.view` | tenant match |
| `create` | `submittal.create` | — |
| `update` (PATCH content) | `submittal.edit` | tenant match + `status ∈ {draft, revising}` (defense-in-depth, mirrors the service guard) |
| `submit` | `submittal.submit` | tenant match |
| `startRevision` | `submittal.submit` (shared, per earlier decision) | tenant match + `status === rejected` |
| `approve` | `submittal.approve` | tenant match |
| `reject` | `submittal.reject` | tenant match |
| `delete` | `submittal.delete` | tenant match + `status === draft` |

Controllers call `$this->authorize(...)` on every action; route middleware `rbac:submittal.*` is kept as the outer layer (additive, not a replacement). Cross-tenant: `TenantScope` global scope + the controller's explicit tenant filter mean a record from another tenant always resolves to `ModelNotFoundException` → **404**, never 403 (no existence leak).

## 8. Notification behavior

Fires only when `submit()` performs the `revising → submitted` transition (a genuine resubmit; not on the first `draft → submitted` submit). Recipient is read from revision history, not the mutable `rejected_by` column on the parent:

```php
$recipient = $submittal->revisions()
    ->where('decision', 'rejected')
    ->orderByDesc('revision_no')
    ->value('decided_by');
```

Runs after commit; failures are logged via `Log::error`, never fail the main request.

## 9. Acceptance criteria

- No controller path calls `$submittal->update(['status' => ...])` directly — every transition goes through `SubmittalLifecycleService`.
- `PATCH /submittals/{id}` returns 422/409 when `status ∉ {draft, revising}`, even when the request doesn't touch `status` itself.
- Content of a rejected revision is preserved unchanged in `submittal_revisions`, never overwritten by a later resubmit.
- `approved` has no endpoint or service method that moves it anywhere else.
- Two concurrent `approve()`/`reject()` requests on the same submittal: exactly one succeeds, the other gets a clear conflict error (no silent overwrite).
- A user from a different tenant gets 404 on every submittal endpoint.
- Notification failures always produce a log entry, never fail silently.

## 10. Test matrix

| # | Case | Type |
|---|---|---|
| 1 | Revision snapshot is immutable — editing the working-copy after submit doesn't affect the created revision | Feature |
| 2 | `PATCH` blocked (422/409) when status is `submitted`/`rejected`/`approved`; allowed for `draft`/`revising` | Feature |
| 3 | Concurrent double-submit (two parallel `submit()` calls on a `revising` submittal) — only one creates a new revision | Feature + DB lock assertion |
| 4 | Repeated `start-revision()` call while already `revising` → transition error, no side effects | Feature |
| 5 | Cross-tenant: tenant B user calls every endpoint with a tenant A submittal ID → 404 | Feature |
| 6 | Authorization: user missing each of `submittal.edit/submit/approve/reject/delete` → 403 | Feature |
| 7 | Notification runs after commit; simulated Notification-creation failure → request still 200, log has an error entry | Feature (mock/log assertion) |
| 8 | `approved` is terminal — no endpoint can move it out | Feature |
| 9 | `status` sent via `PATCH /submittals/{id}` is ignored/rejected even when other fields are valid | Feature |
| 10 | Full audit chain: `draft → submit → reject → start-revision → submit(resubmit) → approve` produces the expected `EventRecord`s + 2 `submittal_revisions` rows with decisions in the right order | Feature (end-to-end) |

## Out of scope (tracked separately)

- Contract status has no transition guard / no owning permission (item #2 of the original 4-item list).
- `Lead::discarded` has no exit path (item #4).
- CRM→Contract chain (`LeadController`, `OpportunityController`, `ContractController`) sends zero notifications on any transition (item #3).
- `STATUS_PENDING_REVIEW` on `Submittal` remains dead code, untouched by this spec.
