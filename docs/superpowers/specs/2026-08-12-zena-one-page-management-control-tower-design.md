# ZENA One-Page Management & Operations Control Tower — Business Design

Date: 2026-08-12
Status: conversational design approved by the Owner; **not** a repository Owner-Gate packet and **not** implementation authorization
Related: GitHub Issue #248 (Project OPPM discovery/design request)

## 1. Purpose

ZenaManage should evolve from a collection of operational screens into a coherent **One-Page Management operating system** for Z.E.N.A.

The governing product principle is:

> **One management question = one management page.**

The goal is not to place every datum on one giant dashboard. The goal is to let an Owner, Admin, PM, team lead, or staff member answer the most important operational question from one concise page and drill down only when an exception needs investigation.

This design covers the whole project portfolio of Z.E.N.A across three operational lenses:

1. **Design** — architectural/interior/engineering design work and client review cycles.
2. **Construction** — site execution, materials, QA/QC, NCR, acceptance, and delivery.
3. **Inspection** — Z.E.N.A's standalone inspection/assessment engagements, including quotation, contract, survey, technical assessment, reporting, acceptance, and payment.

These are **operational lenses on one shared Project platform, not three separate project-management systems**.

A project may appear in more than one lens when the real engagement spans multiple scopes (for example a Design–Build project). Implementation must not force exclusive project typing unless the repository audit proves that exclusivity is a real business rule.

## 2. Product hierarchy

```text
                        ZENA OPERATIONS CONTROL TOWER
                         Company / Owner / Admin
                                  │
        ┌─────────────────────────┼──────────────────────────┐
        │                         │                          │
        ▼                         ▼                          ▼
 DESIGN PORTFOLIO       CONSTRUCTION PORTFOLIO      INSPECTION PORTFOLIO
        │                         │                          │
        └─────────────────────────┼──────────────────────────┘
                                  │
                                  ▼
                         RESOURCE CONTROL
                                  │
                                  ▼
                           PROJECT OPPM
                                  │
                    TASK / DOMAIN WORK / DOCUMENT
```

`/app/today` remains the personal execution surface:

> **Today = What do I personally need to do now?**

The pages answer different management questions and must not be collapsed into one oversized dashboard.

| Surface | Primary question |
|---|---|
| Today | What do I personally need to do now? |
| Resource Control | What is everyone doing and where can work be rebalanced? |
| Design Portfolio | Where are all design engagements and client-review cycles? |
| Construction Portfolio | Where are all construction engagements and site exceptions? |
| Inspection Portfolio | Where are all standalone inspection engagements from commercial intake through closeout? |
| Project OPPM | What is the complete one-page health of this project? |
| Operations Control Tower | Where does management need to intervene across the company? |

## 3. Core management doctrine

### 3.1 Exception management first

Management pages must prioritize exceptions, not merely totals.

The high-level visual semantics are:

- **RED** — management action required now.
- **AMBER** — attention/monitoring required.
- **GREEN** — on track.
- **GRAY** — insufficient or unreliable data.

Exact color implementation is an engineering/UI decision. The business requirement is the four-way semantic distinction, including the explicit `unknown / insufficient data` state.

### 3.2 Lifecycle is not attention state

Do not overload a project's lifecycle status with operational attention.

A project can remain `active` while simultaneously being:

- contract-late;
- forecast-late;
- blocked;
- waiting for client;
- in revision;
- waiting for material;
- waiting for inspection/acceptance.

These are **derived attention flags**, not replacements for Project lifecycle.

### 3.3 Derived state should not become a second source of truth

Management states such as `WAITING_CLIENT`, `FORECAST_LATE`, or `SITE_BLOCKED` should be derived from canonical operational data wherever possible.

Do not create duplicate management tables merely to copy Task, DesignItem, Baseline, inspection, material, contract, or assignment data for display.

Dedicated storage is acceptable only for information that does not exist in operational modules and is genuinely management-authored, such as:

- OPPM management note;
- explicit weight;
- management decision;
- next action when it cannot be derived;
- historical snapshot when historical reporting requires immutability.

### 3.4 Shared semantics across all pages

