---
work_id: GAP-095
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-095/02-design.md
---

# GAP-095 — Treasury S4b-2 contract-payment traceability: implementation plan

Executes approved Gate 2 Option A (`docs/owner-decisions/GAP-095/02-design.md`); TDD; no migration.

1. **Service** `TreasuryRouteService`: `createContractPaymentRoute` (paid payment of the project, class-1 lock of the
   `contract_payments` row, allocation cap over non-cancelled routes, expected destination wallet, no document,
   duplicate warning against same-amount / same-date funding); legs, leg reversal, cancel and completion branched for
   a route without a document (leg re-checks the payment is paid under the class-1 lock; completion on the expected
   destination); `contractPayments()` reminder list with traced / arrived / remaining and flags. Test:
   `tests/Feature/Treasury/TreasuryContractPaymentRouteTest.php`.
2. **Funding duplicate warning** in `TreasuryPostingService::guardDuplicate` (also used by S4b-1 funding routes):
   a paid contract payment of the project with the same amount and `paid_at` date.
3. **Summary** line in `TreasuryBalanceService::projectSummary`: completed contract-payment routes.
4. **API**: `GET contract-payments`, `POST contract-payments/{payment}/routes` in `TreasuryRouteController` +
   `routes/api_zena.php`; route detail shows the payment. Test: `TreasuryContractPaymentRouteApiTest`.
5. **Web**: "Thanh toán hợp đồng" card with the reminder list and "Truy vết" form on the project Treasury page; the
   route page shows a Case A route; summary line. Test: `TreasuryContractPaymentRouteWebTest`.
6. **Real-MySQL race**: two routes for one payment over its amount; hidden command + method in
   `tests/Feature/Concurrency/TreasuryTransferConcurrencyTest.php`; class-1 lock removal mutation must go red.
7. Verification: Treasury, API, Web, Zena, Architecture, Deployment, Unit suites; PHPStan; php-cs-fixer; SSOT lint;
   route guard; exact-head CI.
