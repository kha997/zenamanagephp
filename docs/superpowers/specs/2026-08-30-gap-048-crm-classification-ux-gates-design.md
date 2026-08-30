---
work_id: GAP-048
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-048/02-design.md
---

# GAP-048 — CRM Classification UX & Gates: Design (Gate 2)

**Status:** Gate 2, Round 3 re-presentation, awaiting Owner review.
**This document is docs-only.** It authorizes no migration, model,
controller, service, route, or UI change by itself. Gate 2 approval (if
granted) authorizes a bounded implementation to *begin*, in a separate
future implementation session/PR, strictly within this document's
boundary — no implementation occurs in the session that writes this
document.

**Round 1 correction notice:** Owner reviewed the initial submission (PR
#294, head `deaa7a81`) and returned **CHANGES REQUESTED** — a targeted
design correction, not a rejection; the overall design direction was
accepted. Seven corrections were directed and are applied throughout this
re-presentation: (1) the in-flight/grace policy is now Owner-decided (no
grandfather, no time-based grace — §17, was previously left open); (2) a
complete legacy→canonical synchronization contract now covers all three
active `service_category` writers, not just Lead conversion (§4, new);
(3) classification mutation is now an atomic desired-set reconciliation
with an explicit lifecycle invariant and delete/tenant safety design (§5,
replaces the old simple "promote-in-place" description); (4) the
multi-select UI write contract is now precise (§3); (5) the two legacy
consumers' multi-line/INFERRED/CONFIRMED behavior is now fully specified,
including the corrected "complete CONFIRMED set, not one arbitrary line"
rule for `DesignItemPageController` (§14); (6) external Quote
synchronization vs. local-use semantics are now explicitly distinguished
(§13); (7) the "gates initially inert" rollout mode is removed — gates are
active at deployment (§21). A shared `CONFIRMED`-predicate requirement is
also added (§10, new).

**Round 2 correction notice:** Owner reviewed the Round-1-corrected
submission (head `10985db3`) and returned a **second, FINAL, targeted
CHANGES REQUESTED** — concurrency/atomic-consistency only; all seven
Round-1 corrections were explicitly accepted and are **not** reopened
below. Owner identified a genuine check-then-act race: each individual
operation in the Round-1 design checked the `CONFIRMED` predicate
correctly in isolation, but nothing serialized two concurrent operations
against the same Opportunity row, so a classification-reconciliation
request and a gated-transition request racing each other could both pass
their own check and jointly commit an illegal state (e.g.
`scope_defined` + zero `CONFIRMED`). This is corrected by a new §19,
"Concurrency & Atomic Consistency" (row-level serialization, canonical
lock ordering, mandatory re-check-under-lock, legacy-writer atomicity,
deadlock/retry semantics), and three new real-MySQL concurrency
acceptance tests added to §18. Sections §19 onward are renumbered by +1
from the Round-1 version to make room for the new §19 (old §19→§20, old
§20→§21, old §21→§22, old §22→§23, old §23→§24, old §24→§25) — content
of those sections is otherwise unchanged from Round 1. The full verbatim
Owner directives and permanent Round-1 + Round-2 history are recorded in
`docs/owner-decisions/GAP-048/02-design.md`.

**Preconditions verified before drafting this design** (Design Dependency
Preflight, re-run per Owner instruction against the new canonical main):

- Canonical main: `e71b5508d29f12abb461e34c61ad2fe42b23db17` — this is
  exactly `87bb7d36128f878d8b6291705fed2c4262b11819` (the Gate-1 audit's
  original baseline) plus the squash-merge of GAP-048's own approved Gate
  1 (PR #293). `git diff --stat 87bb7d36..e71b5508` confirms the only
  change is the two GAP-048 Gate-1 documentation files — **zero CRM,
  Opportunity, Quote, Project, or Service-Line source file changed**
  between the Gate-1 audit and this Gate-2 design. The Gate-1 evidence
  (file:line citations throughout) therefore remains current and does not
  need re-verification against a moved target. Re-confirmed unchanged at
  Round 2 (still `e71b5508`, verified again before this correction round).
- Canonical SSOT (`docs/superpowers/specs/2026-08-15-zena-one-page-management-canonical-semantics.md`)
  re-read: §2 (Service-Line taxonomy), §3 (classification maturity through
  the funnel), §10 (legacy migration principles) — unchanged, still
  governing.
- Released GAP-046 Gate 2 design (`docs/owner-decisions/GAP-046/02-design.md`)
  and Gate 3 release record (`docs/owner-decisions/GAP-046/03-release.md`)
  re-read: the persistence foundation (`opportunity_service_lines`/
  `project_service_lines`, `EnforcesServiceLineIntegrity`, the backfill
  command) is binding and must be reused, not reimplemented. **Correction
  #3 below (§5) explicitly corrects an over-claim in the Round-1 design
  about what `EnforcesServiceLineIntegrity` actually protects** (it hooks
  Eloquent `saving` — create/update only, never `delete`).
- GAP-048's own approved Gate 1 (`docs/owner-decisions/GAP-048/01-request.md`,
  both Round 1 and Round 2 history) and its audit
  (`docs/audits/2026-08-29-gap-048-crm-classification-gates-audit.md`)
  re-read in full — this design answers exactly the problem boundary that
  document's approval authorized (§2 below), respecting its explicit
  out-of-scope list.
- Current governance schema (`docs/owner-governance/packet-schema.yml`,
  `docs/owner-governance/OWNER_DECISION_RULES.md`,
  `docs/owner-governance/GOVERNED_DOCUMENT_FRONTMATTER.md`) re-read live,
  not from memory, to build this document's frontmatter and the
  accompanying Gate-2 packet.
- CRM/Quote/portal/external-integration source surfaces identified in Gate
  1 (`OpportunityController`, `LeadController`, `CrmPageController`,
  `PortalQuoteController`, `QuoteLifecycleService`, `OpportunityStageTransitionService`,
  `EnforcesServiceLineIntegrity`, `ServiceLine`/`ServiceLineProvenance`)
  are the exact surfaces this design proposes to extend — no new surface
  discovered since Gate 1 that would require re-scoping.

**No conflicting or moved dependency was found.** This design proceeds
from the Gate-1-approved boundary without reconciliation.

---

## 1. Approved Gate-1 problem recap (not re-litigated here)

Today: (a) two application-code call sites silently default a new
Opportunity's legacy `service_category` scalar to `'architecture'`
(`OpportunityController.php:217`, `LeadController.php:304`); (b) the
canonical multi-valued `opportunity_service_lines` foundation (GAP-046) has
zero write paths from any UI/controller and zero read paths from any UI;
(c) no pipeline stage transition, no point across the full Quote lifecycle
(native DRAFT/revision/send/accept/reject, client-portal accept/reject, or
the zena-boq-core external accepted-Quote path feeding `createContract()`),
and no WON→Project conversion currently checks canonical, confirmed
classification; (d) there is no supported path for any user to produce a
`CONFIRMED` row at all. Full evidence: the Gate-1 audit, §§3-15.

## 2. Gate-2 problem boundary (verbatim from Owner Gate-1 Round 2 approval)

Owner authorized design work on: (A) truthful multi-valued classification
UX; (B) explicit confirmation semantics producing trustworthy `CONFIRMED`;
(C) correct `UNKNOWN`/`NEEDS_REVIEW` handling as non-membership,
subject-level states; (D) removal of the silent Architecture behavior
(both application fallbacks and the DB default), with Gate 2 deciding the
safe migration/compatibility strategy; (E) pipeline gates from
`scope_defined` and appropriate downstream stages; (F) a WON→Project
classification gate (no propagation); (G) a formal-Quote gate explicitly
placed against the real lifecycle, not assumed at `storeQuote()`,
accounting for the native-OR-external-accepted `createContract()` path;
(H) narrow compatibility treatment for `BusinessKpiService::serviceCategoryPerformance()`
and `DesignItemPageController`. Full boundary and the explicit
out-of-scope list: `docs/owner-decisions/GAP-048/01-request.md`, Round 2
history.

**Scope-boundary verification (Owner directive §3, Gate-1 Round 2):** this
design was checked against every out-of-scope item (Opportunity→Project
propagation, Project classification UX/backfill, Quote Scope Snapshot
persistence, Contract multi-Service-Line, Portfolio, Project Health,
Commercial/Finance/Resource Control, OPPM, Control Tower, Treasury,
legacy-taxonomy retirement, GAP-041/042/045). **None of them was found to
be genuinely required** for this design to be coherent — the Quote-gate
design (§13) closes the external-Quote bypass identified in Gate 1
entirely from GAP-048's own side (gating `createContract()` in this
codebase), without requiring any change to zena-boq-core itself
(clarified further per Round 1 Correction #6, §13). No scope expansion is
proposed or silently assumed. Re-verified at Round 2: none of the Round-1
corrections required touching any out-of-scope item either.

## 3. Canonical classification write model and UX

**Alternatives considered** (unchanged from Round 1):

- **A. Replace the legacy scalar `<select>` outright** with a canonical
  multi-select of the 3 values, writing directly to
  `opportunity_service_lines`. Rejected — forces the §14 compatibility
  question immediately.
- **B. Add a canonical multi-select alongside the untouched legacy
  `<select>`,** with an explicit, separate "Confirm classification"
  action. Selected.
- **C. Auto-infer only**, no manual multi-select. Rejected — cannot
  express a Service Line the legacy taxonomy cannot represent cleanly.

**Decision: B, with C's inference folded in as an assist.** At
Lead-conversion time, the legacy `<select>` is retained (compatibility
bridge, §14), and the shared legacy→canonical mapper (§4) seeds `INFERRED`
rows synchronously.

**UI write contract — corrected and made precise (Round 1 Correction #4):**

- The canonical multi-select control itself is an **unpersisted form
  draft**. Checking or unchecking a box, by itself, writes nothing —
  neither an `INFERRED` row nor a `CONFIRMED` row.
- Clicking **"Confirm classification"** submits the complete desired
  canonical set (0..N of `DESIGN`/`CONSTRUCTION`/`INSPECTION`) as one
  atomic request.
- Every Service Line present in the submitted set becomes (or remains)
  `CONFIRMED` on the Opportunity.
- Every mapper-owned `INFERRED` row **not** present in the submitted set
  is reconciled/removed according to the atomic mutation rules in §5 (a
  `CONFIRMED` row is never silently dropped merely because a later
  legacy-scalar edit no longer maps to it — see §4 rule 4).
- **No UI action creates a manual `INFERRED` row.** `INFERRED` remains
  exclusively system-derived from the legacy mapper (§4) — the Round-1
  design's speculative reference to a "possible future manual-add-without-
  confirm path" is removed; that capability is not part of GAP-048.
- The exact atomic reconciliation transaction (what happens to the DB
  rows and audit trail when "Confirm classification" is submitted) is
  specified in §5, not here — this section defines only the UI-facing
  contract.

## 4. Legacy→canonical synchronization contract for all active writer paths (NEW — Round 1 Correction #2)

The Round-1 design only described synchronous inference at Lead
conversion. Owner correctly identified this as incomplete: the Gate-1
audit's own writer inventory names **three** live `service_category`
write paths, and Gate 2 must define one coherent contract covering all
three, not one.

**Single mapping source (binding):** the exact legacy→canonical mapping
GAP-046's backfill command already encodes must be extracted into **one**
shared mapping source (illustrative name: `App\Support\LegacyServiceCategoryMapper`)
— `architecture`/`interior`/`landscape`/`structure`/`mep` → `DESIGN`/
`INFERRED`; `construction` → `CONSTRUCTION`/`INFERRED`;
`inspection`/`consulting`/`combined_package`/null/unrecognized → no
membership row. The existing CLI backfill command and every runtime
call site below must consume this **same** source — the mapping table is
never duplicated.

**A. Direct Opportunity create — `Api\OpportunityController::store()`:**
after §9's nullable migration ships, an omitted/unmapped
`service_category` persists as `NULL` and produces zero canonical rows;
an unambiguous legacy value runs through the shared mapper and produces
exactly the same `INFERRED` row the backfill command would have produced
for that value. This must behave identically to Lead conversion (rule B)
for the same input value — one behavior, two entry points, one shared
mapper.

**B. Lead→Opportunity conversion — `Api\LeadController::convert()`:**
identical rule to A, run synchronously in-request (as already described
in §3).

**C. Opportunity `service_category` UPDATE —
`Api\OpportunityController::update()` (the writer the Round-1 design
omitted entirely):** this is where canonical/legacy drift could be
introduced if handled naively, so the following ownership rule is
**binding**:

1. A membership row created by the mapper (mapper-owned, currently
   `INFERRED`) **may** be reconciled to match the newly-supplied legacy
   scalar on update — e.g. changing `service_category` from
   `architecture` to `construction` reconciles the mapper-owned `DESIGN`/
   `INFERRED` row to `CONSTRUCTION`/`INFERRED`.
2. **A `CONFIRMED` row is NEVER overwritten, demoted, or deleted by the
   legacy mapper, under any legacy-scalar edit.** A human's explicit
   confirmation is never silently reinterpreted by an unrelated scalar
   field changing.
3. An ambiguous/null legacy value on update produces no new mapper-owned
   inferred membership (same rule as create).
4. Changing the legacy scalar is never allowed to reinterpret or remove
   an existing `CONFIRMED` human decision — this is the same invariant
   §5's atomic mutation design enforces from the confirmation side; this
   rule states it from the legacy-update side so the two can never
   contradict each other.
5. The implementation may distinguish "mapper-owned" rows from
   human-confirmed rows via the existing `provenance` column value
   (`INFERRED` rows are always mapper-owned in this design; a row can only
   become `CONFIRMED` through the explicit confirmation action in §5,
   never through the legacy mapper) — no new column is required for this
   distinction, but the exact mechanism is an implementation-time choice
   bound by the semantic ownership rule above.

**Backfill command unchanged:** the existing CLI
`service-lines:backfill-opportunities` remains idempotent and
`INFERRED`-only — this design does not turn it into a `CONFIRMED`
mechanism, and does not add any new command.

## 5. Confirmation / reclassification — atomic mutation, lifecycle invariant, and delete/tenant safety (corrected — Round 1 Correction #3)

**Round-1 error, corrected:** the initial design described confirm/remove
as ordinary `EnforcesServiceLineIntegrity`-protected writes and implied a
naked child-row DELETE endpoint would be safe because that trait
validates it. **This is wrong and is corrected here.**
`EnforcesServiceLineIntegrity` (GAP-046) hooks Eloquent's `saving` event —
it protects `create`/`update` only. **A DELETE does not pass through
`saving`**, so a delete endpoint's correctness cannot depend on that
trait. This design no longer proposes any naked child-row DELETE.

**Corrected design — one atomic desired-set reconciliation operation:**

```
user selects the desired canonical set (unpersisted UI draft, §3)
  -> explicit "Confirm classification" action
  -> ONE database transaction that:
       (a) reconciles the Opportunity's canonical membership set to
           exactly the submitted set, subject to the invariant below,
       (b) writes the corresponding EventRecord(s) audit trail,
     both inside the same transaction (transactionally coherent — an
     audit record is never written for a reconciliation that didn't
     commit, and vice versa).
```

**Binding invariant, enforced by this operation itself (not by
`EnforcesServiceLineIntegrity`, which cannot see deletes):** if the
Opportunity's current `pipeline_stage` is one of `scope_defined,
proposal_draft, proposal_sent, negotiation, contracting, won`, then the
**resulting** canonical membership set after the transaction commits must
still contain ≥1 `CONFIRMED` Service Line. Consequently:

- A **pre-scope** Opportunity (stage before `scope_defined`) may
  legitimately reconcile back down to zero confirmed membership — nothing
  downstream depends on it yet.
- An **active/gated** Opportunity (stage in the list above) may change
  its classification (e.g. atomically replace `DESIGN` with
  `CONSTRUCTION` in one transaction, never passing through an
  externally-observable zero-confirmed intermediate state), but it
  **cannot** reconcile to a set with zero `CONFIRMED` members — the
  operation must reject (and roll back, writing no partial state) a
  submitted set that would leave an active/gated Opportunity with no
  `CONFIRMED` line.
- The same invariant is additionally checked against any **already-formal
  commercial state that depends on trustworthy classification**, not only
  `pipeline_stage`: at minimum, an Opportunity with a native Quote at
  `SENT` or `ACCEPTED`, an `external_quote_snapshot.status === 'ACCEPTED'`,
  or an already-`won` stage must retain ≥1 `CONFIRMED` line after any
  reconciliation — this is the same predicate as §10's shared
  `CONFIRMED` check, evaluated defensively at mutation time as well as at
  each individual gate.

**Delete/tenant safety, stated explicitly (since the underlying `saving`
trait cannot cover this):** the reconciliation operation's authorization
and tenant-scoping must be enforced **explicitly** in the operation
itself — parent (Opportunity) and tenant match verified the same way
every other Opportunity-mutating controller method in this codebase
already does (Gate-1 audit §13), **not** delegated to
`EnforcesServiceLineIntegrity` for the removal half of the operation. The
`create`/`update` half of the same transaction still benefits from that
trait's existing enforcement as a second, redundant layer — but the
transaction's own explicit tenant/parent check is what makes the
operation safe overall, including its delete component.

## 6. Auditability

Reuse `App\Models\EventRecord` — already the established pattern for
every other Opportunity/Quote lifecycle write in this codebase. Event
keys (illustrative, exact naming is an implementation-time decision):
`crm.opportunity.service_line_confirmed` (a line entered or remained in
the submitted set), `crm.opportunity.service_line_removed` (a
mapper-owned `INFERRED` line reconciled out, or a `CONFIRMED` line
removed by a pre-scope Opportunity's legitimate zero-return per §5).
**The Round-1 design's speculative `service_line_added` event key for a
"future manual-add-without-confirm path" is removed** — that path does
not exist in this design (§3). Payload: `service_line`,
`prior_provenance`, `new_provenance` (or `null` for removal),
`actor_user_id`. Written inside the same transaction as the membership
reconciliation (§5) — never as a separate, potentially-inconsistent
follow-up write.

## 7. INFERRED handling

`INFERRED` rows are produced only by the shared legacy mapper (§4) — at
Opportunity create, at Lead conversion, at legacy-scalar update
reconciliation, or via the existing idempotent CLI backfill command for
already-existing Opportunities (§21) — **never** by a raw UI toggle (§3).
An `INFERRED` row alone **never** satisfies any gate (§10-§13) or counts
toward the §5 lifecycle invariant — only `CONFIRMED` does.

## 8. UNKNOWN / NEEDS_REVIEW representation

**These are never persisted as rows** — GAP-046's binding rule ("no
membership row without a known canonical line") is not reopened. They are
**read-time-derived states**, computed from (a) the count/provenance of
canonical rows and (b) the legacy `service_category` scalar, via a small
read-side value object/query (illustrative name:
`OpportunityClassificationState`):

- **Classified:** ≥1 canonical row exists (report the set, each with its
  provenance).
- **NEEDS_REVIEW (subject-level, not a row):** zero canonical rows exist,
  but `service_category` ∈ `{inspection, consulting, combined_package}`.
- **UNKNOWN-by-absence (subject-level, not a row):** zero canonical rows
  exist and `service_category` is `NULL`/unrecognized.

## 9. Architecture-default removal + DB default — safe migration strategy

**Decision (unchanged from Round 1): nullable migration.**
`service_category` becomes nullable, its DB `DEFAULT 'architecture'` is
dropped, and both application-level fallbacks
(`OpportunityController.php:217`, `LeadController.php:304`) are removed,
so an omitted classification persists as `NULL` — verified on SQLite
**and** real MySQL in the future implementation session, not decided
further here. **Historical data is not touched or reclassified** —
existing `'architecture'` rows stay exactly `'architecture'`; only the
mechanism producing new false values is removed (SSOT §10.1/§10.2).
Rejected alternatives (sentinel value; gate-only/no-migration) — see
Round 1 rationale, unchanged.

## 10. Shared CONFIRMED predicate (NEW — Round 1 Correction #10)

The same business invariant — **"the Opportunity has at least one
`CONFIRMED` canonical Service-Line membership"** — is now independently
evaluated at five surfaces in this design: the pipeline transition gate
(§11), `sendQuote()` (§13), `convert()`/`createContract()` (§12/§13), and
the §5 classification-mutation lifecycle invariant. **Binding design
rule:** all five must be backed by **one shared domain predicate/helper**
(illustrative shape: a method such as
`Opportunity::hasConfirmedServiceLine(): bool` or an equivalent
single-source service), never five independently-written `count(...)`
queries that could silently diverge (e.g. one checking `>= 1` while
another accidentally checks `> 1`, or one forgetting to filter by
`provenance`). Exact class/method name and location are implementation
details; the single-source requirement is the binding decision.

## 11. Pipeline classification gate

**Placement decision (unchanged):** a single, centralized check inside
`OpportunityStageTransitionService::transition()` — no scattered gate.
**Exact stage boundary:** fires when `$to` is `scope_defined` or any of
`proposal_draft, proposal_sent, negotiation, contracting, won`. **Explicit
exemption:** transitions into `lost`, `no_bid`, `nurture` are **never**
gated. Requirement: the shared predicate (§10) must be true — an
`INFERRED`-only Opportunity is blocked. **No grandfather/time-based
exception of any kind** (§17) — this applies identically to an
Opportunity already sitting in a gated stage before this feature ships;
only its **next** transition into another gated stage is checked, per
§17's resolved policy. Failure UX: §16.

## 12. WON→Project conversion gate

**Placement decision (unchanged):** the pipeline gate (§11) already
blocks entry into `won`, but a second, redundant, defense-in-depth check
using the same shared predicate (§10) is added directly inside
`OpportunityController::convert()` and inside `createContract()`'s inline
Project-creation branch. Rationale unchanged from Round 1: negligible
cost; guards against any future capability reopening the gap between
reaching `won` and calling `convert()`; the Owner boundary names this gate
as its own explicit requirement. **No grandfather exception** — an
Opportunity already `won` before this feature ships is still blocked from
`convert()`/`createContract()` until it gains ≥1 `CONFIRMED` line (§17).
**No propagation is added.**

## 13. Formal-Quote gate — placement across the full lifecycle

**Decision (unchanged from Round 1): gate at native `sendQuote()`
(`DRAFT`→`SENT`) + an independent `createContract()` gate**, both using
the shared predicate (§10). `sendQuote()` transitively secures the entire
native+portal accept/reject lifecycle (state machine requires `SENT`
before `ACCEPTED`/`REJECTED`); `createContract()` is the one point where
native-accepted and external-accepted paths converge, so it must be
gated independently. **Explicit exemptions (unchanged):** `rejectQuote()`/
`PortalQuoteController::reject()` and `reviseQuote()`-at-creation are
never gated.

**External Quote synchronization vs. local use — explicit semantic
distinction (NEW, Round 1 Correction #6):** Owner directed this design to
state precisely *why* `syncExternalQuote()`/`linkExternalBoqProject()`
themselves are not gated, so the design does not imply
`createContract()` somehow prevents zena-boq-core from issuing or
accepting a Quote. The distinction:

- `linkExternalBoqProject()`/`syncExternalQuote()` are **evidence
  ingestion / synchronization of an external fact.** This application does
  not control, and has no authority over, whether zena-boq-core has
  already issued or accepted a Quote — that decision happens entirely
  inside the external system.
- Therefore **GAP-048 does not reject or hide synchronization** solely
  because local Opportunity classification is incomplete. An
  `external_quote_snapshot.status === 'ACCEPTED'` may be synced and
  displayed at any time, regardless of classification state — gating
  synchronization itself would misrepresent an already-true external fact.
- **The first consequential *local* action that relies on that accepted
  external Quote — `createContract()` — is what must fail closed**
  without ≥1 `CONFIRMED` line. The gate is on this codebase's own
  decision to create a local `Contract`/`Project` from that external
  fact, not on knowing or displaying the fact itself.
- Net effect, stated plainly: an externally-accepted Quote may be visible/
  synced while classification is incomplete, but it **may not be used to
  create the local Contract/Project** until the classification
  requirement is satisfied. Nothing about `linkExternalBoqProject()`/
  `syncExternalQuote()` changes; zena-boq-core itself requires no change
  (§2).

## 14. Legacy `service_category` compatibility strategy

**`BusinessKpiService::serviceCategoryPerformance()` — stated as an
explicit temporary compatibility decision, not an omission (Round 1
Correction #5):** continues reading `service_category` unchanged in
GAP-048; `NULL` (possible once §9 ships) becomes an explicit
"Unclassified" bucket rather than being silently dropped or grouped under
an empty key; **it does not become multi-Service-Line aware in GAP-048**
— canonical `CONFIRMED`/`INFERRED` rows do not change KPI bucketing in
this Work ID. Deferred to the SSOT's own later "Shared Project Health Read
Model" slice (§14 item 2 of the SSOT roadmap).

**`DesignItemPageController`/`AiAssistService` — corrected, precise rule
(Round 1 Correction #5; the previous "prefer CONFIRMED DESIGN-family
classification" wording is removed as ambiguous, non-canonical
terminology, and wrong for collapsing a set to one line):**

1. Read **all** `CONFIRMED` canonical Service Lines for the Opportunity
   behind the converted Project (not one arbitrarily selected value).
2. Order deterministically in the canonical stable order: `DESIGN,
   CONSTRUCTION, INSPECTION`.
3. If **one or more** `CONFIRMED` lines exist: pass the **complete
   stable set** as AI context (e.g. a Design-Build Opportunity confirmed
   as both `DESIGN` and `CONSTRUCTION` passes `"DESIGN, CONSTRUCTION"`,
   never just one of the two). `AiAssistService` currently accepts a
   nullable string context — the implementation may serialize the set
   deterministically into that string, or make a narrow signature
   improvement; exact code shape is an implementation-time decision, not
   fixed here.
4. If **zero** `CONFIRMED` rows exist: fall back to the nullable legacy
   `service_category` scalar (unchanged from Round 1's "degrade to
   legacy" principle).
5. If both a `CONFIRMED` set and a legacy scalar exist: the **`CONFIRMED`
   set wins** — the legacy scalar is not consulted at all in that case.
6. **`INFERRED`-only does NOT outrank the legacy-scalar fallback** for
   this narrow compatibility bridge — an `INFERRED`-only Opportunity
   behaves the same as a zero-`CONFIRMED` Opportunity for this consumer
   (falls back to the legacy scalar), consistent with `INFERRED` never
   satisfying anything else in this design either.

## 15. Tenant/RBAC design

**No new permission is proposed.** The classification-selection UI and
the §5 atomic confirm/reconcile operation reuse the existing `crm.manage`
permission — checked against the Owner Decision Rules' four-question
anti-escalation test: unchanged conclusion from Round 1, no new
permission tier warranted. **§5's explicit tenant/parent check** (not a
reliance on `EnforcesServiceLineIntegrity` for the delete half of the
operation) is what makes this safe end-to-end, including for the
membership-removal component the trait cannot see.

## 16. Failure/error UX when a gate blocks an action

Unchanged from Round 1: reuse the exact response conventions already
established in the audited controllers — API paths return
`$this->validationError([...])` with a distinct key (illustrative:
`'service_line' => [...]`, mirroring `createContract()`'s existing
`'quote'` key convention); web paths return `back()->with('error', ...)`.
No new response shape.

## 17. Backward compatibility for current records — in-flight policy (RESOLVED, Round 1 Correction #1 — no longer an open question)

**Owner decision, binding, not deferred to Gate 3:**

1. Existing Opportunities are **not** retroactively invalidated merely
   because, at deployment time, they already sit in `scope_defined,
   proposal_draft, proposal_sent, negotiation, contracting, won`. They
   remain readable and otherwise visible — nothing about their current,
   already-reached state is disturbed.
2. However, their **next** gated business action requires ≥1 `CONFIRMED`
   canonical Service Line, with **no exception**. Concretely:
   `negotiation`→`contracting`: blocked until `CONFIRMED`.
   `contracting`→`won`: blocked until `CONFIRMED`.
   An existing `won` Opportunity calling `convert()`: blocked until
   `CONFIRMED`. An existing `won` Opportunity calling `createContract()`:
   blocked until `CONFIRMED`. Sending a native Quote (`sendQuote()`):
   blocked until `CONFIRMED`.
3. These exits remain allowed regardless of classification, with no
   change: `lost`, `no_bid`, `nurture`, Quote rejection.
4. The existing GAP-046 backfill command may be re-run to seed `INFERRED`
   rows as a **user aid only** (§4/§21) — `INFERRED` **never** constitutes
   grace and **never** satisfies any gate or the §5 invariant.

**No grandfather bypass. No time-based grace period. No automatic
`CONFIRMED` promotion. No gate bypass based on record age or pre-existing
stage.** This is now the binding design for §11/§12/§13/§5 — none of them
carry any exception logic beyond the `lost`/`no_bid`/`nurture`/reject
exemptions already stated. This section previously framed the policy as
an open Gate-3/business question; that framing is removed.

## 18. Test strategy (categories only — no tests written in this session; expanded per Round 1 Correction #11 and Round 2 concurrency correction)

**A.** Direct Opportunity `store()`: omitted `service_category` →
persisted `NULL` + zero canonical rows.
**B.** Direct Opportunity `store()`: `construction` →
`CONSTRUCTION`/`INFERRED`.
**C.** Lead conversion: identical mapping outcome to B for the same input.
**D.** Opportunity legacy-category `update()`: mapper-owned `INFERRED`
row reconciled to the newly-supplied scalar.
**E.** Legacy-category `update()` never overwrites/demotes/deletes an
existing `CONFIRMED` row.
**F.** Active-stage (`scope_defined`+) last-`CONFIRMED`-line removal via
the §5 operation is rejected (invariant enforced).
**G.** Atomic confirmed-set replacement (e.g. `DESIGN`→`CONSTRUCTION` in
one transaction) succeeds without an externally-observable zero-confirmed
state.
**H.** Pre-scope Opportunity's confirmed set may legitimately return to
zero via §5.
**I.** Multiple `CONFIRMED` Service Lines survive as a set (not collapsed
to one) through reconciliation.
**J.** `DesignItemPageController`/`AiAssistService` context preserves the
**complete** confirmed set, stable order — covering zero classification,
one confirmed line, multiple confirmed lines, `INFERRED`-only (falls back
to legacy), and confirmed-set-vs-conflicting-legacy-scalar (confirmed set
wins).
**K.** Native Quote `sendQuote()` gate blocks without `CONFIRMED`.
**L.** External accepted snapshot may sync freely (`syncExternalQuote()`
unblocked), but `createContract()` is blocked until `CONFIRMED` — proving
§13's ingestion-vs-local-use distinction is real, not just documentation.
**M.** An already-`won` legacy Opportunity (existing before this feature
ships) must gain `CONFIRMED` classification before `convert()`/
`createContract()` succeeds — proves §17's no-grandfather policy.
**N.** No grandfather/time-based bypass exists anywhere — an explicit
negative test asserting record age/pre-existing stage has zero effect on
gate outcome.
**O.** `lost`/`no_bid`/`nurture` transitions and Quote rejection remain
available with zero classification, unconditionally.
**Security (unchanged from Round 1):** cross-tenant confirm/reconcile
attempts rejected, reusing GAP-046's existing cross-tenant test pattern,
now also covering the §5 operation's explicit tenant check (not merely
`EnforcesServiceLineIntegrity`, since that trait cannot cover the delete
half).

**Concurrency acceptance evidence (NEW — Round 2 Correction, real MySQL
required, SQLite-only evidence is not sufficient for row-lock semantics;
tag under this repository's existing `@group mysql-parity` mechanism
where suitable, per §19):**

**CONCURRENCY-1.** Initial state: a pre-scope Opportunity with `{DESIGN /
CONFIRMED}`. Race two concurrent requests against the same Opportunity:
(a) classification reconciliation toward `{}` (zero confirmed), and (b) a
gated pipeline transition into `scope_defined`. Required outcome, proven
against real MySQL: it must be **impossible** for the final committed
state to be `scope_defined` + zero `CONFIRMED` — one of the two
operations must observe the serialized post-lock state and reject. This
is the exact race Owner's Round 2 directive specified as currently
possible without serialization (§19).

**CONCURRENCY-2.** Initial state: a `DRAFT` native Quote on an
Opportunity with `{DESIGN / CONFIRMED}`. Race (a) classification
reconciliation toward `{}` against (b) `sendQuote()`. Required outcome,
proven against real MySQL: it must be impossible for the final committed
state to be Quote `SENT` + zero `CONFIRMED`.

**CONCURRENCY-3.** A legacy `service_category` `update()` whose
mapper-owned `INFERRED` reconciliation step fails (e.g. a simulated
constraint violation): required proof that the scalar update itself rolls
back too — no partially-applied state (legacy value changed but canonical
reconciliation silently absent), proving §19's legacy-writer atomicity
rule.

**Must not use sequential/non-discriminating calls to fake this
evidence** — a test that merely calls operation A then operation B in
sequence cannot prove serialization; the test harness must genuinely
interleave two concurrent connections/transactions against the same
Opportunity row (e.g. one transaction holds the row lock while a second
attempts the competing operation, proving the second either blocks until
the first commits/rolls back or is correctly rejected against the
post-lock state) for CONCURRENCY-1/2 to be discriminating.

## 19. Concurrency & Atomic Consistency (NEW — Round 2 Correction)

**The defect Owner identified:** the Round-1 design ensured every
individual operation *checks* the shared `CONFIRMED` predicate (§10)
correctly, but nothing in that design *serialized* two operations racing
against the same Opportunity. Neither `OpportunityStageTransitionService::transition()`
nor `CrmPageController::sendQuote()` locks the parent Opportunity row
today (Gate-1 audit, full-method reads). Concrete forbidden race, stated
by Owner: an Opportunity at `survey_or_inputs_received` with `{DESIGN /
CONFIRMED}` receives concurrent Request A (transition to `scope_defined`)
and Request B (reconcile confirmed set to `{}`); without serialization, A
can read-and-pass the gate, B can read the old pre-scope stage and permit
the empty set, and both commit — yielding an illegal final state
(`scope_defined` with zero `CONFIRMED`). Equivalent races exist between
classification reconciliation and `sendQuote()`, `convert()`, and
`createContract()`, and any other operation that both relies on the
`CONFIRMED` predicate and can alter a lifecycle state that changes
whether zero classification is legal.

**Binding design: one canonical Opportunity-row serialization rule.**
Every business operation that can establish, consume, or remove the
`CONFIRMED` invariant on an Opportunity must serialize against the
**same** parent Opportunity row. This applies, at minimum, to: (A)
classification desired-set reconciliation (§5); (B) the gated pipeline
transition (§11); (C) native `sendQuote()` (§13); (D)
`OpportunityController::convert()` (§12); (E) `createContract()` (§12/§13);
(F) the legacy `service_category` update + mapper reconciliation (§4).
One consistent transaction/locking discipline for all six:

1. Begin a DB transaction.
2. Acquire an **exclusive row lock** on the authoritative Opportunity row
   for the subject Opportunity (conceptually `SELECT ... FOR UPDATE` /
   Laravel's `lockForUpdate()` — exact code structure is an
   implementation-time decision, not fixed here).
3. **Re-read all state relevant to the operation AFTER the lock is
   held** — current `pipeline_stage`, current `CONFIRMED` membership,
   relevant native Quote status, external accepted snapshot where
   applicable. **Never validate against a model instance loaded before
   lock acquisition** — this is a binding prohibition on check-then-act
   (restated explicitly, with more detail, later in this section).
4. Evaluate the shared `CONFIRMED` predicate (§10) / lifecycle invariant
   (§5) under that lock, against the freshly-read state.
5. Perform the lifecycle or classification mutation.
6. Write the corresponding `EventRecord`(s) in the same transaction,
   where applicable (consistent with §6/§5's existing transactional-
   coherence requirement).
7. Commit.

**Canonical lock order (binding, to avoid turning this fix into a
deadlock source):** the authoritative **Opportunity row is always locked
first**; any related child rows this operation also needs (Service-Line
membership rows, Quote rows, other child rows) are locked **after** the
Opportunity row, never before. No GAP-048 path may lock a child Quote or
Service-Line row first and only later attempt the Opportunity lock while
a different path does the reverse — that inverted-order combination is
exactly what produces a deadlock under concurrent load. Exact query
implementation remains an implementation-time decision; the ordering rule
itself is binding.

**Legacy writer atomicity (§4, tightened here):** for
`OpportunityController::store()`, `LeadController::convert()`, and
`OpportunityController::update()`, the write of the legacy scalar and the
synchronization of its mapper-owned `INFERRED` canonical membership must
happen as **one** atomic business operation, under the same Opportunity
lock discipline above — never a partially-applied state. On `update()`:
the scalar mutation and the mapper-owned `INFERRED` reconciliation occur
in one transaction; a failure in the canonical-reconciliation step rolls
back the scalar change too. On creation/Lead-conversion: Opportunity
creation and its mapper-derived `INFERRED` membership creation are one
atomic operation — a canonical-row creation failure must not leave behind
a persisted Opportunity whose legacy scalar implies a classification that
silently never got its corresponding canonical row. `CONFIRMED` rows
remain protected exactly as already designed (§4 rule 2) — this
correction is about atomicity/failure-handling, not about reopening which
rows the legacy mapper may touch.

**Recheck-under-lock, not check-then-act (binding, in addition to §10's
shared predicate, not a replacement for it):** the shared predicate (§10)
states *what* to check; this section states *when*. The decisive
predicate check for every invariant-sensitive operation must occur
**inside the transaction, after the Opportunity lock is acquired, against
freshly-re-read state** — never `read Opportunity → check predicate →
later begin transaction → mutate`, which is exactly the check-then-act
pattern that produces the CONCURRENCY-1/CONCURRENCY-2 races (§18). A
predicate evaluated before lock acquisition is advisory/UX-only (e.g. to
decide whether to even show a "Confirm" button state) and must never be
treated as the operation's authoritative gate decision.

**Deadlock/retry behavior:** this design does not introduce a silent
retry that can bypass the invariant. Normal framework/database deadlock
retry behavior may be used if already standard in this codebase, but
**after any retry the operation must reacquire the Opportunity lock,
re-read state, and re-evaluate the predicate/invariant from scratch** —
the gate decision is never cached across a retry attempt.

## 20. MySQL/SQLite parity

Unchanged from Round 1: the nullable-`service_category` migration (§9)
verified on SQLite and real MySQL, following this repository's
established parity pattern. No new table introduced — this design uses
GAP-046's existing tables and the existing `EventRecord` table, both
already portable.

## 21. Rollout/backfill boundaries (corrected — Round 1 Correction #9, "gates initially inert" mode removed)

**Round-1 error, corrected:** the initial design recommended shipping the
gates "initially inert" and enabling them later via an unspecified
activation mechanism. Owner correctly identified this as implying an
undesigned feature-flag/activation mechanism that would itself constitute
a hidden bypass state. **This is removed.**

**Owner decision, binding:** GAP-048 does not introduce any hidden
inert-gate mode. **When GAP-048 is actually deployed/released, its gates
(§11/§12/§13) are active** — there is no separate "enable" step. **No
historical Project backfill** (GAP-046's binding zero-backfill decision,
not reopened). The pre-existing Opportunity-side backfill command may be
run as a rollout aid to populate `INFERRED` rows ahead of deployment
(§17 item 4) — it is **not required** to fabricate `CONFIRMED`, it **does
not bypass** any gate, and users must explicitly confirm (§5) before their
next gated action regardless of whether the backfill ran. **If production
execution of the backfill cannot be proven at Gate 3, that must be
reported truthfully — it must never be claimed to have occurred without
proof.**

## 22. What this design explicitly does NOT solve

- Exact UI copy/Vietnamese wording for the new classification panel and
  gate error messages.
- Exact migration SQL, route names, controller/method names, event-key
  strings, and the exact shape of the §10 shared predicate helper — all
  illustrative in this document, decided at implementation time within
  this design's boundary.
- Full multi-Service-Line-aware rewrite of `BusinessKpiService`/any
  reporting surface (§14) — deferred to the SSOT's own later "Shared
  Project Health Read Model" slice.
- Any change to zena-boq-core itself — not needed by this design (§13);
  if a future implementation session discovers it genuinely is needed,
  that must be flagged to Owner as a new dependency, not silently done.
- Every item in the Owner-directed out-of-scope list (§2): Opportunity→
  Project propagation, Project classification UX/backfill, Quote Scope
  Snapshot persistence, Contract multi-Service-Line, Portfolio, Project
  Health, Commercial/Finance/Resource Control, OPPM, Control Tower,
  Treasury, legacy-taxonomy retirement, GAP-041/042/045.
- **(Resolved in Round 1, no longer listed as undecided): the
  in-flight/grace policy (§17) and the rollout-gate-activation mode
  (§21)** — both are Owner-decided, not open questions.
- Exact locking/transaction code structure (query builder calls, retry
  middleware configuration) implementing §19's serialization rule — the
  serialization/lock-order/recheck-under-lock requirements themselves are
  binding; their precise code shape is an implementation-time decision.

## 23. Loại trừ rõ ràng (restated)

Không thiết kế/triển khai: Opportunity→Project Service-Line propagation;
Project classification UX; lịch sử backfill phía Project; Quote Scope
Snapshot persistence; Contract multi-Service-Line; Portfolio Membership;
Project OPPM; Operations Control Tower; Finance/Treasury; retirement cuối
cùng của taxonomy cũ; GAP-041/GAP-042/GAP-045. Không sửa đổi
zena-boq-core. Không có chế độ "cổng tạm tắt" (inert-gate) khi triển
khai — cổng luôn hoạt động ngay khi release. Không có ân hạn/grace theo
thời gian hay theo giai đoạn có sẵn. Không có cơ chế retry ngầm có thể bỏ
qua kiểm tra bất biến — mọi lần thử lại đều phải khoá lại, đọc lại, kiểm
tra lại từ đầu (§19). Gate 2 này không viết code, không viết migration,
không viết test, không viết implementation plan.

## 24. Decision Needed

Owner chọn một trong: Approve (cho phép mở phiên triển khai riêng, đúng
ranh giới đã sửa ở trên) / Yêu cầu sửa đổi thêm (changes_requested) / Từ
chối (declined).

## 25. What the owner is NOT being asked to decide

Owner không được yêu cầu duyệt tên route/controller/method/migration/event
key cụ thể, cấu trúc lock/transaction chính xác, hay câu chữ UI chính xác
— đó là quyết định ở phiên triển khai trong ranh giới đã duyệt. Owner đã
quyết định (không còn là câu hỏi mở): chính sách không-ân-hạn cho deal dở
dang (§17); gỡ bỏ chế độ cổng-tạm-tắt khi rollout (§21); đặt cổng Quote
tại `sendQuote()` + `createContract()` (§13); chiến lược migration
nullable cho `service_category` (§9); ranh giới ingestion-vs-local-use cho
Quote ngoài zena-boq-core (§13); quy tắc tập CONFIRMED đầy đủ (không phải
1 dòng tuỳ ý) cho gợi ý AI hạng mục thiết kế (§14); và quy tắc khoá hàng
Opportunity + thứ tự khoá chuẩn + bắt buộc kiểm tra lại dưới khoá (§19,
Round 2).
