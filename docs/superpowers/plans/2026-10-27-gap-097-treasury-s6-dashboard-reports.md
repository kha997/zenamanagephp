---
work_id: GAP-097
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-097/02-design.md
---

# GAP-097 — Treasury S6 dashboard and reports: implementation plan

Executes approved Gate 2 Option A (`docs/owner-decisions/GAP-097/02-design.md`); TDD; read-only; no migration, no new
permission.

1. **Dashboard** new `TreasuryDashboardService`: cards, wallet table, alerts (30 / 90-day fixed thresholds), overview
   rows. Test: `tests/Feature/Treasury/TreasuryDashboardServiceTest.php`.
2. **Register and reports** new `TreasuryReportService`: filtered register query, the ten reports as headers + rows,
   CSV writer with formula escaping. Test: `TreasuryReportServiceTest`.
3. **Timeline** new `TreasuryTimelineService`: month window, events with sentences and links. Test:
   `TreasuryTimelineServiceTest`.
4. **API** `TreasuryReportController` + `routes/api_zena.php` (`api.zena.treasury.reports.*`). Test:
   `TreasuryReportApiTest`.
5. **Web** `TreasuryPageController` (project header, register filters, index overview), new
   `TreasuryReportPageController`, `routes/web.php`, views. Test: `TreasuryReportWebTest`; existing web tests adjusted
   only where markup moves.
6. **Verification**: Treasury, Api, Web, Unit, Architecture suites; PHPStan; php-cs-fixer; SSOT lints; route guard;
   Gate 3 packet.
