# ZENA Service-Line Taxonomy — CRM → Commercial → Project Design

Date: 2026-08-12
Status: conversational design approved by the Owner; **not** a repository Owner-Gate packet and **not** implementation authorization
Parent design: `docs/superpowers/specs/2026-08-12-zena-one-page-management-control-tower-design.md`
Companion design: `docs/superpowers/specs/2026-08-12-zena-contract-finance-one-page-control-design.md`
Related: GitHub Issue #248 (Project OPPM discovery/design request)

## 1. Purpose

Create one canonical business classification that carries Z.E.N.A work coherently from CRM intake through opportunity qualification, quotation/contract, project creation, operational portfolios, Project OPPM, Contract Control, Finance Control, and Operations Control Tower.

The design fixes a current semantic break:

- Lead captures raw demand but has no service classification;
- Opportunity has one `service_category`, but its values mix business lines with technical disciplines and package labels;
- Project does not currently carry that classification when an Opportunity is converted;
- therefore downstream Design / Construction / Inspection portfolios would otherwise have to guess project membership from tasks, contracts, artifacts, names, or legacy categories.

The governing principle is:

> **One canonical Project, one CRM pipeline, multiple explicit operational lenses.**

The system must not create three separate CRMs or three separate Project systems merely to distinguish Design, Construction, and Inspection.

---

## 2. Verified repository state

### 2.1 Lead

`App\Models\Lead` currently represents a fast intake inbox. It stores contact hint, project description, source, status, converted Opportunity, notes, and captured-by. It has no service-line field or relation.

The current Lead UI captures contact/source/description. Service classification appears only inside the Lead → Opportunity conversion form.

### 2.2 Opportunity

`App\Models\Opportunity` currently contains:

- `service_category`;
- `service_scope_summary`;
- 14-stage CRM pipeline semantics;
- `converted_project_id`;
- quote/BOQ integration metadata.

Its current `VALID_SERVICE_CATEGORIES` are:

- `architecture`;
- `interior`;
- `landscape`;
- `structure`;
- `mep`;
- `construction`;
- `inspection`;
- `consulting`;
- `combined_package`.

This list mixes different dimensions:

- technical disciplines such as architecture/structure/MEP;
- operational/business lines such as construction/inspection;
- a generic consulting category;
- a commercial/package concept `combined_package`.

The current create and Lead-convert flows can default a missing service category to `architecture`. This is unsafe because unknown demand silently becomes Design/Architecture.

### 2.3 CRM UI semantic mismatch

The current Lead conversion UI renders legacy `inspection` with the Vietnamese label **“Giám sát”**.

The approved One-Page Management architecture defines standalone **Inspection / Kiểm định** as a distinct operational lens and explicitly separates it from Construction QA/QC inspection. Therefore legacy `inspection` data cannot automatically be assumed to mean standalone Z.E.N.A Inspection Service without provenance review.

### 2.4 Project

`App\Models\Project` is the canonical shared Project object. It contains lifecycle, PM, dates, progress, budget/cost, teams, tasks/components, milestones, baselines, design items, documents and related operational artifacts.

It currently has no canonical Design / Construction / Inspection classification.

Current Opportunity WON → Project conversion copies name, description, budget estimate, dates and PM ownership, but it does **not** propagate `service_category` or an equivalent classification to the Project.

### 2.5 Quote and Contract downstream

`App\Models\Quote` belongs to an Opportunity and holds revision/status/financial terms, but it does not currently expose a separate service-line snapshot.

`App\Models\Contract` currently has a legacy single-value `contract_type` concept (`design`, `construction`, `other`) and source Opportunity/Quote references. A separate Contract/Finance design already requires inspection of every contract-type consumer before changing that model.

These downstream facts mean the taxonomy must be designed once and propagated deliberately rather than adding unrelated `type` fields screen by screen.

---

## 3. Product decision — two-level taxonomy

