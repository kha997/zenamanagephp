---
work_id: GAP-060
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-09-30-gap-060-a11y-perf-workflow-evidence.md
  plan: null
  branch: docs/GAP-060-a11y-perf-ci-red
  pr: https://github.com/kha997/zenamanagephp/pull/329
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-09-30T19:58:25+07:00"
  owner_response_reference: "Owner decision in-session on 2026-09-30: 'APPROVE GAP-060 Gate 2 Option 1'. Reviewed design head: 4dd7739c7c20d934d906586ed95b7241d316971a. Approves Option 1 and its exact contracts/allowlist (keep and fix e2e-tests; remove accessibility-tests, performance-budget, performance-heavy, lighthouse-ci; rename workflow; fix stale E2E expectations with per-item disclosure, stop on genuine app bugs; add WorkflowReferencesExistTest); not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-30T19:56:43+07:00"
  updated_at: "2026-09-30T19:58:25+07:00"
generated_by: agent
---

# GAP-060 — Nightly a11y/perf workflow: Gate 2 design

## OWNER GATE 2: APPROVED — OPTION 1

Owner approved Option 1 in-session on 2026-09-30 against reviewed design head
`4dd7739c7c20d934d906586ed95b7241d316971a`. This authorizes only the bounded implementation defined by this packet;
it does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

Giữ lại đúng phần có giá trị thật của bộ kiểm thử hằng đêm — **các test E2E**
(chỉ chạy ở đây) — sửa cho chúng xanh thật; gỡ các job rỗng (0 test), trùng
với CI chính, hoặc đo sai trang. Thêm một kiểm tra tự động để không workflow
nào gọi script không tồn tại hoặc nhóm test rỗng nữa. Đề xuất **Phương án 1**.
Nếu sau này muốn theo dõi điểm hiệu năng trang (Lighthouse), đó là tính năng
mới cần Owner đặt ngưỡng.

## So sánh phương án

| Phương án | Giữ | Gỡ | Kết luận |
|---|---|---|---|
| **1. Chỉ giữ E2E, sửa cho xanh thật** | e2e-tests | performance-budget, performance-heavy (0 test), accessibility-tests (trùng CI chính), lighthouse-ci (đo trang đăng nhập, không ngưỡng) | **Đề xuất** — mọi job còn lại đều kiểm tra thật |
| 2. Giữ thêm Lighthouse, chỉ sửa DB | e2e + lighthouse | 3 job còn lại | Lighthouse vẫn đo trang đăng nhập, không có ngưỡng → một job "xanh" không nói lên điều gì |
| 3. Lighthouse có đăng nhập + ngưỡng | e2e + lighthouse đầy đủ | 3 job còn lại | Cần Owner đặt ngưỡng hiệu năng (quyết định sản phẩm); làm như tính năng riêng nếu muốn |
| 4. Sửa tất cả tại chỗ | tất cả | — | Loại: 2 job hiệu năng sẽ "xanh giả" (0 test) |

## Thiết kế: Phương án 1

### `.github/workflows/a11y-perf-testing.yml`

- Xoá job `accessibility-tests`, `performance-budget`, `performance-heavy`,
  `lighthouse-ci`; `test-summary` chỉ còn `needs: [e2e-tests]` và báo cáo
  đúng một job.
- `name:` đổi thành `Nightly E2E Tests` (tên file giữ nguyên để lịch sử run
  liền mạch); lịch `0 3 * * *` + `workflow_dispatch` giữ nguyên.
- Job `e2e-tests`: bỏ cờ không hợp lệ `--junit` (giữ `--log-junit=…`);
  các bước khác (MySQL service, migrate, GAP-039 preflight, GAP-040 cold-start
  proof) không đổi.

### `tests/E2E/CriticalUserFlowsE2ETest.php`

- Kỳ vọng chuyển hướng sau đăng nhập: `/app/dashboard` → `/app/today` (hành
  vi hiện tại, `AuthController.php:46`).
- Test E2E đang bị `--stop-on-failure` che (16 test chưa từng chạy): nếu lộ
  thêm kỳ vọng **lỗi thời do thay đổi sản phẩm đã có chủ đích**, sửa kỳ vọng và
  liệt kê từng cái ở Gate 3. Nếu lộ **lỗi thật của ứng dụng**: dừng, không sửa
  tạm, đăng ký gap mới (nguyên tắc "deferred over workaround").

### Hàng rào mới `tests/Architecture/WorkflowReferencesExistTest.php`

1. Mọi đường dẫn script `./…`/`scripts/…`/`.github/scripts/…` được `run:`
   trong `.github/workflows/*.yml` phải tồn tại trong repo.
2. Mọi `--group <tên>` trong `.github/workflows/*.yml` phải có ít nhất một
   test mang nhóm đó (docblock `@group` hoặc attribute `#[Group]`).

Đỏ ở base (script thiếu; nhóm `performance_budget`, `performance_heavy`),
xanh sau sửa.

## Allowlist

`.github/workflows/a11y-perf-testing.yml`, `tests/E2E/*.php` (chỉ kỳ vọng
lỗi thời, kèm danh sách ở Gate 3), `tests/Architecture/WorkflowReferencesExistTest.php`,
`docs/superpowers/plans/2026-09-30-gap-060-nightly-e2e-implementation.md`
(governance frontmatter), Gate packet/evidence GAP-060. Khai báo lint SSOT nếu
cần sẽ ghi rõ ở Gate 3. Không sửa register, CI chính hay mã ứng dụng.

## Verification trước Gate 3

- Test kiến trúc mới đỏ ở base, xanh ở subject.
- **Chạy thật workflow trên nhánh** (`gh workflow run a11y-perf-testing.yml
  --ref <branch>`): job E2E xanh, không bước nào bị bỏ qua âm thầm, số test
  E2E thực chạy > 0 và ghi vào Gate 3.
- Required PR CI xanh exact head; digest canonical.

## Rollback

Revert squash commit (khôi phục workflow cũ, vốn luôn đỏ).

## Explicit Exclusions

Ngưỡng hiệu năng/Lighthouse mới; sửa mã ứng dụng; CI chính; deploy.

## Decision Needed

Owner chọn: Approve Phương án 1 / Approve Phương án 2 / Chọn Phương án 3 (tôi
sẽ hỏi ngưỡng hiệu năng) / Request changes / Decline.

## What the owner is NOT being asked to decide

Không duyệt Gate 3, merge hay deployment.
