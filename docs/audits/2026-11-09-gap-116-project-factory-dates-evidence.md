# GAP-116 — Flaky ProjectApiTest (project factory dates): discovery evidence

Date: 2026-11-09. Base: `main` at `9a23ac6abea7a27f8890bc3fbcfa6407b4117813` (after GAP-115).

## 1. Symptom

CI "Feature Tests" on PR #405 (head `13d8d199`) failed once with one test:
`Tests\Feature\ProjectApiTest > can update project` → 422 `end_date: Ngày kết thúc phải sau ngày bắt đầu`. The same job
was green on the next heads. The test passes locally. Nothing in GAP-115 touched projects.

## 2. Cause

`database/factories/ProjectFactory.php`, default `definition()`:
- `start_date` is drawn from `dateTimeBetween('-1 month', '+1 month')`;
- `end_date` is drawn from `dateTimeBetween('+1 month', '+6 months')`.

Both ranges meet at "+1 month", so both values can fall on the same calendar day. `test_can_update_project` re-submits
both dates as `Y-m-d`, and `ProjectFormRequest` / `StoreProjectRequest` require `end_date` `after:start_date`. The
request is rejected whenever the two days are equal. The chance per run is small but not zero.

Same family, not yet failing anywhere:
- `completed()` sets `end_date` in the last month;
- `overdue()` sets `end_date` between one month ago and yesterday.

In both states `start_date` keeps the default range (up to +1 month). The end can therefore come **before** the start.
No test in `tests/` uses these states today (searched).

## 3. Open points for Gate 2

- Fix only the default ranges, or also make `completed()` / `overdue()` keep start before end.
- Whether to add a guard test that builds many projects and asserts start < end, to stop it coming back.

## 4. Exclusions

Validation rules, the controller, the API test's assertions, other factories, deploy.
