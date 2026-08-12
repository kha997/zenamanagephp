# GAP-033 — Document Approver Assignment: Gate 2 Business Design

**Status:** Gate 1 approved (2026-08-12). Gate 2 preparation — awaiting Owner decision. Gate 3 not started; implementation, merge, and release are not authorized.

**Objective:** Define how a specific approver/action-owner is designated per document, so that (a) `DocumentPolicy::approve()` can identify a specific responsible actor rather than only a tenant-wide permission, and (b) a future Today Workspace integration can query "documents awaiting my decision" with a direct, deterministic condition.

**Explicitly not designed here:** any Today Workspace query object, UI, or notification (those are separate, later work items that would consume this gate's output); GAP-030 (the analogous RFI gap).

---

## 1. Facts (repository evidence, not production)

Established in the Gate 1 evidence file: `docs/audits/2026-08-12-gap-033-document-approver-assignment-evidence.md`. No production database was inspected.

### 1.1 Current enforcement

- `DocumentPolicy::approve(User $user, Document $document)` (`app/Policies/DocumentPolicy.php:113-121`) checks only tenant match + `$user->hasPermission('document.approve')` — a role-wide permission, never compared against any field on `$document`.
- `document.approve` is granted to the "Project Manager" role (`database/seeders/PermissionSeeder.php:117`) and to admin-tier roles via full-permission sync (`database/seeders/ZenaAdminRolePermissionSeeder.php:11-16,30-39`). "Project Member" does not receive it.

### 1.2 Current schema

- `App\Models\Document` has no approver/assignee/reviewer column anywhere in its migration history (`app/Models/Document.php:102-132` `$fillable`).
- `DocumentApprovalEvent.actor_id` (`app/Models/DocumentApprovalEvent.php:53-63,116-120`) is a post-hoc, append-only audit field recorded at the moment a decision happens — not a pre-assignment field. GAP-033 must not conflate the two.

### 1.3 Existing repo precedent — a per-record actor field enforced at the policy layer

- `Ncr` (`database/migrations/2025_09_20_142033_create_ncrs_table.php:27`, `app/Models/Ncr.php:29,80-82,160`) has a nullable `assigned_to` column (`belongsTo(User::class, 'assigned_to')`), and `NcrPolicy::resolve()` (`app/Policies/NcrPolicy.php:47-51`) checks:
  ```php
  return $user->id === $ncr->assigned_to || $user->hasRole(['super_admin', 'admin', 'pm']);
  ```
  **Important semantic detail:** being the assigned actor is *sufficient on its own* — it is not an additional narrowing on top of a role requirement; an assigned user with no listed role can still resolve the NCR. This is a real business-rule choice already made elsewhere in this codebase and is directly relevant to §6.3 below.
- `Rfi` has the same-shaped `assigned_to` column (`database/migrations/2025_09_20_133629_create_rfis_table.php:30`) but its `approve()` policy does **not** check it (`app/Policies/RfiPolicy.php:47-51`, role-only) — this is the parallel, separately-tracked GAP-030, not solved here and not to be folded in.

### 1.4 Existing project-level field usable as a default

- `App\Models\Project` already has `pm_id` (`app/Models/Project.php:51` in `$fillable`), with two relations pointing at it — `manager()` and `projectManager()` (`app/Models/Project.php:196,201`, both `belongsTo(User::class, 'pm_id')`) — and a `manager_id` accessor/mutator pair that aliases the same underlying column (`app/Models/Project.php:211-217`). Every project that has been assigned a project manager already has this reference populated; no new schema is required to read it.

### 1.5 Today Workspace's exact admission condition

`docs/superpowers/specs/2026-07-31-today-workspace-mvp-design.md:316-323` — condition 1: "Actor xác định được bằng 1 điều kiện truy vấn cụ thể (**cột trực tiếp hoặc join xác định**, không suy đoán)." — a direct column *or a deterministic join* both satisfy this condition; only guessing/inference is disallowed. This matters for evaluating Option B/C below (a join to `projects.pm_id` is admissible, not just a direct `documents` column).

### 1.6 Production facts

Production data distribution for whether documents/projects currently have any of these fields populated is **UNKNOWN**. No production query was performed in Gate 1 or Gate 2.

---

## 2. Business requirements

1. **A specific responsible actor must be identifiable per document**, satisfying Today Workspace's admission condition (a direct column or a deterministic join, never an inference).
2. **No regression for existing documents.** A document with no explicit assignment must continue to be approvable by anyone holding `document.approve` today, exactly as it works now.
3. **No forced manual work on every document.** The business should not require someone to remember to set an approver on every single document for the system to keep working as it does today.
4. **The mechanism must not weaken tenant isolation or existing authorization.** Whatever narrows or designates an approver must stay inside `DocumentPolicy`'s tenant-scoped checks.
5. **The mechanism must be reassignable.** A wrongly-assigned or now-unavailable approver must be correctable by an authorized user before a decision is made.
6. **The mechanism must have a defined behavior across reopen/resubmit cycles** (GAP-032 §6.6/§6.11: reopening resets Approval to `not-submitted`) — GAP-033 must state whether a prior assignment survives a reopen or must be re-set.

---

## 3. Alternative business models

### Option A — Explicit per-document approver field only

**Concept:** Add a nullable per-document field naming a specific user as the designated approver, following the exact `Ncr.assigned_to` precedent (§1.3).

**How it works:**
- A document may optionally carry a reference to one specific user.
- If set, that user (in addition to anyone with the role-wide `document.approve` permission) is the designated approver for this document.
- If unset (the default, and the case for every existing/legacy document), behavior is unchanged from today.

**Query for "awaiting my decision":** `approval_status = 'awaiting-approval' AND approver_id = :user_id` — one direct column. Satisfies Today Workspace's condition 1 trivially, but **only** for documents that were explicitly assigned; unassigned documents remain excluded, same as today.

**Trade-offs:**
- Simplest to reason about; smallest schema footprint (one column).
- Directly mirrors an already-shipping repo pattern (`Ncr`), lowering design risk.
- Places 100% of the assignment burden on whoever creates/submits each document — nothing is assigned by default, so most documents likely remain unassigned unless someone remembers to set it every time.

### Option B — Project-level default approver only (reuse `projects.pm_id`)

**Concept:** No new column on `documents`. Every document's designated approver defaults to its project's existing `pm_id` (§1.4).

**How it works:**
- No per-document assignment step exists or is needed.
- "The designated approver for any document in project P" = "project P's project manager."

**Query for "awaiting my decision":** a join from `documents` to `projects` on `project_id`, filtering `projects.pm_id = :user_id` — a deterministic join, explicitly admissible under Today Workspace's condition 1 (§1.5).

**Trade-offs:**
- Zero migration on `documents`; works immediately for every project that already has a `pm_id` set.
- Zero per-document manual work — every document automatically has a designated approver the moment its project has a PM.
- **Cannot route an individual document to anyone other than the project's PM.** A document that specifically needs a different specialist's sign-off (e.g. a structural drawing needing the structural engineer, not the PM) has no way to be routed differently.
- Because the Project Manager role already holds `document.approve` today, this option mostly makes an *existing, already-working* default explicit and queryable — it does not add any new assignment capability beyond what a PM can already do.

### Option C — Hybrid: project-level default with optional per-document override

**Concept:** Combine A and B. A document may optionally carry an explicit per-document approver (as in A); if none is set, the designated approver defaults to the project's `pm_id` (as in B); if neither exists, behavior is unchanged from today (role-wide permission only).

**How it works (precedence, most specific wins):**
1. If the document has an explicit per-document approver set → that person is the designated approver.
2. Else, if the document's project has a `pm_id` set → that person is the designated approver.
3. Else → no specific designated approver; today's tenant/role-wide `document.approve` behavior applies unchanged (this is also the exact state of every legacy/never-touched document — full backward compatibility by construction).

**Query for "awaiting my decision":** `documents.approver_id = :user_id OR (documents.approver_id IS NULL AND EXISTS (SELECT 1 FROM projects WHERE projects.id = documents.project_id AND projects.pm_id = :user_id))` — still a deterministic condition (direct column with a deterministic join fallback), no guessing.

**Trade-offs:**
- Most closely matches how this kind of work actually happens in practice: most documents follow the project's default reviewer, but some need a named exception.
- Reuses two already-existing schema elements (the `Ncr.assigned_to` pattern and `Project.pm_id`) rather than inventing a new one.
- Degrades gracefully to exactly today's behavior wherever nothing is set — the safest legacy-compatibility posture, consistent with GAP-032's "preserve legacy status until an explicit action occurs" principle.
- Most implementation complexity of the three options (a two-tier resolution rule instead of one flat check).
- Raises one open sub-question for implementation planning (not decided here, see §7.5): whether to reuse `projects.pm_id` directly for this new purpose, or add a dedicated `projects.default_document_approver_id` field to avoid overloading a column that already carries broader "who manages this project" meaning elsewhere in the codebase.

---

## 4. Trade-off analysis

| Dimension | Option A — explicit per-document only | Option B — project default only | Option C — hybrid |
|---|---|---|---|
| Setup burden per document | High — must be set every time to have any effect | None — automatic | Low — only needed for exceptions |
| Backward compatibility for untouched/legacy documents | High — unset behaves exactly as today | High — automatic, but changes the *practical* approver set the moment a project has a PM (see note below) | High — unset behaves exactly as today, same as A |
| Flexibility (route a specific document to a non-default person) | High | None | High |
| Reuses existing repo pattern | Yes (`Ncr.assigned_to`) | Yes (`Project.pm_id`) | Yes (both) |
| Today Workspace query determinism | Direct column (simplest) | Deterministic join (still admissible) | Direct column + deterministic join fallback (still admissible, more complex) |
| Schema footprint | One nullable column on `documents` | None | One nullable column on `documents`; possible new field on `projects` (open question, §7.5) |
| Implementation complexity | Low | Low | Medium |
| Risk of a stale/wrong default going unnoticed | Low (nothing is automatic) | Medium — if a project's PM changes, every document in that project silently re-routes to the new PM without anyone deciding that on a per-document basis | Low for explicitly-assigned documents; same medium risk as B for documents relying on the fallback |

**Note on Option B's backward-compatibility row:** Option B does not change who is *authorized* to approve anything (PMs already hold `document.approve` today) — it only makes an existing default explicit and queryable. It is "backward compatible" in the authorization sense, but it does change which documents would newly appear in a future personalized "awaiting my decision" list, which is a real behavior change for that future consumer, not for approval authorization itself.

---

## 5. Recommended business model

**Recommendation: Option C — hybrid, with the per-document field taking precedence over the project default.**

1. **It matches how approval routing actually works in construction/design practice.** Most project documents naturally follow the project's own manager as the default reviewer; some specific documents (a structural calc, a specialty submittal) need a named exception. Neither pure-A (all manual) nor pure-B (no exceptions possible) matches this reality on its own.
2. **It reuses two patterns that already exist and are already trusted in this codebase**, rather than inventing a third: the `Ncr.assigned_to` + policy-check pattern, and `Project.pm_id` as an existing "who's responsible for this project" reference.
3. **It degrades safely to today's exact behavior wherever nothing is configured**, which is the same "preserve until an explicit action occurs" principle the Owner already approved for GAP-032's legacy data policy — no new implicit behavior is forced onto existing data.
4. **The added implementation complexity (a two-tier resolution rule) is a one-time, well-scoped engineering cost**, not an ongoing business complexity — from the business's perspective, the rule is simply "specific assignment wins; otherwise the project's manager is the default; otherwise, nothing changes."

The team recommends Option C but presents all three so the Owner can weigh setup-burden vs. flexibility directly, since that trade-off is a business judgment, not a technical one.

---

## 6. Business rules proposed under the recommended model (Option C)

### 6.1 Resolution order

1. Explicit per-document approver, if set.
2. Otherwise, the document's project's designated manager (`pm_id`), if set.
3. Otherwise, no specific designated approver — today's tenant/role-wide `document.approve` permission continues to govern, unchanged.

### 6.2 Who may set or change the per-document approver

**Owner decision needed.** Two reasonable choices, not resolved here:
- (a) The same set of people who can edit a document generally (today's `DocumentPolicy::update()` — `super_admin`/`admin`/`pm`/`designer`), or
- (b) A narrower set, e.g. only the project manager or an admin, to prevent an ordinary editor from routing a document to whichever approver they prefer.

### 6.3 Does being the designated approver alone grant decision rights, or is `document.approve` still required in addition?

**Owner decision needed — this is the single most consequential rule in this gate.** The existing `Ncr` precedent (§1.3) makes assignment **sufficient on its own** — an assigned user can resolve the record even without holding the broader role. Two options:
- (a) **Follow the `Ncr` precedent exactly:** an assigned approver may decide the document even if they do not otherwise hold `document.approve` (e.g. a `designer` role user, explicitly named on one document, could approve that one document). This maximizes flexibility (any tenant user could in principle be named) but widens who can approve beyond today's role-based set, on a per-document basis, whenever an assignment is made.
- (b) **Require both:** the assigned user must *also* independently hold `document.approve` (or a to-be-defined narrower permission) for the assignment to have any effect. This never widens today's set of possible approvers — assignment only narrows/designates *which* already-permitted person is expected to act, it never grants new capability. Safer by default, but assignment to someone outside the currently-permitted set (e.g. a specialist engineer without `document.approve`) would silently do nothing until that person is also given the permission — a likely source of confusion if not made an intentional business rule.

### 6.4 Reassignment

An authorized user (per §6.2) may change the designated approver on a document at any time before a decision is recorded, including after Submit. Reassignment does not itself create an approval-workflow event (GAP-032's `DocumentApprovalEvent` remains scoped to actual submit/decide/reopen/reactivate actions) — it is a document-level change, not an approval-dimension transition.

### 6.5 Behavior across reopen/resubmit

**Owner decision needed.** When a document is reopened (GAP-032 §6.6/§6.11: Approval resets to `not-submitted`), does the prior per-document assignment:
- (a) **Persist** — the same person remains the designated approver for the next cycle unless explicitly changed, or
- (b) **Clear** — the document reverts to "no specific assignment" (falls back to the project default, or to role-wide permission) and must be explicitly re-assigned before the next submission if a specific approver is wanted again.

### 6.6 Single approver only

This gate designs a single designated approver per document, matching every existing per-record assignment pattern in this codebase (`Ncr`, `Rfi`, `ChangeRequest`, `DesignItem`, `SupportTicket` all use a single `assigned_to`-shaped field). Multiple/sequential/parallel approvers are explicitly out of scope and not designed here.

### 6.7 Legacy data policy

No bulk migration. Every existing document has no per-document approver by construction (the column does not exist yet); after implementation, every existing document row will have this field `NULL`, which resolves under §6.1 exactly as intended — falling through to the project default or to today's unchanged role-wide behavior. No document's current approvability changes as a side effect of this gate's implementation.

---

## 7. Explicit exclusions

This Gate 2 design does not authorize, and does not contain:

1. Any database column, migration, controller, service, model, route, policy, or test change.
2. Any Today Workspace query object, view-model field, UI, or notification.
3. GAP-030 (the analogous RFI gap) — parallel, not folded into this decision.
4. Multiple/sequential/parallel approvers (§6.6).
5. **§7.5 (deferred to implementation planning, not a Gate 2 business question):** whether the project-level default reuses `projects.pm_id` directly, or a new dedicated `projects.default_document_approver_id` field is added instead, to avoid overloading a column that already carries broader "who manages this project" meaning elsewhere in the codebase.

Implementation planning may begin only after explicit Owner Gate 2 approval, and only for whichever option and business-rule answers (§6.2, §6.3, §6.5) the Owner selects.

---

## 8. Owner decision

**Selected option:** Option C — hybrid (project-level default + optional per-document override)

**Owner approval recorded at:** 2026-08-12T21:35:50+07:00

**Owner response, verbatim:** "Hướng C, đồng ý."

**Still OPEN — not answered by the above, no answer inferred, required before Gate 3 implementation planning can be scoped:**
- §6.2 — who may set/change the per-document approver
- §6.3 — does assignment alone grant decision rights, or is `document.approve` still required in addition
- §6.5 — does an assignment persist or clear across reopen/resubmit

Gate 2 is recorded as APPROVED for the Option C business model selection only. A separate binding clarification on §6.2/§6.3/§6.5 is required before implementation planning for the parts of this design that depend on them, matching the precedent already established for GAP-032 (Gate 2 approved, followed by separate binding Owner rulings resolving specific open blockers before implementation dispatch).