A metric must mean the same thing everywhere.

For example, `contract late` must not have one definition in Portfolio, a second in OPPM, and a third in Control Tower.

The implementation should establish shared management read semantics before building all final pages.

## 4. Shared Management Read Layer

The preferred architectural direction is a shared read layer over canonical operational data:

```text
Canonical operational data
  Project
  Baseline
  Task
  DesignItem
  WorkInstance / WorkInstanceStep
  Team / TaskAssignment
  Contracts / financial records
  Construction site / materials / QA-QC / NCR
  Inspection service workflow
  EventRecord / audit trail
            │
            ▼
      MANAGEMENT READ LAYER
            │
    ┌───────┼────────┬────────┐
    ▼       ▼        ▼        ▼
 Project  Design  Resource  Attention
 Health    Flow     Load      / Aging
    │       │        │        │
    └───────┴────────┴────────┘
            │
            ▼
 Control Tower / Portfolios / OPPM
```

Class names are deliberately not prescribed in this business design. The implementation agent must inspect repository conventions and canonical ownership before naming technical components.

## 5. Shared Project Health semantics

Every portfolio and Project OPPM should consume one shared project-health concept containing, as available:

- project identity;
- PM / accountable owner;
- participating team(s);
- lifecycle;
- delivery progress;
- progress reliability;
- contract baseline condition;
- execution baseline condition;
- attention flags;
- blockers;
- overdue work;
- waiting-external work;
- nearest important deadline;
- management-data availability/reliability.

The read model must preserve uncertainty. Missing data must not silently become zero or green.

## 6. Company Operations Control Tower

### 6.1 Purpose

The Control Tower answers:

> **Where does company management need to intervene today?**

### 6.2 Top-level indicators

The Control Tower should be able to summarize, subject to reliable source availability:

- active projects;
- projects needing attention;
- contract-late projects;
- forecast-late projects;
- blocked projects/work;
- work waiting on external parties;
- unassigned open work;
- resource exceptions;
- upcoming critical milestones/deadlines.

The same figures should be segmentable by Design, Construction, and Inspection lenses.

### 6.3 Company one-page layout

The default one-page experience should expose:

1. **Company KPI strip** — compact exception-oriented counts.
2. **Three portfolio summary blocks** — Design, Construction, Inspection.
3. **Top project exceptions** — only the projects management should inspect first.
4. **Top resource exceptions** — only the people/teams where balancing or intervention is needed.
5. **Direct drilldown** — every summary must navigate to the relevant filtered portfolio/resource/project page.

The Control Tower is not a replacement for the detailed portfolio pages.

## 7. Design Portfolio One Page

### 7.1 Purpose

The Design Portfolio answers:

> **Where are all current design engagements in the internal/client review cycle, who owns them, and which ones need intervention?**

### 7.2 Existing semantic foundation to reuse

The repository already has a DesignItem client-facing review state machine. The business semantics are suitable for portfolio-level aggregation:

- `draft` — active preparation;
- `internal_review` — waiting internal review;
- `sent_to_client` — waiting client response;
- `revision_requested` — client revision required;
- `approved` — accepted by client;
- `final` — finalized;
- blocker fields — operational blocking condition.

The Design Portfolio must use the canonical DesignItem authority and must not infer client review state from unrelated WorkInstanceStep status.

### 7.3 Portfolio row

A project row should be able to show, when data exists:

- project;
- PM;
- active design phase/workstream;
- overall progress;
- primary attention;
- number of design items waiting internal review;
- number waiting client;
- number in revision;
- blocked count;
- responsible team/member(s);
- nearest client/contract deadline;
- contract-delay status.

### 7.4 Design attention priority

The portfolio may derive a primary attention label to aid scanning. Recommended business precedence:

1. contract late;
2. blocked;
3. revision requested;
4. internal review waiting;
5. waiting client;
6. in progress;
7. clear/on-track.

This primary label is a display compression; the underlying project may retain multiple simultaneous attention flags.

### 7.5 Waiting aging

A waiting state is more useful when paired with age.

Where the audit trail reliably identifies the time a DesignItem entered `sent_to_client`, `internal_review`, or another waiting state, the management read layer should calculate how long it has been waiting.

