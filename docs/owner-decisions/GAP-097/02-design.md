---
work_id: GAP-097
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-27-gap-097-treasury-s6-dashboard-reports-readiness.md
  plan: docs/superpowers/plans/2026-10-27-gap-097-treasury-s6-dashboard-reports.md
  branch: docs/GAP-097-treasury-s6-dashboard-reports
  pr: https://github.com/kha997/zenamanagephp/pull/376
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-27T18:17:26+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-27, verbatim: 'APPROVE GAP-097 Gate 2 Option A'. Reviewed design head: 73899f109cf9e390660629376bb57dbf606b4693. Approves Option A and its exact allowlist (read-only dashboard cards, wallet table and fixed-threshold alerts, filtered and paginated register with CSV, monthly financial timeline, project report page with ten reports and CSV, all-projects overview; view with treasury.view and project scope, CSV with treasury.export); no schema change, no new permission, no change to posting, approval, reconciliation, route or advance behaviour; not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-27T18:15:21+07:00"
  updated_at: "2026-10-27T18:17:26+07:00"
generated_by: agent
---

# GAP-097 — Treasury S6 dashboard and reports: Gate 2 design

## OWNER GATE 2: APPROVED — OPTION A

Owner approved Option A in-session on 2026-10-27 against reviewed design head
`73899f109cf9e390660629376bb57dbf606b4693`. This authorizes only the bounded implementation defined by this packet; it
does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

Thiết kế theo spec §10.1, §10.2, §10.7, §11 (phần đã có dữ liệu) và bốn câu trả lời của Owner ở Gate 1. **Chỉ đọc:**
không đổi cách ghi sổ, **không đổi cơ sở dữ liệu**. Cách dùng:

- **Trang ngân quỹ dự án** có thêm phần đầu trang:
  - **Thẻ tổng:** nhà đầu tư đã trả, tiền về qua trung gian, góp vốn chủ, chi phí đã ghi sổ, tiền đang giữ, tiền nhận
    chưa đối soát, khoản chờ duyệt, tạm ứng còn nợ.
  - **Bảng ví:** người giữ, số dư, phần chưa đối soát, tạm ứng còn nợ chi từ ví đó, lần phát sinh cuối.
  - **Cảnh báo:** tiền nhận chưa đối soát quá 30 ngày; chi phí / tạm ứng chờ duyệt; tạm ứng còn nợ trên 90 ngày; ví
    âm; tuyến trung gian chưa về đủ; đề nghị quyết toán chờ duyệt.
- **Sổ giao dịch có bộ lọc** (từ ngày–đến ngày, loại, trạng thái, ví, đối tác, người lập, người duyệt, chỉ tự duyệt,
  đã / chưa đối soát), phân trang thay cho "100 dòng mới nhất"; xuất CSV đúng bộ lọc.
