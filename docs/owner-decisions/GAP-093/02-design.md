---
work_id: GAP-093
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-10-23-gap-093-nightly-matrix-evidence.md
  plan: null
  branch: docs/GAP-093-nightly-server
  pr: https://github.com/kha997/zenamanagephp/pull/366
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-23T15:50:33+07:00"
  updated_at: "2026-10-23T15:50:33+07:00"
generated_by: agent
---

# GAP-093 — Nightly Test Matrix: Gate 2 design

## OWNER GATE 2: AWAITING OWNER

## Owner Summary

Mình chạy thử nightly trên máy, dùng file env tạo từ `.env.example` và Redis thật:

- **Bước test nhanh** (`test:fast`) chạy lại gần như toàn bộ bộ test mà CI đã chạy trên mọi PR:
  - Kết quả: 2.855 test, có **117 lỗi** và **35 test không đạt**.
  - **117 lỗi** cùng một kiểu, đăng nhập API trả "Too many requests". Nightly đặt `CACHE_DRIVER=redis` cho mọi test,
    nên giới hạn số lần đăng nhập dồn qua các test.
  - **35 test không đạt** chưa rõ nguyên nhân.
  - Vì script dừng ở bước hỏng đầu tiên, các nhóm test riêng của nightly không bao giờ được chạy.
- **Bốn nhóm test riêng của nightly** (slow, load, stress, redis): chạy riêng thì **đều qua**. Cache trong bộ nhớ cho 3 nhóm
  đầu, Redis cho nhóm redis. Các nhóm này nhỏ: slow 1 test, stress 28 (20 bỏ qua), redis 1, load chưa có test nào.
- **Không test nào trong 4 nhóm cần server app.** Bước `artisan serve` chỉ dùng để in dòng "app có chạy không" vào báo cáo.
  Bước websocket không chạy được vì thiếu thư viện Ratchet.

**Phương án A (khuyến nghị): nightly chỉ chạy phần CI chưa chạy.**
- Workflow tạo file env từ `.env.example` thay cho `.env.testing` không tồn tại.
- Mặc định dùng cache trong bộ nhớ. Chỉ nhóm redis dùng Redis.
- Bỏ bước test nhanh và `ssot:lint`, vì CI đã chạy cả hai trên mọi PR.
- **Bỏ hẳn server app và bước websocket**, vì không test nào cần. Như vậy cũng hết `artisan serve`.
- Báo cáo nightly bỏ hai dòng kiểm tra app và websocket.

**Phương án B:** như A nhưng **giữ bước test nhanh** với cache trong bộ nhớ, và chạy server bằng nginx + php-fpm. Khi đó phải
điều tra và xử lý 35 test không đạt. Có thể lòi ra lỗi thật; phạm vi lớn hơn và chưa biết trước.

**Phương án C:** tắt hẳn Nightly Test Matrix.

Đề xuất **Phương án A**.

## Design: Option A (exact allowlist)

1. **`.github/workflows/nightly-matrix.yml`**:
   - "Prepare application env": `cp .env.example .env` replaces `cp .env.testing .env`. The rest of the step is unchanged:
     `key:generate`, SQLite file and `migrate:fresh`. The job's `env:` block supplies `APP_ENV`, `DB_*` and `REDIS_*` as it does today.
   - The job `env:` sets `CACHE_DRIVER: array` instead of `redis`, and `QUEUE_CONNECTION: sync` instead of `redis`.
   - Removed steps: "Start local app server", "Start websocket server (best effort)". The readiness step is renamed
     "Wait for Redis" and keeps only the Redis check.
   - Removed env: `APP_URL`, `WEBSOCKET_HOST`, `WEBSOCKET_PORT`.
   - The artifact list drops `/tmp/laravel-serve.log` and `/tmp/websocket.log`.
2. **`scripts/test/nightly_matrix.sh`**:
   - removes the `ssot:lint` and `test:fast` stages and keeps `optimize:clear`;
   - the `redis` group runs with `CACHE_DRIVER=redis`. Other groups use the job's cache setting;
   - the report drops the "App URL reachable" and "WebSocket port reachable" lines, and keeps the Redis line;
   - the script still exits non-zero if any group fails.
3. **Docs**: plan, this packet, 03-release.

**Option B:** item 1 keeps the app server, but serves it with php-fpm behind nginx as in GAP-092. Item 2 keeps `test:fast`
with `CACHE_DRIVER=array`. Then add a triage of the 35 failing tests; each one is fixed in this PR or handed to its own GAP
by Owner decision.

**Not changed:** application code, tests, and other workflows. The schedule (`0 3 * * *`) is also unchanged.

## Verification

- **CI proof:** run the workflow with `workflow_dispatch` on this branch. The run must be green, the report must list the
  four groups with status PASS or SKIP, and the artifact must be uploaded. A temporary `pull_request` trigger is used only if
  `workflow_dispatch` cannot target the branch; it is reverted afterwards.
- **Local:** the four groups pass as described above. `bash -n` on the script passes.
- actionlint reports no new findings. Exact-head CI is green, and the governance and docs lints pass.

## Rollback

Revert the squash commit.

## Out of scope

- The 35 failing tests found when the whole suite runs with the nightly environment. They are recorded here, not fixed
  (Option A).
- Adding tests to the `load` group, which is empty.
- Deployment.

## Decision Needed

Owner chọn: `APPROVE GAP-093 Gate 2 Option A` (khuyến nghị) / Option B / Option C / Request changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, release hay deploy.
