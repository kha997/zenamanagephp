# O-18 — Read-only permission impact matrix (Project Member, repeated seeding)

**Status: OPEN pre-merge security / deployment-readiness obligation. NOT resolved, NOT declared acceptable.** Do not resolve without a separate Owner Gate. Generated from the integrated code (`php artisan route:list --json`, 1332 routes) on the integration branch; no database was read or written.

## What happens
`DatabaseSeeder` gives the Project Member role **15** grants on the first run and **26** on the second and third. The 11 extra codes below are declared in `PermissionSeeder`'s Member list (committed 2026-07-20, before this PR) but are looked up *before* `ZenaPermissionsSeeder` creates their rows, so the first run silently drops them. This PR does **not** change that list, the seed order, or any Member grant. Effective Member access therefore depends on how many times seeding has run on an environment (a freshly installed database ≠ a re-seeded one).

## Impact matrix (potential exposure if an environment has been seeded more than once)
All gating below is by `RoleBasedAccessControlMiddleware:<permission>` on the route; `TenantIsolationMiddleware` still limits results to the caller's tenant (O-3: project data is tenant-visible by decision). HTTP methods of the gated routes: **GET, POST** — 7 POST routes exist (see "Non-read routes").

| Permission | Resource (potential business-data exposure to **every** Project Member of the tenant) | Gated routes | Methods | Example routes | Related policy / guard |
|---|---|---|---|---|---|
| `task.view` | Tasks (name, status, assignee, schedule, progress) | 22 | GET/POST | `api/v1/task-assignments/{taskAssignment}`, `api/v1/tasks`, `api/v1/tasks/{task}` | TaskAccessMiddleware; PermissionMatrixController |
| `crm.view` | CRM leads, accounts, opportunities, quotes (customer & pipeline data) | 16 | GET/POST | `api/zena/crm/accounts`, `api/zena/crm/accounts/{id}`, `api/zena/crm/leads` | LeadPolicy, AccountPolicy, OpportunityPolicy, QuotePolicy |
| `material.view` | Material catalogue | 4 | GET | `api/zena/materials`, `api/zena/materials/{id}`, `operator/materials` | MaterialPolicy |
| `material.read` | Material requests | 3 | GET | `api/zena/material-requests`, `api/zena/material-requests/{id}`, `api/zena/material-requests/{id}/receipts` | MaterialRequestPolicy |
| `vendor.view` | Vendors / suppliers | 4 | GET | `api/zena/vendors`, `api/zena/vendors/{id}`, `operator/vendors` | VendorPolicy |
| `boq.view` | Bills of quantities (quantities and prices) | 6 | GET | `api/zena/boqs`, `api/zena/boqs/{boq}/line-items`, `api/zena/boqs/{boq}/line-items/{lineItem}` | BoqPolicy |
| `contract.view` | Contracts (parties, values, status) | 12 | GET | `api/v1/projects/{project}/contracts`, `api/v1/projects/{project}/contracts/{contract}`, `api/zena/projects/{project}/contracts` | ContractPolicy |
| `event-record.view` | Event records (project event log) | 3 | GET | `api/zena/event-records`, `api/zena/event-records/{id}`, `operator/activity-feed` | — (route-gated only) |
| `design-item.view` | Design items / deliverables | 5 | GET | `api/zena/design-items`, `api/zena/design-items/{id}`, `api/zena/design-items/{id}/documents` | DesignItemPolicy |
| `site_diary.view` | Site diaries | 4 | GET | `api/zena/site-diaries`, `api/zena/site-diaries/{id}`, `operator/site-diaries` | SiteDiaryPolicy |
| `material-receipt.view` | Material (goods) receipts | 3 | GET | `api/zena/material-receipts`, `api/zena/material-receipts/{id}`, `api/zena/material-receipts/{id}/material-request` | MaterialReceiptPolicy |

## Reading the matrix
- "Potential exposure" is inferred from the resource type; field-level exposure was not audited here.
- The 11 codes are *named* as view/read codes, but **not every gated route is a read** (correction of an earlier statement that they were read-only). Several expose commercially sensitive data (contracts, BOQ prices, vendors, CRM pipeline) to all members rather than only to project participants (membership-scoped reads are a separate, later Work ID per O-3).

## Non-read routes gated by these codes (verified in `routes/web.php` and the controllers)
| Permission | Route | Effect | Additional guard found in the controller |
|---|---|---|---|
| `task.view` | `POST app/time/start`, `/time/stop`, `/time/entries`, `/time/entries/{entry}`, `/time/entries/{entry}/delete` | create / edit / delete the caller's time entries | own-timesheet feature (TimeEntryService); intended for all staff — **not audited here for cross-user access** |
| `task.view` | `POST app/time/review` (`TimeReviewPageController@act`) | approve / reject time entries | `abort_unless($this->review->isReviewer($user), 403)` — a Member who is not a reviewer is refused |
| `crm.view` | `POST operator/crm/opportunities/{id}/ai-summary` | triggers an AI summary of an opportunity | **no additional authorization found** in `summarizeOpportunity` — route-gated by `crm.view` only (potential AI-cost / data-sharing exposure for every Member on a re-seeded environment) |

- The 4 Recovery-01 quarantined tests include one (`test_D3_project_member_can_open_my_work`) whose outcome depends directly on this behaviour.

## Options for the Owner (no option is selected or implied)
1. Accept 26 as the intended state and make a fresh seed produce it deterministically (seed-order fix, separate PR).
2. Remove the 11 codes from the Member list so both first and repeated seeds give the narrower set (revocation; separate PR; check which UI pages Members lose).
3. Defer, keeping the current non-deterministic behaviour (must be recorded as an accepted risk before any deploy).

Before any deploy: determine, **read-only**, how many seed runs each target environment has had and what the Project Member role currently holds there (do not mutate).
