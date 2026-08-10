---
work_id: GAP-032
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/superpowers/specs/2026-08-10-gap032-document-status-semantics-design.md
  plan: null
  branch: docs/GAP-032-document-status-semantics
  pr: https://github.com/kha997/zenamanagephp/pull/256
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-10T20:33:31+07:00"
  updated_at: "2026-08-10T20:33:31+07:00"
generated_by: agent
---

# GAP-032 — Document Status Semantics: Gate 2 Owner Packet

**Status:** Gate 2 design prepared. Awaiting Owner approval. No implementation, migration, schema change, route, controller, service, test, or merge is authorized.

---

## 1. Owner Summary

Today, `Document.status` is one field that tries to answer two unrelated questions at once: "What is this document's business state?" and "What is this document's approval workflow state?" Because both answers share one column, the system has no single, agreed definition of what a document's status means. A document can be stuck at `active` with no path into approval, and once it enters approval, no one can move it out without a separate service call. This makes it impossible for creators, editors, approvers, API clients, or future systems to reason about documents consistently.

Gate 2 proposes a business model that separates these two concerns into distinct dimensions, giving each a clear meaning, clear transitions, and a stable contract that GAP-033 can rely on.

---

## 2. What is wrong today

- **One field, two meanings:** `active` and `review` look like business states, while `submitted`, `approved`, and `rejected` look like workflow states. No one can tell which is which by looking at a value.
- **No path from creation to approval:** API-created documents start at `active`; `submit()` only accepts `draft`. An `active` document can never enter approval without a transition that does not exist.
- **Inconsistent creation:** Web uploads start at `draft`; API uploads start at `active`. The same action produces two different business states depending on the surface.
- **Silent preservation:** Once a document reaches `submitted`, `approved`, or `rejected`, generic updates silently ignore the status field. A client cannot tell whether the document is locked or whether its request was applied.
- **UI/storage mismatch:** The UI filter label "Chờ duyệt" (`pending`) does not match any stored value. The stored "waiting" value is `submitted`.
- **Future collision risk:** Any new approval flow or status concept must fight over the same single column.

---

## 3. Business outcomes required

1. Every actor can look at a document status and know exactly what it means and what can be done with it.
2. A document created via API or Web enters the same business state unless there is an Owner-approved reason for a difference.
3. Every document has a defined path into and out of the approval workflow.
4. Approval decisions are explicit actions, not side effects of generic status editing.
5. Existing API clients, integrations, and stored data do not break unexpectedly.
6. GAP-033 can rely on a stable definition of "awaiting an approver."
7. Legacy production data is handled safely without retroactive invalidation.

---

## 4. Options A / B / C

### Option A — One canonical lifecycle

All status values belong to one coherent state machine. Legacy values are absorbed or deprecated within a single lifecycle.

- **Business simplicity:** One status means one thing. Transitions are uniform.
- **Transition clarity:** Every document has a forward path. Initial state is consistent.
- **Impact on legacy values:** `active`, `review`, `published` must be assigned a place or deprecated.
- **API compatibility risk:** Clients setting legacy values may see validation errors or silent mapping.
- **Reopening/publishing/approval:** All are states in the same machine; reopening is a defined transition.

### Option B — Separate business dimensions

Document lifecycle/status and approval workflow state are different concepts. A document can have both simultaneously.

- **Both can exist simultaneously:** A document can be `published` (lifecycle) and `approved` (approval) at the same time.
- **Better reflects business meaning:** Editors care about lifecycle; approvers care about workflow state.
- **Backward compatibility:** The single API field can hide two dimensions; implementation evolves storage without changing the business contract.
- **GAP-033 interaction:** Approval dimension is stable and explicit; GAP-033 references it directly.

### Option C — Preserve existing combined lifecycle with explicit compatibility rules

Keep one status concept but formally define mappings, entry/re-entry rules, and legacy compatibility.

- **Lowest conceptual change:** Existing clients continue to work; legacy data does not need immediate migration.
- **Compatibility benefit:** No immediate breaking change.
- **Risk of overloaded semantics:** Single field still carries two concepts; future developers may recreate the overload.
- **Long-term maintainability:** Compatibility layer accumulates edge cases; business definition becomes harder to state cleanly.

---

## 5. Trade-off table

