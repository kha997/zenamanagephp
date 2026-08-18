---
work_id: GAP-039
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_more_info_or_decline_or_defer"
references:
  spec: docs/audits/2026-08-18-gap-039-mysql-fk-testing-integrity-evidence.md
  plan: null
  branch: docs/GAP-039-mysql-fk-testing-integrity
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-18T22:43:00+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-18T22:43:00+07:00"
  updated_at: "2026-08-18T22:43:00+07:00"
generated_by: agent
---

## Owner Summary
CI tuyên bố (qua service container MySQL 8.0 + biến `DB_CONNECTION=mysql`) rằng nhiều pipeline kiểm thử chạy trên MySQL thật, nhưng thực tế **17 lượt chạy job** trong **6 workflow** (kể cả pipeline gác cổng PR chính `ci-cd.yml`) âm thầm chạy trên SQLite thay vì MySQL — vì cơ chế chọn backend chỉ "mở khoá" MySQL thật khi có biến `ZENA_INVARIANTS_DB=mysql`, mà 17/19 lượt chạy đó không hề đặt biến này. Riêng biệt, bài test duy nhất trong repo tuyên bố kiểm tra ràng buộc khoá ngoại (`QualityAssuranceTest::test_database_constraints`) không bao giờ thực thi được phần kiểm tra đó, ở bất kỳ cấu hình nào.

## Vấn đề vận hành
`tests/bootstrap.php` (bootstrap của PHPUnit) sẽ ép `DB_CONNECTION=sqlite` ngay từ đầu mỗi lần chạy test, trừ khi biến môi trường `ZENA_INVARIANTS_DB=mysql` được đặt trước đó. Biến này chỉ được đặt ở đúng 3 nơi trong toàn repo — cả 3 đều là script `scripts/ci/*-mysql` dùng cho 3 job riêng biệt (`zena-invariants-mysql`, `rfi-escalation-concurrency-mysql`, `document-workflow-concurrency-mysql`). 16 định nghĩa job khác (17 lượt chạy sau khi tính cả ma trận), trải trên 6 file workflow (`ci-cd.yml`, `button-tests.yml`, `a11y-perf-testing.yml`, `production.yml`, `routes-guardrails.yml`, `automated-testing.yml`), đều dựng container MySQL 8.0, đặt `DB_CONNECTION=mysql`, có job còn `migrate`/`db:seed` thật lên MySQL đó — nhưng bước chạy PHPUnit thật sự lại lặng lẽ dùng SQLite, không cảnh báo, không fail. Đã xác minh cơ chế này bằng thực nghiệm (chạy PHPUnit thật cục bộ với các tổ hợp biến môi trường khác nhau), không chỉ suy luận tĩnh từ YAML.

Riêng job `browser-tests` (Dusk) có biến thể nặng hơn: server `php -S` phục vụ trình duyệt thật sự kết nối MySQL thật (không đi qua `tests/bootstrap.php`), trong khi tiến trình PHPUnit/Dusk đưa ra assertion lại đi qua `tests/bootstrap.php` nên dùng SQLite — hai tiến trình cùng job, hai database khác nhau. Sự kiện này được ghi nhận là một fact-pattern cần tái hiện trực tiếp trên CI thật, chưa được xác minh sống trong lần audit này.

Độc lập với vấn đề trên: `QualityAssuranceTest::test_database_constraints` — bài test duy nhất trong repo có tên/nội dung tuyên bố kiểm tra ràng buộc khoá ngoại — bị loại khỏi MỌI lần chạy vì gắn `@group performance` (nhóm này bị `phpunit.xml` loại trừ mặc định). Ngay cả khi ép chạy riêng, phần kiểm tra khoá ngoại vẫn là mã chết: `expectException()` gọi lần thứ nhất (kiểm tra ràng buộc unique) đã ném ngoại lệ và kết thúc hàm test trước khi dòng lệnh tạo `Widget` với `dashboard_id` không tồn tại kịp chạy.

## Người dùng bị ảnh hưởng
Đội kỹ thuật (tin rằng ~3.037 test method / 507 file đang được xác nhận trên MySQL 8.0 thật — bao gồm cả pipeline gác cổng PR chính — trong khi thực tế không phải vậy); Owner/stakeholder (nhận báo cáo "CI xanh, đã test MySQL" không phản ánh đúng những gì thật sự được kiểm tra); bất kỳ ai sau này dựa vào `QualityAssuranceTest` để tin rằng ràng buộc khoá ngoại của bảng `widgets`/`dashboards` có kiểm thử hồi quy — thực tế không có.

