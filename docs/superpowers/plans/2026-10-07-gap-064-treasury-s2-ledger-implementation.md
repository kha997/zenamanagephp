---
work_id: GAP-064
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-064/02-design.md
---

# GAP-064 — Treasury S2 ledger engine: implementation plan

Executes approved Gate 2 Option A (`docs/owner-decisions/GAP-064/02-design.md`); TDD.

1. **Migration** `2026_10_07_000000_add_transaction_date_and_reference_to_treasury_financial_documents`
   (+ `classifications.json` entry `expand`, required by `deploy:migrate`); model fillable/casts.
2. **Services** `TreasuryPostingService` (funding, owner contribution, transfer, adjustment,
   reversal, replacement link; immediate posting; posting keys; audit rows),
   `TreasuryBalanceService` (derived balances in integer cents; locking balance read for the
   guard), `TreasuryRuleViolation`, `TreasuryDuplicateSuspected`.
   Test: `tests/Feature/Treasury/TreasuryPostingServiceTest.php`.
3. **Access** `TreasuryPolicy` abilities declare/transfer/transfer-from-wallet/adjust/reverse.
4. **API** `Api\Treasury\TreasuryDocumentController` + routes. Test: `TreasuryLedgerApiTest`.
5. **Web** project Treasury page (balances, forms, register, reverse/replacement) +
   routes. Test: `TreasuryLedgerWebTest`.
6. **Concurrency proof on real MySQL** (design §7): hidden test-support command
   `treasury:concurrency-test-transfer`, `tests/Feature/Concurrency/TreasuryTransferConcurrencyTest.php`,
   `scripts/ci/treasury-transfer-concurrency-mysql`, CI job `treasury-transfer-concurrency-mysql`,
   skip-inventory baseline entry — same pattern as the document-workflow concurrency job.
7. Verification: Treasury, Architecture, Deployment, governance, Zena suites; SSOT lint; exact-head CI.