- **Dòng thời gian tài chính:** từng sự kiện theo thời gian ("Nhà đầu tư A trả 100.000.000 ₫ vào ví X", "X chuyển
  50.000.000 ₫ cho Y", "Y tạm ứng 20.000.000 ₫ cho Z", "Z trả 15.000.000 ₫ cho tổ đội D (đã duyệt)"), có link tới
  chứng từ / tuyến / tạm ứng.
- **Trang "Báo cáo ngân quỹ" của dự án** với 10 báo cáo, mỗi báo cáo xem trên trang và **xuất CSV**: dòng tiền theo
  tháng, số dư ví, tiền theo người giữ, tuổi nợ tạm ứng, chi phí theo loại, chi phí theo đối tác, chi phí tự duyệt,
  giao dịch chưa đối soát, tiền đang qua trung gian, đảo / điều chỉnh.
- **Trang Ngân quỹ chung:** bảng tổng các dự án (tiền đang giữ, chi phí, chưa đối soát, tạm ứng còn nợ, số cảnh báo).

**Điểm Owner cần quyết: ai xem và ai xuất báo cáo.** Đề xuất **Phương án A**: ai xem được ngân quỹ dự án thì xem
được dashboard, sổ giao dịch, dòng thời gian và báo cáo; **chỉ người có quyền "Xuất" (chủ DN, kế toán) mới tải CSV**
— dùng quyền `treasury.export` đã có nhưng chưa dùng. Không thêm quyền mới.

## Options

| Option | Content | Verdict |
|---|---|---|
| **A. View for project members, CSV for owner / accountant** | View: `treasury.view` + project scope; CSV: `treasury.export` (owner, accountant) | **Recommended** — PM / kỹ sư xem được số liệu dự án mình, nhưng không mang số liệu ra ngoài; dùng đúng quyền đã gieo |
| B. Everyone who views may export | CSV with `treasury.view` | Kỹ sư / người xem tải được toàn bộ số liệu; quyền `treasury.export` vẫn không dùng |
| C. Reports for owner / accountant only | Report page and CSV need `treasury.export`; dashboard, register and timeline stay `treasury.view` | PM không xem được báo cáo dự án mình |

## Design: Option A (exact allowlist)

### 1. Figures (all derived, read-only)

- **Cards:** existing `projectSummary` figures (investor funding, owner contribution, expenses, held total,
  contract-payment arrivals) plus: funding received via completed routes; unreconciled funding = posted_unreconciled
  funding + owner contribution; pending approvals = count and amount of submitted expenses and advances; outstanding
  advances = S5 ageing total.
- **Wallet table:** balance and reconciled / unreconciled from the existing services; outstanding advances = S5
  outstanding of posted advances paid from the wallet; last activity = latest ledger entry `posted_at`.
- **Alerts (fixed thresholds, Owner answer 3):** funding / owner contribution posted_unreconciled for more than 30 days
  since `transaction_date`; submitted expenses / advances; open settlement requests; advances in the `over_90` bucket;
  wallets with a negative balance; routes planned or partial. Each alert names its count and links to the place to
  act. (Duplicate suspicion has no stored flag and is not an alert.)
- **Register:** same rows as today (ledger documents: posted, reconciled, reversed) with filters date from / to
  (`transaction_date`), type, status, wallet (source or destination), party (source or destination), creator,
  approver, self-approved only (approval log `approval_mode`), reconciled / unreconciled; 50 per page.
- **Timeline:** posted documents (including reversals), route legs and advance settlement events in time order,
  oldest first within a chosen month (default: the current month, with previous / next), one sentence each.
- **Reports (Owner answer 1):** 1 cashflow by month (funding, owner contribution, contract payments arrived, expenses,
  advances paid, advance returns, adjustments ±, reversals netted into their original's month; internal transfers
  excluded); 2 wallet balances; 3 money held per custodian (wallet balances + outstanding advances per party); 4
  advance ageing (S5); 5 expenses by category (net cost allocations of expenses and settlements: contract-expense
  category or "vật tư"); 6 expenses by party (posted expenses by payee; settled advances by holder, marked "qua tạm
  ứng"); 7 self-approved expenses, advances and settlements; 8 unreconciled ledger entries with age; 9 routes in
  transit (paid, held per wallet, arrived, remaining); 10 reversals and adjustments with reason and original.
- **CSV (Owner answer 2):** UTF-8 with BOM, comma-separated, formula-injection escaping as the existing
  report export; one file per report and the filtered register.
- **Overview (Owner answer 4):** one row per project the user may see (`treasury.all_projects` or membership, as the
  index today): held total, expenses, unreconciled funding, outstanding advances, alert count.

### 2. Who

View dashboard, register, timeline, report page: `treasury.view` with the existing project scope. CSV:
`treasury.export`. Overview: the index's existing scope. No new permission, no seeder change.

### 3. API and web

API (`api.zena.treasury.reports.*`): `GET projects/{project}/treasury/dashboard`, `GET .../register` (filters),
`GET .../timeline?month=`, `GET .../reports/{report}`, `GET treasury/overview`. Web: project page header (cards,
wallet table, alerts), register filters + pagination + "Xuất CSV", timeline page, "Báo cáo ngân quỹ" page with a
report selector and "Xuất CSV", overview table on the Treasury index.

### 4. Files (allowlist)

New `TreasuryDashboardService`, `TreasuryReportService` (reports, register query, CSV rows), `TreasuryTimelineService`;
`TreasuryPageController` (project page data, register filters, index overview) and the treasury views; new
`Web\Treasury\TreasuryReportPageController`, new `Api\Treasury\TreasuryReportController`, `routes/web.php`,
`routes/api_zena.php`; existing Treasury web tests only where the register's markup or the index changes (no assertion
removed); new tests; governed plan; this packet and 03-release.

**Not changed:** schema, posting / approval / reconciliation / route / advance services' behaviour, permissions and
seeders, the global Reports page.

### 5. Tests (TDD)

Every card and report total equals the ledger / service figure it restates (fixtures across S2–S5 including
reversals, via-route funding, contract-payment traces, advances and returns); internal transfers never change
cashflow totals; alerts at the 30 / 90-day edges (29 vs 31 days; 90 vs 91); register filters each narrow correctly
and combine; self-approved filter; timeline sentences and order; CSV headers, rows, escaping; tenant and project
isolation (another project's or tenant's data never appears); permissions (engineer views but cannot export;
overview scope); API + web.

## Verification required at Gate 3

All Treasury tests green locally and exact-head CI; PHPStan, php-cs-fixer, SSOT lint clean.

## Decision Needed

Owner chọn: `APPROVE GAP-097 Gate 2 Option A` (khuyến nghị) / Option B / Option C / Request changes / Decline.

## What the owner is NOT being asked to decide

Chưa duyệt Gate 3, merge hay phát hành; không đổi lược đồ hay quyền; không làm chứng từ đính kèm, BOQ, chuỗi quỹ.