The approved model has two separate classification dimensions.

### 3.1 Level 1 — Service Line

Service Line answers:

> **Which Z.E.N.A operational business lens does this engagement belong to?**

Canonical initial Service Lines:

- `DESIGN` — Thiết kế;
- `CONSTRUCTION` — Thi công / vận hành xây dựng;
- `INSPECTION` — Kiểm định độc lập.

Service Line membership is **multi-valued**.

Examples:

```text
Villa A
[DESIGN]
```

```text
Factory B
[CONSTRUCTION]
```

```text
Existing Building Assessment C
[INSPECTION]
```

```text
Design-Build Villa D
[DESIGN, CONSTRUCTION]
```

A Project may therefore appear in more than one operational portfolio while remaining one canonical Project.

### 3.2 Level 2 — Service Scope / Discipline

Scope answers:

> **What professional/technical work does Z.E.N.A perform inside those Service Lines?**

Initial conceptual examples:

#### DESIGN

- Architecture;
- Interior;
- Landscape;
- Structure;
- MEP;
- Planning;
- other approved design disciplines.

#### CONSTRUCTION

- general construction;
- structural works;
- architectural/finishing works;
- interior fit-out;
- MEP works;
- construction supervision if the Owner later confirms this belongs here;
- other approved construction scopes.

#### INSPECTION

- existing-condition survey/assessment;
- structural assessment;
- technical inspection/testing;
- quality assessment;
- other approved independent inspection scopes.

The exact Scope catalog is **not required to be fully normalized in the first implementation slice**. The first priority is establishing correct Service Line semantics across CRM and Project.

---

## 4. Why a single `project_type` is rejected

A field such as:

```text
project_type = design | construction | inspection
```

is rejected as the canonical design because it cannot represent a Design-Build or other multi-scope engagement without inventing ambiguous values such as `combined`.

Likewise, `combined_package` is rejected as a canonical classification because it does not state which Service Lines are combined.

`combined_package` may remain, if still useful, as a **commercial package label** during migration, but it must not determine portfolio membership.

---

## 5. One CRM pipeline, not three CRMs

Z.E.N.A keeps one CRM pipeline.

A customer may begin with a Design need and later expand into Construction. Splitting CRM into independent Design / Construction / Inspection pipelines would fragment customer history, opportunity economics, quotation history and conversion traceability.

The desired model is:

```text
                    ONE CRM PIPELINE
                           │
             ┌─────────────┼─────────────┐
             │             │             │
          DESIGN      CONSTRUCTION    INSPECTION
          filter          filter         filter
```

Sales stages remain the board columns. Service Lines become filter/badge dimensions, not replacement pipelines.

---

## 6. Classification maturity through the funnel

Classification becomes progressively more authoritative as commercial understanding improves.

### 6.1 Lead — Service Intent

Lead is raw intake. Classification is optional.

Lead may carry zero or more **Service Intents**:

- Design;
- Construction;
- Inspection.

A lead may explicitly remain **Unclassified / Unknown**.

Example:

> “Anh có nhà cũ muốn bên em qua xem và tư vấn sửa lại.”

This must not be forced into Design before discovery establishes whether the engagement is design, construction, inspection, or a combination.

AI may suggest intent based on the description, but AI suggestion is never authoritative without human confirmation.

### 6.2 Opportunity — Qualified Service Lines

Opportunity is where Service Lines become business classification.

Early stages may remain unclassified while discovery continues.

Before the Opportunity reaches a stage where scope/proposal is being formally defined, at least one Service Line must be known.

Recommended gate:

- transition **to `scope_defined` or any downstream active sales stage** requires at least one Service Line;
- creating a formal quotation also requires at least one Service Line;
- converting WON Opportunity → Project requires at least one Service Line.

No path may silently default missing classification to Architecture/Design.

### 6.3 Quote — Commercial Scope Snapshot

An Opportunity classification may evolve during negotiation.

