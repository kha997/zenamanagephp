# Design-PM Completion (R-DPM) — Design Spec

Date: 2026-07-13
Status: approved by user (A–A–A on the three decision points, 2026-07-13)
Origin: `docs/superpowers/plans/2026-07-12-hardening-slice-and-completion-roadmap.md`, roadmap item R-DPM.

## Purpose

The ZENA vision's "quản lý dự án" explicitly includes design-project management. An operator must be able to answer, per design project: which tasks exist, who is assigned, what is done/not done, what difficulties (blockers) came up, is the design option approved yet, and which revision number each design item is on.

Code-verified: task breakdown (`App\Models\Task`: `assignee_id`, `status`, `progress_percent`, `phase_id`, dependencies), templated workflows (`WorkTemplate` → `WorkInstance` → `WorkInstanceStep`), and the client review cycle (`DesignItem.review_status` transition graph) already exist. Exactly three pieces are missing; this spec adds them and nothing else.

## Decisions (user-approved)

1. **Revisions are counted per DesignItem** (not per phase, not per project), stored as a history table plus a denormalized counter.
2. **Blockers are lightweight nullable fields** on both `Task` and `DesignItem` (no standalone Blocker model — upgrade path preserved).
3. **The per-project answer view is a section on the existing project detail page** (no separate page).

## Component 1 — DesignItem revision history

### Data

New table `design_item_revisions`:

| column | type | notes |
| --- | --- | --- |
| `id` | ULID PK | matches repo convention (`HasUlids`) |
| `tenant_id` | string, indexed | + `TenantScope` trait on the model |
| `design_item_id` | string, indexed, FK design_items | |
| `revision_no` | unsignedInteger | 1, 2, 3… per design item |
| `client_feedback` | text | snapshot of `client_feedback_notes` at request time |
| `requested_by` | string nullable | user id of the operator recording the request |
| `requested_at` | timestamp | |
| `resolved_at` | timestamp nullable | set when the item leaves `revision_requested` |
| timestamps | | |

New column on `design_items`: `revision_count` unsignedInteger default 0 (denormalized for list badges; always equals `max(revision_no)`).

New model `App\Models\DesignItemRevision` (`HasUlids`, `TenantScope`, `belongsTo(DesignItem)`); `DesignItem` gains `hasMany(DesignItemRevision::class)->orderBy('revision_no')`.

### Behavior

All writes happen inside `DesignItemController::updateStatus()` — the sole authority for `review_status` transitions (per the existing model docblock; this spec does not add any other write path):

- On a valid transition **into** `STATUS_REVISION_REQUESTED` (from `sent_to_client` or `approved`): create a `DesignItemRevision` with `revision_no = revision_count + 1`, `client_feedback` = the validated `client_feedback_notes` (already required by the existing validator), `requested_by` = the authenticated user, `requested_at = now()`; increment `design_items.revision_count`.
- On a valid transition **out of** `STATUS_REVISION_REQUESTED` (to `internal_review`): set `resolved_at = now()` on the latest unresolved revision of that item, if any.
- The transition graph itself is unchanged. No API contract fields are removed; the serializer gains `revision_count` (and the show endpoint gains the revision list).

### Display

- Lists (`design-items.index`, project section below): badge "Sửa lần {revision_count}" when `revision_count > 0`.
- `design-items.show`: revision timeline (revision_no, requested_at, feedback, resolved_at) below the existing status block.

## Component 2 — Blockers on Task and DesignItem

### Data

Three nullable columns on **both** `tasks` and `design_items`:

- `blocked_at` timestamp nullable — non-null means currently blocked
- `blocker_note` string(1000) nullable — required when blocking
- `blocked_by` string nullable — user id who flagged it

No history: unblocking clears all three. If a blocker timeline is ever needed, these columns become "current blocker" and a history table can be added without migration pain.

### Behavior

- Web endpoints (operator group, existing RBAC permissions — `task.manage` / `design-item.manage`):
  - `POST /tasks/{id}/block` and `POST /tasks/{id}/unblock`
  - `POST /design-items/{id}/block` and `POST /design-items/{id}/unblock`
- Block validates `blocker_note` required, max 1000. Unblock requires no body.
- Blocking is orthogonal to status: a blocked task keeps its status; a blocked DesignItem keeps its review_status. No transition-graph interaction.

### Display

- Red "Vướng" badge + note tooltip/inline on task lists, design-item lists, and the project section below.

## Component 3 — Per-project design-management section

One section ("Thiết kế & tiến độ") on the existing project detail page, three blocks:

1. **Hạng mục thiết kế** — each DesignItem of the project: name, `assigned_to` (user name), review_status badge (6 states), "Sửa lần N" badge, "Vướng" badge + note, `due_to_client_at`.
2. **Công việc** — project Tasks grouped by phase: title, assignee name, status, `progress_percent`, "Vướng" badge.
3. **Đang vướng** — union of currently-blocked Tasks + DesignItems with notes and who/when — the "where is this project stuck" answer.

Read-only projection (block/unblock actions link to the item pages); data via the page controller with eager loading (`with(['assignee', ...])`), no N+1.

**Plan-time verification required:** the project detail page has a known dual-model/dual-view history (Phase 9 finding, `app.projects.show`). Before choosing the attachment point, the implementation plan must identify which controller + Blade actually renders the operator project detail page today and attach there — do not guess from view file names.

## Error handling

- Revision creation and counter increment happen in the same DB transaction as the status save (wrap in `DB::transaction`); a failure rolls back the transition entirely.
- Block/unblock on an item of another tenant: 404 via existing scoped-query pattern (+ TenantScope defense in depth).
- Block when already blocked: overwrite note/timestamp (idempotent re-flag); unblock when not blocked: no-op success.

## Testing

- Feature tests on `updateStatus`: first revision gets `revision_no = 1`, second gets 2; snapshot feedback stored; `resolved_at` set on leaving revision_requested; approved → revision_requested also records; counter matches history; whole thing tenant-isolated.
- Feature tests on block/unblock for both models: note required, RBAC denied without permission, cross-tenant 404, unblock clears.
- Page test: project section renders items with expected badges (assignee, status, revision, blocker) for a seeded project; a blocked item appears in "Đang vướng".
- Guard: `DesignItemRevision` added to the TenantScope trait guard list in `tests/Feature/Models/TenantScopedCrmModelsTest.php`.

## Out of scope (YAGNI)

- Blocker history/workflow, blocker assignee, blocker categories.
- Revision diffs or file attachments per revision.
- Client-portal visibility of revisions/blockers (portal remains read-only as-is; candidate for R7).
- Any change to WorkInstanceStep or the transition graph.
- `/api/v1/*` surfaces.