| Dimension | Option A | Option B | Option C |
|---|---|---|---|
| Business clarity | High | Highest | Medium |
| Transition clarity | High | High | Medium |
| Backward compatibility | Medium | High | High |
| API contract stability | Medium | High | High |
| Future GAP-033 support | Medium | Highest | Low |
| Legacy-data risk | Medium | Medium | Low |
| Long-term maintainability | High | High | Low |
| Today Workspace eligibility | Medium | High | Low |

---

## 6. Engineering/Product recommendation

**Recommendation: Option B — Separate business dimensions.**

At the business level, a document's lifecycle and its approval status answer different questions. Editors ask "Is this document ready?" Approvers ask "Has this been submitted to me?" Future systems ask "Is this document awaiting an approver?" Forcing these into one answer creates confusion exactly when clarity matters most: during review, escalation, and audit.

Option B gives each question its own clean answer. It does not require two database columns immediately — that is an implementation question for later gates. The business model defines two dimensions; implementation maps them to storage. Existing API clients continue to see `data.status` as a string. The business meaning behind that string becomes unambiguous, and implementation can evolve without breaking the contract.

This model also makes GAP-033 straightforward: the approval dimension has a stable state `awaiting-approval` that GAP-033 can reference directly. It prevents future collisions if Zena adds compliance review, legal review, or client confirmation flows. And it resolves the initial-state inconsistency: both API and Web create can normalize to the same canonical lifecycle state (`draft`), while the approval dimension starts at `not-submitted`.

The cost is slightly more complex implementation. The benefit is permanent business clarity.

---

## 7. Exact business rules proposed

### 7.1 Meaning of Document status

**Two independent dimensions:**

- **Dimension 1 — Lifecycle:** `draft` ↔ `in-review` ↔ `published` → `archived`.
  - `in-review` means informal/editorial/document review, NOT formal approval.
- **Dimension 2 — Approval:** `not-submitted` → `awaiting-approval` → `approved` | `rejected`.

A document's overall status is the combination of its position in both dimensions.

**Not in Lifecycle:** `submitted`, `submitted-for-approval`, `awaiting-approval`, `approved`, `rejected`. These belong to Approval only.

**Archived reactivation:** Archived documents may be explicitly reactivated for revision through the `Reactivate for revision` action defined in §7.10. There is no automatic reactivation.

### 7.2 Initial state

Every new document enters at `draft` in the lifecycle dimension and `not-submitted` in the approval dimension, regardless of whether it was created via API or Web.

### 7.3 "Chờ duyệt" canonical meaning

The canonical business concept is **`awaiting-approval`** in the approval dimension. The legacy UI label `pending` is a stale display label that maps to `awaiting-approval`. No write path currently stores `pending`.

Under the compatibility contract, the legacy stored value `submitted` may remain in legacy `status` output during the compatibility window even though the canonical Approval concept is `awaiting-approval`.

**Canonical invariant:** Approval transitions may occur ONLY through explicit workflow actions. Generic lifecycle/status editing cannot enter, approve, reject, or otherwise mutate the Approval dimension.

Therefore generic status input must NOT use these values to perform approval transitions: `submitted`, `awaiting-approval`, `approved`, `rejected`, `pending`.

In particular, `submitted` is a **legacy compatibility OUTPUT**, not a generic status input that may trigger `not-submitted` → `awaiting-approval`. Formal submission remains an explicit Submit action. Likewise `approved` and `rejected` remain explicit decision actions only.

### 7.4 Legacy value classifications

| Value | Classification |
|---|---|
| `active` | Legacy lifecycle compatibility input; canonical mapping: `draft` |
| `review` | Legacy lifecycle compatibility input; canonical mapping: `in-review` |
| `published` | Recognized lifecycle concept; may also exist as legacy persisted input/value |
| `draft` | Valid current business concept; canonical entry state in the lifecycle dimension |
| `submitted` | Legacy approval compatibility output only; NOT a generic status input. Canonical approval concept: `awaiting-approval` |
| `awaiting-approval` | Approval dimension value; NOT a generic lifecycle/status input |
| `approved` | Approval dimension value; NOT a generic lifecycle/status input |
| `rejected` | Approval dimension value; NOT a generic lifecycle/status input |
| `pending` | Obsolete UI/filter alias; not a canonical persisted business state |

**Business treatment:**
- `active`, `review`: These aliases may be supported during compatibility because they only affect Lifecycle. They normalize to the nearest canonical lifecycle state only through an explicit lifecycle/status/workflow action.
- `published`: recognized as a legitimate lifecycle state.
- `submitted`: legacy compatibility output only. It is NOT a generic status input and must never trigger `not-submitted` → `awaiting-approval`. Canonical Approval concept is `awaiting-approval`.
- `awaiting-approval`, `approved`, `rejected`: These belong to the Approval dimension only. They are controlled by explicit Approval actions (Submit, Approve, Reject). Generic status editing must never mutate the Approval dimension.
- `pending`: not accepted as a generic status input. It maps to `awaiting-approval` in display/filter logic only.