A sent/accepted Quote is a commercial historical artifact. Its meaning must not silently change because the Opportunity is edited later.

Therefore implementation must audit and choose a repository-compatible mechanism so each Quote revision can preserve the relevant **Service Line / Scope snapshot at issuance**.

This can be accomplished through a snapshot or immutable association consistent with existing quote-revision patterns. The exact storage mechanism is an implementation-audit decision; the semantic requirement is mandatory.

### 6.4 Contract — Committed Scope

Contract classification describes the service actually committed by the signed contract, which may be narrower than the overall Project.

Example:

```text
Project D
[DESIGN, CONSTRUCTION]

Contract 01
[DESIGN]

Contract 02
[CONSTRUCTION]
```

Therefore Project Service Lines and Contract Service Lines are related but are **not interchangeable concepts**.

The current single `contract_type` must be audited before migration/deprecation. Contract Control must not assume `Project.service_lines` can substitute for contract-level commercial scope.

### 6.5 Project — Operational Service Lines

When a WON Opportunity is converted, the Project inherits the confirmed Opportunity Service Lines.

The Project then becomes the authoritative source for **operational portfolio membership**.

Project classification may expand later through approved new scope, variation, or additional contract.

Example:

```text
Initial Project
[DESIGN]

Later approved construction engagement
[DESIGN, CONSTRUCTION]
```

The system does not need a second Project solely because another Service Line is added when the real-world engagement remains the same project.

---

## 7. Canonical portfolio membership

Portfolio membership is based on **confirmed Project Service Lines**, not heuristic inference on every read.

### Design Portfolio

Include Project when:

```text
DESIGN ∈ Project.service_lines
```

### Construction Portfolio

Include Project when:

```text
CONSTRUCTION ∈ Project.service_lines
```

### Inspection Portfolio

Include Project when:

```text
INSPECTION ∈ Project.service_lines
```

A multi-line project appears in multiple portfolios but counts once in company unique-project totals.

Control Tower must distinguish:

- **Unique active projects**;
- **Projects by Service Line**.

It must not add portfolio counts together and call the result the total number of projects.

---

## 8. Classification reliability / provenance

Migration and AI assistance must not create false certainty.

Each migrated or system-derived classification must preserve provenance/reliability semantics compatible with repository trust patterns.

Conceptual reliability states:

- `CONFIRMED` — explicitly confirmed by an authorized user or established through approved commercial conversion;
- `INFERRED` — safely mapped from a legacy value with clear semantics, awaiting optional confirmation;
- `NEEDS_REVIEW` — legacy source is ambiguous;
- `UNKNOWN` — no defensible classification exists.

Exact naming should align with existing Availability/Reliability conventions if the repository already provides a canonical trust representation.

### 8.1 Legacy mapping policy

Safe conceptual mapping:

| Legacy `Opportunity.service_category` | New Service Line | Reliability |
|---|---|---|
| `architecture` | DESIGN | INFERRED |
| `interior` | DESIGN | INFERRED |
| `landscape` | DESIGN | INFERRED |
| `structure` | DESIGN | INFERRED |
| `mep` | DESIGN | INFERRED |
| `construction` | CONSTRUCTION | INFERRED |
| `inspection` | **NEEDS_REVIEW** | ambiguous because current UI labels it “Giám sát” |
| `consulting` | **NEEDS_REVIEW** | cross-line / undefined |
| `combined_package` | **NEEDS_REVIEW** | does not identify which lines are combined |
| null/unknown | UNKNOWN | no inference |

This intentionally refines the earlier conceptual mapping: legacy `inspection` must **not** be auto-promoted to standalone Inspection because current UI semantics prove ambiguity.

### 8.2 Existing Project backfill

If an existing Project is linked by `Opportunity.converted_project_id`, migration may carry the Opportunity-derived classification and its reliability to that Project.

