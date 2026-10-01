---
work_id: GAP-045
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-10-01-gap-045-perf-timing-evidence.md
  plan: null
  branch: docs/GAP-045-perf-timing-gate1
  pr: https://github.com/kha997/zenamanagephp/pull/332
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: null
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-02T00:02:09+07:00"
  updated_at: "2026-10-02T00:02:09+07:00"
generated_by: agent
---

# GAP-045 — Dashboard performance timing assertions: Gate 2 design

## Owner Summary

Gate 1 cho thấy hai kiểm tra thời gian (tải cảnh báo ≤450ms, đánh dấu đã đọc
100 cảnh báo ≤1000ms) đỏ/xanh **theo loại máy CI**, không theo mã nguồn. Đề xuất
**Phương án A**: hai kiểm tra này chuyển sang chặn bằng tiêu chí **ổn định** (số
truy vấn CSDL, kết quả đúng) — bắt được lỗi hiệu năng thật như N+1; thời gian vẫn
**được đo và báo cáo** (cảnh báo vàng trên CI nếu vượt 450/1000ms) nhưng không
làm đỏ build. **Không đổi** con số ngưỡng nào. Các kiểm tra thời gian khác giữ
nguyên. Việc giới hạn danh sách cảnh báo (Phương án C) là quyết định sản phẩm,
để riêng nếu Owner muốn.

## Comparison

| Option | Content | Verdict |
|---|---|---|
| **A. Deterministic gate, timings reported** | Gate on query counts + correctness; keep 450/1000ms as *reported* budgets with a CI warning annotation | **Recommended** — removes the runner lottery, still catches N+1/regressions in query shape, numbers unchanged |
| B. Hardware-normalised ratio | Time a calibration workload per job, assert ratio | Calibration itself is noisy on shared runners; new tuning surface; no evidence it would be stable |
| C. Bound alerts endpoint | Newest N + pagination | Product/API contract change for non-UI clients; Owner must choose N; does not fix mark-read (100 HTTP requests); can follow as its own work |
| D. Raise thresholds | e.g. 600ms / 1300ms | Owner direction: no threshold change; still hardware-coupled (EPYC 7763 already ~996ms) |

## Design: Option A (exact allowlist)

Only `tests/Performance/DashboardPerformanceTest.php`, only these two methods,
plus one private helper in the same class:

1. **`it_can_load_alerts_with_large_dataset_quickly`**
   - Unchanged: warm-up request, 3 measured samples, median, `$latencyBudgetMs =
     $isCi ? 450 : 300`, `$queryBudget = 20`, status 200 assertions.
   - Kept as gate: `assertLessThanOrEqual($queryBudget, $maxQueryCount)`
     (measured 4–5 in all 10 CI jobs and locally).
   - Added gate: response contains every seeded alert of the user (count equals
     the user's alerts in the database) — proves the measured call did the full
     work.
   - Changed: the median-latency `assertLessThan` becomes
     `reportTimingBudget('alerts median', $medianMs, $latencyBudgetMs)`.
2. **`it_can_mark_alerts_as_read_quickly`**
   - Unchanged: 100 sequential `PUT /api/v1/dashboard/alerts/{id}/read`, each
     asserted 200.
   - Added gate: exactly 100 alerts were selected; afterwards all 100 are
     `is_read = true` in the database.
   - Added gate: total queries across the 100 requests ≤ **8 per request**
     (`800`). Measured locally: 502 queries / 100 requests (~5 each); an N+1
     on the read path would exceed it.
   - Changed: the 1000ms `assertLessThan` becomes
     `reportTimingBudget('mark 100 alerts read', $executionTime, 1000)`.
3. **`reportTimingBudget(string $label, float $ms, float $budgetMs): void`**
   (private): always prints `<label>: <ms>ms (budget <budget>ms)`; when over
   budget and `GITHUB_ACTIONS=true` prints a
   `::warning title=Perf budget (GAP-045)::…` workflow command; when
   `GITHUB_STEP_SUMMARY` is set appends one table row. Never fails the test.

**Not changed:** every other timing assertion in the Performance suite (no
evidence of flakiness: they passed in every recorded run, e.g. 17/19 at
`9ad1877c` where only these two failed), all thresholds, workflows,
application code, the alerts API contract.

## Verification required at Gate 3

- Local: the two tests pass; mutation proof — forcing an N+1 in the
  mark-read path (temporary) makes the query gate fail, then reverted.
- CI on the implementation head green; then GAP-041 (#316) exact-head re-run
  with this change merged, on enough runs to include an AMD EPYC 7763 runner,
  showing the warning annotation instead of a failure.

## Risk

Wall-clock regressions in these two endpoints no longer fail CI by
themselves; they surface as warnings + job-summary rows. Query-shape
regressions (the common cause) still fail.

## Out of scope

Option C (alerts pagination), other timing assertions, GAP-041 release.
