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
  recorded_at: "2026-09-30T20:29:38+07:00"
  owner_response_reference: "Owner decision in-session on 2026-09-30: 'APPROVE GAP-060 Gate 2 Option 1v2'. Reviewed design head: 2861856f55fce6caf721eeb0d144c69a54546a18. Approves Option 1v2 and its exact allowlist (keep the workflow only for tests/E2E/TransactionIsolationColdStartTest.php; remove the four broken jobs, the test-summary job and the E2E suite step; rename; WorkflowReferencesExistTest; E2E suite repair deferred to GAP-061); not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: "docs/owner-decisions/GAP-060/02-design.md"
superseded_by: null
timestamps:
  created_at: "2026-09-30T20:01:19+07:00"
  updated_at: "2026-09-30T20:29:38+07:00"
generated_by: agent
---

# GAP-060 — Nightly a11y/perf workflow: Gate 2 design v2 (correction)

## OWNER GATE 2: APPROVED — OPTION 1v2

Owner approved Option 1v2 in-session on 2026-09-30 against reviewed design head
`2861856f55fce6caf721eeb0d144c69a54546a18`. This authorizes only the bounded implementation defined by this packet;
it does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

Bản duyệt trước (v1) giữ lại và sửa các test E2E. Khi bắt tay làm, phát hiện
bộ E2E **chưa từng chạy được kể từ khi viết**: 15 test đều hỏng (13 lỗi, 2 thất
bại) vì dùng hàm/cấu trúc không tồn tại — sửa là **viết lại cả bộ**,
không phải "sửa vài kỳ vọng". Đồng thời phát hiện workflow này là nơi **duy
nhất** chạy một bằng chứng quan trọng đang xanh (GAP-040, 2 test trên MySQL
thật). Đề xuất v2: giữ workflow chỉ để chạy bằng chứng GAP-040 (xanh thật), gỡ
mọi job/bước hỏng, và tách việc sửa bộ E2E thành **GAP-061** riêng.

## Vì sao sửa đổi v1

Làm theo v1 (commit chưa có; mọi thay đổi thử nghiệm đã hoàn tác):

- `tests/E2E/CriticalUserFlowsE2ETest.php` gọi `apiAs()` nhưng chưa từng
  import `Tests\Traits\AuthenticationTrait` (file đổi lần cuối 2026-02-20, cùng
  commit thêm `apiAs`). Thêm trait vẫn còn 8/8 thất bại: 401 ở 5 test, 403 ở
  test đa tenant, thiếu khoá `error` ở test xử lý lỗi, nội dung trang sai ở
  test đăng nhập.
- `tests/E2E/DashboardE2ETest.php`: 7/7 lỗi `NOT NULL constraint failed:
  rfis.title` từ fixture.
- Log CI: mọi lần chạy chỉ tới test đầu tiên rồi dừng (`--stop-on-failure`),
  nên chưa ai thấy mức hư hỏng này.
- `tests/E2E/TransactionIsolationColdStartTest.php` (bằng chứng GAP-040): **2
  passed** mỗi đêm trên MySQL thật (run `36693528967`), được chạy bởi bước
  riêng; `ci-cd.yml:78` chạy một file khác
  (`tests/Feature/Documents/TransactionIsolationColdStartTest.php`).

v1 cho phép "sửa kỳ vọng lỗi thời, liệt kê từng cái"; viết lại 15 test với ý đồ
nghiệp vụ không rõ vượt quá phạm vi đó.

## So sánh phương án

| Phương án | Nội dung | Kết luận |
|---|---|---|
| **1v2. Giữ workflow cho bằng chứng GAP-040; tách E2E thành GAP-061** | Một job MySQL chạy `TransactionIsolationColdStartTest`; gỡ 4 job + bước "Run E2E tests" | **Đề xuất** — workflow xanh thật, không mất bằng chứng đang có giá trị |
| 2v2. Viết lại bộ E2E trong GAP-060 | Sửa 15 test | Phạm vi lớn, cần hiểu ý đồ từng luồng; nên có Gate 1 riêng |
| 3v2. Gỡ cả workflow | Xoá file | Loại: mất bằng chứng GAP-040 duy nhất đang chạy |

## Thiết kế: Phương án 1v2

### `.github/workflows/a11y-perf-testing.yml`

- `name:` → `Nightly MySQL Cold-Start Proof (GAP-040)`; lịch và
  `workflow_dispatch` giữ nguyên; tên file giữ nguyên.
- Xoá job `accessibility-tests`, `performance-budget`, `performance-heavy`,
  `lighthouse-ci`, `test-summary`.
- Job còn lại (từ `e2e-tests`, đổi id thành `gap040-cold-start-proof`): giữ
  MySQL service, cài đặt, migrate, preflight GAP-039; **xoá** bước "Run E2E
  tests" và bước tạo/tải báo cáo E2E; bước chạy
  `tests/E2E/TransactionIsolationColdStartTest.php` bỏ `if: always()` (là
  bước chính) và thêm `--fail-on-empty-test-suite`-tương đương để 0 test là
  thất bại.
- Chú thích đầu file giải thích GAP-060/GAP-061.

### Hàng rào `tests/Architecture/WorkflowReferencesExistTest.php`

Như v1: script workflow gọi phải tồn tại; mọi `--group` phải có test mang
nhóm. Đỏ ở base, xanh sau sửa.

### GAP-061 (ghi nhận, không sửa ở đây)

"Bộ E2E `CriticalUserFlowsE2ETest` (8) + `DashboardE2ETest` (7) chưa từng
chạy được" — đăng ký qua đối soát sau phát hành; cần Gate 1 riêng (có thể cần
Owner xác định luồng nào là "quan trọng").

## Allowlist

`.github/workflows/a11y-perf-testing.yml`,
`tests/Architecture/WorkflowReferencesExistTest.php`,
`docs/superpowers/plans/2026-09-30-gap-060-nightly-workflow-implementation.md`
(governance frontmatter), Gate packet/evidence GAP-060. Không sửa `tests/E2E/*`,
register, CI chính, mã ứng dụng.

## Verification trước Gate 3

Test kiến trúc đỏ ở base, xanh ở subject; **chạy thật workflow trên nhánh**
(`gh workflow run a11y-perf-testing.yml --ref <branch>`) → xanh, 2 test GAP-040
thực chạy; required PR CI xanh exact head; digest canonical.

## Rollback

Revert squash commit.

## Explicit Exclusions

Sửa/viết lại `tests/E2E` (GAP-061); ngưỡng hiệu năng/Lighthouse; CI chính;
deploy.

## Decision Needed

Owner chọn: Approve Phương án 1v2 / Approve Phương án 2v2 / Request changes /
Decline.

## What the owner is NOT being asked to decide

Không duyệt Gate 3, merge hay deployment.
