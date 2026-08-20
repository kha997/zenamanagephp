---
work_id: GAP-039
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
  spec: docs/superpowers/specs/2026-08-18-gap-039-mysql-testing-integrity-design.md
  plan: docs/superpowers/plans/2026-08-19-gap-039-mysql-testing-integrity-implementation.md
  branch: feature/GAP-039-mysql-testing-integrity
  pr: https://github.com/kha997/zenamanagephp/pull/268
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-20T09:50:00+07:00"
  owner_response_reference: "Owner chat message, 2026-08-20: 'GAP-039 — GATE 3 OWNER DECISION: APPROVE. Tôi, Owner, APPROVE Gate 3 / release cho GAP-039. Decision reviewed against: PR #268; current PR head: 51483e5802a3555c92191b6f66995c9ceb0d6133; implementation evidence subject SHA: d7779e241ad0d71efcff371d0a0aa85af7963f8f; approved implementation-tree digest: c4901e25a1ecdbefea2a271b108b478b101909200825ca985ec4b96392612a18; technical readiness: ready; exact CI result: 31 SUCCESS + 1 expected deploy SKIPPED; final whole-branch review findings resolved/adjudicated as recorded; residual risk rating: low. Owner accepts the documented residual limitations, including GAP-040 remaining a separately governed work item and the known governance evidence-poll timing limitation. Neither is a blocker to GAP-039 release. ... Gate 3 approval authorizes this merge/release.'"
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-20T04:47:00+07:00"
  updated_at: "2026-08-20T09:50:00+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "31 CI job bắt buộc SUCCESS + đúng 1 job deploy SKIPPED (không phải lỏng lẻo nói '32 xanh') trên head bằng chứng d7779e24; final whole-branch review đã resolve hoàn toàn (2 Critical + 2 Important đã sửa và xác minh lại độc lập qua scoped re-review riêng; 1 Important ghi nhận thành GAP-040 theo đúng khuyến nghị của reviewer, không sửa dưới GAP-039); chi phí CI đã đo lại bằng số liệu thật ở đúng head cuối (Part 2 của docs/audits/2026-08-20-gap-039-ci-cost-measurement.md), không dùng lại số đo ở head trung gian."
technical_evidence:
  subject_sha: "d7779e241ad0d71efcff371d0a0aa85af7963f8f"
  implementation_tree_digest: "c4901e25a1ecdbefea2a271b108b478b101909200825ca985ec4b96392612a18"
  verified_pr_head_sha: "d7779e241ad0d71efcff371d0a0aa85af7963f8f"
  verified_at: "2026-08-20T06:10:00+07:00"
owner_decision_binding:
  implementation_tree_digest: "c4901e25a1ecdbefea2a271b108b478b101909200825ca985ec4b96392612a18"
  decision_recorded_at: "2026-08-20T09:50:00+07:00"
---

## OWNER GATE 3: APPROVED

Owner phê duyệt GAP-039 Gate 3 / release lúc `2026-08-20T09:50:00+07:00`, đã review head `51483e5802a3555c92191b6f66995c9ceb0d6133` của PR #268 (đúng bằng head hiện tại của nhánh và của PR tại thời điểm phê duyệt). Owner xác nhận: implementation evidence subject SHA `d7779e241ad0d71efcff371d0a0aa85af7963f8f`; implementation-tree digest đã phê duyệt `c4901e25a1ecdbefea2a271b108b478b101909200825ca985ec4b96392612a18`; technical readiness `ready`; CI chính xác 31 SUCCESS + 1 `deploy` SKIPPED (đúng thiết kế); kết quả review toàn nhánh cuối cùng đã resolve/adjudicate như đã ghi nhận; residual risk rating `low`. Owner chấp nhận các giới hạn tồn dư đã ghi nhận, gồm `GAP-040` tiếp tục là work item quản trị riêng và giới hạn thời gian polling của evidence-freshness đã biết — cả hai đều không chặn phát hành GAP-039.

**Được phép:** merge PR #268 vào `main` theo đúng phương thức merge được duyệt sẵn của repo, không bỏ qua branch protection hay required checks; kiểm tra và báo cáo trung thực kết quả deploy workflow thật (nếu có chạy) sau merge, không tạo cơ chế deploy thủ công/ad-hoc.

**Điều kiện bắt buộc trước khi merge:** implementation-tree digest sau khi ghi nhận quyết định này phải giữ nguyên `c4901e25a1ecdbefea2a271b108b478b101909200825ca985ec4b96392612a18` (chỉ được đổi nếu commit khác biệt duy nhất là chính file `03-release.md` này); nếu digest đổi vì bất kỳ lý do nào khác, phê duyệt này KHÔNG còn bao phủ implementation đã thay đổi và phải dừng lại.

`GAP-040` (đã ghi tại `OPERATIONAL_GAP_REGISTER.md`) tiếp tục là work item quản trị riêng, KHÔNG thuộc phạm vi đóng của GAP-039.

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