`pending` must never be interpreted as an approval transition through generic status editing. Whether legacy clients currently send `pending` is UNKNOWN and must be checked before an intentionally breaking rejection policy is released. The UI/filter alias can map to the canonical concept for reads/filtering without becoming a persisted business state.

### 7.5 Entering approval from a legacy state

A document at `active` or `review` must first be normalized to a canonical lifecycle state (`draft` or `in-review`) through an explicit user or system action. There is no automatic normalization that bypasses user intent, and generic status editing cannot perform this transition. A document currently holding a legacy lifecycle value may enter the approval workflow ONLY through an explicit Submit action.

### 7.6 Reopen/resubmit rule

After `approved` or `rejected`, the document may return to `draft` for revision. The approval dimension resets to `not-submitted`. There is no limit on reopen/resubmit cycles unless a future business rule specifies one.

This action intentionally coordinates both dimensions:
- Lifecycle = `draft`
- Approval = `not-submitted`

This is allowed because it is an explicit cross-dimension business action. It is not described as the approval dimension automatically mutating lifecycle.

### 7.7 Backward compatibility

- **Must preserve:** API status outputs (`data.status`, `metadata.status`), existing filters, existing clients/tests, legacy records.
- **May deprecate:** API status inputs for legacy lifecycle values (`active`, `review`) — accepted temporarily with normalization. `submitted`, `awaiting-approval`, `approved`, `rejected`, and `pending` must NOT be accepted as generic status inputs because they are controlled by Approval actions. Generic status editing can NEVER mutate the Approval dimension.
- **May change:** Internal storage representation; two-dimensional mapping is an implementation decision.
- **Unknown:** Production client behavior and production data distribution.

**Legacy `status` compatibility projection:** The legacy `status` string is a compatibility projection, not the canonical business source of truth after GAP-032. Existing clients may temporarily continue to receive the legacy projection. See §8 for the full compatibility contract.

### 7.8 Legacy data policy

**Principle: Preserve legacy status until an explicit lifecycle/status/workflow action occurs.**

- Existing records are not bulk-migrated.
- Normalization may occur only through an explicit business action whose intent includes lifecycle/workflow transition.
- Examples of unrelated changes that must NOT implicitly normalize:
  - rename
  - description edit
  - tag change
  - file metadata edit
  - other non-status update
- Documents never touched remain in their legacy state; they are not invalidated retroactively.

**Production data distribution: UNKNOWN.** No production query was performed. If implementation planning reveals a large fraction of production records hold legacy values, a pre-implementation evidence step must measure that distribution before any bulk normalization is designed.

### 7.9 Publication eligibility

**Invariant:** Approval does NOT automatically publish a document.

**Owner business rule:** Formal approval is NOT universally required before publication. This preserves the independence of the two dimensions because some documents may not require a formal approval workflow.

However, if a Document HAS entered formal approval, then publication is not allowed while Approval is:
- `awaiting-approval`
- `rejected`

Publication is allowed when Approval is:
- `not-submitted`
- `approved`

Therefore `Lifecycle draft/in-review → published` has this business precondition:
- Approval ∈ {`not-submitted`, `approved`}

This means:
- documents that do not require formal approval may be published;
- documents currently awaiting approval cannot be published prematurely;
- rejected documents must be reopened/revised before publication;
- approval still does not automatically cause publication.

Do not introduce document-type-specific approval policy in GAP-032. That is reserved for a future business rule.

### 7.10 Explicit cross-dimension actions

These are the only actions that may mutate the Approval dimension or coordinate both dimensions. Generic status editing must never perform these transitions.

#### Submit

Precondition:
- Lifecycle ∈ {`draft`, `in-review`}
- Approval = `not-submitted`

Effect:
- Approval → `awaiting-approval`
- Lifecycle unchanged

#### Approve

Precondition:
- Approval = `awaiting-approval`

Effect:
- Approval: `awaiting-approval` → `approved`
- Lifecycle unchanged

#### Reject

Precondition:
- Approval = `awaiting-approval`

Effect:
- Approval: `awaiting-approval` → `rejected`
- Lifecycle unchanged

