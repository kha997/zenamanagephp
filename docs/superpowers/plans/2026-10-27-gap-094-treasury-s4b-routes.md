---
work_id: GAP-094
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-094/02-design.md
---

# GAP-094 — Treasury S4b-1 via-route payments: implementation plan

Executes approved Gate 2 Option A (`docs/owner-decisions/GAP-094/02-design.md`); TDD; no migration.

1. **Service** `TreasuryRouteService`: create route (document `via_route` + `approved`,
   route `planned`), record leg (settled at once, entries sourced from the leg),
   reverse leg, cancel route; completion check after each leg / leg reversal (route
   `completed`, document `posted_unreconciled`, or `posted_reconciled` when §12.1
   already holds). Locks 0 → 4 → 5 → 6. Read side: routes, legs, net per wallet,
   remaining. Test: `tests/Feature/Treasury/TreasuryRouteServiceTest.php`.
2. **Reversal** in `TreasuryPostingService`: accept a `via_route` original whose route is
   `completed` (re-checked under the route lock), posted `direct` with swapped shape.
3. **Reconciliation** in `TreasuryReconciliationService`: accept leg-sourced entries;
   §12.1 `via_route` branch; regression on undo and on leg reversal.
4. **Access**: permission `treasury.manage_routes` (permissions seeder, owner +
   accountant grants, seeder test count 17); `TreasuryPolicy::manageRoutes`,
   `recordRouteLeg`; Gates `treasury.manage-routes`, `treasury.record-route-leg`.
5. **API** `Api\Treasury\TreasuryRouteController` + routes in `routes/api_zena.php`.
   Test: `TreasuryRouteApiTest`.
6. **Web**: "Tuyến tiền" section and "Tạo tuyến" form on the project page; route page
   with legs, net per wallet, "Ghi chặng", "Đảo chặng", "Huỷ tuyến"; register badge.
   Test: `TreasuryRouteWebTest`.
7. **Real-MySQL races**: two processes draining one transit wallet; two "from outside"
   legs over the route amount. Hidden command + methods in
   `tests/Feature/Concurrency/TreasuryTransferConcurrencyTest.php` (existing job);
   lock-removal mutation must go red.
8. Verification: Treasury, Architecture, Deployment, governance, Zena, Services suites;
   PHPStan; php-cs-fixer; SSOT lint; orphan routes; exact-head CI.
