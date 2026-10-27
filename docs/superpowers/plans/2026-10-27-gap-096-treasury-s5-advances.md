---
work_id: GAP-096
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-096/02-design.md
---

# GAP-096 — Treasury S5 advances and settlements: implementation plan

Executes approved Gate 2 Option A (`docs/owner-decisions/GAP-096/02-design.md`); TDD; no migration, no new permission.

1. **Service** new `TreasuryAdvanceService`: advance draft / update / submit / approve (wallet 0 → document 5, debit +
   `treasury_advances` row) / reject / copy; outstanding (§6.5); settlement request / withdraw / reject in the advance
   document's approval log; settlement approval (cost sources 2 → advance 3 → document 5, outstanding and §6.3 caps,
   `approved_expense/apply` + allocations); settlement removal (`reverse` + 1:1 reverse allocations); advance return
   (wallet 0 → advance 3, credit + `cash_return/apply`); ageing list (30/60/90). Shared allocation / cost-lock /
   approval-log helpers from `TreasuryExpenseService` (visibility only). Test:
   `tests/Feature/Treasury/TreasuryAdvanceServiceTest.php`.
2. **Reversal** in `TreasuryPostingService::reverse()`: `advance` (refused unless settlement net 0) and `advance_return`
   (coupled `cash_return/reverse`), advance locked (class 3) first.
3. **Policy / gates** mapped to existing permissions.
4. **API** `TreasuryAdvanceController` + `routes/api_zena.php` (`api.zena.treasury.advances.*`). Test:
   `TreasuryAdvanceApiTest`.
5. **Web** `TreasuryAdvancePageController`, `routes/web.php`, "Tạm ứng" card on the project page, advance page. Test:
   `TreasuryAdvanceWebTest`.
6. **Concurrency**: hidden command + real-MySQL races (two settlement approvals past outstanding; settlement approval vs
   S3 expense past a cost's incurred amount); mutation runs (remove class-3 / class-2 locks) red.
7. **Verification**: Treasury, Api, Web, Unit, Architecture suites; PHPStan; php-cs-fixer; SSOT lints; route guard;
   Gate 3 packet.
