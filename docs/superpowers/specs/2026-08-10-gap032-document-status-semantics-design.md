# GAP-032 — Document Status Semantics: Gate 2 Business Design

**Status:** Gate 2 design — awaiting Owner approval. Gate 1 is approved. Gate 3 is not started; implementation, merge and release are not authorized.

**Objective:** Define a single, coherent business meaning for `Document.status` so that every actor — creator, editor, submitter, approver, viewer, API client, and downstream system — can look at a document's status and know exactly what it means, what transitions are allowed, and how legacy data is treated.

---

## 1. Facts (repository evidence, not production)

These are the engineering facts established in the Gate 1 evidence file (`docs/audits/2026-08-10-gap-032-status-semantics-evidence.md`). No production database was inspected.

### 1.1 Current persistence

- `documents.status` is a plain text string, defaulting to `active`. There is no database-level enum or constraint.
- The same status value is written to two places: the `documents.status` column and the `documents.metadata.status` JSON key.

### 1.2 Current write paths

- **API create** defaults new documents to `active`.
- **Web create** defaults new documents to `draft`.
- **Generic update** (API and Web) accepts any non-reserved string and writes it directly to `status`, except when the document already holds a workflow value — in that case the status field is silently preserved.
- **Workflow submit** moves a document from `draft` to `submitted`. Only `draft` is accepted; `active` and `review` cannot enter the workflow.
- **Workflow decide** moves a document from `submitted` to `approved` or `rejected`.

### 1.3 Current values observed in repository artifacts

| Value | Observed source |
|---|---|
| `active` | API create default, database default |
| `draft` | Web create default, workflow entry point, factory, tests |
| `review` | Factory, tests, accepted by generic create/update |
| `published` | Factory only |
| `submitted` | Workflow service, tests |
| `approved` | Workflow service, tests, factory (collision) |
| `rejected` | Workflow service, tests |
| `pending` | UI filter label only — no write path stores it |

### 1.4 Current inconsistencies

- Two different initial statuses depending on surface (`active` via API, `draft` via Web).
- The UI filter label "Chờ duyệt" (`pending`) does not match any stored value; the stored "waiting" value is `submitted`.
- A document at `active` or `review` has no path into the approval workflow.
- The generic API accepts arbitrary strings, so any value — including `published` — can be persisted by a client even if no canonical code path sets it.

### 1.5 Production facts

- **Production value distribution is UNKNOWN.** No production database query was performed in Gate 1 or Gate 2.
- Production records may contain any of the observed values, or values not present in the repository.

### 1.6 GAP-033 boundary

- GAP-033 (per-document approver assignment) is a separate work item.
- GAP-032 must define when a document is "awaiting an approver" in a way that GAP-033 can rely on.
- GAP-032 must not design approver tables, assignment rules, notifications, or Today Workspace queries.

---

## 2. Business requirements

These are the outcomes the business needs, independent of how they are implemented.

### 2.1 Single meaning

Every person looking at a document's status must be able to answer: "What stage is this document in, and what can I do with it?" There must not be two different interpretations of the same stored value.

### 2.2 Clear transitions

If a document is at one status, the next allowed status must be obvious. There must not be hidden paths or silent preservation.

### 2.3 Consistent creation

A newly created document must enter the same business state regardless of whether it was created through the API or the Web interface, unless there is a documented business reason for a difference.

### 2.4 Approval eligibility

A user must be able to move a document into the approval workflow through a defined business step. Documents must not become permanently stuck in a state that has no forward path.

### 2.5 Backward compatibility

Existing API clients, integrations, and stored data must not break unexpectedly. Where a value is being phased out, the business must decide whether to accept it temporarily, normalize it on write, or migrate it.

### 2.6 Future extensibility

The chosen model must leave room for GAP-033 (per-document approver) and any future workflow additions without creating new collisions or ambiguities.

### 2.7 Legacy data safety

Because production data is not fully known, the model must include a clear policy for handling existing records that may hold any status value.

---

## 3. Alternative business models

### Option A — One canonical lifecycle

**Concept:** A Document has exactly one lifecycle. All status values — whether they came from the generic API, the Web form, the factory, or a workflow service — must belong to one coherent state machine.

**How it works conceptually:**
- There is a single set of business states.
- Every possible status value maps to one state in that set.
- Transitions are defined uniformly: from any state, only certain next states are allowed.
- Legacy values like `active` and `review` are either absorbed as legitimate states or reclassified as aliases for canonical states.

