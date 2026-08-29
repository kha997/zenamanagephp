---
work_id: GAP-048
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-048/02-design.md
---

# GAP-048 — CRM Classification UX & Gates: Design (Gate 2)

**Status:** Gate 2, awaiting Owner review. **This document is docs-only.**
It authorizes no migration, model, controller, service, route, or UI
change by itself. Gate 2 approval (if granted) authorizes a bounded
implementation to *begin*, in a separate future implementation session/PR,
strictly within this document's §11 boundary — no implementation occurs
in the session that writes this document.

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
  need re-verification against a moved target.
- Canonical SSOT (`docs/superpowers/specs/2026-08-15-zena-one-page-management-canonical-semantics.md`)
  re-read: §2 (Service-Line taxonomy), §3 (classification maturity through
  the funnel), §10 (legacy migration principles) — unchanged, still
  governing.
- Released GAP-046 Gate 2 design (`docs/owner-decisions/GAP-046/02-design.md`)
  and Gate 3 release record (`docs/owner-decisions/GAP-046/03-release.md`)
  re-read: the persistence foundation (`opportunity_service_lines`/
  `project_service_lines`, `EnforcesServiceLineIntegrity`, the backfill
  command) is binding and must be reused, not reimplemented.
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

**Scope-boundary verification (Owner directive §3):** this design was
checked against every out-of-scope item (Opportunity→Project propagation,
Project classification UX/backfill, Quote Scope Snapshot persistence,
Contract multi-Service-Line, Portfolio, Project Health, Commercial/Finance/
Resource Control, OPPM, Control Tower, Treasury, legacy-taxonomy
retirement, GAP-041/042/045). **None of them was found to be genuinely
required** for this design to be coherent — the Quote-gate design (§11)
closes the external-Quote bypass identified in Gate 1 entirely from
GAP-048's own side (gating `createContract()` in this codebase), without
requiring any change to zena-boq-core itself. No scope expansion is
proposed or silently assumed.

## 3. Canonical classification write model and UX

**Alternatives considered:**

- **A. Replace the legacy scalar `<select>` outright** with a canonical
  multi-select of the 3 values, writing directly to
  `opportunity_service_lines`. Simplest UX, but removes the
  `service_category` data-entry point that `BusinessKpiService`/
  `DesignItemPageController` still read (§8), forcing their compatibility
  question to be resolved immediately rather than on a bridge.
- **B. Add a canonical multi-select alongside the untouched legacy
  `<select>`,** with an explicit, separate "Confirm classification"
  action distinct from merely checking boxes. Preserves the legacy data
  point for existing consumers during the compatibility bridge (§8) while
  establishing the new truthful, multi-valued, provenance-aware surface.
- **C. Auto-infer only** (reuse the legacy→canonical mapping at
  conversion time) with a lightweight "confirm" toggle, no manual
  multi-select at all. Fastest to ship, but does not give users a way to
  express a Service Line the legacy 9-value taxonomy cannot represent
  cleanly (e.g. a genuinely Design-Build Opportunity), and does not
  satisfy "truthful classification UX" as strongly as B.

**Decision: B, with C's inference folded in as an assist, not a
replacement.** At Lead-conversion time, the legacy `<select>` is retained
unchanged for now (compatibility bridge, §8), and the exact same
legacy→canonical mapping table GAP-046's backfill command already encodes
(`architecture`-family→`DESIGN`/`INFERRED`, `construction`→`CONSTRUCTION`/
`INFERRED`, `inspection`/`consulting`/`combined_package`/null→no row) is
run **synchronously, in-request**, immediately seeding `INFERRED` rows for
the new Opportunity — this is a genuine reuse, not a new mapping: the
mapping logic must be extracted into one shared, single-source class (e.g.
`App\Support\LegacyServiceCategoryMapper`) consumed by both the existing
CLI backfill command and this new in-request call, so the two never drift.
The Opportunity detail page gains a new canonical multi-select panel
showing the current membership (checked = row exists) plus each row's
provenance badge (`INFERRED`/`CONFIRMED`); checking/unchecking without
confirming changes nothing (`INFERRED` state only comes from the legacy
mapping or an explicit future manual add, never from casually toggling a
checkbox); a distinct **"Confirm classification"** action is the only way
to write/promote a `CONFIRMED` row (§4). This satisfies "explicit
confirmation" as a deliberate second act, not an accidental side effect of
selection.

