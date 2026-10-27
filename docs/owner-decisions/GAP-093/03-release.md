---
work_id: GAP-093
gate: 3
gate_status: approved
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-23-gap-093-nightly-matrix-evidence.md
  plan: docs/superpowers/plans/2026-10-24-gap-093-nightly-matrix.md
  branch: docs/GAP-093-nightly-server
  pr: https://github.com/kha997/zenamanagephp/pull/366
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-25T08:28:32+07:00"
  owner_response_reference: "Owner Gate-3 decision in-session on 2026-10-25, verbatim: 'APPROVE GAP-093 Gate 3'. Given after the packet at PR head 9233fd2a825efbb37a8f471c0cd2361fea3347f1 (all checks green); bound to implementation subject 4a7ff2fb607a96208c6a7bff8e9b8842acda81fb and implementation-tree digest 7148f8ca7fc6445ffa4769be398f402214d2ef732798a06d067fcb1b84f477dd (recomputed at recording time, zero drift). Merge is covered by the Owner's standing in-session instruction of 2026-09-28; no deployment or release authorized."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-24T22:57:35+07:00"
  updated_at: "2026-10-25T08:28:32+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-093 at subject 4a7ff2fb607a96208c6a7bff8e9b8842acda81fb (Gate 2 Option A). nightly-matrix.yml: 'Prepare application env' copies .env.example instead of the missing .env.testing; the job env uses CACHE_DRIVER array and QUEUE_CONNECTION sync (was redis/redis) and drops APP_URL, WEBSOCKET_HOST and WEBSOCKET_PORT; the 'Start local app server' and 'Start websocket server (best effort)' steps are removed; the readiness step becomes 'Wait for Redis' with only the Redis check; the artifact list drops the two server logs. scripts/test/nightly_matrix.sh: the ssot:lint and test:fast stages are removed (CI runs both on every pull request); the redis group runs with CACHE_DRIVER=redis, other groups with the job's cache; the report drops the app and websocket reachability lines, and the now-unused http_reachable helper is removed; the script still exits non-zero when any group fails. Proof: workflow_dispatch run 40107882741 (Nightly Test Matrix #94) on 4a7ff2fb is green, the first green run of this workflow after 91 consecutive failures; groups slow PASS (1 test), load PASS (0 tests), stress PASS (28 tests, 20 skipped), redis PASS (1 test); report artifact uploaded (10 files). Local: the same four groups pass; with Redis stopped the redis group reports FAIL and the script exits 1, so a missing Redis does not pass silently. Investigation recorded in 02-design.md: running the whole suite with the old nightly environment gives 117 errors from the login rate limiter leaking through a shared Redis cache and 35 other failures; these are out of scope under Option A. Static: bash -n passes; actionlint reports no findings. Exact-head CI at 4a7ff2fb: 36/36 green, including the dispatched nightly run."
technical_evidence:
  base_sha: "b62d5f5beb3d889978614a8419682dcfdcb414cc"
  subject_sha: "4a7ff2fb607a96208c6a7bff8e9b8842acda81fb"
  implementation_tree_digest: "7148f8ca7fc6445ffa4769be398f402214d2ef732798a06d067fcb1b84f477dd"
  verified_pr_head_sha: "4a7ff2fb607a96208c6a7bff8e9b8842acda81fb"
  verified_at: "2026-10-24T22:57:35+07:00"
owner_decision_binding:
  implementation_tree_digest: "7148f8ca7fc6445ffa4769be398f402214d2ef732798a06d067fcb1b84f477dd"
  decision_recorded_at: "2026-10-25T08:28:32+07:00"
---

# GAP-093 — Gate 3 release decision (Nightly Test Matrix)

## OWNER GATE 3: APPROVED

Owner approved Gate 3 in-session on 2026-10-25 ("APPROVE GAP-093 Gate 3"), bound to implementation subject
`4a7ff2fb607a96208c6a7bff8e9b8842acda81fb` and implementation-tree digest `7148f8ca7fc6445ffa4769be398f402214d2ef732798a06d067fcb1b84f477dd`. No deployment or release is authorized.

## Gói quyết định phát hành

**1. Vấn đề là gì?** Nightly Test Matrix đỏ cả 91/91 lần chạy: lần nào cũng dừng ở bước copy file `.env.testing`,
mà repo không có file này (Gate 1).

**2. Sau thay đổi (Gate 2 Phương án A)** — commit `4a7ff2fb`:
- File env được tạo từ `.env.example`.
- Mặc định dùng cache trong bộ nhớ; chỉ nhóm redis dùng Redis.
- Bỏ hẳn bước chạy server app và websocket, vì không test nào cần. Nightly vì vậy không còn dùng `artisan serve`.
- Script nightly bỏ `ssot:lint` và bước test nhanh, vì CI đã chạy cả hai trên mọi PR. Script chỉ chạy 4 nhóm
  slow, load, stress, redis.

**3. Bằng chứng** — base `b62d5f5b`; subject `4a7ff2fb607a96208c6a7bff8e9b8842acda81fb`;
digest `7148f8ca7fc6445ffa4769be398f402214d2ef732798a06d067fcb1b84f477dd`.
- **Lần chạy thật đầu tiên xanh** của Nightly Test Matrix
  ([run 40107882741](https://github.com/kha997/zenamanagephp/actions/runs/40107882741)), sau 91 lần đỏ liên tiếp.
  Cả 4 nhóm đều PASS: slow 1 test, load 0 test, stress 28 test (20 bị bỏ qua), redis 1 test. Báo cáo đã được tải lên.
- Trên máy: nếu tắt Redis, nhóm redis báo FAIL và script đỏ, nên nightly không xanh giả khi thiếu Redis.
- CI chạy đúng trên `4a7ff2fb`: **36/36 xanh**.

**4. Công khai**
- Khi chạy toàn bộ bộ test với môi trường nightly cũ, có 117 lỗi do giới hạn đăng nhập bị dồn qua cache Redis, và
  35 test không đạt. Phương án A không sửa các lỗi này; chúng đã được ghi trong thiết kế Gate 2.
- Các nhóm test nightly đang rất nhỏ (nhóm load chưa có test nào).
- Mình xoá thêm hàm `http_reachable` trong script, vì không còn chỗ nào dùng sau khi bỏ dòng kiểm tra app.

**5. Ảnh hưởng** — Nightly bắt đầu chạy và báo kết quả thật mỗi đêm, mất khoảng 1 phút.

**6. Rủi ro còn lại** — Thấp.

**7. Hoàn tác** — Revert squash commit.

**8. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: `APPROVE GAP-093 Gate 3` / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy hay release; không sửa 35 test không đạt.
