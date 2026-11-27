# O-18 — Owner decision package (Project Member grants depend on how often the seeder has run)

**Status: OWNER DECIDED 2026-11-24, Option D (26 codes) with Project Manager included.** The local patch is verified and uncommitted; see `97-o18-implementation-report.md`. §7 (i)–(ii) and §9 (iii) are still open.

**Correction to §7.1:** the production provisioning command `production:rbac` (GAP-115) ran `ZenaPermissionsSeeder` *before* `PermissionSeeder`. Before this patch, it therefore granted Project Member all 11 codes, 32 in total, on its **first** run. Nothing was granted or revoked, seed order is unchanged, and no code was changed for O-18. This package was prepared 2026-11-24 against PR #422 head `b4440a3d2` and is not committed. All database work used throwaway SQLite databases; no shared, staging or production database was read or written.

Evidence (in `evidence/`):
- `o18-seed-reproduction.txt`: grants after the 1st, 2nd and 3rd seed.
- `o18-route-trace.tsv`: the 82 gated routes.
- `o18-runtime-probe.json`: HTTP results for a seeded Project Member.
- `o18-runtime-probe-test-source.php.txt`: the scratch probe that produced the JSON. It was not added to the test suite.

---

## 1. Reproduction (Task B.1)
| Database | Run | System Admin | Project Manager | Project Member |
|---|---|---|---|---|
| dbA | seed #1 | 249 | 60 | **20** |
| dbA | seed #2 | 249 | 60 | **31** |
| dbA | seed #3 | 249 | 60 | **31** |
| dbB (independent fresh DB) | seed #1 | 249 | 60 | **20** |

- Only Project Member changes between runs. It gains 11 codes and loses none.
- Runs 2 and 3 are identical. A fresh first seed is reproducible (dbA = dbB).

**Root cause.** `PermissionSeeder::assignPermissionsToRoles()` grants Project Member a list that resolves codes with `Permission::whereIn('code', [...])` and then calls `sync()`.
- The 11 codes are created only later, by `ZenaPermissionsSeeder`. On the first run they do not exist yet, so they are silently skipped.
- The list was added by commit `62ce0791c` (2026-07-20, "grant Project Member the modern .view permission codes"). That commit came after a real user test showed Members getting 403 on the main pages.
- **The stated business intent was to grant these codes. On a fresh install they never take effect.**

**Two secondary findings.** Neither is changed here.
- **S-1. `sync()` overwrites manual grants.** PermissionSeeder uses `sync()` for Project Manager and Project Member. Every re-seed therefore deletes any permission an administrator granted to those roles by hand, and later seeders re-add only their own codes. This contradicts the additive policy stated by GAP-098 (`syncWithoutDetaching`).
- **S-2. Re-seeding can abort part-way.** During the probe, running `DatabaseSeeder` again after a tenant had been created failed in `WorkTemplateBaselineSeeder` with a UNIQUE `work_templates(tenant_id, code)` violation. That seeder runs after PermissionSeeder, so a failed re-seed on a live environment would still have widened Member grants. The cause was not investigated.

## 2. Exact codes (Task B.2)
The 11 codes are `task.view`, `crm.view`, `material.view`, `material.read`, `vendor.view`, `boq.view`, `contract.view`, `event-record.view`, `design-item.view`, `site_diary.view` and `material-receipt.view`.

For comparison, the Project Manager role (60 codes) holds **none** of the 11, in any run. See §6, finding P-1.

## 3. Route and authorization trace (Task B.3)
82 routes are gated: 75 GET and 7 POST. There are no PUT, PATCH or DELETE routes. A second authorization layer exists for these codes:
- **Policies.** Lead, Account, Opportunity and Quote use `crm.view`. Material, MaterialRequest, Vendor, Boq, Contract, DesignItem, SiteDiary and MaterialReceipt each use their own code.
  - In every one of these policies, `viewAny` is the permission alone, and `view` is the same tenant plus the permission.
  - **No policy scopes to project membership**, so access is tenant-wide, consistent with O-3.
- **`TaskAccessMiddleware`** uses `task.view`.
- **`contracts/show.blade.php`** shows the BOQ section on `contract.view`.