Do not label a client as `late` merely because an item has been waiting. `Waiting 6 days` is valid. `Client late by 2 days` requires an explicit response due date/SLA.

`due_to_client_at` must not be reinterpreted as the client's response deadline if its canonical meaning is the date Z.E.N.A must deliver to the client.

## 8. Construction Portfolio One Page

### 8.1 Purpose

The Construction Portfolio answers:

> **Where are all construction engagements, which sites are off plan, what is blocking execution, and who owns the next action?**

### 8.2 Management dimensions

A construction project row should be able to summarize, subject to audited source availability:

- project and PM/site lead;
- current execution stage;
- physical/execution progress;
- contract delay / execution forecast;
- next critical deadline;
- site blockers;
- material/procurement waiting conditions;
- open QA/QC inspections;
- failed inspections / open NCRs;
- acceptance/closeout state;
- responsible team/person;
- primary attention.

### 8.3 Construction attention semantics

Recommended derived attention flags include:

- `CONTRACT_LATE`;
- `FORECAST_LATE`;
- `SITE_BLOCKED`;
- `MATERIAL_WAITING`;
- `QC_FAILED` / `NCR_OPEN`;
- `WAITING_APPROVAL`;
- `EXECUTING`;
- `ON_TRACK`;
- `NO_DATA`.

The exact field-to-state mapping must be established from the current canonical construction modules during audit. Do not create new fields simply to reproduce information already represented in site, material, inspection, NCR, task, or baseline records.

### 8.4 Inspection terminology inside construction

**QC inspection inside a construction project is not the same business object as a standalone Z.E.N.A inspection-service project.**

This distinction is mandatory throughout naming, queries, permissions, reports, and UI.

## 9. Inspection Portfolio One Page

### 9.1 Purpose

The Inspection Portfolio covers **standalone inspection/assessment engagements sold by Z.E.N.A to clients**.

It answers:

> **Where is every inspection engagement from initial request through quotation, contract, survey, assessment, report, acceptance, and payment?**

It must not be built by simply listing `QcInspection` records from construction projects.

### 9.2 Inspection business flow

The approved business flow is:

```text
RECEIVE REQUEST
        ↓
DEFINE SCOPE / COLLECT PRELIMINARY DOCUMENTS
        ↓
PREPARE QUOTATION
        ↓
SEND QUOTATION
        ↓
WAIT FOR CLIENT QUOTATION APPROVAL
        ↓
QUOTATION ACCEPTED
        ↓
PREPARE / NEGOTIATE CONTRACT
        ↓
CONTRACT SIGNED AND EFFECTIVE
        ↓
SATISFY PRE-SURVEY PAYMENT / ADVANCE CONDITION, IF CONTRACT REQUIRES IT
        ↓
COMMERCIAL GATE PASSED
        ↓
PREPARE SURVEY
        ↓
SCHEDULE SURVEY
        ↓
FIELD SURVEY
        ↓
TECHNICAL ANALYSIS / ASSESSMENT
        ↓
DRAFT REPORT
        ↓
INTERNAL REVIEW
        ↓
WAIT FOR CLIENT / EXTERNAL FEEDBACK WHEN REQUIRED
        ↓
REVISE / FINALIZE
        ↓
ISSUE OFFICIAL REPORT
        ↓
ACCEPTANCE / PAYMENT
        ↓
COMPLETED
```

### 9.3 Commercial Gate — hard business rule

Z.E.N.A must **not** proceed to survey preparation, survey scheduling, or field survey until the Commercial Gate is passed.

At minimum, Commercial Gate requires:

1. the quotation has been accepted by the client;
2. the contract has been signed and is effective;
3. if the contract requires advance/payment before survey, that contractual precondition has been confirmed;
4. any other explicit contractual commencement condition required before mobilization has been satisfied.

This is a business invariant, not merely a dashboard label.

The implementation must reject or prevent workflow transitions that would bypass this gate.

### 9.4 Received is not activated

A request/project record may exist before Z.E.N.A is authorized to perform professional field work.

Therefore the system must distinguish:

