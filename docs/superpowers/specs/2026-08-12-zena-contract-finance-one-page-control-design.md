# ZENA Contract & Finance One-Page Control — Business Design

Date: 2026-08-12
Status: conversational design approved by the Owner; **not** a repository Owner-Gate packet and **not** implementation authorization
Parent design: `docs/superpowers/specs/2026-08-12-zena-one-page-management-control-tower-design.md`
Related: GitHub Issue #248 (Project OPPM discovery/design request)

## 1. Purpose

Extend ZENA One-Page Management with two horizontal management surfaces that apply across Design, Construction, and standalone Inspection engagements:

1. **Commercial & Contract Control** — answers where quotations/contracts are in their lifecycle, what contractual/commercial obligation is waiting, and which contracts require management intervention.
2. **Finance Control** — answers what Z.E.N.A has contracted, collected, still needs to collect, spent/incurred, and where financial exceptions require management intervention.

The governing principle remains:

> **One management question = one management page.**

These pages must not become a statutory accounting system and must not duplicate operational source-of-truth records that already belong to Contract, Payment, PaymentCertificate, Expense, Material, Project Treasury, or other canonical domains.

---

## 2. Scope boundary with GitHub Issue #248 — mandatory non-overlap rule

Issue #248 owns the **Project OPPM** scope: a concise one-project read model that may display contract and finance summaries for that one project.

Issue #248 already requires OPPM to audit and correctly distinguish:

- project budget / execution cost;
- contract value;
- customer cash received;
- cash paid / expenses;
- task/component costs;
- treasury/cashflow data when canonical and reliable.

That overlap is intentional because OPPM needs a compact Commercial & Finance block for one project.

However, Issue #248 does **not** own the company/portfolio management surfaces defined in this document.

### 2.1 Ownership matrix

| Capability | Owner |
|---|---|
| One-project contract/finance summary inside OPPM | Issue #248 / Project OPPM |
| Company-wide quotation pipeline | Commercial & Contract Control slice |
| Company-wide contract portfolio | Commercial & Contract Control slice |
| Contract attention/expiring/waiting-signature/commercial-gate exceptions | Commercial & Contract Control slice |
| Payment-certificate portfolio and waiting approvals | Commercial & Contract Control slice |
| Retention / advance-recovery portfolio summaries | Commercial & Contract Control slice |
| Company-wide receivables and aging | Finance Control slice |
| Company-wide cashflow summary | Finance Control slice |
| Company-wide cost-vs-cash management view | Finance Control slice |
| Project financial-health comparison across projects | Finance Control slice |
| Detailed wallet/ledger/advance/reconciliation | Project Treasury domain, when canonical |
| Drilldown from OPPM into Contract/Finance/Treasury | Shared navigation only |

### 2.2 Non-duplication rule for Claude Code

When implementing Issue #248, Claude Code may:

- read canonical contract/finance sources;
- calculate the one-project summary defined by OPPM;
- expose drilldown links to Contract/Finance pages when those pages exist.

Issue #248 must **not** independently implement:

- a company-wide Contract Control page;
- a company-wide Finance Control page;
- a new receivables aging engine solely for OPPM;
- a duplicate cashflow report;
- a parallel Project Treasury ledger/wallet system;
- separate financial definitions that conflict with shared Contract/Finance semantics.

The Contract/Finance slices must likewise reuse OPPM/shared Project Health semantics rather than invent a different definition of project health, delay, or data reliability.

---

## 3. Product hierarchy

```text
                    ZENA OPERATIONS CONTROL TOWER
                                │
          ┌─────────────────────┼─────────────────────┐
          │                     │                     │
          ▼                     ▼                     ▼
  PROJECT PORTFOLIOS    CONTRACT CONTROL        FINANCE CONTROL
  Design/Construction    Commercial spine       Money/receivables
  /Inspection                  │                     │
          │                     └──────────┬──────────┘
          │                                │
          └──────────────────────┬─────────┘
                                 ▼
                           PROJECT OPPM
                                 │
                     Commercial & Finance block
                                 │
                     Contract / Treasury drilldown
```

