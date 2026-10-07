---
work_id: GAP-067
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-067/02-design.md
---

# GAP-067 — Treasury S4a reconciliation: implementation plan

Executes approved Gate 2 Option A (`docs/owner-decisions/GAP-067/02-design.md`); TDD; no migration.

1. **Service** `TreasuryReconciliationService`: reconcile (apply), undo one line, undo a
   whole reconciliation. One transaction each; lock class 4 (ledger entries, id ascending)
   then class 5 (documents, id ascending); active-apply check is a locking read; §12.1
   promotion of `direct` documents, §12.2 regression unless `reversed`; route-leg entries
   rejected; type / reference / date rules; undo reason in `audit_logs`.
   Read side: reconciled / unreconciled balance per wallet, unreconciled entries, history.
   Test: `tests/Feature/Treasury/TreasuryReconciliationServiceTest.php`.
2. **Access** `TreasuryPolicy::reconcile` + Gate `treasury.reconcile`.
3. **API** `Api\Treasury\TreasuryReconciliationController` + routes in `routes/api_zena.php`
   (`rbac:treasury.view` / `rbac:treasury.reconcile`). Test: `TreasuryReconciliationApiTest`.
4. **Web** per-wallet reconciled / unreconciled amounts and "Đối soát" link on the project
   page; register status filter; page
   `/operator/projects/{project}/treasury/wallets/{wallet}/reconcile` (plain form, history,
   undo per line / whole with reason; read-only without `treasury.reconcile`).
   Test: `TreasuryReconciliationWebTest`.
5. **Real-MySQL race**: two processes reconcile the same ledger entry; hidden command
   `treasury:concurrency-test-reconcile`, third method in
   `tests/Feature/Concurrency/TreasuryTransferConcurrencyTest.php` (existing job
   `treasury-transfer-concurrency-mysql`); disposable mutation without the class-4 lock and
   the locking read must go red.
6. Verification: Treasury, Architecture, Deployment, governance, Zena, Services suites;
   SSOT lint; orphan routes; exact-head CI.