- **received / commercial pipeline**;
- **commercially cleared / permitted to execute**;
- **technical execution**.

Do not treat project creation time as the automatic start of Z.E.N.A's contractual delivery obligation.

### 9.5 Contractual start anchor

For contractual delay semantics, the commencement anchor must come from the actual agreement and commencement conditions, such as:

- contract effective date;
- advance/payment confirmation date if that triggers commencement;
- client document/site handover date;
- another explicit contractual commencement condition.

The system must not universally calculate contract delay from `projects.created_at`.

### 9.6 Inspection management states

For one-page management, the detailed workflow can be compressed into the following management groups:

- `PRE_SALES` — intake / scope clarification;
- `QUOTATION` — quotation preparing/sent/waiting acceptance;
- `CONTRACT` — contract preparation/negotiation/signature;
- `COMMERCIAL_HOLD` — signed/accepted but a required commercial commencement condition is still missing;
- `SURVEY_PREP` — commercially cleared and preparing/scheduling survey;
- `SURVEY` — field survey in progress;
- `TECHNICAL` — analysis/assessment;
- `REPORTING` — report drafting;
- `REVIEW` — internal review;
- `EXTERNAL_WAITING` — waiting for client/third-party feedback;
- `FINALIZING` — revision/finalization;
- `ISSUED` — official report issued;
- `CLOSEOUT` — acceptance/payment closeout;
- `COMPLETED` — engagement complete.

These management groups may be derived from a more granular workflow implementation.

### 9.7 Inspection Portfolio row

A project row should be able to show:

- client/project;
- PM/technical lead;
- inspection scope;
- commercial stage;
- technical stage;
- Commercial Gate status;
- next milestone/date;
- report state;
- external waiting age;
- contract-delay status;
- acceptance/payment closeout state;
- primary attention.

## 10. Resource Control One Page

### 10.1 Purpose

Resource Control answers:

> **Who is doing what, for which project, what are they waiting on, where are exceptions, and where may work be reassigned?**

It is a horizontal view across Design, Construction, and Inspection.

### 10.2 Recorded workload is not capacity

The repository already contains a useful concept of recorded open work. This must not be mislabeled as real availability.

A person with zero recorded open tasks is not automatically `free`.

The safe Phase-1 wording is:

> **No open work recorded**

not:

> **Available / free**

### 10.3 Work-state decomposition

Resource Control should separate work into at least:

- **Active Work** — this person/team is expected to act now;
- **Waiting Work** — progress is waiting on client/reviewer/external dependency;
- **Blocked Work** — cannot proceed;
- **Upcoming Work** — known but not yet actionable;
- **Overdue Work** — deadline has passed and work is not complete.

This distinction is critical: a person with eight waiting items may have more practical capacity than a person with two active high-effort items.

### 10.4 Resource row

A person/team row should be able to show:

- name/team;
- current focus (top 1–2 active items, not a full task dump);
- number of projects across each operational lens;
- active count;
- waiting count;
- blocked count;
- overdue count;
- nearest deadline;
- unassigned/exception signals;
- recorded-load state.

### 10.5 Real capacity — later capability

Real capacity planning requires more than open task counts.

It may use assigned/actual hours already represented by task-assignment data, but reliable capacity additionally requires business semantics for:

- standard working hours;
- work calendar;
- leave/absence;
- assignment period;
- future planned workload;
- non-project work when relevant.

Until those semantics are established, the system must not display a mathematically precise `free capacity %` as if it were authoritative.

## 11. Project OPPM

Project OPPM is the drill-down one-page view for a specific project and remains related to Issue #248.

### 11.1 Common OPPM shell

Every project OPPM should share a common shell:

1. Project identity, client, PM, contract, baseline.
2. Project health and key KPIs.
3. 8–12 compressed workstreams rather than every task.
4. Responsible people/teams.
5. Timeline / upcoming milestones.
6. Blocked / overdue / waiting exceptions.
7. Management next actions / decisions.
8. Data availability/reliability metadata where needed.

### 11.2 Domain-specific OPPM block

The common shell is extended by the operational lens:

**Design**
- design-item review cycle;
- client waiting;
- revisions;
- approval/finalization.

