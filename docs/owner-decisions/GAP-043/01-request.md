---
work_id: GAP-043
gate: 1
gate_status: approved
owner_decision:
  value: approve
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-08-21-gap-043-performance-test-mysql-portability-evidence.md
  plan: null
  branch: docs/GAP-043-gate1-investigation
  pr: "https://github.com/kha997/zenamanagephp/pull/279"
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-21T20:20:00+07:00"
  owner_response_reference: "Owner chat message, 2026-08-21: GAP-043 - GATE 1 OWNER DECISION: APPROVE. PR #279, Reviewed Gate-1 head 6d698645caff4546deee4d1e5cf40c7ec1c7fe40, canonical reviewed baseline 25cab7f4955ed9a9b5d0c7113c19ca1ea679c3ac. APPROVE GAP-043 Gate 1 v2; the Gate-1 problem/evidence boundary is accepted; Gate 1 does NOT select a technical solution. Owner inventory clarification: the corrected 25-line/20-file PRAGMA inventory is specifically the result of running git grep for PRAGMA over tracked PHP files (*.php), not a claim about every file type in the entire repository - correct only the remaining imprecise labels (repo-wide grep, exhaustive repo search) to tracked-PHP inventory / exhaustive tracked-PHP search, non-substantive wording only, no technical findings or counts altered."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-21T19:14:00+07:00"
  updated_at: "2026-08-21T20:20:00+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner phê duyệt GAP-043 Gate 1 (v2) lúc `2026-08-21T20:20:00+07:00`, đã review PR #279 tại head `6d698645caff4546deee4d1e5cf40c7ec1c7fe40`, đối chiếu canonical baseline `25cab7f4955ed9a9b5d0c7113c19ca1ea679c3ac`. Phạm vi vấn đề/bằng chứng Gate 1 được chấp nhận.

Các phát hiện ràng buộc (binding findings) Owner xác nhận:
1. `PerformanceMonitoringTest::tableInsertDefaults()` chứa `PRAGMA table_info(...)` chỉ SQLite hiểu, lỗi trên MySQL thật.
2. 7/10 test method gọi cấu trúc `createTestData() -> tableInsertDefaults()`.
3. Bằng chứng LIVE: 6 method lỗi trực tiếp tại lệnh PRAGMA; 3 method không gọi helper thì PASS.
4. Caller thứ 7 (`test_api_performance_budgets`) **KHÔNG** phải lỗi LIVE trực tiếp của GAP-043 — LIVE execution của nó bị che khuất sớm hơn bởi triệu chứng SAVEPOINT của GAP-044. Giữ nguyên phân biệt: **6 LIVE trực tiếp + 1 STATIC-reachable/LIVE-masked + 3 không bị ảnh hưởng.**
5. Defect vẫn là test-side theo bằng chứng hiện tại. Không đụng application/schema/RBAC/tenant/business semantics.
6. Gate 1 KHÔNG chọn giải pháp kỹ thuật.

**Owner inventory clarification:** Inventory PRAGMA 25 dòng/20 file đã sửa là kết quả cụ thể của `git grep -n "PRAGMA" -- '*.php'` trên tracked PHP files — KHÔNG phải tuyên bố về mọi loại file trong toàn repo. Theo chỉ đạo Owner, đã sửa 2 nhãn phạm vi còn thiếu chính xác trong đợt ghi nhận approval này: "repo-wide grep" (trong `01-request.md`) → "tracked-PHP inventory grep"; heading "exhaustive repo search" (trong audit doc, mục C) → "exhaustive tracked-PHP search". Đây là sửa wording epistemic không ảnh hưởng nội dung, được uỷ quyền cùng lúc với việc ghi nhận quyết định Owner này. Không có phát hiện kỹ thuật hay số đếm nào bị thay đổi.

Quyết định này CHỈ xác nhận phạm vi vấn đề/bằng chứng Gate 1. KHÔNG cho phép Gate 2, KHÔNG sửa test, KHÔNG implementation, KHÔNG công việc GAP-044/GAP-045/GAP-042, KHÔNG merge/release/deploy trong phiên ghi nhận approval này. PR #279 vẫn giữ trạng thái Draft.

## Owner Summary

`PerformanceMonitoringTest::tableInsertDefaults()` (`tests/Performance/PerformanceMonitoringTest.php:445-460`) calls `DB::select("PRAGMA table_info({$table})")` — SQLite-only introspection syntax with no MySQL branch. On real MySQL this is a hard `SQLSTATE[42000]` syntax error. Gate 1 independently traced the exact call path, confirmed the LIVE-run test-code blob is byte-identical to current canonical `main`, and confirmed the defect is fully contained to this one helper — no other MySQL-incompatible code exists elsewhere in the file.

## Vấn đề vận hành

Bảy trong số 10 test method của `PerformanceMonitoringTest` gọi `createTestData()`, hàm này gọi `tableInsertDefaults()` để đọc default cột từ schema trước khi raw-insert hàng loạt (bypass Eloquent attribute defaults vì lý do hiệu năng). `tableInsertDefaults()` dùng `PRAGMA table_info` — cú pháp chỉ SQLite hiểu. LIVE run `32471481216` (job `96739005481`, nhánh monitoring) xác nhận: `Tests: 7 failed, 3 passed`. Đối chiếu JUnit XML từng test: 6/7 lỗi trực tiếp là `SQLSTATE[42000] ... near 'PRAGMA table_info(projects)'`; test thứ 7 (`test_api_performance_budgets`) lỗi ở lớp SAVEPOINT (GAP-044) trước khi kịp chạy tới PRAGMA — một tương tác che khuất được ghi nhận, không hấp thụ vào gap này. 3 test còn lại (không gọi `createTestData()`) PASS, khớp với đọc mã tĩnh.