Contract Control and Finance Control are **horizontal lenses** across all operational project lenses.

---

## 4. Shared financial semantic rules

These rules are mandatory across Control Tower, Contract Control, Finance Control, Project OPPM, reports, and future Treasury integrations.

### 4.1 Contract lifecycle is not contract attention

Canonical contract lifecycle may remain simple, e.g. draft/active/closed/cancelled.

Management attention is derived separately, for example:

- waiting signature;
- commercial condition pending;
- payment overdue;
- expiring soon;
- advance recovery outstanding;
- retention outstanding;
- payment certificate waiting;
- acceptance/closeout pending;
- clear.

Do not overload a canonical lifecycle field merely to create dashboard labels.

### 4.2 Cost is not cash

The system must distinguish:

- **cost incurred / execution cost** — economic/project cost represented by canonical operational cost sources;
- **cash paid** — money that actually left a controlled wallet/account;
- **cash received** — money actually received;
- **receivable** — amount contractually expected from the customer but not yet received;
- **contract value** — commercial value of the signed contract;
- **revenue / profit** — separate accounting concepts that must not be inferred from the above without an approved accounting definition.

A material receipt may represent incurred cost without proving that the supplier has been paid.

### 4.3 `cash received - cash paid` is not profit

Where the system shows `đã thu - đã chi`, label it as a cash/net-cash measure only.

Do not call it profit, margin, earned revenue, or accounting income unless a future approved accounting model explicitly defines those concepts.

### 4.4 Missing financial data must remain unknown

Missing or failed source data must never silently become:

- `0`;
- `0%`;
- `paid`;
- `on track`;
- `green`.

Use the repository's availability/reliability pattern or equivalent compatible trust semantics.

---

## 5. Commercial & Contract Control One Page

### 5.1 Primary management question

> **Where are all quotations/contracts, what commercial or contractual obligation is waiting, and which contracts require management intervention?**

### 5.2 One-page structure

The default page should contain:

1. compact exception-oriented KPI strip;
2. pre-contract / quotation pipeline summary;
3. contract portfolio table;
4. top contract exceptions;
5. payment-certificate / closeout exceptions where applicable;
6. drilldown to contract and project OPPM.

### 5.3 Recommended KPI strip

Subject to audited reliable data:

- quotations awaiting client acceptance;
- accepted quotations awaiting contract;
- contracts awaiting signature/effectiveness;
- active contracts;
- contracts expiring within a configurable period;
- contracts with overdue receivables;
- payment certificates awaiting action;
- retention outstanding;
- advance recovery outstanding;
- contracts requiring management intervention.

Exact threshold values must be configurable or approved separately; they must not be invented by implementation.

### 5.4 Pre-contract / quotation pipeline

Commercial & Contract Control should show the transition from commercial opportunity into executable contract where canonical data supports it:

```text
Opportunity
    ↓
Quotation preparing
    ↓
Quotation sent
    ↓
Waiting client acceptance
    ↓
Quotation accepted
    ↓
Contract preparing / negotiating
    ↓
Contract signed and effective
    ↓
Commercial commencement conditions satisfied
    ↓
Execution permitted
```

For standalone Inspection engagements, the existing approved Inspection Commercial Gate remains authoritative: no survey preparation/scheduling/field survey before quotation acceptance, effective contract, and contract-required advance/precondition.

Contract Control should surface that gate; it must not redefine it.

### 5.5 Contract portfolio row

A contract row should be able to show, where reliable:

- project/client;
- contract code/number;
- operational lens/type;
- contract value and currency;
- lifecycle status;
- signed/effective date;
- contractual start/end date;
- amount received;
- amount remaining receivable;
- overdue receivable amount;
- retention outstanding;
- advance outstanding/recovery state;
- primary contract attention;
- PM/accountable person.

The one-page row must remain compressed. Detailed terms, every payment, every certificate, and every document belong in drilldown.

### 5.6 Contract attention semantics