**Construction**
- site execution;
- materials;
- QA/QC;
- NCR;
- acceptance/closeout.

**Inspection**
- quotation/contract/Commercial Gate;
- survey;
- technical assessment;
- report/review;
- issue/acceptance/payment.

A multi-scope project may expose more than one domain block.

## 12. Role-aware scope

The same management semantics should be scoped by role, not reimplemented as separate products.

### Owner/Admin

Company-wide tenant view, subject to RBAC.

### PM

Projects for which the user is accountable plus authorized team scope.

### Team Lead

Authorized team members and their relevant project work.

### Staff

Personal Today/My Work and only project/resource data allowed by existing RBAC/project visibility rules.

The implementation must audit existing permission names and prefer existing permissions where their semantics are correct. New permissions should be added only when the existing model cannot express the required business boundary.

## 13. Data reliability and freshness

All management pages must respect the repository's data-trust approach.

A KPI or status must communicate when data is:

- available and reliable;
- limited/partial;
- stale where freshness matters;
- not available;
- failed to load.

Missing source data must never silently appear as `0`, `0%`, `on track`, or `green`.

This applies especially to:

- project progress;
- cost/budget;
- resource load;
- contract delay;
- inspection Commercial Gate;
- construction QA/QC state.

## 14. Repository constraints the implementation must preserve

Before implementation, the agent must perform a current-repo audit and verify all of the following against source, migrations, mounted routes, tests, and SSOT:

1. canonical ownership for Project, Task, DesignItem, WorkTemplate/WorkInstance, Team, TaskAssignment, Baseline, Contract/financial records, construction site/material/QC/NCR modules, and inspection-related modules;
2. compatibility/legacy models that must not be expanded;
3. tenant scopes and any tables that require project-based tenant guarding rather than a direct `tenant_id`;
4. RBAC/policy conventions;
5. query-budget / N+1 conventions;
6. existing dashboard trust metadata;
7. actual semantics of current inspection/QC models;
8. existing quotation and contract models before creating new commercial workflow tables;
9. existing project categorization/template/service-category mechanisms before adding a new project-type field.

**Do not add a `project_type` field merely because this design names three portfolios.** Portfolio membership is a business lens and may be derived from project scope, template, service category, linked domain work, or a dedicated classification model after audit.

## 15. Known reusable repository foundations

The design is intentionally aligned with patterns already present in the repository and should audit/reuse them rather than duplicate them:

- canonical Project/Task management;
- DesignItem state machine and DesignItemStatusService;
- OpenWorkReadQuery and existing Workload/My Work surfaces;
- Team and TaskAssignment data;
- Baseline and project-delay semantics;
- Today Workspace read-model/data-trust patterns;
- schedule/Gantt;
- QcInspection / inspection-NCR workflows for construction QA/QC;
- Site Engineer dashboard-related capabilities;
- contract/payment/treasury work where canonical and semantically appropriate;
- EventRecord/audit history.

Presence in the repository is **not** proof that a component has the right semantics for this design. Reuse follows audit, not name matching.

## 16. Critical semantic distinction: two kinds of inspection

The repository and future UI must preserve two separate concepts:

### A. Construction QA/QC inspection

An inspection performed inside a Construction project to verify site/material/work quality.

It belongs under the Construction operational lens and can drive NCR/acceptance attention.

### B. Standalone Inspection Service Project

A client engagement where Z.E.N.A is contracted to inspect/assess a building, structure, system, condition, or other agreed scope and issue a professional report/deliverable.

It has its own commercial and technical lifecycle defined in §9.

One may reuse shared technical primitives only if the audit proves the semantics are compatible. The UI and business reporting must never merge the two concepts into one count merely because both use the word `inspection`.

## 17. Acceptance scenarios for the overall product design

The implementation program must eventually prove at least these business scenarios:

### Scenario A — Owner sees company exceptions

Given Design, Construction, and Inspection projects exist,
when Owner/Admin opens the Control Tower,
then the page shows reliable company-wide exception counts and top projects needing intervention,
and each number drills into the matching filtered portfolio.

### Scenario B — Design waiting-client aging