## Bằng chứng
Toàn bộ bằng chứng (kiểm tra tĩnh từng file `.github/workflows/*.yml`, đối chiếu `scripts/ci/*mysql*`, và 2 lần chạy PHPUnit thực nghiệm cục bộ để chứng minh thứ tự ưu tiên biến môi trường thật) được ghi đầy đủ tại `docs/audits/2026-08-18-gap-039-mysql-fk-testing-integrity-evidence.md` (bản v2, đã sửa và hoàn chỉnh so với bản nháp đầu). Không có thay đổi mã ứng dụng nào được thực hiện để tạo ra bằng chứng này.

## Tác động nếu không xử lý
Bất kỳ hành vi đặc thù của MySQL (ràng buộc khoá ngoại thật, `sql_mode` nghiêm ngặt, phân biệt kiểu cột `ulid`/`json`, thứ tự sắp xếp/collation) mà các job này lẽ ra phải bắt được sẽ tiếp tục không được kiểm tra — trong khi báo cáo CI vẫn hiển thị "đã chạy trên MySQL 8.0" như thể đã được xác nhận. Rủi ro tăng dần theo thời gian khi càng nhiều tính năng phụ thuộc ngầm vào hành vi riêng của MySQL production nhưng chỉ từng được test trên SQLite.

## Phạm vi đề xuất
Gate 1 chỉ xác nhận: (1) vấn đề trên là có thật và đã được xác minh bằng bằng chứng thực nghiệm, (2) cần một quyết định Gate 2 về mức đảm bảo chất lượng mong muốn cho từng nhóm job (bắt buộc chạy MySQL thật kèm fail-closed, hay công khai đổi nhãn thành "SQLite only" và bỏ container MySQL không dùng tới), và (3) cần xử lý riêng bài `QualityAssuranceTest::test_database_constraints` (mã chết + bị loại nhóm). Gate 1 KHÔNG chọn cơ chế kỹ thuật cụ thể (biến môi trường, wrapper script, file XML riêng, v.v.) — đó là quyết định của Gate 2/đội kỹ thuật.

## Loại trừ rõ ràng
Không đụng đến GAP-037 (schema Treasury, đã duyệt Gate 2) hay GAP-038 (ràng buộc CHECK gốc MySQL cho Treasury) — đây là work item độc lập, dù được phát hiện trong quá trình chuẩn bị 2 gap đó. Không sửa bất kỳ file workflow, script CI, hay file test nào ở Gate 1 này — tài liệu này thuần tuý là bằng chứng và xin quyết định, không có thay đổi hành vi hệ thống nào đi kèm. Không tự suy luận rằng owner đã phê duyệt bất cứ điều gì.

## Đề xuất
Đội kỹ thuật đề xuất: owner phê duyệt để tiến hành thiết kế chi tiết (Gate 2) cho GAP-039, với 2 nhánh cần thiết kế riêng biệt nhưng trong cùng work item — (a) đảm bảo mọi job tuyên bố/dựng MySQL cho PHPUnit phải thật sự chạy trên MySQL đó theo kiểu fail-closed, hoặc được đổi nhãn trung thực thành SQLite; (b) sửa `QualityAssuranceTest::test_database_constraints` để phần kiểm tra khoá ngoại thật sự chạy được và không còn bị loại nhóm ngoài ý muốn.

## Decision Needed
Owner chọn một trong: Phê duyệt để tiến hành thiết kế Gate 2 / Yêu cầu thêm thông tin / Từ chối / Hoãn lại.

## What the owner is NOT being asked to decide
Owner không được yêu cầu phê duyệt bất kỳ thay đổi mã nguồn, file workflow CI, biến môi trường cụ thể, hay cơ chế kỹ thuật triển khai nào ở bước này — chỉ xác nhận vấn đề là có thật (đã có bằng chứng thực nghiệm) và đáng để thiết kế giải pháp. Owner cũng không quyết định về GAP-037, GAP-038, hay bất kỳ gap nào khác trong quyết định này. Không có mã sản xuất, không có Gate 3, không có merge nào được cấp phép bởi tài liệu này.