Projects without a trustworthy source remain UNKNOWN/NEEDS_REVIEW and enter an admin classification queue.

Do not infer Project Service Line from project name, task names, team names or presence of arbitrary artifacts unless a separately approved migration rule proves that inference safe.

---

## 9. Recommended persistence approach

Three approaches were considered.

### Option A — one scalar `type`

Rejected because it cannot model multi-line engagements.

### Option B — JSON arrays directly on Lead/Opportunity/Project

Advantages:

- quick implementation;
- low table count.

Weaknesses:

- weaker relational integrity;
- more awkward indexing/filtering/reporting;
- harder provenance/confirmation metadata per membership;
- difficult controlled removal/audit semantics.

### Option C — explicit multi-value membership relations

**Recommended.**

Conceptually maintain entity-specific Service Line membership for:

- Lead Service Intents;
- Opportunity Service Lines;
- Project Service Lines;
- later Contract Service Lines if the contract audit confirms this direction.

The exact table/class names and whether tenant IDs are duplicated on relation rows must follow current canonical repository conventions after schema/tenant audit.

A single central Service-Line value set must own the allowed values. Do not duplicate slightly different arrays of `design/construction/inspection` across several models/controllers/views.

A database catalog table is not mandatory for only three Owner-controlled values; implementation should prefer the least complex repository-consistent representation that still enforces uniqueness and queryability.

---

## 10. Scope/discipline normalization strategy

The existing `service_category` should not be deleted prematurely because it currently carries useful detail.

Recommended transition:

### Phase A

Establish canonical Service Lines and preserve legacy `service_category` for compatibility.

### Phase B

Normalize technical Scope/Discipline where business value justifies it.

Examples:

```text
legacy architecture
→ Service Line = DESIGN
→ Scope = Architecture
```

```text
legacy construction
→ Service Line = CONSTRUCTION
→ scope determined from discovery/quote details
```

`service_scope_summary` remains free text and can coexist with structured Scope values.

Do not force every scope detail into a taxonomy before there is a real reporting/workflow need.

---

## 11. CRM UX design

### 11.1 Lead Inbox

The fast-capture nature must remain.

Add an optional compact Service Intent control:

```text
Nhu cầu dịch vụ
[ ] Thiết kế
[ ] Thi công
[ ] Kiểm định

Không rõ thì để trống.
```

The form must not force the receptionist/sales user to classify a vague inquiry.

Lead list rows may show confirmed/suggested intent chips where present.

### 11.2 Lead → Opportunity conversion

The conversion form should promote intent into Opportunity classification but allow the user to correct it.

Legacy dropdown “Loại dịch vụ” must no longer be the sole classification control.

The current misleading mapping `inspection => Giám sát` must be corrected as part of the semantic migration; standalone Inspection UI should use **Kiểm định**. If Z.E.N.A needs “Giám sát” as a separate commercial scope, it must receive its own explicitly approved scope semantics rather than reusing `inspection`.

### 11.3 Opportunity detail

Show Service Lines prominently near the opportunity identity:

```text
[THIẾT KẾ] [THI CÔNG]
```

Show Scope/Discipline separately when available.

### 11.4 Pipeline board

Keep sales stage as columns.

Opportunity cards should show Service Line badges.

Provide filters:

```text
Tất cả | Thiết kế | Thi công | Kiểm định | Đa dịch vụ | Chưa phân loại
```

A multi-service Opportunity appears under each relevant filter but remains one Opportunity.

---

## 12. Opportunity stage and commercial gates

The existing Opportunity stage transition service remains the owner of stage transitions.

The classification design adds business validation, not a second CRM state machine.

Required behavior:

1. Early discovery stages may have no Service Line.
2. Transition to `scope_defined` requires at least one Service Line.
3. Downstream proposal/negotiation/contracting stages cannot erase all Service Lines.
4. Formal Quote creation requires at least one Service Line.
5. WON → Project conversion requires at least one Service Line.
6. Missing classification returns an actionable validation error; it never applies a default.
7. Terminal opportunity history keeps the Service Lines that existed at closure.