Given a DesignItem has been sent to a client,
when the Design Portfolio is opened,
then the relevant project shows `waiting client` and the waiting age when derivable,
without falsely declaring the client contractually late unless a response deadline exists.

### Scenario C — Construction quality exception

Given a construction project has an open failed QA/QC inspection or NCR,
when the Construction Portfolio is opened,
then that project is surfaced as an attention exception using canonical QA/QC/NCR data.

### Scenario D — Inspection Commercial Gate blocks survey

Given an inspection-service engagement has an accepted quotation but the contract is not yet signed/effective,
when a user attempts to schedule or start field survey,
then the system prevents the transition.

Given the contract is signed/effective but a contract-required advance has not been confirmed,
then the system still prevents survey execution.

Given all required Commercial Gate conditions are satisfied,
then survey preparation/scheduling may proceed.

### Scenario E — Inspection contract delay uses correct commencement anchor

Given an inspection contract starts its delivery period only after advance confirmation or another defined commencement condition,
when contract delay is calculated,
then the delay uses that contractual commencement anchor rather than project creation time.

### Scenario F — Resource waiting is not treated as active capacity consumption blindly

Given a staff member has several open items but most are waiting on client/reviewer action,
when Resource Control is opened,
then active, waiting, blocked, and overdue work are shown separately.

The system must not declare exact free-capacity percentage unless capacity semantics are configured and reliable.

### Scenario G — Multi-lens project

Given one engagement includes Design and Construction scope,
when portfolio views are opened,
then the project may legitimately appear in both Design and Construction portfolios,
while remaining one canonical Project and one shared set of core management semantics.

### Scenario H — QC inspection is not counted as standalone inspection project

Given a Construction project contains QcInspection records,
when the standalone Inspection Portfolio is opened,
then those internal construction QA/QC inspections do not become standalone inspection-service projects.

## 18. Delivery decomposition

This umbrella design is intentionally **too broad for a single implementation work item**. It must be decomposed into independently reviewable slices.

Recommended sequence:

1. **Management Semantics Audit** — inventory canonical data and lock shared definitions.
2. **Shared Project Health Read Model** — contract/execution delay, progress, attention, reliability.
3. **Design Portfolio One Page**.
4. **Construction Portfolio One Page**.
5. **Inspection Service Workflow + Commercial Gate + Inspection Portfolio One Page**.
6. **Resource Control One Page — Recorded Workload**.
7. **Project OPPM** — Issue #248 should consume the shared semantics instead of inventing its own.
8. **Company Operations Control Tower** — aggregate already-proven read models.
9. **Real Resource Capacity Planning** — only after capacity/calendar semantics are approved.

Slices 3–7 may be re-ordered after the semantics audit if repository dependencies justify it, but the Control Tower should be assembled after its underlying read semantics are stable.

Each implementation slice must follow the repository's current Owner Control Layer and have its own correctly allocated Work ID / gate lifecycle as required. This umbrella document does not allocate or guess Work IDs and does not constitute Gate 1, Gate 2, or Gate 3 evidence by itself.

## 19. Out of scope for this umbrella design

Unless separately approved, this document does not define:

- a new accounting system;
- statutory accounting/tax reporting;
- automatic bank feeds;
- AI resource scheduling;
- automatic commitment of staff to projects without a human decision;
- replacement of Zena BOQ pricing ownership;
- a new document storage subsystem;
- a separate database for Design, Construction, or Inspection;
- a one-screen dashboard containing every detailed record.

## 20. Final product principle

The completed system should behave as one operating hierarchy:

```text
Owner/Admin
    ↓
Operations Control Tower
    ↓
Design / Construction / Inspection Portfolios
    ↘           ↓            ↙
           Resource Control
                 ↓
             Project OPPM
                 ↓
       Task / Domain Work / Evidence
```

The core rule is:

> **Design, Construction, and Inspection are operational lenses on one shared Project platform. Resource is the horizontal layer across all lenses. Project OPPM is the one-project drill-down. Operations Control Tower is the highest exception-management layer. Today remains the individual's action page.**

This architecture should make ZenaManage an operating system for management decisions, not merely a place where work records are stored.
