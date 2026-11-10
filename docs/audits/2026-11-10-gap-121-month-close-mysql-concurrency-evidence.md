# GAP-121 — Automated real-MySQL concurrency tests for the month close: discovery evidence

Date: 2026-11-10. Base: `main` at `503f92edbdc0e8da334ffc48ed22b17727d78947` (after GAP-120).

## 1. What the month close protects with locks today

| Path | Lock |
| --- | --- |
| `PayrollService::close` (GAP-109 / 113) | Locks the payroll month row `FOR UPDATE` (`lockDraft`). Refuses if any time entry of the month is still running or submitted. Computes lines, writes the labour-cost allocation, marks the month closed. |
| `TimeEntryService::isLocked` / `requireOpenMonth` (GAP-113 amendment 1) | Reads the payroll month row with a **shared lock** inside every time-entry write and every review (`TimeReviewService::approve` / reject). It waits for a running close and then sees "closed". |
| `PayrollService::reopen` (GAP-114) | Locks the payroll row, reads `overhead_periods` with a shared lock, and refuses while overhead is closed. |
| `OverheadService::close` (GAP-114) | Locks the payroll month row first, then the overhead row (same order as reopen). Requires payroll closed. Replaces the allocations and marks the month closed. |

## 2. How it was verified

- **GAP-113 Gate 3:** two concurrent MySQL 8 sessions, run by hand. The reading session waited for the closing session
  and then saw "closed".
- **Automated tests** run payroll / time / overhead rules on MySQL, but **one connection at a time**. No automated test
  races two real connections.

**What CI already does for other modules:** four "… Concurrency (real MySQL)" jobs in
`.github/workflows/automated-testing.yml`:
- RFI escalation;
- GAP-048 service line;
- document workflow;
- Treasury transfer.

**How those jobs work**, for example `tests/Feature/Concurrency/TreasuryTransferConcurrencyTest.php` with
`scripts/ci/treasury-transfer-concurrency-mysql`:
- **Separate processes.** The test starts a hidden test-support Artisan command (`app/Console/Commands/Testing/*`) in a
  separate OS process. The command keeps its transaction open (`--hold`), and a second process races it.
- **Skip guard.** The test skips without MySQL. The CI script fails if the test skips although MySQL is reachable.

## 3. Races worth proving (candidates for Gate 2)

1. **Payroll close vs a time-entry write in the same month** (approve, manual add or edit while the close holds the
   month). The write must wait, then be refused. The allocation must match the approved entries exactly.
2. **Two payroll closes of the same month at once.** Exactly one succeeds. Allocations are written once.
3. **Overhead close vs payroll reopen of the same month.** Exactly one succeeds. The state "overhead closed, payroll
   open" must never appear.
4. **Two overhead closes at once.** Exactly one succeeds. One set of allocations.

## 4. Open points for Gate 2

- Which of the four races to cover.
- One CI job, or one per race.
- Whether the test-support commands live with the existing ones (`app/Console/Commands/Testing`).

## 5. Exclusions

- Changing the close rules or locks. If a test exposes a real race, the fix is reported and asked for, not slipped in.
- Treasury.
- Deploy.