Exact interaction with existing stage-transition rules must be audited and tested rather than bypassed in controllers.

---

## 13. Project creation and classification changes

### 13.1 CRM conversion

WON Opportunity → Project must copy confirmed Service Lines to the Project inside the same business transaction or another atomically safe repository pattern.

The conversion event should provide enough audit evidence to trace classification provenance from Opportunity to Project.

### 13.2 Direct Project creation

Normal user-facing creation of a new operational/client Project should request at least one Service Line.

Legacy import/migration or special internal flows may temporarily result in UNKNOWN classification if current repository requirements demand compatibility, but such Projects must be visibly queued for classification rather than defaulted.

### 13.3 Adding Service Lines

Adding a Service Line after Project creation is allowed when commercial scope expands.

Example:

```text
[DESIGN]
→ [DESIGN, CONSTRUCTION]
```

The change must be audited with actor/time and a reason/source where repository conventions support it.

### 13.4 Removing Service Lines

Removing a Service Line is more dangerous than adding one.

The system must not allow a simple checkbox to remove a line when authoritative artifacts still prove that operational scope exists, for example:

- DESIGN with DesignItems or active Design contract/scope;
- CONSTRUCTION with active site/construction artifacts;
- INSPECTION with an active standalone Inspection workflow/contract.

Implementation must define a controlled reclassification path for such cases rather than hiding existing domain history by changing a badge.

Historical records are never deleted solely because a Service Line is removed from current active scope.

---

## 14. Relationship to Inspection Commercial Gate

Service Line classification answers **what kind of engagement this is**.

It does not replace the Inspection Commercial Gate.

For a Project containing `INSPECTION`, the standalone Inspection workflow still enforces:

```text
accepted quotation
+ effective contract
+ required advance/preconditions
= Commercial Gate PASS
```

Only after PASS may survey preparation/scheduling/field survey proceed.

Classification determines that Inspection semantics apply; Commercial Gate determines whether execution is permitted.

Construction QA/QC inspections remain construction artifacts and do not automatically add the standalone `INSPECTION` Service Line.

---

## 15. Relationship to Contract classification

Project Service Lines represent the overall operational engagement.

Contract scope may differ.

Therefore the current `Contract.contract_type` must not become the source of truth for Project Portfolio membership merely because it already exists.

Before changing Contract classification, implementation must inventory:

- every `contract_type` consumer;
- contract creation from Opportunity/Quote;
- Contract Control grouping;
- Design/Construction progress blocks;
- payment-certificate logic;
- legacy data;
- standalone Inspection requirements.

The likely long-term direction is multi-value Contract Service Lines or another equivalent committed-scope representation, but this is subordinate to the Contract semantic audit and must not be implemented blindly in the first CRM/Project taxonomy slice.

---

## 16. Relationship to Project OPPM — Issue #248

Issue #248 remains the one-project OPPM work item.

OPPM does not own Service-Line CRUD or CRM classification.

OPPM consumes confirmed Project Service Lines to decide which domain blocks to render:

```text
DESIGN present
→ Design block

CONSTRUCTION present
→ Site / Construction / QA-QC block

INSPECTION present
→ standalone Inspection block
```

A Design-Build Project can therefore render both Design and Construction blocks.

OPPM must not infer these blocks from presence/absence of arbitrary records once canonical Project Service Lines exist.

---

## 17. Relationship to Contract & Finance Control

Contract Control and Finance Control are horizontal management lenses.

Service Lines provide consistent filters/grouping across those pages but do not change financial semantics.

Examples:

- Contract Control filter `Inspection` shows contracts commercially classified as standalone Inspection, once contract-level classification becomes canonical;
- Finance Control may compare receivables by Service Line without counting one multi-line Project as multiple unique Projects;
- cost/cash/revenue/profit semantic separation remains unchanged.

