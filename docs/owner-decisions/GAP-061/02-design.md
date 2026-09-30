---
work_id: GAP-061
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-09-30-gap-061-e2e-suite-evidence.md
  plan: null
  branch: docs/GAP-061-e2e-suite-never-worked
  pr: https://github.com/kha997/zenamanagephp/pull/330
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-09-30T22:30:27+07:00"
  owner_response_reference: "Owner decision in-session on 2026-09-30: 'APPROVE GAP-061 Gate 2 Option 1'. Reviewed design head: 69996b5174f16babad12686fcfeb0bff68718cf9. Approves Option 1 and its exact allowlist (delete tests/E2E/CriticalUserFlowsE2ETest.php and tests/E2E/DashboardE2ETest.php; plan; GAP-061 packets); not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-30T22:28:57+07:00"
  updated_at: "2026-09-30T22:30:27+07:00"
generated_by: agent
---

# GAP-061 — Retire the never-working E2E files: Gate 2 design

## OWNER GATE 2: APPROVED — OPTION 1

Owner approved Option 1 in-session on 2026-09-30 against reviewed design head
`69996b5174f16babad12686fcfeb0bff68718cf9`. This authorizes only the bounded retirement defined by this packet; it
does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

Theo lựa chọn "gỡ bây giờ": xoá 2 file test E2E chưa từng chạy được. Bằng chứng
GAP-040 trong cùng thư mục giữ nguyên và vẫn chạy mỗi đêm. Không đổi mã ứng
dụng, workflow hay CI chính. Đề xuất **Phương án 1**.

## So sánh phương án

| Phương án | Cách làm | Kết luận |
|---|---|---|
| **1. Xoá đúng 2 file** | `git rm` `CriticalUserFlowsE2ETest.php`, `DashboardE2ETest.php` | **Đề xuất** — đúng lựa chọn Owner; không mất gì đang chạy |
| 2. Đánh dấu `markTestSkipped` | Giữ file, bỏ qua test | Loại: giữ mã chết, trông như "có test" mà không có |

## Thiết kế: Phương án 1

- Xoá `tests/E2E/CriticalUserFlowsE2ETest.php` và
  `tests/E2E/DashboardE2ETest.php`.
- Giữ `tests/E2E/TransactionIsolationColdStartTest.php` (GAP-040) và workflow
  đêm (GAP-060) nguyên trạng.
- Không sửa tài liệu lịch sử nhắc tới hai file (`docs/archive/*`,
  `docs/testing/*`, `docs/refactor/*`, các audit/Gate packet cũ) và file sinh
  sẵn đã cũ `scripts/ssot/orphan_routes.generated.txt` (không script/workflow
  nào đọc; đổi lần cuối 2026-02-20) — là bản ghi lịch sử, không phải mã chạy.
- Không thêm test mới: việc xoá được chứng minh bằng các kiểm tra bên dưới.

## Allowlist

Hai file bị xoá;
`docs/superpowers/plans/2026-09-30-gap-061-e2e-retirement-implementation.md`
(governance frontmatter); Gate packet/evidence GAP-061. Không file nào khác.

## Verification trước Gate 3

- `./vendor/bin/phpunit tests/E2E` chỉ còn `TransactionIsolationColdStartTest`
  (bỏ qua trên SQLite như trước).
- `scripts/ssot/find_orphan_test_routes.php` và
  `tests/Architecture` + `tests/Unit/OwnerGovernance` xanh.
- `git grep` không còn tham chiếu tới hai class trong `app`, `tests`,
  `routes`, `.github`, `scripts` (trừ file sinh sẵn đã nêu và chú thích
  GAP-060 trong workflow).
- Chạy workflow đêm trên nhánh: vẫn xanh.
- Required PR CI xanh exact head; digest canonical.

## Rollback

Revert squash commit (khôi phục hai file hỏng).

## Explicit Exclusions

Viết hành trình E2E mới (Owner: làm sau nếu muốn, như việc riêng); sửa tài
liệu lịch sử; deploy.

## Decision Needed

Owner chọn: Approve Phương án 1 / Request changes / Decline.

## What the owner is NOT being asked to decide

Không duyệt Gate 3, merge hay deployment.
