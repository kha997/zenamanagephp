# GAP-107 — Treasury period lock and bank statement import: discovery evidence

Date: 2026-11-04. Base: `main` at `249c8b7194400552aae88a65055c14d1df200d3c`.

Sources read:
- the canonical Treasury spec `docs/superpowers/specs/2026-08-07-project-treasury-cashflow-design.md`, read on branch
  `docs/project-treasury-spec`;
- the GAP-063 permission design;
- the GAP-097 exclusions;
- the code on `main`.

Method: static reading of the code and documents only; no production data was used.

## 1. What the canonical spec says

- **Period lock:** the spec lists the permission `treasury.manage_period_lock` and gives no other rule. GAP-063 seeded
  this permission for the owner role only, and nothing uses it yet. The GAP-097 exclusion list names "period lock".
- **Bank statement:** "bank statement" appears as an evidence type (§7.12) and as a reconciliation type (§7.11,
  `bank`).
- **Out of scope by the spec itself:** non-goal 4 is "Direct bank API integrations". An import therefore means the user
  uploads a file the bank produced; the system does not connect to the bank.

## 2. What exists

### Posting entry points (each would need a lock check)

| Service | Method | Date used |
|---|---|---|
| `TreasuryPostingService` | `declareFunding` (funding / owner contribution) | `transaction_date` |
| | `transfer` | `transaction_date` |
| | `adjust` | `transaction_date` |
| | `reverse` | the reversal's own `transaction_date` |
| `TreasuryExpenseService` | `approve` (posts the expense) | `transaction_date` |
| `TreasuryAdvanceService` | `approve` (posts the advance) | `transaction_date` |
| | `recordReturn` | `transaction_date` |
| | `approveSettlementRequest`, `removeSettlement` | settlement date |
| `TreasuryRouteService` | `recordLeg` / leg reversal | leg `occurred_at` |
| `TreasuryReconciliationService` | `reconcile` / `undoEntry` / `undoReconciliation` | `reconciled_at` |

There is no lock table or setting today. Posted documents are already immutable, so a lock is about **new** postings
or reversals dated inside a closed period.

### Reconciliation (S4a, GAP-067)

`reconcile(project, actor, wallet, {reconciliation_type, external_reference, reconciled_at, ledger_entry_ids})`
confirms chosen ledger entries of one wallet against independent evidence. Matching is fully manual: the user ticks
entries. Undo is possible per entry or per reconciliation, with a reason.

The reconciliation types include `bank`, and a bank reconciliation needs a reference.

### Files and parsing

- The CSV pattern exists (S6 export).
- No import code exists in Treasury.
- Excel parsing: `maatwebsite/excel` / PhpSpreadsheet availability must be checked at Gate 2. CSV is the safe baseline,
  because every Vietnamese bank's internet banking exports CSV or XLSX.

## 3. Open points for Gate 2

### Period lock

1. **Scope:** a lock per project, or per tenant (all projects).
2. **Rule:** lock "through date D" for all postings dated on or before D; decide whether reconciliation is also blocked
   in a locked period.
3. **Who:** `treasury.manage_period_lock` (already seeded for the owner).
   - Unlock: allowed or not.
   - Every lock / unlock needs a reason and an audit record.
4. **Storage:** a new table (history of locks) or a field on the project / tenant settings.

### Bank import

1. **Format:**
   - CSV with a column-mapping step;
   - or fixed templates for named banks (Vietcombank, Techcombank, BIDV, …);
   - or both.

   XLSX support depends on the library check.
2. **What happens to the lines:**
   - only suggest matches to unreconciled ledger entries of a bank wallet (same amount and direction, date within
     N days);
   - the user confirms;
   - confirming calls the existing `reconcile` with type `bank` and the statement reference.

   Lines that do not match are shown, not posted.
3. **Storage of imported lines:** a table (keeps unmatched lines and the source file) or transient (parse → match →
   reconcile, nothing kept).
4. **Duplicates:** importing the same statement twice; detect by file hash or by line fingerprint.
5. **Never auto-post:** an unmatched bank line must not create funding or expense documents automatically, because that
   would bypass approval.

## 4. Constraints

- Tenant and project isolation.
- Treasury policy on every page.
- Posted documents stay immutable.
- MySQL and SQLite parity for any migration.
- Keep the GAP-038 CHECK constraints intact.

## 5. Out of scope

- Direct bank API / open banking.
- OCR of PDF statements.
- Auto-creating documents from bank lines.
- Thresholds and advance deadlines (separate Work ID).
- Accounting period closing for the general ledger (there is none).
- Deployment.