---

## 18. Relationship to Operations Control Tower

Control Tower can safely aggregate:

- unique project count;
- Design project count;
- Construction project count;
- Inspection project count;
- multi-service project count;
- unclassified project count.

The following identity must be understood:

```text
Design count + Construction count + Inspection count
≠ unique project count
```

because one Project may belong to several Service Lines.

Unclassified records should become a management-data-quality exception, not disappear from totals.

---

## 19. Data-quality and trust rules

1. Unknown classification is shown as UNKNOWN/UNCLASSIFIED, never as Design by default.
2. Inferred legacy mappings remain distinguishable from confirmed classifications.
3. AI suggestion is not confirmation.
4. Ambiguous legacy `inspection`, `consulting`, and `combined_package` values require review.
5. Missing relation/query data must degrade safely rather than returning an empty Service Line set and implying “no service”.
6. Portfolio membership requires confirmed or policy-approved inferred Project classification; exact visibility policy for INFERRED rows must be decided in the implementation slice.
7. Every dashboard using Service Lines must use the same canonical read semantics.

---

## 20. Migration approach

Migration should be additive and reversible in stages.

### Step 1 — Inventory

Measure existing values/counts for:

- Lead records;
- Opportunity `service_category` values/nulls;
- Opportunity → Project conversions;
- current Project population;
- current Contract types;
- Quotes and accepted revisions;
- any controller/view/report/filter relying on `service_category`.

### Step 2 — Add canonical Service-Line membership

Do not remove legacy fields yet.

### Step 3 — Backfill only defensible mappings

Automatically infer DESIGN for architecture/interior/landscape/structure/MEP and CONSTRUCTION for construction.

Mark ambiguous values for review.

### Step 4 — Carry Opportunity classification to linked Projects

Preserve reliability/provenance.

### Step 5 — Update CRM forms/filters and conversion gates

Remove unsafe Architecture default behavior.

### Step 6 — Project classification UX

Add project Service Line visibility/editing with audit controls.

### Step 7 — Portfolio consumers

Design / Construction / Inspection Portfolio consume canonical Project Service Lines.

### Step 8 — Downstream commercial alignment

After audit, align Quote snapshot and Contract committed-scope classification.

### Step 9 — Legacy retirement

Only after all consumers are migrated and compatibility tests pass may legacy `service_category` / single-type semantics be deprecated or repurposed.

No destructive migration is part of the first taxonomy release.

---

## 21. Acceptance scenarios

### Scenario A — vague Lead remains unclassified

Given a Lead only says “nhà cũ cần xem và tư vấn”,
when the Lead is captured,
then no Service Line is forced,
and the record remains valid.

### Scenario B — no silent Architecture default

Given a user converts a Lead without selecting a Service Line,
when the Opportunity remains in an early discovery stage,
then it may remain UNCLASSIFIED,
and the system does not silently assign Architecture/Design.

### Scenario C — qualification requires classification

Given an Opportunity has no Service Line,
when the user attempts to move it to `scope_defined`,
then the transition is rejected with an actionable message requesting Service Line classification.

### Scenario D — Design-Build remains one Opportunity and one Project

Given an Opportunity is classified `[DESIGN, CONSTRUCTION]`,
when it is WON and converted,
then one Project is created with both Service Lines,
and it appears in both Design and Construction Portfolios.

### Scenario E — company totals deduplicate multi-line Projects

Given one Design-only Project and one Design-Build Project,
then Design Portfolio count is 2,
Construction Portfolio count is 1,
but unique company Project count is 2, not 3.

### Scenario F — legacy inspection is not auto-confirmed

Given a legacy Opportunity has `service_category = inspection` created under a UI that labelled it “Giám sát”,
when taxonomy migration runs,
then it is marked NEEDS_REVIEW rather than automatically confirmed as standalone Inspection.