Đọc toàn bộ 478 dòng file: không tìm thấy cú pháp MySQL-không-tương-thích nào khác ngoài dòng 449. Blast radius bên trong file này được xác nhận đóng kín ở một dòng/một helper.

`git grep -n "PRAGMA" -- '*.php'` trên tracked tree (chỉ mã PHP thực thi — `app/`, `database/migrations/`, `tests/`, không phải toàn repo/mọi loại file) cho đúng **25 dòng xuất hiện trên 20 file duy nhất**, chia 4 nhóm không chồng lấn: (1) 1 file/1 dòng — chính defect GAP-043 (`PerformanceMonitoringTest.php:449`); (2) 8 file/13 dòng — migration + lệnh bảo trì ứng dụng, đã xác nhận từng dòng đều nằm trong nhánh driver-guard (`if ($driver === 'sqlite')`, có nhánh MySQL tương đương) — đọc trực tiếp, không suy luận; (3) 10 file/10 dòng — test Feature/Integration gọi `PRAGMA foreign_keys=OFF` không có driver-guard; (4) 1 file/1 dòng — riêng `ExportTenantIsolationTest.php` gọi `PRAGMA defer_foreign_keys = ON` (câu lệnh khác, mục đích khác, tách riêng khỏi nhóm (3) để không gộp nhầm). `tableInsertDefaults`: chỉ 1 định nghĩa, 2 điểm gọi, cùng 1 file. Nhóm (3)+(4) — 11 file tổng cộng — xác nhận qua `.github/workflows/automated-testing.yml` rằng không CI job nào hiện chạy các file này trên MySQL (chỉ 5 job đặt `DB_CONNECTION: mysql`, không job nào trong đó bao gồm 11 file này; `phpunit.xml` mặc định `sqlite`). Đây là rủi ro portability **đang ngủ yên (dormant)**, được ghi nhận và phân loại riêng, KHÔNG hấp thụ vào GAP-043, KHÔNG tự sửa.

## Người dùng bị ảnh hưởng

Đội kỹ thuật (tin rằng `performance-tests` job giờ đã thật sự chạy trên MySQL — đúng, nhờ GAP-041 — nhưng chưa biết 6/10 test của `PerformanceMonitoringTest` sẽ luôn fail cho tới khi GAP-043 được sửa); Owner/stakeholder (nhận báo cáo CI đỏ ở `performance-tests` mà chưa có bối cảnh đây là 1 trong 3 defect riêng biệt được phát hiện cùng lúc, không phải 1 lỗi lớn duy nhất); GAP-041 (đang `blocked_technical`, phụ thuộc GAP-043/044/045 được xử lý trước khi có thể trình Gate 3).

## Bằng chứng

Đầy đủ tại `docs/audits/2026-08-21-gap-043-performance-test-mysql-portability-evidence.md`: LIVE evidence (GitHub Actions run `32471481216`, job `96739005481` monitoring leg + job `96739005491` dashboard leg xác nhận KHÔNG có PRAGMA ở đó); STATIC evidence (source code tại `origin/main` `25cab7f4`, blob-hash byte-identity giữa commit chạy LIVE/`main`/nhánh điều tra này); tracked-PHP inventory grep cho `PRAGMA`/`tableInsertDefaults`; đối chiếu CI workflow để xác nhận driver mapping từng job. Không có LOCAL reproduction (không cần thiết — LIVE + STATIC đã đủ xác lập vấn đề, call path, và blast radius).

## Tác động nếu không xử lý

`performance-tests` (nhánh monitoring) tiếp tục đỏ vĩnh viễn ngay cả sau khi GAP-044 được sửa — vì 6/7 test gọi `createTestData()` sẽ luôn hit đúng dòng PRAGMA này. GAP-041 tiếp tục kẹt ở `blocked_technical`, không thể trình Gate 3 (yêu cầu 1 lượt chạy LIVE toàn xanh). Đội kỹ thuật không có test hiệu năng thật nào chạy được trên MySQL cho tới khi việc này được xử lý.

## Phạm vi đề xuất

Gate 1 chỉ xác nhận: (1) vấn đề portability tại `tableInsertDefaults()` là có thật, đã LIVE-confirm, và blast radius đã được xác định đóng kín (6 test lỗi trực tiếp + 1 test bị GAP-044 che khuất, trong tổng 7 test gọi tới helper này); (2) vấn đề là thuần test-side, không đụng application/schema/production semantics — không kích hoạt Design Dependency Preflight; (3) cần một quyết định Gate 2 riêng về cách sửa (ví dụ: đọc default cột qua Laravel Schema builder portable thay vì raw PRAGMA, hay driver-branch giống pattern đã có trong migration `2025_09_20_145756_disable_foreign_keys_for_testing.php`, hay loại bỏ hoàn toàn việc reconstruct default nếu Gate 2 xác định nó không cần thiết cho bất kỳ assertion nào) — Gate 1 KHÔNG chọn cơ chế kỹ thuật cụ thể.

## Loại trừ rõ ràng

Không đụng GAP-041 (selector/truthfulness — chỉ đọc provenance record của nó), GAP-044 (SAVEPOINT — chỉ ghi nhận tương tác che khuất, không điều tra root cause), GAP-045 (latency budget — xác nhận không liên quan vì `DashboardPerformanceTest.php` không có PRAGMA nào), hay GAP-042 (RBAC production-fidelity). Không sửa bất kỳ file test hay application code nào ở Gate 1 này. Không tự suy luận rằng Owner đã phê duyệt bất cứ điều gì — chỉ báo cáo bằng chứng và xin quyết định.
