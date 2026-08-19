---
work_id: GAP-039
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_changes_or_decline"
references:
  spec: docs/superpowers/specs/2026-08-18-gap-039-mysql-testing-integrity-design.md
  plan: null
  branch: docs/GAP-039-gate2-continuation
  pr: "https://github.com/kha997/zenamanagephp/pull/267"
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
  created_at: "2026-08-18T23:37:00+07:00"
  updated_at: "2026-08-19T16:17:00+07:00"
generated_by: agent
---

Gate 2 tiếp nối từ Gate 1 đã được Owner APPROVE tại PR #266 (lịch sử/bằng chứng Gate 1 vẫn giữ nguyên ở đó).

## Owner Summary
CI hiện có nhiều pipeline nói rằng đang kiểm thử trên MySQL thật, nhưng phần lớn trong số đó âm thầm chạy trên SQLite. Đề xuất: công khai chia mọi nhóm kiểm thử thành 2 tầng rõ ràng — SQLite (nhanh, cho logic ứng dụng) và MySQL parity (cho các kiểm tra mà sai khác giữa 2 loại database có thể ảnh hưởng đến tính đúng đắn ở production) — và đảm bảo CI không bao giờ tự nhận đã kiểm thử MySQL nếu không chứng minh được điều đó thật.

## Hiện tại / Sau khi thay đổi
**Hiện tại:** Nhiều job CI dựng container MySQL 8.0, khai báo `DB_CONNECTION=mysql`, có nơi còn migrate/seed dữ liệu thật lên MySQL đó — nhưng bước chạy test thật sự lại âm thầm dùng SQLite, không cảnh báo, không fail. Bài test duy nhất tuyên bố kiểm tra ràng buộc khoá ngoại không bao giờ thực thi được phần đó (bị loại nhóm + là mã chết). Nghiên cứu bổ sung ở Gate 2 xác nhận thêm: job kiểm thử trình duyệt (Dusk/`browser-tests`) hiện **thực sự** đã dùng MySQL thật — nhưng đó là kết quả tình cờ của cách một công cụ bên thứ ba tự xử lý cấu hình, chưa từng được kiểm chứng hay bảo vệ có chủ đích, nên vẫn có nguy cơ âm thầm hỏng trong tương lai như các job khác.

**Sau khi thay đổi:** Mỗi nhóm test công khai thuộc một trong hai tầng:
- **SQLite** — nhanh, dùng cho logic ứng dụng không phụ thuộc vào loại database engine.
- **MySQL parity** — dùng cho các kiểm tra mà sai khác giữa database có thể ảnh hưởng tính đúng đắn ở production.

CI không bao giờ được báo hoặc ngầm ám chỉ "đã kiểm thử MySQL" khi không chứng minh được đó là MySQL thật. Có cơ chế bảo vệ (guardrail) để một thay đổi CI trong tương lai không thể âm thầm phá vỡ cam kết này. Bài kiểm tra ràng buộc khoá ngoại và ràng buộc unique được tách thành 2 kiểm tra độc lập, cả hai đều thực sự chạy được.

## Các nhóm bắt buộc/ưu tiên chạy trên MySQL parity
- Ràng buộc database (khoá ngoại, unique, v.v.)
- Cơ chế bảo vệ cách ly dữ liệu theo tenant (tenant/data-isolation safeguards)
- Concurrency/khoá đồng thời (concurrency/locking)
- Luồng end-to-end/trình duyệt toàn diện, nơi sự khác biệt với production thực sự quan trọng
- Các kiểm tra hiệu năng nhạy với đặc điểm database

**Không yêu cầu toàn bộ bộ test (hiện ~3.037 test method) phải chạy trên MySQL** — phần lớn logic nghiệp vụ, kiểm tra HTTP contract, validation không phụ thuộc loại database engine và tiếp tục chạy nhanh trên SQLite như mặc định.

## Chi phí CI
Các nhóm được chuyển sang tầng MySQL parity sẽ chạy chậm hơn đáng kể so với SQLite (dữ liệu đo được trong lịch sử CI thật của repo cho thấy chênh lệch tới hàng chục lần đối với một nhóm test truy vấn nhiều). Đề xuất Owner chấp nhận mức tăng chi phí này ở phạm vi hợp lý cho các nhóm parity (không phải toàn bộ suite) — với điều kiện chi phí thực tế phải được đo lại bằng số liệu thật trong evidence khi triển khai (implementation), không dừng ở ước tính thiết kế.

## Kịch bản chấp nhận
- Một job tuyên bố/dựng MySQL cho PHPUnit phải chứng minh được đó thật sự là MySQL trước khi test chạy, nếu không phải fail — không được âm thầm chạy SQLite rồi báo xanh.
- Một nhóm chỉ cần SQLite không được tự nhận/ngầm hiển thị như thể đã kiểm thử trên MySQL.
- Kiểm tra ràng buộc khoá ngoại và kiểm tra ràng buộc unique phải cùng thực thi được độc lập — không cái nào là mã chết hay bị loại nhóm ngoài ý muốn.
- Nếu tương lai có thay đổi CI làm một job quay lại âm thầm dùng SQLite dù tuyên bố MySQL, phải có cơ chế tự động bắt được, không phải chờ audit thủ công phát hiện lại.
- Chi phí CI tăng thêm cho các nhóm MySQL parity phải được đo bằng số liệu thật và báo cáo lại trong evidence khi triển khai, không dừng ở ước tính thiết kế.

## Vòng đời / thẩm quyền sau khi Gate 2 được duyệt
Nếu Owner **APPROVE** Gate 2 này: đội kỹ thuật được phép lập kế hoạch triển khai (implementation plan) và tiến hành implementation, testing, review. **Gate 3 chỉ mở ra bước quyết định của Owner sau khi implementation đã hoàn tất và đủ điều kiện kỹ thuật (technical readiness)** — Gate 3 approval là bước duy nhất cho phép release/merge/deploy, không phải một bước xét duyệt trung gian nào khác chen giữa Gate 2 và implementation.

## Loại trừ phạm vi
Không đụng đến GAP-037 (schema Treasury) hay GAP-038 (ràng buộc CHECK Treasury) — work item độc lập. Không mở rộng phạm vi thành dọn dẹp trùng lặp giữa các workflow CI (một phát hiện riêng, đã ghi nhận trong spec kỹ thuật, không xử lý trong GAP-039). Gate 2 này không thay đổi bất kỳ file workflow, `tests/bootstrap.php`, cấu hình PHPUnit, test code, hay production code nào — thuần tuý là thiết kế/quyết định phạm vi.

## Decision Needed
Owner chọn một trong: **Approve** (cho phép lập kế hoạch triển khai và bắt đầu implementation) / **Request changes** (yêu cầu sửa lại thiết kế) / **Decline**.

## What the owner is NOT being asked to decide
Owner không được yêu cầu chọn tên script, biến môi trường, file cấu hình PHPUnit/Dusk, cơ chế kiểm tra PDO, chiến lược parse YAML, cấu trúc thư viện shell, hay tên/tổ chức file test cụ thể — toàn bộ những chi tiết đó thuộc `docs/superpowers/specs/2026-08-18-gap-039-mysql-testing-integrity-design.md`, do đội kỹ thuật sở hữu. Owner chỉ được yêu cầu quyết định về nguyên tắc phân tầng SQLite/MySQL parity, yêu cầu về guardrail chống hồi quy, yêu cầu tách kiểm tra khoá ngoại/unique độc lập, và mức chi phí CI tăng thêm chấp nhận được. Quyết định này không phê duyệt implementation, không mở Gate 3, không cho phép merge hay deploy.