## 4. Confirmation workflow (INFERRED/unset → CONFIRMED)

**Alternatives considered:**

- **Promote-in-place:** update the existing `OpportunityServiceLine` row's
  `provenance` column from `INFERRED` to `CONFIRMED` (or create a fresh
  `CONFIRMED` row if none existed). Reuses `EnforcesServiceLineIntegrity`'s
  already-built update-path enforcement (GAP-046 Gate-3 Correction Round 1
  item 2) directly — no new integrity logic needed.
- **Create-new-and-supersede:** always insert a new `CONFIRMED` row and
  mark the prior `INFERRED` row logically superseded/soft-deleted. Gives a
  denser audit trail on the row itself, but requires a new
  supersession/soft-delete concept the GAP-046 schema does not have (it
  has no `status`/`superseded_by` column on either Service-Line table) —
  would need its own schema change, out of proportion to the problem.

**Decision: promote-in-place**, via an ordinary Eloquent `update()` (for
an existing row) or `create()` (for a value with no prior row), gated by
`crm.manage` (§13) and always paired with an `EventRecord` write (§5). No
schema change to the GAP-046 tables is proposed. Actor: any user
authorized to edit the Opportunity today (§13 — no new permission tier).

## 5. Auditability

Reuse `App\Models\EventRecord` — already the established pattern for
every other Opportunity/Quote lifecycle write in this codebase
(`crm.opportunity.converted`, `quote.sent`, `quote.accepted`, etc., all
cited in the Gate-1 audit §6/§9). New event keys (illustrative, exact
naming is an implementation-time decision, not decided here):
`crm.opportunity.service_line_confirmed`,
`crm.opportunity.service_line_added` (a fresh `INFERRED`→none-existed
case does not occur through the confirm action, but a possible future
manual-add-without-confirm action would use this),
`crm.opportunity.service_line_removed`. Payload: `service_line`,
`prior_provenance`, `new_provenance`, `actor_user_id` (already the
convention `EventRecord` payloads follow elsewhere in this codebase). No
new audit table — `EventRecord` already serves this purpose for the rest
of CRM.

## 6. INFERRED handling

As stated in §3: `INFERRED` rows are produced only by the shared legacy
mapper (run at Lead-conversion time, and available to be re-run
idempotently by the existing GAP-046 backfill command for
already-existing Opportunities, §18) — never by a raw UI toggle. An
`INFERRED` row alone **never** satisfies any of the gates in §9-§11 (only
`CONFIRMED` does) — this is the same rule GAP-046's own Gate-1 evidence
already proved is necessary (Gate-1 audit §10 scenario B).

## 7. UNKNOWN / NEEDS_REVIEW representation

**These are never persisted as rows** — this repeats and does not
reopen GAP-046's binding rule (Gate 2 §4/§7: "no membership row without a
known canonical line"). They are **read-time-derived states**, computed
from (a) the count/provenance of canonical rows and (b) the legacy
`service_category` scalar, via a small new read-side value
object/query (illustrative name: `OpportunityClassificationState`,
exact implementation shape is a Gate-3-time decision):

- **Classified:** ≥1 canonical row exists (report the set, each with its
  provenance).
- **NEEDS_REVIEW (subject-level, not a membership row):** zero canonical
  rows exist, but `service_category` ∈ `{inspection, consulting,
  combined_package}` — the ambiguous legacy set GAP-046's own mapping
  already refuses to convert into a row.
- **UNKNOWN-by-absence (subject-level, not a membership row):** zero
  canonical rows exist and `service_category` is null/unrecognized (in
  practice today, `service_category` is `NOT NULL` with the current
  Architecture default — see §8 for why this becomes truthfully possible
  only after the migration in §8 ships).

This state is what the UI (§3) and any future reporting surface render —
never a fabricated `UNKNOWN`/`NEEDS_REVIEW` row in
`opportunity_service_lines`.

## 8. Architecture-default removal + DB default — safe migration strategy

**Alternatives considered:**

