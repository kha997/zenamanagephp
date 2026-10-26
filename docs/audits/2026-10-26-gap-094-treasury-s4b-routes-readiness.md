# GAP-094 — Treasury S4b via-route payments: Gate-1 evidence

**Date:** 2026-10-26 (+07:00)

**Canonical base:** `740b84500a805805bea4f9a81758ea6ea2691a30`

**Branch:** `docs/GAP-094-treasury-s4b-routes`

**Scope:** Read-only analysis for slice S4b of Issue #244. No code or schema change.

## 1. Where S4b comes from

GAP-067 (S4a, released) split the planned S4 into S4a reconciliation and
S4b "via-route payments + ContractPayment traceability (later Work ID)"
(`docs/audits/2026-10-08-gap-067-treasury-s4a-reconciliation-readiness.md`
§1). The Owner answered S4b's business questions on 2026-10-08; they are
recorded there in §6 and authorize nothing on their own:

1. Intermediaries (company account A, courier C) are **transit wallets inside
   the project** (e.g. "TK công ty – trung chuyển", custodian the
   accountant); no company-level wallets.
2. Route legs: X and the accountant create routes and record any leg; PM/Site
   Engineer record only legs leaving or entering a wallet they hold (same
   rule as S2 transfers).
3. ContractPayment traceability is **optional**, with a reminder list of paid
   contract payments not fully traced; nothing is blocked on the contract
   side; an amount traced this way must not also be declared as `funding`.

## 2. What exists on main

- **Schema (GAP-037 v17 §4, GAP-038):** `treasury_payment_routes`
  (`total_allocated_amount`, `status` `planned|partial|completed|cancelled`,
  exactly one of `linked_financial_document_id` / `linked_contract_payment_id`,
  `expected_destination_wallet_id` set exactly when linked to a contract
  payment) and `treasury_payment_route_legs` (`sequence_no`,
  `from_wallet_id` nullable, `to_wallet_id`, `amount`, `status`
  `in_transit|settled|reversed`, `occurred_at`). Ledger entries already carry
  `source_payment_route_leg_id`; fund-chain members already reference routes.
  Models: `app/Models/Treasury/TreasuryPaymentRoute.php`,
  `TreasuryPaymentRouteLeg.php`.
- **Wallet type for intermediaries:** `TreasurySetupService::WALLET_TYPES`
  already has `intermediary_control` ("Tài khoản bên trung gian").
- **No runtime for routes:** no service, controller, API or web route creates
  a route or leg. Every posting writes `posting_path = direct`
  (`TreasuryPostingService.php:327`, `TreasuryExpenseService.php:160`);
  reversal refuses non-`direct` documents (`TreasuryPostingService.php:198`);
  reconciliation rejects route-leg entries
  (`TreasuryReconciliationService.php:591`).
- **ContractPayment:** `app/Models/ContractPayment.php` (`status`
  `planned|paid|overdue`, `paid_at`). No treasury code references it.

## 3. Binding rules (GAP-037 v17, approved)

- **§4.1 eligibility:** `via_route` only for a document whose destination is a
  wallet and has no source wallet (`funding`, `owner_contribution`,
  `advance_return`, increase `adjustment`), or a wallet-to-wallet
  `internal_transfer`. `expense` and `advance` are always `direct`.
- **§4.1 Case B:** a route linked to a document carries
  `total_allocated_amount = document.amount`, frozen at attachment.
- **§4.1 Case A:** routes linked to a contract payment satisfy
  `SUM(total_allocated_amount) <= ContractPayment.amount`.
- **§4.2 custody:** under the route lock, a wallet-backed leg is limited by
  the wallet balance; an external-entry leg (`from_wallet_id` NULL) by the
  route's remaining amount; leg and ledger entries are written together.
- **§4.2a:** a Case A leg may settle only when the contract payment is `paid`
  with `paid_at` set (lock `contract_payments` before the route).
- **§4.2b:** a leg reversal and the reversal of its ledger entries are one
  atomic write.
- **§4.3 completion:** every leg settled or reversed, the destination (Case A:
  expected destination wallet) holds the full amount, intermediaries net zero.
- **§5a:** a wallet-to-wallet leg posts a debit/credit pair; an external-entry
  leg posts a lone credit.
- **§2.2a / §2.2e:** reversal of a `via_route` document completes only when the
  original route is fully completed, re-verified at completion.
- **§11:** fixed global lock order, routes locked lower-id first.
- **§12.1:** a `via_route` document becomes "đã đối soát" when its route is
  completed and every leg-sourced entry has an active `apply`.

## 4. Size of the slice

S4b touches posting, reversal, reconciliation, permissions, a new API and
web page, and the `contract_payments` lock order. GAP-037 v17 already fixes
every rule above, so S4b needs no schema change, but it is larger than S4a.
The packet therefore offers a split (see Gate-1 packet).

## 5. Explicit exclusions

Advances (S5) including the `advance_return` via-route branch (§7.6);
dashboard/reports (S6); bank-statement import; period lock; any change to
`ContractPayment` or the contract pages; schema changes; reopening approved
design; deployment.

## 6. Risks

- Lock order: a missed `contract_payments`/route lock could let two legs
  overdraw a wallet or over-allocate a contract payment; mitigated by the
  real-MySQL concurrency jobs used for S2–S4a.
- Double counting: money traced to a contract payment and also declared as
  `funding` would be counted twice; S4b must enforce the Owner's rule 3.
