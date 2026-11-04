# GAP-108 — Treasury configurable thresholds and per-advance deadline: discovery evidence

Date: 2026-11-04. Base: `main` at `08788500cae7107b878f5af061b10f646f1bd556`.

Sources read:
- The canonical Treasury spec, `docs/superpowers/specs/2026-08-07-project-treasury-cashflow-design.md` (branch
  `docs/project-treasury-spec`).
- The Owner answers recorded for GAP-097 (S6).
- The code on `main`.

Method: static reading; no production data was used.

## 1. What the canonical spec asks

- Alert "unreconciled funding older than **configured** threshold" (§10.1).
- Advance fields `due_date` (nullable) and status `overdue` (§7.x), with the state rule
  "`open|partially_settled + due date passed -> overdue`".
- The advance ageing report shows "overdue days" (§10.6). Alerts for overdue advances (goal 18).

## 2. What exists

### Thresholds

GAP-097 Owner answer 3 fixed the thresholds in code:
- `TreasuryDashboardService::UNRECONCILED_FUNDING_DAYS = 30`: funding left unreconciled for more than 30 days raises
  the alert.
- An advance is overdue when it falls in the ageing bucket `over_90`. The buckets in
  `TreasuryAdvanceService::summaryRow` are `<30`, `30–60`, `60–90` and `>90` days since posting.

There is no setting table. GAP-106 introduced the pattern of keeping tenant settings in `tenants.settings` JSON (the
evidence switch), changed by owner-level users and audited.

### Advances

- A `treasury_advances` row is created when the advance document is approved and posted. It has `tenant_id`,
  `project_id`, `financial_party_id`, `originating_financial_document_id` and `amount`, but **no due date**.
- The request is a Treasury document (draft → submitted → approved/posted) and has no due-date field either.
- The table carries native CHECK constraints (GAP-038). A nullable `date` column can be added with
  `ALTER TABLE ADD COLUMN` on SQLite without a table rebuild, so the existing constraints are kept (GAP-105 precedent).
- The ageing rows (dashboard wallet table, advance ageing report, overview) and the S6 alert `overdue_advances` all
  build on `summaryRow`.

## 3. Open points for Gate 2

1. **Which thresholds become configurable:**
   - days before unreconciled funding alerts (30);
   - days before an advance without a due date counts as overdue (90);
   - possibly the ageing bucket edges, which today are fixed at 30 / 60 / 90.
2. **Scope:** per tenant (`tenants.settings`, as in GAP-106) or per project.
3. **Who changes them:** owner-level users, with audit.
4. **Advance due date:**
   - Where it lives: on the advance document while it is a draft, copied to the advance row at posting; or only on the
     advance row.
   - Whether it is optional or required.
   - Whether an accountant may change it after posting (with reason and audit).
5. **Overdue rule:**
   - an advance with a due date is overdue once the due date has passed and money is still outstanding;
   - an advance without a due date falls back to the configurable day threshold;
   - show "days overdue".

## 4. Constraints

- Ledger amounts and statuses are unchanged; this work only changes how advances are classified for display and alerts.
- Tenant and project isolation; Treasury policy on every page.
- Keep the GAP-038 CHECK constraints when adding any column on SQLite.
- MySQL parity.

## 5. Out of scope

- Automatic reminders or notifications (email / Slack / Zalo).
- Penalties or interest on overdue advances.
- Changing the S6 report set beyond the overdue columns.
- Deployment.
