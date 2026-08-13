# GAP-033 — Engineering Evidence Layer: Document approver assignment (current `main`)

> Fact-finding pass for Gate 1. Records **FACTS** only from current `main`. No solutions recommended.

## Attestation

- **Work ID:** GAP-033
- **Baseline:** `origin/main` HEAD `19c96cfa6f1b4aca654b280a092b2ff511e749d3` (GAP-032 Gate 3 release merge, PR #256).
- **Branch:** `docs/GAP-033-gate1-prep`, created directly off `19c96cfa`.
- **Method:** repository file inspection only (migrations, models, policies, seeders, routes, specs). No runtime execution. Production DB **not** queried.

## Evidence provenance key

- `CANONICAL MIGRATION/SCHEMA EVIDENCE` — statements in `database/migrations/*.php`.
- `RUNTIME/CANONICAL CODE EVIDENCE` — statements in `app/` (live `App\` namespace only).
- `SPEC EVIDENCE` — statements in `docs/superpowers/specs/*.md`.
- `PRODUCTION FACTS UNKNOWN` — value of production only; stated explicitly when not derivable from repo contract.

---

## 1. How `document.approve` is granted today

### Runtime/canonical code evidence

- `database/seeders/PermissionSeeder.php:46` — permission definition: `['code' => 'document.approve', 'module' => 'document', 'action' => 'approve', ...]`.
- `database/seeders/ZenaPermissionsSeeder.php:140` — same code defined in the canonical seeder: `'code' => 'document.approve'`.
- `database/seeders/PermissionSeeder.php:117` — role grant: `document.approve` is assigned to the "Project Manager" role inside `assignPermissionsToRoles()` (`PermissionSeeder.php:99-127`), alongside `document.create/read/update`.
- `database/seeders/PermissionSeeder.php:105-109` — "System Admin" role is synced **all** permissions (`Permission::all()`), which includes `document.approve`.
- `database/seeders/ZenaAdminRolePermissionSeeder.php:11-16,30-39` — canonical seeder syncs the entire `ZenaPermissionsSeeder::CANONICAL_PERMISSIONS` list (including `document.approve`) to every role named `System Admin`, `Admin`, `super_admin`, `system_admin`.
- `database/seeders/PermissionSeeder.php:131-157` — "Project Member" role receives only `document.read`/`document.create` — **not** `document.approve`.

### Enforcement — confirmed tenant-wide/role-wide, not per-document

- `app/Policies/DocumentPolicy.php:113-121`:
  ```php
  public function approve(User $user, Document $document)
  {
      if ($user->tenant_id !== $document->tenant_id) {
          return false;
      }
      return $user->hasPermission('document.approve');
  }
  ```
  No comparison of `$user->id` against any field on `$document`. Tenant match + role-wide permission is the entire check.
- `app/Models/User.php:194-199` — `hasPermission()` resolves via `$this->roles()->whereHas('permissions', ...)->exists()` — true for **any** user whose role carries the permission; no record-level scoping in this method.
- `routes/web.php:420-421` — `POST /documents/{document}/approve` and `/reject` gated only by `->middleware('rbac:document.approve')`.
- `routes/api_zena.php:485` — `POST /{id}/decision` (canonical API decision endpoint, `SimpleDocumentController::decision`) gated only by `rbac:document.approve`.

**FACT:** Every enforcement point (policy method, both route middlewares) checks tenant membership + a role-wide permission only. No enforcement point anywhere in the current `main` compares the authenticated user's identity to any per-document field.

---

## 2. Does `App\Models\Document` have a per-document approver field?

### Canonical migration/schema evidence

- `database/migrations/2025_09_14_160324_create_zena_documents_table.php` — original `documents` columns: `id, project_id, uploaded_by, name, original_name, file_path, file_type, mime_type, file_size, file_hash, category, description, metadata, status, version, is_current_version, parent_document_id`. No approver/assignee/reviewer column.
- Later alter migrations on `documents` — none add an approver-shaped column:
  - `database/migrations/2026_02_02_090000_add_documents_creator_columns.php` (adds `created_by`/`updated_by`)
  - `database/migrations/2026_02_09_010000_add_current_version_id_to_documents.php` (adds `current_version_id`)
  - `database/migrations/2026_02_09_020000_add_visibility_and_linked_entities_to_documents.php` (adds `visibility`, `linked_entity_type`, `linked_entity_id`)
  - `database/migrations/2026_03_16_090000_add_document_center_metadata_columns.php` (adds document-center metadata columns)
  - `database/migrations/2026_08_10_230000_add_canonical_status_dimensions_to_documents.php` (GAP-032: adds `lifecycle_status`/`approval_status`)

### Runtime/canonical code evidence

- `app/Models/Document.php:102-132` — full `$fillable` list: `project_id, tenant_id, uploaded_by, created_by, updated_by, name, title, document_type, discipline, package, revision, original_name, file_path, file_type, mime_type, file_size, file_hash, category, visibility, client_approved, linked_entity_type, linked_entity_id, description, metadata, status, version, is_current_version, current_version_id, parent_document_id`. No `approver_id`/`assigned_to`/`reviewer_id`.
- `app/Models/Document.php:165-192` — relations: `uploader()` (`uploaded_by`), `creator()` (`created_by`), `updater()` (`updated_by`), `project()`, `tenant()`. No relation to a designated decision-maker.

### `DocumentApprovalEvent` is a post-hoc audit trail, not a pre-assignment

- `app/Models/DocumentApprovalEvent.php:53-63` — `$fillable`: `tenant_id, document_id, document_version_id, event, from_approval_status, to_approval_status, actor_id, note, context`.
- `app/Models/DocumentApprovalEvent.php:73-97` — `save()`/`delete()` throw `LogicException`; rows are append-only, written exclusively by `DocumentWorkflowService` at the moment a `submitted`/`approved`/`rejected`/`reopened`/`reactivated` event actually occurs.
- `app/Models/DocumentApprovalEvent.php:116-120` — `actor()` relation: `belongsTo(User::class, 'actor_id')`.

**FACT:** `actor_id` records who *performed* a decision, written at the time the decision happens. No field on `Document` or `DocumentApprovalEvent` records, in advance of a decision, who *should* be the one to decide. Pre-assignment and post-hoc audit are two different, currently-conflated concepts; only the audit half exists today.

---

## 3. Today Workspace spec — exact exclusion condition

### Spec evidence

`docs/superpowers/specs/2026-07-31-today-workspace-mvp-design.md`:

- Line 310 (§7 opening): "Kết luận sau khi xác minh code: 0 nguồn trong repo hiện tại thoả đủ điều kiện actor/hành động/record/trạng thái/quyền/route xác định. Do đó Action Required không xuất hiện trong `TodayWorkspaceViewModel`, không có query object, không có UI, không có section trong Blade view của MVP."
- Line 313 (Document-specific exclusion): "**Document approval** — loại trừ vì `Document` không có cột approver nào; `DocumentController::approve()`/`reject()` ghi các field (`approved_by`, `approved_at`, `approval_note`) không nằm trong `$fillable` (bị mass-assignment bỏ qua âm thầm). Đã đăng ký **GAP-031**."
- Lines 316-323 (admission conditions — condition 1 is the one Document fails today): "1. Actor xác định được bằng 1 điều kiện truy vấn cụ thể (cột trực tiếp hoặc join xác định, không suy đoán)." and "4. Permission bắt buộc ở route đích khớp với chính xác tập actor xác định ở (1)..."
- Line 330 (§8 cross-reference): "`GAP-031` (Tier 2) vẫn được đăng ký nguyên trạng: `Document::$fillable` không có field approver; ... không có cách xác định approver cụ thể cho 1 Document."

**Correction to the register's prior citation:** the substantive Action Required exclusion text is entirely in **§7** of this spec (line 308 onward). §6.4 in this spec is "Upcoming Milestones" (line 201), unrelated to Document approval. `OPERATIONAL_GAP_REGISTER.md`'s GAP-033 row has been corrected to cite §7 only.

**FACT:** The spec's own admission condition 1 (actor identifiable by a specific, direct query condition) is exactly what §1/§2 of this evidence file confirm is missing for `Document` today.

---

## 4. Existing repo precedent: a per-record actor field enforced at the policy layer

### `Ncr` — exact analog

- `database/migrations/2025_09_20_142033_create_ncrs_table.php:27` — `$table->ulid('assigned_to')->nullable();` (indexed line 43, FK to `users` line 52).
- `app/Models/Ncr.php:29` — `assigned_to` in `$fillable`.
- `app/Models/Ncr.php:80-82` — `assignee(): BelongsTo` → `User::class, 'assigned_to'`.
- `app/Models/Ncr.php:160` — `scopeAssignedTo` — `where('assigned_to', $userId)`.
- `app/Policies/NcrPolicy.php:47-51`:
  ```php
  public function resolve(User $user, Ncr $ncr)
  {
      if ($user->tenant_id !== $ncr->tenant_id) return false;
      return $user->id === $ncr->assigned_to || $user->hasRole(['super_admin', 'admin', 'pm']);
  }
  ```

**FACT:** `NcrPolicy::resolve()` compares the authenticated user's ID directly against a per-record `assigned_to` column, in addition to allowing broader roles. This is a real, currently-shipping example in this codebase of the exact mechanism `Document`/`DocumentPolicy::approve()` lacks.

### `Rfi` — has the field, but the approve policy does not use it (parallel gap, tracked as GAP-030)

- `database/migrations/2025_09_20_133629_create_rfis_table.php:30` — `assigned_to` column exists on `rfis`.
- `app/Models/Rfi.php:26,65,143,266` — `assigned_to` fillable, `assignee()` relation, `scopeAssignedTo`.
- `app/Policies/RfiPolicy.php:47-51` — `approve()` checks only `hasRole(['super_admin','admin','pm'])`, ignoring `assigned_to` entirely.

**FACT:** `assigned_to`-style per-record actor columns already exist on multiple tables in this schema (`Rfi`, `ChangeRequest`, `DesignItem`, `SupportTicket`, `OpportunityAppointment` — `app/Models/ChangeRequest.php:98,187-189`; `app/Models/DesignItem.php:111,136-138`; `app/Models/SupportTicket.php:27,59`; `app/Models/OpportunityAppointment.php:69,92-94`), and at least one of them (`Ncr`) is fully wired into its approval-equivalent policy check. `Document` has neither the column nor the policy check.

---

## 5. Roles and `document.approve` grant summary

- `database/seeders/RoleSeeder.php:23-51` — three system roles: **System Admin** (23-31), **Project Manager** (33-41), **Project Member** (43-51).
- **System Admin** — has `document.approve` (full-permission sync, §1 above).
- **Project Manager** — has `document.approve` (explicit grant, `PermissionSeeder.php:117`).
- **Project Member** — does not have `document.approve` (`PermissionSeeder.php:131-157`, only `document.read`/`document.create`).
- **Naming-convention note (not part of this gap's core claim):** `app/Policies/DocumentPolicy.php` and test fixtures (e.g. `tests/Feature/Web/DocumentWorkflowControllerTest.php:205`) reference lowercase role slugs (`super_admin`, `admin`, `pm`, `designer`, `engineer`) distinct from the seeded human-readable role names (`System Admin`, `Project Manager`, `Project Member`) — two naming conventions coexist in the codebase. Flagged for awareness; not evidence for or against GAP-033 itself.

---

## 6. No existing "default approver" / "project-type-based approver" concept

- Repository-wide search for `default_approver` in `app/` and `database/migrations/`: **zero matches**.
- Repository-wide search for `approver_id`/`reviewer_id` as literal column/field names in `app/` and `database/migrations/`: **zero matches**. (Per-record actor fields that do exist use different names: `assigned_to`, `assignee_id`, `owner_id`, `sales_owner_id`, `technical_owner_id` — see §4.)
- No commented-out or otherwise dead code matching `approver`/`default_approver` patterns found in `app/` or `database/migrations/`.

**FACT:** There is no partial, dead, or in-progress implementation of an approver-assignment concept anywhere in the current codebase for any entity. The `Ncr`/`Rfi`-style `assigned_to` pattern (§4) is the closest existing precedent, not a direct GAP-033 solution already in place.

---

## 7. Register cross-reference (dependency/overlap check)

From `OPERATIONAL_GAP_REGISTER.md` at this baseline:

- **GAP-031** — `RESOLVED (verified)`. Its own notes disclaim GAP-033: fixed workflow correctness/audit, explicitly did not add a per-document approver.
- **GAP-032** — `RESOLVED (verified)` as of this evidence pass (Gate 3 Owner-approved 2026-08-12, merged `19c96cfa`). Gives GAP-033 a stable canonical `awaiting-approval` Approval state to key off of; did not add an approver field. Positive precondition, not a blocker.
- **GAP-030** — `OPEN (verified) — intentionally deferred`. Same class of problem for `Rfi` (role-wide `rfi.escalate`/resolve permission, no target-specific check at the route-middleware layer), explicitly deferred pending a business decision on the actor-role capability model. Parallel/analogous, not a direct dependency — different entity, and the Owner has separately asked that GAP-033 not expand scope into other work items.
- **GAP-012/GAP-013** — `OPEN` (notification fan-out gaps for Change Request/Submittal). Referenced in the same Today Workspace spec §7 as siblings excluded from Action Required, but a different mechanism (notification delivery, not per-record actor identity) and a different entity — not a dependency of GAP-033.
- No other OPEN register row references `document.approve`, `DocumentPolicy`, or Today Workspace RBAC directly.

**FACT:** GAP-033 has no unresolved blocking dependency. Its one positive precondition (a stable canonical Approval state) was met by GAP-032's release on this same baseline.