**Implementation evidence subject SHA (head bằng chứng — commit làm bằng chứng CI/digest cho gói này):** `d7779e24` (`d7779e241ad0d71efcff371d0a0aa85af7963f8f`).

**PR head mà CI thật đã được kiểm tra trực tiếp:** `d7779e24` — cùng một commit, không lệch. Đây là commit gần nhất đã đưa CI thật lên GitHub Actions và được xác nhận qua `gh pr checks 268`.

**Digest cây triển khai hiện tại:** `c4901e25a1ecdbefea2a271b108b478b101909200825ca985ec4b96392612a18` — tính thật bằng `owner_governance_compute_implementation_tree_digest()` trên head `d7779e24`, không phải placeholder. Đã xác minh: chạy lại phép tính này ngay trước và ngay sau commit chỉ sửa `docs/owner-decisions/GAP-039/03-release.md` cho ra cùng một giá trị digest — đúng như quy tắc tự loại trừ (self-reference exclusion) trong `packet-schema.yml`.

**Nếu PR head hiện tại khác `d7779e24`:** commit khác biệt duy nhất là bản ghi Gate 3 này (`03-release.md`) — không có thay đổi nào khác. Nếu về sau head PR #268 tiếp tục dịch chuyển chỉ vì các lần cập nhật gói Gate 3 (không phải vì thay đổi implementation), digest ở trên vẫn còn đúng theo đúng cơ chế tự loại trừ; nếu head dịch chuyển vì bất kỳ file nào khác ngoài `03-release.md`, digest sẽ đổi và gói này lập tức trở nên STALE (được `check-evidence-freshness.sh`/`owner_governance_lint.php` tự phát hiện, không cần thông báo thủ công).

**Trạng thái CI chính xác trên head `d7779e24`:** 31 job bắt buộc **SUCCESS**, đúng 1 job (`deploy`) **SKIPPED** (đúng theo thiết kế — chỉ chạy khi merge vào `main`, không chạy trên nhánh PR). Không phải cách nói lỏng lẻo "32 job xanh" — đã liệt kê và đếm chính xác qua `gh pr checks 268`, bao gồm cả việc `Owner Governance Lint` job's evidence-freshness self-check ban đầu fail vì tự chạy sớm hơn các job MySQL-parity chậm hơn (Treasury ~27 phút) — đây là hành vi polling có giới hạn 5 phút đã biết trước trong chính script, không phải lỗi thật; đã đợi các job khác xanh rồi chạy lại đúng job đó, kết quả xanh sạch.

**Kết quả review toàn nhánh cuối cùng:** 1 vòng ban đầu trả về "With fixes" (2 Critical + 4 Important) → 1 gói sửa duy nhất (theo đúng quy tắc "một vòng sửa, một vòng review phạm vi hẹp") → 1 vòng xác minh phạm vi hẹp độc lập xác nhận **RESOLVED** cả 4 mục (2 Critical đã sửa đúng; 2 Important đã sửa đúng cùng lúc; 1 Important ghi nhận thành `GAP-040` theo đúng khuyến nghị của reviewer, không sửa dưới GAP-039). 2 điểm còn sót nhỏ không chặn do vòng xác minh phạm vi hẹp nêu ra (SQLSTATE assertion chưa phân biệt loại vi phạm; trích dẫn dòng GAP-040 lệch ~7/1 dòng) đã được sửa luôn trong cùng phiên. Các phát hiện Minor còn lại + 4 giới hạn phát hiện đã biết của guard chống hồi quy được ghi trong ledger và trong `mandatory_technical_gate_summary`, không chặn phát hành.

**Chi phí CI hiện tại (bằng chứng đã đo lại đúng ở head cuối):** `docs/audits/2026-08-20-gap-039-ci-cost-measurement.md` Part 2 (FINAL, AUTHORITATIVE) — đo lại toàn bộ job có khả năng bị ảnh hưởng bởi các commit sau lần đo đầu tiên, dùng timestamp thật từ `gh api` trên head `14189e2d` (run `32305896514`, cùng nội dung code với head bằng chứng `d7779e24` — 2 commit chênh lệch giữa chúng chỉ là tài liệu governance, không đổi code CI). Kết luận: cả 5 job chuyển thật về SQLite lẫn 2 job chuyển thật lên MySQL-parity đều **nhanh hơn** mốc trước GAP-039 (chi phí ròng do GAP-039 gây ra là **âm**, không phải dương); job MySQL-parity tham chiếu sẵn có giữ hệ số chậm ~14.5×, cùng dải với ước tính Gate 2 (~19.6×) qua cả 3 lần đo (baseline/trung gian/cuối); dị thường Treasury (8.520s ở head trung gian) đã được đo lại ở head cuối và xác nhận là sự cố hạ tầng một lần, không tái diễn (quay lại ~1.614s, khớp baseline ~1.598s) — không phải chi phí do GAP-039, không phải hồi quy.

**Quyết định cần Owner:** phê duyệt phát hành (approved) / yêu cầu sửa (correction_requested) / hoãn (deferred).
