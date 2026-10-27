---
work_id: GAP-066
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-066/02-design.md
---

# GAP-066 — Treasury S3 expenses & approvals: implementation plan

Executes approved Gate 2 Option A (`docs/owner-decisions/GAP-066/02-design.md`); TDD.

1. **Migration** `2026_10_07_100000_add_expense_plan_to_treasury_financial_documents`
   (+ `classifications.json` `expand`); model cast.
2. **Service** `TreasuryExpenseService`: draft/update/submit/approve(=post)/reject/copy,
   payables, incurred + net allocation (locking read at approval); lock order
   0 → 2 → 4 → 5; atomic `ContractExpense` creation; approval log + §10.1 gate.
   `TreasuryPostingService`: expense reversal with §2.2b allocation coupling
   (class 2 before 5), public `lockWallet`/`requireBalance`/`postEntries`/`audit`.
   `TreasuryBalanceService`: expenses and self-approved totals.
   Test: `tests/Feature/Treasury/TreasuryExpenseServiceTest.php`.
3. **Access** `TreasuryPolicy` create/submit/approve expense abilities.
4. **API** `Api\Treasury\TreasuryExpenseController` + routes. Test: `TreasuryExpenseApiTest`.
5. **Web** expense form (≤ 3 cost lines or new contract expense), pending queue,
   payables table, "Tự duyệt" badge, register limited to ledger facts.
   Test: `TreasuryExpenseWebTest`.
6. **Real-MySQL race** on one cost's cap from two different wallets: hidden
   command `treasury:concurrency-test-approve-expense`, second method in
   `tests/Feature/Concurrency/TreasuryTransferConcurrencyTest.php` (existing job).
7. Verification: Treasury, Architecture, Deployment, governance, Zena, Services
   suites; SSOT lint; orphan routes; exact-head CI; lock-removal mutation.