Recommended derived attention flags:

- `WAITING_SIGNATURE`;
- `WAITING_EFFECTIVE_DATE_OR_COMMENCEMENT`;
- `COMMERCIAL_CONDITION_PENDING`;
- `PAYMENT_OVERDUE`;
- `EXPIRING_SOON`;
- `ADVANCE_RECOVERY_OUTSTANDING`;
- `RETENTION_OUTSTANDING`;
- `PAYMENT_CERTIFICATE_WAITING`;
- `WAITING_ACCEPTANCE`;
- `CLOSEOUT_PENDING`;
- `CLEAR`;
- `NO_DATA`.

A contract may have multiple simultaneous flags. A single primary-attention label is display compression only.

### 5.7 Payment certificates for construction

The repository already has a PaymentCertificate concept with draft/submitted/approved lifecycle and fields for period value, retention, advance deduction, and net payable.

Contract Control should therefore be able to surface at portfolio level:

- certificates awaiting submission/approval;
- value pending approval;
- aging of submitted certificates when derivable;
- retention deducted;
- advance recovery deducted;
- net payable.

Do not create a second certificate model merely for dashboard convenience.

### 5.8 Contract types / operational lenses

The current Contract model must be audited before changing contract categorization.

At the time of this design, the canonical model exposes design/construction/other semantics. Standalone Inspection is now an approved operational lens, so the implementation audit must decide whether inspection requires:

- an additional canonical contract type;
- an existing service-category/contract metadata mechanism;
- or another already-established classification.

Do not add `inspection` solely to make a badge render before reviewing every consumer of contract type.

---

## 6. Finance Control One Page

### 6.1 Primary management question

> **What has Z.E.N.A contracted, collected, still needs to collect, incurred/spent, and where are the financial exceptions that require management intervention?**

### 6.2 One-page structure

The default page should contain:

1. company financial KPI strip;
2. receivables / collections exception table;
3. project financial-health comparison;
4. cashflow summary;
5. treasury/advance/reconciliation exceptions when canonical Project Treasury data exists;
6. drilldown to Contract, Project OPPM, Cashflow Report, and Project Treasury.

### 6.3 Recommended company KPI strip

Subject to reliable canonical data:

- total active contract value;
- cash received;
- remaining receivables;
- overdue receivables;
- expected collections in the next management window;
- recorded/incurred project cost;
- actual cash out, only when cash-source semantics are trustworthy;
- current-period net cash;
- retention outstanding;
- advances outstanding;
- projects with financial exceptions.

Every KPI must have one documented definition and source. Control Tower, Finance Control, OPPM, and reports must not calculate the same label differently.

### 6.4 Receivables are the primary exception surface

The repository already contains ContractPayment with planned/paid/overdue semantics, due date, and paid date.

Finance Control should provide a concise collection-action table such as:

- project;
- contract;
- payment milestone/name;
- amount;
- due date;
- current status;
- aging days;
- accountable owner / follow-up action when available.

This table answers **what money Z.E.N.A needs to collect now**, rather than forcing management to open every contract.

### 6.5 Receivables aging

A future/approved Finance Control slice may group unpaid receivables into aging buckets such as:

- not yet due;
- 1–30 days overdue;
- 31–60 days;
- 61–90 days;
- over 90 days.

Aging is a management projection over canonical due dates; it should not create duplicate payment records.

The exact bucket scheme is a business-design choice for the implementation slice. The system must preserve raw due-date detail regardless of grouping.

### 6.6 Project financial-health comparison

Finance Control should provide one row per project with only decision-relevant measures, for example:

- active contract value;
- cash received;
- remaining receivable;
- overdue receivable;
- recorded/incurred cost;
- actual cash position when trustworthy;
- retention outstanding;
- advance outstanding;
- financial attention;
- data reliability.

The row must not pretend to show profit unless an approved profit/accounting model exists.

### 6.7 Company cashflow summary

The repository already has an approved company cashflow design using:

- actual cash-in from paid contract payments;
- actual cash-out from recorded contract expenses under its current semantics;
- net cash;
- cumulative net inside the displayed window;
- expected-in from planned/overdue payments.

Finance Control should **reuse or summarize** that capability, not rebuild a second cashflow calculation.

The company Finance page may show a compact current/next-period panel and link to the detailed cashflow report.

### 6.8 Expected cash-in is not a full forecast

If the source contains planned receivables but no trustworthy planned-expense schedule, Finance Control may say:

- `expected collections next 30 days`;

but must not imply:

- `forecast company cash surplus next 30 days`;

unless both sides of the forecast are defined and reliable.

### 6.9 Cost source vs cash source

ContractExpense and automatic material cost currently have deliberately different semantics; material cost must not be manually duplicated where the repository already derives it from material receipts.

Finance Control must inventory and label each source as one of:

- incurred/project cost;
- actual cash payment;
- receivable;
- contractual value;
- treasury movement.

Do not sum unlike measures into a single `chi` number without proving they represent the same economic concept.

---

## 7. Project Treasury relationship

A separate Project Treasury & Cashflow design exists for detailed project money traceability, including wallets, funding, multi-leg routes, transfers, expenses, advances, reconciliation, immutable posting, and audit.

Finance Control must not duplicate that domain.

### 7.1 Layering rule

```text
FINANCE CONTROL
Company / portfolio exception summary
        ↓
PROJECT OPPM
One-project commercial & financial summary
        ↓
PROJECT TREASURY
Wallet / route / advance / expense / reconciliation detail
        ↓
Ledger / evidence / audit
```

### 7.2 Progressive availability

Until Project Treasury is canonical and released, Finance Control may use only currently canonical contract/payment/expense/cashflow sources and explicitly mark unavailable treasury measures as limited/no-data.

It must not create a temporary parallel wallet/ledger implementation merely to fill dashboard cards.

When Treasury later becomes canonical, Finance Control should consume its read models rather than reinterpret raw ledger entries independently in several screens.

---

## 8. Project OPPM integration

Project OPPM keeps a small Commercial & Finance block. It should be a **summary and drilldown surface**, not another finance application.

Recommended project-level summary, where reliable:

### Commercial

- active contract(s);
- contract value;
- contract lifecycle/attention;
- contractual delay/expiry condition;
- key payment/acceptance milestone.

### Finance

- cash received;
- remaining receivable;
- overdue receivable;
- recorded/incurred project cost;
- retention outstanding;
- advance outstanding/recovery;
- treasury status only if canonical/reliable.

OPPM should link to the contract and finance/treasury detail pages rather than replicate full transaction tables.

The one-project figures used here must come from the same shared definitions used by Contract Control and Finance Control.

---

## 9. Operations Control Tower integration

The highest company Control Tower should only carry a small set of commercial/financial exceptions, for example:

- contracts awaiting key action;
- overdue receivables;
- contracts expiring soon;
- retention/advance exceptions;
- projects with red financial health.

The Control Tower must drill into Contract Control or Finance Control instead of displaying every contract/payment detail itself.

---

## 10. Existing repository foundations to audit/reuse

Before any implementation plan, Claude Code must inspect current source, migrations, mounted routes, tests, and SSOT for at least:

- `App\Models\Contract` and canonical Contract ownership;
- Contract type/status consumers;
- `ContractPayment` and payment permissions/controllers;
- `ContractExpense` and automatic material-cost aggregation;
- `PaymentCertificate` and certificate-line/approval flows;
- Contract/Project finance pages and rollups;
- company cashflow report and its exact cash semantics;
- quotation / Opportunity integration and source quote fields;
- Baseline/contract delay semantics;
- Project Treasury design/branch/status before depending on it;
- material receipt/payment semantics;
- existing Contract Policy / Payment Policy / report permissions;
- tenant isolation and any relations without direct tenant scope;
- data-trust Availability/Reliability patterns.

Presence of a class or document is not proof that it is current or canonical. Reuse follows audit.

---

## 11. Acceptance scenarios

