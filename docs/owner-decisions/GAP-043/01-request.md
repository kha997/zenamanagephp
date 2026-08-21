---
work_id: GAP-043
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_changes_or_decline"
references:
  spec: docs/audits/2026-08-21-gap-043-performance-test-mysql-portability-evidence.md
  plan: null
  branch: docs/GAP-043-044-045-register-discovery
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-21T19:14:00+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-21T19:14:00+07:00"
  updated_at: "2026-08-21T19:14:00+07:00"
generated_by: agent
---

## Owner Summary

`PerformanceMonitoringTest::tableInsertDefaults()` (`tests/Performance/PerformanceMonitoringTest.php:445-460`) calls `DB::select("PRAGMA table_info({$table})")` — SQLite-only introspection syntax with no MySQL branch. On real MySQL this is a hard `SQLSTATE[42000]` syntax error. Gate 1 independently traced the exact call path, confirmed the LIVE-run test-code blob is byte-identical to current canonical `main`, and confirmed the defect is fully contained to this one helper — no other MySQL-incompatible code exists elsewhere in the file.

## Vấn đề vận hành

Bảy trong số 10 test method của `PerformanceMonitoringTest` gọi `createTestData()`, hàm này gọi `tableInsertDefaults()` để đọc default cột từ schema trước khi raw-insert hàng loạt (bypass Eloquent attribute defaults vì lý do hiệu năng). `tableInsertDefaults()` dùng `PRAGMA table_info` — cú pháp chỉ SQLite hiểu. LIVE run `32471481216` (job `96739005481`, nhánh monitoring) xác nhận: `Tests: 7 failed, 3 passed`. Đối chiếu JUnit XML từng test: 6/7 lỗi trực tiếp là `SQLSTATE[42000] ... near 'PRAGMA table_info(projects)'`; test thứ 7 (`test_api_performance_budgets`) lỗi ở lớp SAVEPOINT (GAP-044) trước khi kịp chạy tới PRAGMA — một tương tác che khuất được ghi nhận, không hấp thụ vào gap này. 3 test còn lại (không gọi `createTestData()`) PASS, khớp với đọc mã tĩnh.

Đọc toàn bộ 478 dòng file: không tìm thấy cú pháp MySQL-không-tương-thích nào khác ngoài dòng 449. Blast radius bên trong file này được xác nhận đóng kín ở một dòng/một helper.

Tìm kiếm toàn repo cho `PRAGMA` (17 lần xuất hiện) và `tableInsertDefaults` (chỉ 1 định nghĩa, 2 điểm gọi, cùng 1 file): 6 lần khác nằm trong migration đã có driver-guard (`if ($driver === 'sqlite')`/`mysql` branch rõ ràng — không phải lỗi); 2 lần trong lệnh bảo trì ứng dụng (`PRAGMA optimize`) ngoài phạm vi test. 10 file test Feature/Integration khác gọi `PRAGMA foreign_keys=OFF` không có driver-guard — nhưng xác nhận qua `.github/workflows/automated-testing.yml` rằng không CI job nào hiện chạy các file này trên MySQL (chỉ 5 job đặt `DB_CONNECTION: mysql`, không job nào trong đó bao gồm 10 file này; `phpunit.xml` mặc định `sqlite`). Đây là rủi ro portability **đang ngủ yên (dormant)**, được ghi nhận và phân loại riêng, KHÔNG hấp thụ vào GAP-043, KHÔNG tự sửa.

## Người dùng bị ảnh hưởng

Đội kỹ thuật (tin rằng `performance-tests` job giờ đã thật sự chạy trên MySQL — đúng, nhờ GAP-041 — nhưng chưa biết 6/10 test của `PerformanceMonitoringTest` sẽ luôn fail cho tới khi GAP-043 được sửa); Owner/stakeholder (nhận báo cáo CI đỏ ở `performance-tests` mà chưa có bối cảnh đây là 1 trong 3 defect riêng biệt được phát hiện cùng lúc, không phải 1 lỗi lớn duy nhất); GAP-041 (đang `blocked_technical`, phụ thuộc GAP-043/044/045 được xử lý trước khi có thể trình Gate 3).

## Bằng chứng

Đầy đủ tại `docs/audits/2026-08-21-gap-043-performance-test-mysql-portability-evidence.md`: LIVE evidence (GitHub Actions run `32471481216`, job `96739005481` monitoring leg + job `96739005491` dashboard leg xác nhận KHÔNG có PRAGMA ở đó); STATIC evidence (source code tại `origin/main` `25cab7f4`, blob-hash byte-identity giữa commit chạy LIVE/`main`/nhánh điều tra này); repo-wide grep cho `PRAGMA`/`tableInsertDefaults`; đối chiếu CI workflow để xác nhận driver mapping từng job. Không có LOCAL reproduction (không cần thiết — LIVE + STATIC đã đủ xác lập vấn đề, call path, và blast radius).

## Tác động nếu không xử lý

`performance-tests` (nhánh monitoring) tiếp tục đỏ vĩnh viễn ngay cả sau khi GAP-044 được sửa — vì 6/7 test gọi `createTestData()` sẽ luôn hit đúng dòng PRAGMA này. GAP-041 tiếp tục kẹt ở `blocked_technical`, không thể trình Gate 3 (yêu cầu 1 lượt chạy LIVE toàn xanh). Đội kỹ thuật không có test hiệu năng thật nào chạy được trên MySQL cho tới khi việc này được xử lý.

## Phạm vi đề xuất

Gate 1 chỉ xác nhận: (1) vấn đề portability tại `tableInsertDefaults()` là có thật, đã LIVE-confirm, và blast radius đã được xác định đóng kín (6 test lỗi trực tiếp + 1 test bị GAP-044 che khuất, trong tổng 7 test gọi tới helper này); (2) vấn đề là thuần test-side, không đụng application/schema/production semantics — không kích hoạt Design Dependency Preflight; (3) cần một quyết định Gate 2 riêng về cách sửa (ví dụ: đọc default cột qua Laravel Schema builder portable thay vì raw PRAGMA, hay driver-branch giống pattern đã có trong migration `2025_09_20_145756_disable_foreign_keys_for_testing.php`, hay loại bỏ hoàn toàn việc reconstruct default nếu Gate 2 xác định nó không cần thiết cho bất kỳ assertion nào) — Gate 1 KHÔNG chọn cơ chế kỹ thuật cụ thể.

## Loại trừ rõ ràng

Không đụng GAP-041 (selector/truthfulness — chỉ đọc provenance record của nó), GAP-044 (SAVEPOINT — chỉ ghi nhận tương tác che khuất, không điều tra root cause), GAP-045 (latency budget — xác nhận không liên quan vì `DashboardPerformanceTest.php` không có PRAGMA nào), hay GAP-042 (RBAC production-fidelity). Không sửa bất kỳ file test hay application code nào ở Gate 1 này. Không tự suy luận rằng Owner đã phê duyệt bất cứ điều gì — chỉ báo cáo bằng chứng và xin quyết định.