**Business simplicity:**
- One status means one thing. No actor needs to ask "Is this a workflow status or a business status?"
- Transition rules are uniform. A document at `active` knows exactly how to reach `submitted` (or whatever the path is).

**Transition clarity:**
- Every document has a forward path.
- The initial state is the same regardless of surface.

**Impact on legacy values:**
- `active`, `review`, and `published` must be assigned a place in the lifecycle, deprecated with a migration rule, or explicitly marked as invalid going forward.
- Existing API clients that set these values will see a behavior change unless compatibility is built in.

**API compatibility risk:**
- If legacy values are no longer accepted by the generic create/update path, clients that currently set `active` or `review` will receive validation errors.
- If legacy values are accepted but silently mapped, clients may not notice, but the internal model becomes more complex.

**Implications for reopening/publishing/approval:**
- Because there is only one lifecycle, `approved` and `rejected` are states within the same machine. Reopening is a transition like any other, defined by business rule.
- `published` (if it has business meaning) becomes a state in the same machine, not a separate concept.

---

### Option B — Separate business dimensions

**Concept:** Document lifecycle/status and approval workflow state are different business concepts.

**How it works conceptually:**
- One dimension answers: "Where is this document in its business lifecycle?" (e.g., `draft`, `in-review`, `published`, `archived`).
- The other dimension answers: "Has this document been submitted for approval, and what was the outcome?" (e.g., `not-submitted`, `awaiting-approval`, `approved`, `rejected`).
- A document can have both dimensions simultaneously: it can be `published` and also `approved` for a specific purpose.

**Business simplicity:**
- Each concept has a clear, narrow meaning.
- Actors reason about the dimension that matters to them: editors care about lifecycle, approvers care about workflow state.

**Transition clarity:**
- Each dimension has its own transitions. Changes to one dimension do not accidentally affect the other.
- A document at `published` in the lifecycle dimension can still be `awaiting-approval` in the workflow dimension if the business requires it.

**Backward compatibility:**
- The existing single field does not need to be torn apart immediately. The business model can define two dimensions even if the current implementation stores them in one place; the mapping is an implementation question.
- Existing API clients continue to see a single `status` field, but the business definition behind it changes.

**Interaction with GAP-033 later:**
- GAP-033 needs a stable definition of "awaiting an approver." If approval state is a separate dimension, GAP-033 can reference that dimension directly without interfering with lifecycle state.
- This model naturally supports multiple parallel approval flows (e.g., quality review, compliance review) without collision.

**Important:** This option does not require two database columns at the Owner level. Whether one field, two fields, or a structured object is used is an implementation decision for later gates.

---

### Option C — Preserve the existing combined lifecycle with explicit compatibility rules

**Concept:** Keep one status concept, but formally define which values are canonical, which are legacy, what mappings apply, and what the entry/re-entry rules are.

**How it works conceptually:**
- There is still one lifecycle.
- However, the lifecycle explicitly includes compatibility rules: some values are accepted temporarily for backward compatibility but are mapped to canonical states internally.
- Legacy values like `active` and `review` are documented as "compatibility inputs" rather than first-class states.
- The state machine has explicit "compatibility transitions" that normalize legacy inputs on entry.

**Lowest conceptual change:**
- This option changes the least from the current contract. Existing API clients that set `active` or `review` continue to work, but the business meaning is clarified.
- The initial state inconsistency (`active` vs `draft`) can be resolved by a compatibility rule that normalizes both to the same canonical state.

**Potential compatibility benefit:**
- No immediate breaking change for clients.
- Legacy data does not need immediate migration; it normalizes when touched.

**Risk of retaining overloaded semantics:**
- The single field still carries two concepts in practice, even if documented.
- Future developers may add new values without understanding the compatibility rules, recreating the overload.
- The business definition is more complex because it must explain both the canonical machine and the compatibility layer.

**Long-term maintainability/business ambiguity:**
- Over time, the compatibility layer accumulates edge cases.
- The business cannot cleanly answer "What does `active` mean?" without explaining "It means `draft` for compatibility, but only if the document was created before date X via the API."
- This model makes GAP-033 harder because the approval state is still entangled with legacy values.

---

## 4. Trade-off analysis

