# GAP-010c Reproduction Evidence — NOT REPRODUCED ON CURRENT MAIN

## Baseline

- Main SHA: `1325c0e6a2e10e5f18e97869aa9151dba4de2480`
- Date: 2026-08-10
- Surface: `GET /schedule` → `SchedulePageController::index` → `resources/views/schedule/index.blade.php`

## Reproduction procedure

1. Checkout baseline main at `1325c0e6`.
2. Seed a tenant with tasks whose `start_date`/`end_date` span mixed calendar days.
3. Access `/schedule` as a tenant user.
4. Compare rendered Gantt bar positions/offsets against the stored date values.
5. Repeat with browser timezone set to UTC+/- offsets and with client-side locale changes.

## 8-case matrix

| Case | Timezone setting | Expected shift | Observed shift |
|---|---|---|---|
| 1 | Browser UTC | 0 | 0 |
| 2 | Browser UTC+7 | 0 | 0 |
| 3 | Browser UTC-5 | 0 | 0 |
| 4 | Browser UTC+12 | 0 | 0 |
| 5 | Stored date = month boundary | 0 | 0 |
| 6 | Stored date = year boundary | 0 | 0 |
| 7 | DST transition date | 0 | 0 |
| 8 | Leap-day date | 0 | 0 |

Result: `SHIFTED=0` across all 8 cases.

## Repeatability

All 8 cases were executed twice on the same baseline. Both runs produced identical results: 0 shifts. No flake observed.

## Migration / schema evidence

- `database/migrations/2025_09_15_042450_create_tasks_table.php` defines `start_date` and `end_date` as `date` (not `datetime`).
- No migration in the current tree converts these columns to `datetime`.
- The canonical schema contract for `Task.start_date` / `Task.end_date` is therefore `DATE`.

## Live `/schedule` path evidence

Current `SchedulePageController::index()` (`app/Http/Controllers/Web/SchedulePageController.php:47-93`):
- Extracts date-only values via `substr((string) $value, 0, 10)` before CarbonImmutable parsing.
- Formats output bars via `$start->format('d/m/Y')` and `$end->format('d/m/Y')`.
- No `toDateTimeString()`, no `timezone()` conversion, no JS `Date` object construction in the Blade view.

Current `resources/views/schedule/index.blade.php:111-116`:
- Input values use `substr((string) $task->start_date, 0, 10)` — server-side date truncation only.
- No inline `<script>` timezone math, no `moment.js`, no `luxon`, no `Intl.DateTimeFormat` calls.

## Historical commit `63afc21f`

Commit `63afc21f4598e35b8b2f7252aa37f7b1fc58eb02` (2026-07-08) introduced the date-only normalization path:
> "Gantt normalizes start/end to date-only before offset math, eliminating one-day bar drift on mixed-timezone deployments."

The current controller and view retain this pattern unchanged since that commit.

## Browser / client-side timezone finding

No client-side timezone conversion was found on the `/schedule` surface. The page renders server-computed `d/m/Y` strings directly. Client timezone settings therefore cannot shift the displayed dates because no client-side date construction occurs.

## Explicit conclusion

**GAP-010c NOT REPRODUCED ON CURRENT MAIN**

## Evidence separation

- **Historical bug/candidate:** The original audit described a Gantt timezone-drift symptom. A live candidate page (`/schedule`) was found with a date-truncation pattern consistent with that symptom.
- **Current-main reproduction result:** 8 tested cases, 0 date shifts. The bug is not present on current main.
- **Canonical migration schema:** `tasks.start_date` / `tasks.end_date` are `DATE` columns per migration. No evidence suggests they are stored as `datetime` in the canonical schema.
- **Disposable runtime observations:** The reproduction used a disposable local runtime. Task records, controller behavior, and Blade output were inspected in that runtime.
- **Production DB facts NOT directly verified:** The actual production database schema was not directly queried. This evidence does not claim the production DB schema matches the migration beyond what the migration contract states.
