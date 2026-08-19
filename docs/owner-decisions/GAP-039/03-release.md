---
work_id: GAP-039
gate: 3
gate_status: awaiting_owner
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_correction_or_defer"
references:
  spec: docs/superpowers/specs/2026-08-18-gap-039-mysql-testing-integrity-design.md
  plan: docs/superpowers/plans/2026-08-19-gap-039-mysql-testing-integrity-implementation.md
  branch: feature/GAP-039-mysql-testing-integrity
  pr: https://github.com/kha997/zenamanagephp/pull/268
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
  created_at: "2026-08-20T04:47:00+07:00"
  updated_at: "2026-08-20T04:47:00+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "Toàn bộ CI bắt buộc xanh trên head cuối d9ba35d0; final whole-branch review đã resolve hoàn toàn (2 Critical + 2 Important đã sửa và xác minh lại độc lập; 1 Important ghi nhận thành GAP-040 riêng theo đúng khuyến nghị của reviewer, không sửa dưới GAP-039); chi phí CI đã đo bằng số liệu thật từ gh api, không dùng lại ước tính Gate 2."
technical_evidence:
  subject_sha: "94f259ddc11a60bbc2863b024d7ecaaedd473833"
  implementation_tree_digest: "a0d22ae608284bfb2021b4994c3f2f74b4a80ee577a281a691abdf7cb37d4c1e"
  verified_pr_head_sha: "94f259ddc11a60bbc2863b024d7ecaaedd473833"
  verified_at: "2026-08-20T05:05:00+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

## Owner Summary

CI trước đây có nhiều pipeline tuyên bố đang kiểm thử trên MySQL thật nhưng âm thầm chạy SQLite. GAP-039 làm cho CI trung thực: mỗi nhóm test thuộc rõ một trong hai tầng (SQLite hoặc MySQL parity), có guardrail chống hồi quy tự động, và bài kiểm tra khoá ngoại/unique trước đây là mã chết nay đã tách thành hai bài kiểm tra độc lập, thực sự chạy được. Toàn bộ 12 task của plan đã hoàn tất, review cuối cùng trên toàn nhánh đã resolve, CI bắt buộc xanh trên head cuối. Chờ quyết định phát hành.

## Gói quyết định phát hành — GAP-039: MySQL testing integrity

**1. Vấn đề đã xảy ra là gì?**
`tests/bootstrap.php` chỉ cho `DB_CONNECTION=mysql` sống sót vào PHPUnit khi biến `ZENA_INVARIANTS_DB=mysql` cũng được export — một biến gần như không job nào từng đặt. Kết quả: nhiều job CI dựng container MySQL 8.0 thật, khai báo `DB_CONNECTION=mysql`, thậm chí migrate/seed dữ liệu thật lên đó — nhưng bước chạy test thật sự lại âm thầm chạy trên SQLite, không cảnh báo, không fail. Bài test duy nhất tuyên bố kiểm tra ràng buộc khoá ngoại chưa từng thực thi được phần đó (bị loại nhóm + bị mã chết che khuất bởi một `expectException()` khác đứng trước).

**2. Ai/việc gì bị ảnh hưởng?**
Không có người dùng cuối nào bị ảnh hưởng trực tiếp — đây là nợ kỹ thuật trong hạ tầng CI. Ảnh hưởng thực sự là niềm tin: đội kỹ thuật tưởng đang có bằng chứng MySQL thật bảo vệ các ràng buộc dữ liệu (khoá ngoại, tenant isolation, concurrency) nhưng thực ra phần lớn các job đó chưa từng chạm MySQL.

**3. Bây giờ CI đảm bảo điều gì?**
- CI không bao giờ tuyên bố "đã kiểm thử MySQL" mà không chứng minh được đó là MySQL thật (`scripts/ci/lint-mysql-claim-truthfulness.php`, chạy trong `owner-governance-lint.yml`, chặn PR nếu vi phạm).
- Bài kiểm tra khoá ngoại và bài kiểm tra unique nay là hai method độc lập, cả hai đều thực sự thực thi và không còn thể bị một nguyên nhân không liên quan che khuất (`tests/Feature/DatabaseConstraintsTest.php`).
- 5 job (`Unit/Feature/API Fast/API Slow/Integration Tests`) được phân loại lại trung thực về SQLite — nhanh hơn vì không còn dựng container MySQL không dùng tới.
- Các nhóm cần độ trung thực production (ràng buộc DB, tenant isolation, concurrency, performance nhạy database) chạy trên tầng MySQL parity thật, có preflight fail-closed (`scripts/ci/lib/mysql-fail-closed.sh`) — nếu MySQL không thật, job fail rõ ràng thay vì âm thầm chạy sai.