#### Reopen for revision

Precondition:
- Approval ∈ {`approved`, `rejected`}

Effect:
- Lifecycle → `draft`
- Approval → `not-submitted`

Historical decision audit is preserved.

#### Publish

Preconditions:
- Lifecycle ∈ {`draft`, `in-review`}
- Approval ∈ {`not-submitted`, `approved`}

Effect:
- Lifecycle → `published`
- Approval unchanged

#### Archive

Precondition:
- Lifecycle = `published`

Effect:
- Lifecycle: `published` → `archived`
- Approval unchanged

#### Reactivate for revision

Precondition:
- Lifecycle = `archived`

Effect:
- Lifecycle: `archived` → `draft`
- Approval → `not-submitted`

Historical approval/audit records are preserved.

---

### 7.11 Canonical API/business representation

Future canonical consumers must be able to observe Lifecycle and Approval separately. The existing single `status` field is legacy compatibility, not the long-term canonical two-dimensional representation.

Implementation planning may later decide:
- columns
- JSON
- DTO/resource fields
- versioned API
- compatibility adapter

Gate 2 does not decide those mechanics.

---

## 8. Legacy `status` compatibility projection

The legacy `status` string is a compatibility projection. It is NOT the canonical business source of truth after GAP-032.

The canonical business model is:
- Lifecycle dimension
- Approval dimension

Existing clients may temporarily continue to receive the legacy projection.

**Deterministic projection rule:**

If Approval != `not-submitted`:
    legacy status projects Approval
Else:
    legacy status projects Lifecycle

**Projection table:**

| Canonical state                                     | Legacy `status` projection |
| --------------------------------------------------- | -------------------------- |
| Approval = `awaiting-approval`                      | `submitted`                |
| Approval = `approved`                               | `approved`                 |
| Approval = `rejected`                               | `rejected`                 |
| Approval = `not-submitted`, Lifecycle = `draft`     | `draft`                    |
| Approval = `not-submitted`, Lifecycle = `in-review` | `review`                   |
| Approval = `not-submitted`, Lifecycle = `published` | `published`                |
| Approval = `not-submitted`, Lifecycle = `archived`  | `archived`                 |

`metadata.status` mirrors the same legacy projection during compatibility.

The canonical source of truth remains:
- Lifecycle
- Approval

not the legacy projection.

---

## 9. Legacy-data position

- **Policy:** Preserve legacy status until an explicit lifecycle/status/workflow action occurs.
- **Bulk migration:** Not authorized in this gate. Implementation planning must include a pre-implementation evidence step if production data distribution is needed.
- **Production distribution:** UNKNOWN.

---

## 10. GAP-033 boundary

GAP-032 defines the approval dimension and its states. GAP-033 uses that definition to assign approvers and determine who can act on a document in state `awaiting-approval`. GAP-032 must not design:

- approver assignment tables
- per-document approver fields
- role matrices
- Today Workspace queries or notifications
- escalation rules
- default approver logic

These remain exclusively within GAP-033 scope.

What GAP-033 may now assume after Gate 2 approval:
- There is a stable, unambiguous approval dimension with states `not-submitted`, `awaiting-approval`, `approved`, `rejected`.
- GAP-033 may rely on the fact that Approval = `awaiting-approval` means the document is formally waiting for an approval decision.
- Reopening a rejected or approved document resets the approval dimension to `not-submitted`, making it eligible for a new approval cycle.

GAP-033 itself will decide:
- when the approver is assigned
- whether assignment is required before submit
- whether pre-assignment is allowed
- replacement/reassignment rules

---

## 11. Explicit exclusions

This Gate 2 design does not authorize, and does not contain:

- Any database column, enum, table, or migration.
- Any controller, service, model, route, or test change.
- Any API contract change or OpenAPI update.
- Any data migration script or bulk normalization.
- Any implementation plan.
- GAP-033 artifacts (approver assignment, Today Workspace integration, notifications).
- Merge or release.

Implementation planning may begin only after explicit Owner Gate 2 approval.

---

## 12. Decision Needed

**Owner chooses one:**

1. **APPROVE** — Accept Option B (separate business dimensions) and the business rules in §7. Proceed to implementation planning.
2. **REQUEST CHANGES** — Specify which section needs revision (business rules, compatibility position, legacy-data policy, or option selection).
3. **DEFER** — Return for additional evidence or business consultation.
4. **DECLINE** — Halt GAP-032; no further gates proceed.

This packet does not authorize implementation, merge, or release.