| Dimension | Option A — One canonical lifecycle | Option B — Separate dimensions | Option C — Preserve with compatibility rules |
|---|---|---|---|
| **Business clarity** | High: one meaning per value | Highest: each concept has its own meaning | Medium: canonical meaning exists, but compatibility layer adds noise |
| **Transition clarity** | High: uniform state machine | High: each dimension has its own transitions | Medium: compatibility transitions obscure the primary machine |
| **Backward compatibility** | Medium: legacy values need explicit handling | High: single field can hide two dimensions behind one interface | High: legacy values accepted without immediate change |
| **API contract stability** | Medium: may break clients that set legacy values | High: can preserve current API surface | High: current API surface preserved longest |
| **Future GAP-033 support** | Medium: approval is one state among many | Highest: approval dimension is explicitly available | Low: approval entangled with legacy values |
| **Legacy-data risk** | Medium: requires migration or normalization policy | Medium: can normalize per-dimension | Low: normalize on touch, no bulk change |
| **Long-term maintainability** | High: one machine to understand and extend | High: clean separation of concerns | Low: compatibility layer grows over time |
| **Today Workspace eligibility** | Medium: needs stable "awaiting approval" definition | High: approval dimension is stable and explicit | Low: entanglement makes stable definition harder |

---

## 5. Recommended business model

**Recommendation: Option B — Separate business dimensions.**

### Why Option B is best for Zena's document workflow

**1. Business clarity matches actual business meaning.**

In Zena's workflow, editors and project managers care about where a document is in its lifecycle — is it a draft being edited, a final version ready for client review, or a published document on record? Approvers care about a different question: has this document been submitted for my review, and what did I decide? These are not the same question, and forcing them into one answer creates confusion at exactly the moments when clarity matters most: during review, during escalation, and when auditing decisions.

**2. GAP-033 depends on a stable approval definition.**

GAP-033 must designate a per-document approver and know when a document is "awaiting an approver." If approval state is baked into the same lifecycle as document state, every future change to the lifecycle (adding `archived`, adding `under-editing`, etc.) risks colliding with approval semantics. A separate dimension gives GAP-033 a stable, unambiguous home.

**3. Backward compatibility is achievable without sacrificing clarity.**

Option B does not require two database columns immediately. The business model defines two dimensions; the implementation can map them to one field, two fields, or a structured object. Existing API clients continue to see `data.status` as a string. The difference is that the business now has a clear definition of what that string represents, and implementation can evolve the storage without changing the business contract.

**4. Legacy data is safer under a two-dimensional model.**

If a production document currently holds `active`, the business can say: "Lifecycle dimension = `active` (to be normalized), approval dimension = `not-submitted`." There is no need to force `active` into a workflow state it was never designed for. Legacy data stays valid in its own dimension until the business decides otherwise.

**5. It solves the initial-state inconsistency.**

The current mismatch — API create → `active`, Web create → `draft` — is a symptom of one field trying to serve two surfaces with different assumptions. Under Option B, both surfaces can create a document at the same canonical lifecycle state (e.g., `draft`), while the approval dimension starts at `not-submitted`. The inconsistency disappears because the two dimensions are independent.

**6. It prevents future collisions.**

If Zena later adds a compliance review flow, a legal review flow, or a client confirmation flow, each can be a new dimension or a new value within the approval dimension. They do not need to fight over `draft`, `submitted`, or `approved` because those values belong to a specific dimension with a specific meaning.

**7. The cost of Option B is implementation complexity, not business complexity.**

Option B is slightly more complex to implement because the storage layer must handle two concepts. But at the business level, it is simpler: each status label means one thing, each transition is obvious, and each actor knows which dimension to look at. The engineering cost is paid once; the business clarity pays dividends every time someone reads a document status.

---

## 6. Business rules proposed under Option B

### 6.1 Dimension 1 — Document lifecycle

**Question answered:** What stage is the document itself in?

**Canonical states:**

| Business state | Meaning | Who uses it |
|---|---|---|
| `draft` | Document is being created or edited; not ready for any external process. | Creators, editors |
| `in-review` | Document is complete and open for informal/editorial/document review, NOT formal approval. | Editors, reviewers |
| `published` | Document is released to its intended audience (client, project team, public). | Publishers, viewers |
| `archived` | Document is retained but no longer active. | Archivists, viewers |

**Initial state:** Every new document enters at `draft`, regardless of whether it was created via API or Web.

**Transitions (conceptual):**
- `draft` ↔ `in-review` (editor marks ready for review; returns to draft for changes)
- `draft` / `in-review` → `published` (publisher releases)
- `published` → `archived` (retire document)
- `archived` → `draft` (reactivate archived document, if business allows)

**Note:** The exact transition rules — which roles can trigger which transitions, whether notes are required, etc. — are implementation details. The business rule is that every state has a defined set of forward and backward paths.