**4. Đã kiểm thử những gì?**
Toàn bộ 12 task của implementation plan hoàn tất, mỗi task có review riêng (spec compliance + code quality). 3 lỗi thật chỉ lộ ra khi CI thật chạy (không thể bắt được bằng review cục bộ, vì máy local không có MySQL/GitHub runner) đã được tìm và sửa: thiếu APP_KEY ở 5 job sau khi dọn bước migrate cũ; DNS `getaddrinfo` sai cho bước preflight Dusk; và một lỗi sâu về `RefreshDatabase`/`RefreshDatabaseState::$migrated` khiến bài kiểm tra khoá ngoại có thể âm thầm mất hiệu lực FK ngay trên MySQL thật — sửa qua 3 vòng thử (2 vòng đầu bị review độc lập chứng minh sai: sai thời điểm hook, rồi bị PHP trait-precedence vô hiệu hoá method override).

Sau đó, review toàn nhánh cuối cùng (không chỉ review từng task) tìm thêm 2 lỗi Critical mới: bài kiểm tra unique có cùng lỗi che khuất mà bài FK từng mắc (ném lỗi vì thiếu `tenant_id`, không phải vì vi phạm unique — đã sửa, nhắm đúng vào `slug`, cột unique thật duy nhất); và job `accessibility-tests` bị phân loại nhầm về SQLite vì `config:cache` đóng băng cấu hình MySQL mặc định trước khi override runtime có hiệu lực — đã sửa bằng cách ghi `DB_CONNECTION=sqlite` vào `.env` trước `config:cache`. Cả hai đã được một review độc lập thứ hai (scoped re-review) xác nhận RESOLVED, kèm 2 chỉnh sửa nhỏ không chặn (SQLSTATE assertion + trích dẫn dòng) đã áp dụng luôn.

Chi phí CI đã đo bằng số liệu thật (`docs/audits/2026-08-20-gap-039-ci-cost-measurement.md`), không dùng lại ước tính Gate 2: 5 job SQLite nhanh hơn (bỏ container MySQL không dùng); 2 job mới lên MySQL parity thật chỉ tốn thêm 0–16 giây; job MySQL parity tham chiếu sẵn có (Zena RBAC/Tenant Invariants) có hệ số chậm ~13–16× khớp với ước tính Gate 2 (~19.6×) đủ gần để xác nhận đây là đặc tính thật của suite, không phải sai số ước tính.

**5. Có phát hiện gì ngoài phạm vi GAP-039 không?**
Có một: `tests/TestCase.php::ensureSqliteZenaRbacTables()` chạy DDL vô điều kiện, có thể phá vỡ cách ly transaction trên tầng MySQL parity — lỗi có từ trước GAP-039, nhưng GAP-039 mở rộng phạm vi ảnh hưởng thật của nó (vì tắt cơ chế tự động migrate lại vốn từng vô tình dọn dẹp trạng thái). Đã ghi nhận thành `GAP-040` trong `OPERATIONAL_GAP_REGISTER.md` theo đúng khuyến nghị của reviewer — **không sửa dưới GAP-039**, cần quyết định/ưu tiên riêng.

Guard chống hồi quy (`lint-mysql-claim-truthfulness.php`) có 4 giới hạn phát hiện đã biết (job-scoped thay vì step-scoped; chỉ neo theo `services: image: mysql*`; không thấy được tương tác `config:cache`; chỉ quét `run:` steps trong file `*.yml`) — ghi nhận là giới hạn đã biết, không chặn phát hành.

**6. Rủi ro còn lại nếu phát hành?**
Thấp. Không có thay đổi mã ứng dụng/nghiệp vụ (`app/`, `src/`, `database/`, `routes/`, `config/` — 0 thay đổi, xác nhận qua `git diff --stat`). Không có migration nào bị chạm. Một thay đổi phạm vi kiểm thử có thật: `TenantIsolationProjectsTest` nay chỉ chạy ở bước MySQL-parity của `routes-guardrails.yml` (gắn `@group mysql-parity`), không còn chạy trong bộ test mặc định cục bộ — vẫn được CI PR gate, chỉ không còn chạy khi gõ `./vendor/bin/phpunit` trần.

## Kỹ thuật xác minh (tóm tắt cho Owner, không cần đọc code)
- CI bắt buộc: toàn bộ xanh trên head cuối `d9ba35d0` (đã `gh pr checks --watch` xác nhận).
- Local: 2302/2302 test (7 lỗi Redis cục bộ đã biết trước, không liên quan GAP-039, xác nhận qua git-stash bisect).
- Review toàn nhánh cuối: 1 vòng ban đầu ("With fixes") → 1 gói sửa → 1 vòng xác minh phạm vi hẹp → RESOLVED cả 2 Critical + 2 Important đã sửa; 1 Important ghi nhận GAP-040; các phát hiện Minor còn lại ghi trong ledger, không chặn.
- Digest cây triển khai (`implementation_tree_digest`) đã tính thật bằng `owner_governance_compute_implementation_tree_digest()` trên head `d9ba35d0`, không phải placeholder.

**Quyết định cần Owner:** phê duyệt phát hành (approved) / yêu cầu sửa (correction_requested) / hoãn (deferred).