| Code | Routes | Writes | What a Member can reach | Class (Task B.4) |
|---|---|---|---|---|
| `task.view` | 22 | 7 POST (time) | my-work, today, schedule, team workload (all tenant users' open work), tenant-wide task lists (api/v1, api/zena, work-template), own timesheet | **Necessary employee functionality.** Workload shows everyone's work. Writes have extra authorization (§4) |
| `site_diary.view` | 4 | — | site diaries, tenant-wide | Project-specific operational |
| `design-item.view` | 5 | — | design items and their documents | Project-specific operational (core for a design firm) |
| `material.read` | 3 | — | material requests and their receipts | Project-specific operational |
| `material-receipt.view` | 3 | — | goods receipts | Project-specific operational |
| `material.view` | 4 | — | material catalogue (the model has `unit`; no price field found) | Reference data, low sensitivity |
| `event-record.view` | 3 | — | activity feed and event API, **including CRM lead, opportunity and quote events (accepted, rejected, sent) and payment-certificate events** | **Mixed.** Indirectly exposes commercial events |
| `vendor.view` | 4 | — | vendor/supplier directory | Commercially sensitive (procurement) |
| `boq.view` | 6 | — | BOQs and line items (quantities and prices) | **Commercially sensitive** |
| `contract.view` | 12 | — | contracts, cost summary, PDFs, BOQ PDF, contract documents | **Commercially sensitive / management** |
| `crm.view` | 16 | 1 POST (AI summary) | leads, accounts, opportunities, quotes, quote PDFs, price-reference history, CRM reports | **Commercially sensitive (sales pipeline, customer data, pricing)** |

**Runtime confirmation** (from `o18-runtime-probe.json`). The Member is in the same tenant and is not a participant in the project.

| Request | after seed #1 (20) | after seed #2 (31) |
|---|---|---|
| GET operator/crm, opportunity of another user, api/zena/crm/opportunities | denied | **200** |
| GET contracts of a project the Member is not on (API and web) | denied | **200** |
| GET api/zena/vendors, api/zena/boqs | 403 | **200** |
| GET app/my-work, app/time | denied | 200 |
| GET app/time/report (+ CSV), app/time/review | denied | **403** (still denied) |
| POST operator/crm/opportunities/{id}/ai-summary | denied | **denied** (302 RBAC redirect) |

## 4. CRM AI summary and timesheets (Task B.5)
**AI summary: correction to the 80 matrix.**
- The route is `->middleware(['rbac:crm.view', 'rbac:ai.suggest', 'throttle:ai-suggest'])` (`routes/web.php:972`). It needs **`ai.suggest` as well**, which only System Admin holds in every seed state, and it is rate-limited to 10 requests per minute per user and IP.
- The 80 matrix said "no additional authorization found". That statement is **wrong**. It is corrected here rather than in the 80 file, which keeps its original text as evidence.
- The runtime probe shows a 31-grant Member is denied.
- **Residual issues.** The controller does not call `authorize('view', $opportunity)`; it relies on `forTenant()`. It also sends appointment notes and quote data to the AI provider. Both are acceptable while only `ai.suggest` holders can reach it, but any future grant of `ai.suggest` makes this an exposure of the whole tenant's CRM.

**Timesheets: no access to other users' entries.** This was checked in the code and at runtime.
- **Edit and delete.** `TimeEntryService` throws "Chỉ sửa / xoá được giờ của mình." unless `entry.user_id` is the caller and the tenant matches. The probe tried to edit and delete another Member's entry; both were refused, and the entry was unchanged.
- **Start and manual entry.** `canLogOn` limits these to tasks assigned to the caller, or to projects where the caller is a member or the PM.
- **Report and review pages.**
  - Access requires `payroll.manage`, `project.update` or being a PM (`TimeReportService::canView`, `TimeReviewService::isReviewer`). A Member has none of these, and the probe returned 403.
  - Approving entries is limited to projects the reviewer manages, and reviewers cannot approve their own entries unless they hold `payroll.close`.
- **Not yet covered by tests.** There is no regression test for cross-user edit or delete at the HTTP layer. See §8, R-6.

## 5. Recommended least-privilege model (Task B.6)
**Recommendation: Option D, split by sensitivity. Make the grants deterministic on the first run.**

| Code | Project Member | Rationale |
|---|---|---|
| `task.view` | **GRANT** | Members need my-work, today and their own timesheet. Writes already have their own guards. The tenant-wide task list is accepted under O-3 |
| `site_diary.view`, `design-item.view`, `material.read`, `material-receipt.view`, `material.view` | **GRANT** | Day-to-day project and site work. No pricing found |
| `event-record.view` | **WITHHOLD** until the feed is filtered by module permission | The feed carries CRM, quote and payment events. Granting it would let commercial data in through the side |
| `crm.view`, `contract.view`, `boq.view`, `vendor.view` | **WITHHOLD** | Commercially sensitive. The PM role does not hold them either. They belong to sales, procurement or management roles that do not exist yet (O-6) |

- **Resulting Member set:** 20 + 6 = **26 codes, identical on every run.**
- **Prices and contracts for project staff.** If Members need them for their own projects only, that requires membership-scoped policies (deferred by O-3). Do not grant tenant-wide.
- **Alternatives:**
  - Option 1: grant all 11 deterministically (31 codes). This exposes the CRM pipeline, contracts, BOQ prices and vendors to every Member.
  - Option 2: grant none of the 11 (20 codes). Members get 403 on my-work, today and their own timesheet, which repeats the 2026-07-20 UAT failure.
  - Option 3: defer. Not recommended: what a Member can do then depends on how many times each environment was seeded.

## 6. Related finding P-1 (Project Manager)
- The seeded Project Manager role has no `task.view`. A seeded PM therefore cannot open my-work, today, schedule or their own timesheet (`app/time`) unless granted by hand. The existing GAP-112 tests grant `task.view` explicitly.
- On a re-seeded environment, a Member can see the CRM and contracts while a PM cannot.
- **Recommendation:** grant `task.view` and the five operational codes to Project Manager in the same change, or explicitly decide otherwise. This expands permissions, so it needs an explicit Owner decision under O-18.

## 7. Existing environments (Task B.7)
1. **Inventory, read-only, per environment.** Production `app.zena.com.vn`, staging, and any developer database that is shared.
   - Run one SELECT per environment: Project Member and Project Manager grant codes from `role_permissions` joined to `roles` and `permissions`, plus the number of users holding each role.
   - Do not write anything. The seed count is not recorded anywhere; the grant set is the only reliable signal.
   - This needs the Owner's approval for production read access.
2. **Classify each environment:**
   - (a) 20 codes, a fresh first seed;
   - (b) 31 codes, re-seeded;
   - (c) anything else, meaning manual edits.
3. **Converge to the approved set.** Use an explicit and idempotent Artisan command, modeled on `rbac:backfill-user-roles`:
   - a dry-run report by default;
   - `--apply` writes an `audit_logs` row for every grant or revoke;
   - `--rollback` restores from the recorded diff.
   - It is run per environment, only after Owner approval. **It is never run automatically by deploy or by the seeder.**
4. **Before revoking on an environment in state (b).** List the Members who actually used `crm.view`, `contract.view`, `boq.view` or `vendor.view` routes, from access logs if they are available. Then notify those users or business owners, because they will lose pages they can see today.
5. **Hand-granted permissions are wiped on re-seed (S-1).** Until it is fixed, do not re-run `DatabaseSeeder` on production as a "repair" step.

## 8. Implementation and regression criteria (Task B.10). For a separate, governed PR after approval
**Implementation.** Scope: seeders, one Artisan command and tests only.
- I-1. Create every code that a role list references **before** it is granted. One way is to move the Member and PM grants for these codes into a seeder that runs after `ZenaPermissionsSeeder`, or to `firstOrCreate` them in PermissionSeeder. The change in seed order is authorized only by this decision.
- I-2. Replace the silent `whereIn()` with a check that fails loudly when a listed code does not exist.
- I-3. Use the approved Member set (and PM set, per P-1) as a single constant shared by the seeder and the tests.
- I-4. Fixing the `sync()` problem (S-1) is optional and decided separately.
- I-5. Add the convergence command from §7.3.

**Regression tests**, all required:
- R-1. Seeding once, twice and three times gives identical grant sets for System Admin, Project Manager and Project Member.
- R-2. The Project Member grant set equals the approved constant exactly, and the same holds for Project Manager.
- R-3. With 1 or 2 seeds, a Member gets 403 or the RBAC redirect on every route gated by `crm.view`, `contract.view`, `boq.view`, `vendor.view` and `event-record.view`. The test must cover the API and the web routes.
- R-4. A Member can open `app/my-work`, `app/today` and `app/time`, which turns the quarantined `test_D3_project_member_can_open_my_work` green. Its removal from quarantine follows `QUARANTINE.md`.
- R-5. AI summary: a Member, and a Member who holds `crm.view`, is denied. Only an `ai.suggest` holder in the same tenant gets 200, with the AI client mocked.
- R-6. Timesheet: a Member cannot edit or delete another user's entry, and the entry is unchanged. A Member gets 403 on `app/time/report`, the CSV export and the review pages.
- R-7. Convergence command: the dry run writes nothing; `--apply` then `--rollback` restores the exact prior set; and the audit rows are present.
- R-8. Recovery, Feature, Unit, PHPStan and SSOT lint show no new failures, and CI is green, including the MySQL jobs.

## 9. Owner decision requested
Choose one:
- **D. Recommended:** 26-code Member set. Includes the §6 PM `task.view` and operational grants (yes / no).
- **1.** 31 codes.
- **2.** 20 codes.
- **3.** Defer, recorded as accepted risk.

Also approve or reject:
- (i) the convergence approach in §7;
- (ii) read-only production inventory access;
- (iii) whether S-1 and S-2 get their own Work IDs.