**Not in this dimension:** `submitted`, `submitted-for-approval`, `awaiting-approval`, `approved`, `rejected`. These belong to the Approval dimension only.

### 6.2 Dimension 2 — Approval workflow state

**Question answered:** What is the formal approval state?

**Canonical states:**

| Business state | Meaning |
|---|---|
| `not-submitted` | Document has not entered any approval workflow. |
| `awaiting-approval` | Document is submitted and waiting for an approver to decide. |
| `approved` | An approver has accepted the document. |
| `rejected` | An approver has rejected the document. |

**Entry rule:** Any document in a lifecycle state that is eligible for approval (`draft`, `in-review`) can be submitted by an authorized user. Documents in `published`, `archived`, or already in an approval state require an explicit re-entry or reopening step before they can be submitted again.

**Transitions (conceptual):**
- `not-submitted` → `awaiting-approval` (submit for approval)
- `awaiting-approval` → `approved` (approver accepts)
- `awaiting-approval` → `rejected` (approver rejects)

**Reopen/resubmit rule:** After `approved` or `rejected`, the document may return to `draft` for revision. The approval dimension resets to `not-submitted` upon reopening. There is no limit on reopen/resubmit cycles unless a future business rule specifies one.

**Independence:** Changing the approval dimension does not change the lifecycle dimension, and vice versa. A document can be `in-review` (lifecycle) and `awaiting-approval` (approval) simultaneously if the business requires a parallel track.

### 6.3 Canonical "Chờ duyệt" meaning

The canonical business concept for "Chờ duyệt" / "Awaiting approval" is **`awaiting-approval`** in the approval workflow dimension. The legacy UI label `pending` is a stale label that should map to `awaiting-approval` in displays and filters. No write path currently stores `pending`.

Under the compatibility contract, the legacy stored value `submitted` may remain in legacy `status` output during the compatibility window even though the canonical Approval concept is `awaiting-approval`.

### 6.4 Legacy value classification

| Value | Classification |
|---|---|
| `active` | Legacy lifecycle compatibility value; canonical mapping candidate: `draft` |
| `review` | Legacy lifecycle compatibility value; canonical mapping candidate: `in-review` |
| `published` | Recognized lifecycle concept; may also exist as legacy persisted input/value |
| `draft` | Valid current business concept; canonical entry state in the lifecycle dimension |
| `submitted` | Legacy approval compatibility value; canonical approval concept: `awaiting-approval` |
| `approved` | Valid current business concept; exists only in the Approval dimension |
| `rejected` | Valid current business concept; exists only in the Approval dimension |
| `pending` | Obsolete UI/filter alias; not a canonical persisted business state |

**Business treatment:**
- `active`, `review`: normalize to the nearest canonical lifecycle state on entry to workflow.
- `published`: recognized as a legitimate lifecycle state.
- `submitted`: remains available as a legacy compatibility output; canonical Approval concept is `awaiting-approval`.
- `pending`: not accepted as input; maps to `awaiting-approval` in display logic only.

### 6.5 Entering approval from a legacy state

A document currently holding a legacy lifecycle value (`active`, `review`) may enter the approval workflow through an explicit preparation step: the user (or system) first normalizes the document to a canonical lifecycle state (`draft` or `in-review`), then submits it for approval. There is no automatic normalization that bypasses user intent — the transition from legacy to canonical must be a deliberate action.

The submit action is an Approval transition. It may have a lifecycle eligibility precondition (Lifecycle must be `draft` or `in-review`) but it does not create a new lifecycle state.

### 6.6 Reopening after decision

`approved` and `rejected` in the approval dimension are **not permanently terminal**. The business permits reopening: an authorized user may explicitly reopen the document for revision, returning it to `draft`. Each reopen/resubmit cycle creates a new decision record in the audit trail.

This action intentionally coordinates both dimensions:
- Lifecycle = `draft`
- Approval = `not-submitted`

This is allowed because it is an explicit cross-dimension business action. It is not described as the approval dimension automatically mutating lifecycle.

### 6.7 Backward compatibility classification