- **1. Nullable migration:** alter `opportunities.service_category` to be
  nullable, drop the `DEFAULT 'architecture'` at the DB level, and remove
  the two application-level `?? 'architecture'` fallbacks
  (`OpportunityController.php:217`, `LeadController.php:304`), so an
  omitted classification is truthfully persisted as `NULL`. This is the
  only option that makes §7's "UNKNOWN-by-absence" state truthfully
  representable at the legacy-column level too, and directly satisfies
  SSOT §2.4 ("never default to Design/Architecture; missing classification
  must remain UNKNOWN/unclassified").
- **2. Sentinel value:** introduce a new explicit string (e.g.
  `'unclassified'`) as a 10th legal `service_category` value instead of
  nullability. Avoids a nullable-column migration, but fabricates a new
  taxonomy value the SSOT never defined, and conflicts with the
  established principle (Gate-1 audit, GAP-046 binding rules) that
  absence — not a sentinel string — represents "unknown."
- **3. Leave the DB/application default as-is, gate only on the canonical
  side.** Simplest, zero migration — but leaves SSOT §2.4/§10.3's already-
  recorded "active violation" unresolved, which is explicitly named in the
  Gate-1-approved problem boundary as something Gate 2 must address (§2,
  item D).

**Decision: Option 1.** `service_category` becomes nullable, its DB
`DEFAULT 'architecture'` is dropped, and both application-level fallbacks
are removed so an omitted classification persists as `NULL` — this is a
real ALTER COLUMN migration, to be written and verified (SQLite **and**
real MySQL, per this repo's established parity-verification pattern used
throughout GAP-046/GAP-043/GAP-044) in the future implementation session,
not in this design document. **Historical data is not touched or
reclassified** — every existing `'architecture'` row (real or previously
defaulted, indistinguishable, per the Gate-1 audit) stays exactly
`'architecture'` in the column; this migration only removes the
*mechanism* that produces new false-Architecture values going forward,
consistent with SSOT §10.1 ("no destructive migration is authorized") and
§10.2 ("legacy ambiguity is resolved by marking rows NEEDS_REVIEW/UNKNOWN,
never by silent reinterpretation" — reinterpreting existing rows is
exactly what this design does NOT do).

## 9. Pipeline classification gate

**Placement decision:** a single, centralized check inside the existing
`OpportunityStageTransitionService::transition()` (Gate-1 audit §8 already
confirms this is the one and only enforcement point for every stage
transition across every caller) — no new scattered gate. **Exact stage
boundary:** the gate fires when `$to` is `scope_defined` or any of
`proposal_draft, proposal_sent, negotiation, contracting, won` (the
"downstream active" stages per SSOT §3.2's own wording). **Explicit
exemption, stated as a design decision, not an oversight:** transitions
into `lost`, `no_bid`, and `nurture` are **never** gated — an
Opportunity must always be closeable/archivable/deferrable regardless of
classification state; forcing classification merely to decline or shelve
a deal would be a genuine UX regression with no SSOT justification.
Requirement: `count(opportunity->serviceLines()->where('provenance', 'CONFIRMED')) >= 1`
— an `INFERRED`-only Opportunity is blocked (Gate-1 audit §10 scenario B,
directly enforced here). Failure UX: §14.

## 10. WON→Project conversion gate

**Placement decision:** the pipeline gate (§9) already blocks entry into
`won` without ≥1 `CONFIRMED` row, so by the time `OpportunityController::convert()`
runs, classification should already be guaranteed. **Decision: add a
second, redundant, defense-in-depth check directly inside `convert()`
(and inside `createContract()`'s inline Project-creation branch) rather
than relying solely on the pipeline gate.** Rationale: (a) negligible
cost; (b) guards against any future capability (e.g. a not-yet-designed
"remove Service Line" action) silently reopening the gap between reaching
`won` and calling `convert()`; (c) the Owner's approved boundary names the
WON gate as its own explicit requirement (item F), distinct from the
pipeline gate — treating it as automatically covered by §9 alone would
under-deliver that requirement. **No propagation is added** — this gate
only reads `opportunity_service_lines`; it never writes
`project_service_lines` (GAP-046's own binding exclusion, restated, not
reopened).

## 11. Formal-Quote gate — placement across the full lifecycle

This is the section Owner's Gate-1 Round 1 correction specifically
required to be resolved with the real lifecycle, not assumed.

**Alternatives considered (see Gate-1 audit §9 for the full lifecycle
evidence each of these is compared against):**

- **A. Gate at native DRAFT creation** (`storeQuote()`/`reviseQuote()`).
  Earliest possible point. Rejected: blocks routine internal
  drafting/estimation work that legitimately happens before scope is
  fully settled; SSOT §3.3 defines the *formal* commercial moment as
  issuance ("A sent/accepted Quote is a historical commercial artifact"),
  not drafting.
- **B. Gate at native DRAFT→SENT formalization** (`sendQuote()`).
  Directly matches SSOT §3.3's own definition of the formal commercial
  moment. Covers the native operator lifecycle completely and, because
  the state machine requires `SENT` before `ACCEPTED`/`REJECTED`
  (`Quote::TRANSITIONS`, Gate-1 audit §9.1), transitively covers both the
  operator-accept and client-portal-accept paths (§9.2 D, §9.4 F) without
  needing a second gate at acceptance. Does **not** cover the external
  zena-boq-core path, which never creates a native `Quote` row at all.
- **C. Gate at every ACCEPTED transition** (operator + portal,
  `QuoteLifecycleService::accept()`). Redundant with B for the native
  lifecycle (a Quote cannot reach `SENT`→`ACCEPTED` without having already
  passed a gate at `SENT`); adds no coverage the SENT gate doesn't already
  provide, while gating one method deeper than necessary.
- **D. Gate at `createContract()`'s commercial-prerequisite check.**
  Necessary regardless of A/B/C, because this is the **one point** where
  the native-Quote-accepted path and the external-zena-boq-accepted-
  snapshot path converge (Gate-1 audit §9.5) — a gate confined to the
  native lifecycle alone (any of A/B/C) would leave the external path
  fully unconstrained, which is exactly the bypass Owner's Round 1
  directive required this design to account for.

**Decision: B + D together, not either alone.** Gate at native
`sendQuote()` (`DRAFT`→`SENT`) — this is "the formal Quote" per SSOT
§3.3, and transitively secures the entire native+portal accept/reject
lifecycle behind it — **and** independently gate `createContract()`
itself, checking `count(...CONFIRMED) >= 1` unconditionally, regardless of
whether the accepted Quote is native or external. This satisfies the
Owner's explicit requirement: *"a native-only classification gate must
NOT be presented as complete unless the external path is demonstrably
covered elsewhere"* — here the external path is covered by the second,
independent `createContract()` gate, not by assuming the native gate
extends to it.

**Explicit exemptions, stated as decisions:** `rejectQuote()`/
`PortalQuoteController::reject()` are **never** gated — a Quote must
always be rejectable regardless of classification state, symmetric with
§9's `lost`/`no_bid` exemption. `reviseQuote()` (creating a new `DRAFT`
copy) is **not** gated at creation (consistent with rejecting Option A
above) — its own eventual `sendQuote()` call is gated like any other
Quote's, so no special-case logic is needed for revisions.

**External path — explicitly not requiring a zena-boq-core change:** the
`createContract()` gate is enforced entirely on this codebase's own side
(refusing to proceed past the commercial-prerequisite check without
`CONFIRMED` classification, regardless of which quote source satisfied
`$hasNativeAccepted`/`$hasExternalAccepted`). Nothing about
`linkExternalBoqProject()`/`syncExternalQuote()` needs to change. This
confirms the §2 scope-boundary verification: zena-boq-core does not need
to become part of GAP-048.

## 12. Legacy `service_category` compatibility strategy

**`BusinessKpiService::serviceCategoryPerformance()` (Gate-1 audit §12):**
continues reading `service_category` unchanged for now — this design does
**not** rewrite it to be canonical-Service-Line-aware (that is a
reporting-surface migration of its own scale, consistent with the SSOT's
own roadmap ordering, §14 item 2, "Shared Project Health Read Model +
Shared Commercial/Financial Read Semantics," which is explicitly a later,
separate slice). **Compatibility decision required now, because §8 makes
the column nullable:** the report must add an explicit "Unclassified"
bucket for `NULL`, rather than silently dropping those rows or grouping
them under an empty-string key — this is the one behavior change this
slice does require of that consumer, to avoid it silently mis-reporting
once nulls become possible. Multi-Service-Line-aware reporting (e.g.
counting a Design-Build Opportunity once in each of two buckets) is
**explicitly out of scope** for this compatibility bridge.

**`DesignItemPageController` AI-suggestion consumer (Gate-1 audit §12,
`->value('service_category')` → `AiAssistService::suggestDesignItemDescription()`):**
compatibility bridge, not a rewrite: prefer the Opportunity's canonical
`CONFIRMED` `DESIGN`-family classification as the AI-suggestion input
when one exists; **fall back to the legacy `service_category` scalar**
only when no canonical `CONFIRMED` row exists (i.e. exactly the same
truthful "prefer canonical, degrade to legacy" pattern, not a forced
cutover). Exact fallback code shape is an implementation-time decision.

## 13. Tenant/RBAC design

**No new permission is proposed.** Both the classification-selection UI
and the confirm/remove actions reuse the existing `crm.manage` permission
(the same permission that already governs Opportunity `store`/`update`) —
checked against the Owner Decision Rules' four-question anti-escalation
test (`docs/owner-governance/OWNER_DECISION_RULES.md`): this does not
change what a user can do beyond what `crm.manage` already implies (edit
an Opportunity's business-meaning fields), does not change data
visibility beyond what `crm.view` already grants, does not shift decision
responsibility to a new role, and does not change risk the business
carries in a way that warrants a new permission tier — introducing a
narrower `crm.classify`/`crm.confirm` permission was considered and
rejected as unnecessary granularity (YAGNI) unless Owner specifically
wants a narrower confirm-only role in a future round. **Every new write
path (confirm, remove) goes through the existing, unmodified
`EnforcesServiceLineIntegrity` trait** (GAP-046, tenant-parent-derivation,
acting-tenant-context check) — reused exactly as-is, not reimplemented,
per the binding constraint in §7 of the Owner directive. New controller
methods derive `tenant_id` the same way every existing Opportunity
controller method already does (never trusted from request input).

## 14. Failure/error UX when a gate blocks an action

Reuse the exact response conventions already established in the audited
controllers (Gate-1 audit §3.2/§9): API paths return
`$this->validationError([...])` with a distinct key (illustrative:
`'service_line' => ['At least one confirmed Service Line is required...']`,
mirroring `createContract()`'s existing `'quote' => [...]` key
convention); web paths return `back()->with('error', ...)`. No new
response shape is introduced.

## 15. Backward compatibility for current records

**Open question surfaced, not resolved here (explicitly deferred to
Gate-3 planning / Owner input):** every existing Opportunity today has
zero canonical rows (Gate-1 audit, confirmed repo-wide). Gates in §9-§11
only fire **on transition**, not on read/display of an already-existing
record — an Opportunity already sitting in e.g. `negotiation` is not
retroactively blocked from remaining there, but it *would* be blocked
from its *next* transition once these gates ship, unless it already has
(or gains) a `CONFIRMED` row. This could strand legitimate in-flight
deals that predate classification. **This design recommends, but does
not decide, running the existing GAP-046 backfill command
(`service-lines:backfill-opportunities`, already idempotent, already
produces only `INFERRED`) across existing non-terminal Opportunities
before the gates ship** — but `INFERRED` alone still does not satisfy the
gate (§6), so a real business decision is needed on whether in-flight
deals get a one-time grace/grandfather allowance or must be manually
confirmed. **This is flagged as an explicit open question for Gate 3
planning and Owner input**, not silently decided by this design.

## 16. Test strategy (categories only — no tests written in this session)

Discriminating negative cases: unclassified Opportunity blocked from
`scope_defined`; unclassified Quote blocked from `sendQuote()`;
unclassified Opportunity blocked from `createContract()` via the **native**
path; unclassified Opportunity blocked from `createContract()` via the
**external accepted-snapshot** path (directly closing the Gate-1-identified
bypass — this is the single most important new negative test this design
implies); `INFERRED`-only classification still blocked at every gate
(proves `INFERRED` insufficiency is preserved). Discriminating positive
cases: `CONFIRMED`-classified Opportunity passes every gate; promoting
`INFERRED`→`CONFIRMED` via the new confirm action then passes; `lost`/
`no_bid`/`nurture` transitions and Quote rejection are never blocked
regardless of classification (proves the exemptions in §9/§11 are real,
not accidental gaps). Security: cross-tenant confirm/remove attempts
rejected (reusing GAP-046's existing cross-tenant test pattern).

## 17. MySQL/SQLite parity

The nullable-`service_category` migration (§8) must be verified on both
SQLite and real MySQL, following this repository's established parity
pattern (GAP-046/GAP-043/GAP-044: local + Docker MySQL 8.0 + the
`@group mysql-parity` live-CI mechanism). No new table is introduced by
this design — it uses GAP-046's existing `opportunity_service_lines`
table and the existing `EventRecord` table, both already MySQL/SQLite
portable.

## 18. Rollout/backfill boundaries

**No historical Project backfill** — GAP-046's binding "Option A, zero
Project backfill" decision is not reopened. The Opportunity-side backfill
command (already built, already idempotent) may be re-run as an
operational rollout step to seed `INFERRED` rows for existing
Opportunities (§15) — this is reuse of an existing tool, not new code.
**Recommended (not decided) rollout sequencing** for the future
implementation/Gate-3 planning: (1) ship the nullable migration + new UX +
confirm workflow with the gates initially inert (mergeable without
immediately blocking any existing workflow); (2) re-run the backfill
command; (3) enable the gates after Owner/business decides the §15 grace
question. This sequencing is a recommendation surfaced for Gate 3
planning, not authorized or decided by this Gate 2 document.

## 19. What this design explicitly does NOT solve

- The exact grace-period/grandfather policy for in-flight Opportunities
  (§15) — a business decision, not an engineering one.
- Exact UI copy/Vietnamese wording for the new classification panel and
  gate error messages.
- Exact migration SQL, route names, controller/method names, and
  event-key strings — all illustrative in this document, decided at
  implementation time within this design's boundary.
- Full multi-Service-Line-aware rewrite of `BusinessKpiService`/any
  reporting surface (§12) — deferred to the SSOT's own later "Shared
  Project Health Read Model" slice.
- Any change to zena-boq-core itself — not needed by this design (§11);
  if a future implementation session discovers it genuinely is needed,
  that must be flagged to Owner as a new dependency, not silently done.
- Every item in the Owner-directed out-of-scope list (§2): Opportunity→
  Project propagation, Project classification UX/backfill, Quote Scope
  Snapshot persistence, Contract multi-Service-Line, Portfolio, Project
  Health, Commercial/Finance/Resource Control, OPPM, Control Tower,
  Treasury, legacy-taxonomy retirement, GAP-041/042/045.

## 20. Loại trừ rõ ràng (restated)

Không thiết kế/triển khai: Opportunity→Project Service-Line propagation;
Project classification UX; lịch sử backfill phía Project; Quote Scope
Snapshot persistence; Contract multi-Service-Line; Portfolio Membership;
Project OPPM; Operations Control Tower; Finance/Treasury; retirement cuối
cùng của taxonomy cũ; GAP-041/GAP-042/GAP-045. Không sửa đổi
zena-boq-core. Gate 2 này không viết code, không viết migration, không
viết test, không viết implementation plan.

## 21. Decision Needed

Owner chọn một trong: Approve (cho phép mở phiên triển khai riêng, đúng
ranh giới §3-§18 ở trên) / Yêu cầu sửa đổi (changes_requested) / Từ chối
(declined).

## 22. What the owner is NOT being asked to decide

Owner không được yêu cầu duyệt tên route/controller/method/migration cụ
thể, chuỗi sự kiện audit cụ thể, hay câu chữ UI chính xác — đó là quyết
định ở phiên triển khai trong ranh giới đã duyệt. Owner **được** yêu cầu
quyết định (hoặc xác nhận đội kỹ thuật quyết định đúng): lựa chọn đặt cổng
Quote tại `sendQuote()` + `createContract()` thay vì `storeQuote()` (§11);
chiến lược migration nullable cho `service_category` thay vì sentinel
value (§8); việc không mở rộng phạm vi sang zena-boq-core (§2/§11); và câu
hỏi mở về chính sách ân hạn cho deal đang xử lý dở (§15, cần input kinh
doanh trước khi triển khai).
