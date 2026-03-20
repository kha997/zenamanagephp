# Product Purpose SSOT

Last updated: 2026-03-19
Status: temporary canonical purpose statement pending explicit replacement

## Purpose

ZenaManage is a multi-tenant enterprise operations platform for companies working across architecture, construction, interiors, and adjacent inspection/quality workflows.

The current product direction is not a generic dashboard showcase and not a single-function PM tool. The runtime-backed direction is:

1. manage tenant-scoped projects, tasks, documents, contracts, teams, and role-based access
2. support controlled delivery workflows through reusable work templates and project/component work instances
3. treat documents, approvals, change requests, RFIs, submittals, and inspections as operational records with tenant isolation and auditability
4. provide role-oriented operational views for PM, designer, and site-engineer users
5. keep notification, audit, and export capabilities close to those operational flows

## In Scope Near-Term

- Multi-tenant tenant isolation and RBAC as non-negotiable runtime invariants
- Project, task, document, team, settings, and contract operations required for day-to-day delivery
- WorkTemplate -> WorkInstance -> Deliverable flow as the process backbone
- Change request, RFI, submittal, and inspection modules where they connect to real project execution
- Evidence-bearing document and export flows
- Role-based dashboards only where they summarize live operational modules

## Product Verticals

- Architecture and design delivery
- Construction project execution
- Interior fit-out and procurement-adjacent delivery
- Inspection / quality assessment readiness through QC plan, inspection, NCR, and evidence workflows

## Explicit Boundary

The near-term product is not:

- a demo gallery for dashboards, universal-frame experiments, or debug pages
- a microservices program
- a pure standalone React SPA migration
- a generalized BI/analytics product separate from operational modules
- a full ERP for finance, HR, payroll, CRM, or procurement depth beyond project-delivery needs
- a full inspection/QMS suite independent from the work-template/document/change backbone

## Canonical Runtime Bias

When docs conflict, prefer:

1. route list truth
2. controller/model/migration truth
3. current tests proving active contracts
4. SSOT docs updated in 2026
5. older completion or overview reports only as historical context

## Current Backbone To Preserve

- `/app/*` tenant web surface for authenticated HTML navigation
- `/api/zena/*` for the clearest modern business contracts
- `docs/roadmap/canonical-roadmap.md` for execution sequencing
- `docs/roadmap/backlog.yaml` for backlog governance and story IDs
- `docs/agent-ssot-rules.md` for evidence and verification law