### Scenario A — OPPM does not duplicate Finance Control

Given Finance Control exists,
when a PM opens Project OPPM,
then OPPM shows only the project's compact financial summary and drilldown,
and does not render company-wide receivables, cashflow, or treasury portfolio tables.

### Scenario B — Contract lifecycle and attention remain separate

Given a contract is `active` but has an overdue payment,
when Contract Control is opened,
then lifecycle remains `active` while attention includes `PAYMENT_OVERDUE`.

### Scenario C — Cost does not become cash automatically

Given materials are received and recognized as project cost but supplier payment is not proven,
when Finance Control is opened,
then the system does not automatically report that material amount as actual cash paid.

### Scenario D — Cash net is not profit

Given Z.E.N.A has collected more cash than it has paid during the displayed period,
then the page may display positive net cash,
but must not label the difference as profit or margin.

### Scenario E — Inspection Commercial Gate appears in Contract Control

Given an Inspection quotation is accepted but the contract is not effective or a required advance is missing,
then Contract Control surfaces a commercial hold,
and the existing Inspection Commercial Gate still prevents survey execution.

### Scenario F — Payment-certificate exception

Given a construction payment certificate has been submitted but remains unapproved,
then Contract Control surfaces it as a waiting certificate exception without creating a duplicate payment-certificate record.

### Scenario G — Receivable aging uses canonical due date

Given an unpaid ContractPayment is past due,
then Finance Control derives its aging from the canonical due date and does not overwrite the payment record merely to place it in an aging bucket.

### Scenario H — Treasury data degrades safely

Given Project Treasury is not yet canonical/released or a treasury section fails to load,
then Finance Control continues to show reliable Contract/Payment measures and marks treasury measures limited/unavailable rather than zero.

---

## 12. Recommended delivery decomposition

This document is an umbrella business design, not one implementation work item.

Recommended slices:

1. **Contract/Finance Semantics Audit** — canonical sources, definitions, overlap with Project Treasury and existing cashflow/reporting.
2. **Shared Commercial & Financial Read Semantics** — reusable calculations/attention/trust metadata.
3. **Commercial & Contract Control One Page**.
4. **Receivables & Finance Control One Page**.
5. **Contract/Finance block in Project OPPM** — coordinated with Issue #248; this consumes shared semantics, it does not own slices 3–4.
6. **Operations Control Tower commercial/finance exceptions** — only after lower read models are stable.
7. **Treasury-enriched Finance Control** — only after Project Treasury becomes canonical and released.

Each implementation slice must receive its own correctly allocated Work ID / Owner Control Layer lifecycle as required by current repository governance.

**Do not use Issue #248 as the Work ID or catch-all implementation scope for Contract Control or Finance Control.**

---

## 13. Out of scope

Unless separately approved, this design does not define:

- statutory accounting;
- VAT/tax accounting;
- VAS chart of accounts;
- automatic bank feeds;
- revenue recognition;
- profit-and-loss accounting;
- foreign-exchange revaluation;
- payroll accounting;
- automatic debt collection;
- AI financial recommendations;
- duplicate wallet/ledger schema outside canonical Project Treasury;
- automatic contract legal interpretation.

---

## 14. Final product principle

The completed hierarchy should preserve one source of operational truth and several concise management lenses:

```text
Operational sources
Contract / Payment / Certificate / Expense / Material / Treasury
                         │
                         ▼
          SHARED COMMERCIAL & FINANCIAL SEMANTICS
                         │
        ┌────────────────┼─────────────────┐
        ▼                ▼                 ▼
 Contract Control   Finance Control    Project OPPM
        │                │                 │
        └────────────────┼─────────────────┘
                         ▼
             Operations Control Tower
```

The non-negotiable semantic rules are:

> **Contract lifecycle is not contract attention.**

> **Cost is not cash; cash is not revenue; net cash is not profit.**

> **OPPM summarizes one project; Contract Control and Finance Control manage the company/portfolio; Project Treasury owns transaction traceability.**
