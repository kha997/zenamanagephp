---
work_id: GAP-100
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-29-gap-100-test-db-leak-evidence.md
  plan: docs/superpowers/plans/2026-10-29-gap-100-test-db-leak.md
  branch: docs/GAP-100-test-db-leak
  pr: https://github.com/kha997/zenamanagephp/pull/383
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-29T06:46:15+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-29, verbatim: 'APPROVE GAP-100 Gate 2 Option A'. Reviewed design head: 52c1710f7e1ff8ea40d2e8955f52b81a9ce23fc8. Approves Option A and its exact allowlist (RefreshDatabase on tests/Feature/ProjectManagementTest.php and tests/Feature/Deployment/DeployMigrateCommandTest.php; SQLite-only leak guard hooked into tests/TestCase.php setUp/tearDown; new tests/Support/DatabaseLeakGuard.php and tests/Unit/Support/DatabaseLeakGuardTest.php; plan and packets); no application code, phpunit config, CI workflow, test order, Dusk or MySQL-only test change; not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-29T02:22:55+07:00"
  updated_at: "2026-10-29T06:46:15+07:00"
generated_by: agent
---

# GAP-100 — Test classes that leak DB rows: Gate 2 design

## OWNER GATE 2: APPROVED — OPTION A

Owner approved Option A in-session on 2026-10-29 against reviewed design head
`52c1710f7e1ff8ea40d2e8955f52b81a9ce23fc8`. This authorizes only the bounded implementation defined by this packet; it
does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

Đề xuất **Phương án A**:

1. **Sửa 2 file test rò dữ liệu** (`ProjectManagementTest`, `DeployMigrateCommandTest`): thêm `RefreshDatabase` để dữ
   liệu tự xoá sau mỗi test.
2. **Thêm cơ chế tự phát hiện** chạy trong mọi lần test: test nào không tự dọn dữ liệu mà để lại bản ghi trong DB test thì
   **chính test đó đỏ**, kèm tên bảng và số bản ghi thừa. Lỗi kiểu GAP-065 / GAP-100 sẽ bị bắt ngay ở test gây ra, không
   còn đỏ giả ở test khác.

Chi phí (đo 1 lần, máy này): Unit + Feature + Integration 366 giây → 391 giây (+25 giây, khoảng 7%); cơ chế chỉ chạy cho
~324 test không tự dọn.

## So sánh phương án

| Phương án | Nội dung | Kết luận |
|---|---|---|
| **A. Sửa 2 file + cơ chế phát hiện luôn bật** | như trên | **Đề xuất** — chặn lỗi tái diễn ở đúng nơi gây ra |
| B. Chỉ sửa 2 file | Không có cơ chế phát hiện | Nhỏ nhất, nhưng lỗi cùng kiểu sẽ quay lại mà không ai biết (đã xảy ra 2 lần: GAP-065, GAP-100) |
| C. Sửa 2 file + cơ chế phát hiện chỉ chạy khi bật biến môi trường | Lần chạy thường không đổi | Không ai bật thì không bắt được gì; phải thêm job CI riêng |

## Thiết kế: Phương án A

### 1. Hai file test

`tests/Feature/ProjectManagementTest.php` và `tests/Feature/Deployment/DeployMigrateCommandTest.php`: thêm
`use RefreshDatabase;`. Không đổi assertion nào. (`DeployMigrateCommandTest` chạy migration giả trong transaction của
`RefreshDatabase`; SQLite hỗ trợ DDL trong transaction — thử nghiệm Finding 4 đã xanh. Các job MySQL không chạy file này.)

### 2. Cơ chế phát hiện

- File mới `tests/Support/DatabaseLeakGuard.php` (chỉ dùng cho test):
  - `applies(string $testClass)`: đúng khi lớp test **không** dùng `RefreshDatabase`, `DatabaseTransactions`,
    `DatabaseMigrations` hay `DatabaseTruncation` (các lớp đó đã tự dọn).
  - `snapshot(Connection)`: đếm số bản ghi mọi bảng; chỉ chạy với SQLite (bộ test thường), trả `null` với MySQL (các job
    MySQL không bị ảnh hưởng).
  - `leaks(before, after)`: danh sách `bảng: trước → sau` cho bảng có số bản ghi thay đổi; bảng mới tạo mà rỗng thì bỏ qua.
- `tests/TestCase.php`: cuối `setUp()` lấy ảnh chụp (giữ chính đối tượng connection, để test nào giả lập facade `DB` vẫn
  không ảnh hưởng); đầu `tearDown()` so sánh, có chênh lệch thì `fail()` với thông báo
  `GAP-100: <Lớp>::<test> left rows in the shared test database: tenants: 0 -> 1, ...` rồi vẫn gọi `parent::tearDown()`.
- Bản thử (đã hoàn tác) giữ connection như trên: không còn lỗi giả ở `ProductionReadinessEndpointTest` mà bản đo Gate 1 gặp;
  bản đo (chỉ ghi lại, chưa làm test đỏ) không gây lỗi mới: chỉ còn 2 test hỏng sẵn do chạy bằng root.
- Không có cơ chế bỏ qua (opt-out): đo toàn suite hiện chỉ có 2 lớp rò, cả 2 được sửa ở mục 1.

### 3. Test (đỏ trước / xanh sau)

- `tests/Unit/Support/DatabaseLeakGuardTest.php` (mới, dùng SQLite in-memory riêng, không đụng DB test chung): `applies`
  đúng/sai theo trait; `snapshot` đếm đúng; `leaks` báo bảng thay đổi, bỏ qua bảng mới rỗng, trả rỗng khi không đổi;
  `snapshot` trả `null` với driver khác SQLite. Đỏ trước: lớp chưa tồn tại.
- Lệnh tái hiện Gate 1 (`VendorApiTest + ProjectManagementTest + ProductionBootstrapCommandTest`): đỏ 3 trước, xanh sau.
- Bằng chứng guard hoạt động: trước khi sửa mục 1, bật guard thì đúng 3 test của 2 lớp kia đỏ với thông báo GAP-100.

### 4. Files (allowlist)

`tests/Feature/ProjectManagementTest.php`, `tests/Feature/Deployment/DeployMigrateCommandTest.php` (mỗi file 2 dòng
`use`); `tests/TestCase.php` (móc vào `setUp`/`tearDown`); `tests/Support/DatabaseLeakGuard.php` (mới);
`tests/Unit/Support/DatabaseLeakGuardTest.php` (mới); plan `docs/superpowers/plans/2026-10-29-gap-100-test-db-leak.md`;
packet và evidence GAP-100.

**Không đổi:** mã ứng dụng, `phpunit*.xml`, workflow CI, thứ tự chạy, test Dusk, test chỉ chạy MySQL, các test khác.

## Verification required at Gate 3

Đỏ trước / xanh sau như mục 3; toàn bộ Unit + Feature + Integration và `tests/Feature/Api` không có lỗi mới so với
baseline (5 test hỏng sẵn đã biết); chi phí thời gian đo lại; PHPStan, php-cs-fixer, SSOT lint, governance/docs lint;
exact-head CI xanh.

## Rollback

Revert squash commit.

## Decision Needed

Owner chọn: `APPROVE GAP-100 Gate 2 Option A` (khuyến nghị) / Option B / Option C / Request changes / Decline.

## What the owner is NOT being asked to decide

Chưa duyệt Gate 3, merge, phát hành; không đổi CI hay mã ứng dụng.