### Scenario G — standalone Inspection differs from Construction QC

Given a Construction Project has internal `QcInspection` records,
then those records do not by themselves add `INSPECTION` to Project Service Lines.

### Scenario H — Project inherits CRM classification

Given a confirmed `[INSPECTION]` Opportunity reaches WON,
when it is converted,
then the resulting Project inherits `[INSPECTION]` with traceable provenance.

### Scenario I — adding scope is auditable

Given a DESIGN Project later receives an approved Construction engagement,
when CONSTRUCTION is added,
then Project becomes `[DESIGN, CONSTRUCTION]` and the change is auditable.

### Scenario J — unsafe removal is blocked

Given a Project has authoritative Design artifacts/contract scope,
when a user attempts to remove DESIGN through a simple edit,
then the system refuses or routes through controlled reclassification rather than hiding the domain history.

### Scenario K — Quote history does not drift

Given a Quote revision was sent when an Opportunity was `[DESIGN]`,
and the Opportunity later expands to `[DESIGN, CONSTRUCTION]`,
then the previously sent Quote still represents its original commercial scope.

### Scenario L — OPPM uses canonical Project Service Lines

Given a Project is `[DESIGN, INSPECTION]`,
when OPPM is opened,
then it can render Design and standalone Inspection domain blocks without inferring classification from task names.

---

## 22. Recommended delivery decomposition

This is a semantic umbrella design, not one large implementation work item.

Recommended slices:

1. **Service-Line Inventory & Semantics Audit** — schema, consumers, counts, stage/quote/contract/project paths.
2. **Canonical Service-Line Foundation** — central value set + Opportunity/Project membership + provenance/trust.
3. **CRM Classification UX & Gates** — Lead intent, Opportunity badges/filters, no-default rule, stage validation.
4. **Opportunity → Project Propagation & Project Classification UX**.
5. **Portfolio Membership Migration** — Design/Construction/Inspection reads use canonical Project Service Lines.
6. **Quote Scope Snapshot Alignment**.
7. **Contract Service-Line Alignment** — only after Contract consumer audit.
8. **Legacy taxonomy retirement** — only after all readers/writers migrate.

Each slice must use a correctly allocated Work ID and Owner Control Layer lifecycle.

This taxonomy work should precede portfolio implementation that depends on classification.

---

## 23. Explicit non-overlap with Issue #248

Issue #248 may consume Service Lines for Project OPPM rendering and filtering.

Issue #248 must not become the implementation owner for:

- Lead Service Intent;
- CRM Service-Line classification;
- Opportunity stage gates;
- migration of legacy `service_category`;
- Quote classification snapshots;
- Contract classification migration;
- Project classification CRUD/audit;
- portfolio membership taxonomy.

These are shared semantics used by OPPM and other management surfaces.

---

## 24. Final product rule

The final model should read coherently from first customer contact to executive management:

```text
Lead
optional Service Intent
        ↓
Opportunity
qualified multi-value Service Lines
        ↓
Quote
commercial scope snapshot
        ↓
Contract
committed scope
        ↓
Project
confirmed operational Service Lines
        │
        ├───────────────┬────────────────┐
        ▼               ▼                ▼
     DESIGN        CONSTRUCTION       INSPECTION
    PORTFOLIO        PORTFOLIO         PORTFOLIO
        └───────────────┼────────────────┘
                        ▼
                   PROJECT OPPM
                        ▼
              OPERATIONS CONTROL TOWER
```

Non-negotiable principles:

> **One CRM pipeline, not three CRMs.**

> **One canonical Project, not three Project systems.**

> **Service Line is multi-valued; Scope/Discipline is a separate dimension.**

> **Unknown means UNKNOWN — never silently default to Design/Architecture.**

> **Portfolio membership comes from canonical Project Service Lines, not heuristic guessing.**

> **Commercial documents preserve their historical scope; later CRM edits must not rewrite history.**