| Contract element | Classification |
|---|---|
| API status inputs (generic create/update) | May deprecate: legacy values (`active`, `review`, `submitted`) accepted temporarily with normalization; `pending` rejected immediately. |
| API status outputs (`data.status`) | Must preserve: continues to return a string; business-meaning mapping is internal. |
| `metadata.status` | Must preserve: continues to mirror the primary status representation. |
| Existing filters (status-based list filters) | Must preserve temporarily: filters continue to work; legacy values may return results until data is normalized. |
| Existing clients/tests | Must preserve: no immediate breaking change to accepted input set; tests may need adjustment if they assert specific legacy behavior. |
| Legacy records | Preserve until touched: existing `active`/`review`/`submitted` records remain valid until modified by a user or workflow action. |

**Legacy `status` compatibility projection:** The legacy `status` string is a compatibility projection, not the canonical business source of truth after GAP-032. Existing clients may temporarily continue to receive the legacy projection. See §6.9 for the full compatibility contract.

### 6.8 Legacy data policy

**Principle: Preserve legacy status until an explicit lifecycle/status/workflow action occurs.**

- Existing records are not bulk-migrated in this gate.
- Normalization may occur only through an explicit business action whose intent includes lifecycle/workflow transition.
- Examples of unrelated changes that must NOT implicitly normalize:
  - rename
  - description edit
  - tag change
  - file metadata edit
  - other non-status update
- Documents that are never touched remain in their legacy state indefinitely; they are not invalidated retroactively.
- If future business analysis shows that a specific legacy value is truly obsolete (not just legacy), a separate migration decision can be made.

**Production data distribution: UNKNOWN.** No production query was performed. If implementation planning reveals that a large fraction of production records hold legacy values, a pre-implementation evidence step must measure that distribution before any bulk normalization is designed.

### 6.9 Legacy `status` compatibility contract

The legacy `status` string becomes a compatibility projection. It is NOT the canonical business source of truth after GAP-032.

The canonical business model is:
- Lifecycle dimension
- Approval dimension

Existing clients may temporarily continue to receive the legacy projection.

**Compatibility principle:**
- When Approval is `awaiting-approval`, `approved`, or `rejected`, legacy `status` may project the existing workflow-compatible value.
- When Approval is `not-submitted`, legacy `status` may project the lifecycle-compatible value.

`submitted` may remain in legacy `status` output during the compatibility window even though the canonical Approval concept is `awaiting-approval`.

`pending` remains a stale UI/filter alias only; it is not a canonical persisted business state.

### 6.10 Effect of approval decisions on lifecycle

Approval decisions and document publication/lifecycle changes are separate actions.

**Invariant:** Approval does not automatically mean Published.

An approval decision changes only the Approval dimension. Publication is a separate lifecycle action.

Likewise, `rejected` does not itself have to be a lifecycle state. If rejection requires revision, the user may explicitly reopen the document for revision, and that business action may coordinate:
- Lifecycle → `draft`
- Approval → `not-submitted`

Do not call `approved` or `rejected` "terminal in both dimensions". They are Approval outcomes only.

### 6.11 Canonical API/business representation

Future canonical consumers must be able to observe Lifecycle and Approval separately. The existing single `status` field is legacy compatibility, not the long-term canonical two-dimensional representation.

Implementation planning may later decide:
- columns
- JSON
- DTO/resource fields
- versioned API
- compatibility adapter

Gate 2 does not decide those mechanics.

---

## 7. Business invariants

The following invariants must hold under the chosen model:

1. Lifecycle and Approval are separate dimensions.
2. `approved`/`rejected` exist only in Approval.
3. `submitted`/`awaiting-approval` exist only in Approval.
4. `published` exists only in Lifecycle.
5. Formal approval does not automatically equal publication.
6. Unrelated document edits do not normalize legacy status.
7. Legacy `status` is explicitly a compatibility projection.
8. Canonical business representation contains both dimensions.
9. `submitted` may remain a legacy compatibility output while `awaiting-approval` is the canonical Approval concept.
10. GAP-032 does not decide when GAP-033 assigns the approver.

---

## 8. GAP-033 boundary

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

## 9. Owner decision

This section records the business model selected by the Owner. It is not self-approved.

**Selected option:** [Owner to choose: A / B / C]

**Owner approval recorded at:** [Owner approval timestamp]

**Conditions or clarifications attached:** [None, or Owner-specified amendments]

---

## 10. Implementation exclusions

This Gate 2 design does not authorize, and does not contain:

- Any database column, enum, table, or migration.
- Any controller, service, model, route, or test change.
- Any API contract change or OpenAPI update.
- Any data migration script or bulk normalization.
- Any implementation plan.
- GAP-033 artifacts (approver assignment, Today Workspace integration, notifications).
- Merge or release.

Implementation planning may begin only after explicit Owner Gate 2 approval.
