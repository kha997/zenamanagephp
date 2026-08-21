---
work_id: GAP-041
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-08-21-gap-041-zero-test-performance-ci-evidence.md
  plan: null
  branch: docs/GAP-041-gate1-investigation
  pr: 276
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-21T15:30:00+07:00"
  owner_response_reference: "Owner chat message, 2026-08-21: 'GAP-041 — OWNER GATE 1 DECISION: APPROVE... Gate 1 PR: #276, reviewed Gate-1 head 98a45eac9d2819caa0ea40c572f2a0ac8d675aa3, canonical baseline 0b77747551a3e0da08e3e41c73a0a88f529b19f3... I APPROVE GAP-041 Gate 1. I accept the Gate-1 evidence and the following problem statement: GAP-041 is a CI truthfulness/test-selection integrity defect. performance-tests has been live-proven on exact canonical main to pass real-MySQL preflight, select zero PHPUnit tests, and still finish successfully. performance-budget and performance-heavy are independently proven to have zero matching test groups by deterministic repo inspection/local PHPUnit selection, but their current live workflows fail earlier because of a separate missing-script defect, so they must NOT be described as currently live false-green. This approval authorizes Gate 2 design only. It does NOT authorize implementation, merge, release, deployment, GAP-042 work, or repair of the unrelated missing-script defect.' Owner also directed: rehydrate from repository not prior chat before any mutation; reconcile OPERATIONAL_GAP_REGISTER.md GAP-041 wording so it no longer implies all three jobs are currently live-green while executing zero tests (factual SSOT correction only, not scope expansion); do not register/investigate/fix the missing .github/scripts/ci_prepare_testing_env.sh defect under GAP-041; Gate 2 must reconstruct intended semantics of all three jobs independently rather than jumping to '--group' as the answer, separate correct-selection from fail-closed-truthfulness as two design concerns, explicitly address the masked-job (performance-budget/performance-heavy) verification problem, and finish at gate_status: awaiting_owner with no implementation plan or code."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-21T08:01:00+07:00"
  updated_at: "2026-08-21T15:30:00+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner phê duyệt GAP-041 Gate 1 lúc `2026-08-21T15:30:00+07:00`, đã review head `98a45eac9d2819caa0ea40c572f2a0ac8d675aa3` của PR #276 (canonical baseline `0b77747551a3e0da08e3e41c73a0a88f529b19f3`). Owner xác nhận GAP-041 là một lỗi CI truthfulness/test-selection integrity có thật: `performance-tests` đã được chứng minh SỐNG trên đúng head chính tắc hiện tại là vượt qua preflight MySQL thật, chọn 0 test PHPUnit, và vẫn báo hoàn tất thành công. `performance-budget` và `performance-heavy` được chứng minh độc lập là có 0 test khớp group bằng kiểm tra repo tất định/lựa chọn PHPUnit cục bộ, nhưng workflow sống hiện tại của chúng fail sớm hơn vì một lỗi thiếu-script riêng biệt — do đó KHÔNG được mô tả là đang live false-green hiện nay.

Quyết định này CHỈ cho phép chuyển sang Gate 2 design. KHÔNG cấp phép implementation, merge, release, deployment, công việc GAP-042, hay sửa lỗi thiếu-script không liên quan. Owner cũng chỉ đạo: rehydrate từ repository (không phải chat trước) trước bất kỳ thay đổi nào; đối chiếu lại cách diễn đạt dòng GAP-041 trong `OPERATIONAL_GAP_REGISTER.md` để không còn ngụ ý cả 3 job hiện đang live-green trong khi chạy 0 test (chỉ là sửa SSOT thực tế, không mở rộng phạm vi); không đăng ký/điều tra/sửa lỗi thiếu `.github/scripts/ci_prepare_testing_env.sh` dưới GAP-041; Gate 2 phải tái dựng ngữ nghĩa dự kiến của cả 3 job một cách độc lập thay vì nhảy thẳng đến `--group`, tách "lựa chọn test đúng" và "fail-closed truthfulness" thành 2 mối quan tâm thiết kế riêng, giải quyết rõ ràng vấn đề xác minh job-bị-che-khuất (`performance-budget`/`performance-heavy`), và kết thúc ở `gate_status: awaiting_owner` mà không có implementation plan hay code.

## Owner Summary
Ba job CI tuyên bố kiểm thử hiệu năng trên MySQL thật (`automated-testing.yml`'s `performance-tests`, `a11y-perf-testing.yml`'s `performance-budget`/`performance-heavy`) — cả ba đều dựng container MySQL 8.0 thật, đặt biến kết nối thật, và chạy fail-closed preflight (`zena_mysql_ensure_connection`/`zena_mysql_preflight_connection`, cơ chế GAP-039) trước bước test. Nhưng lệnh PHPUnit thực tế của cả ba job chọn **0 test**, do lệch tên nhóm (`@group`) giữa lệnh CI và annotation trên chính các file test — và với `performance-tests`, job vẫn báo `success` dù không chạy test nào.

## Vấn đề vận hành
`performance-tests`: lệnh `php artisan test "${perf_file}"` không có cờ `--group`, trong khi `phpunit.xml` mặc định loại trừ nhóm `performance` — và 2 class test duy nhất trong thư mục này chỉ gắn `@group performance`, không nhóm nào khác. Xác nhận SỐNG trên đúng head `main` hiện tại (SHA `0b777475`, run CI thật): bước preflight log "Preflight MySQL connection succeeded", ngay sau đó bước PHPUnit log "INFO No tests found.", và job vẫn kết thúc `success`.

`performance-budget`/`performance-heavy`: lệnh dùng `--group performance_budget`/`--group performance_heavy`, nhưng **không có bất kỳ test nào trong toàn repo** gắn 1 trong 2 tên nhóm này (xác nhận bằng tìm kiếm toàn repo, không phải suy luận). Cơ chế lựa chọn 0-test được xác nhận cục bộ (PHPUnit thoát mã 0 kèm "No tests executed!"). Tuy nhiên, khi thử tái hiện sống trên CI thật đúng head hiện tại, phát hiện thêm: cả 2 job này hiện đang FAIL sớm hơn — ở bước "Prepare testing environment" — vì file `.github/scripts/ci_prepare_testing_env.sh` **không tồn tại trong repo** (xác nhận `git log --all` không có commit nào tạo file này). Đây là một lỗi khác, không liên quan đến GAP-041, đang che khuất việc chứng minh sống hiện tượng "xanh giả" cho riêng 2 job này — dù cơ chế 0-test bên dưới vẫn là thật và sẽ lộ ra ngay khi lỗi file-thiếu kia được xử lý riêng.

## Người dùng bị ảnh hưởng
Đội kỹ thuật (tin rằng `performance-tests` đang xác nhận hiệu năng trên MySQL thật mỗi PR/push vào `main`, trong khi thực tế 0 test chạy); Owner/stakeholder (nhìn check xanh trên PR mà không biết không có test hiệu năng nào thật sự thực thi — dù đây không phải required check chặn merge); bất kỳ ai sau này viết thêm test hiệu năng mà tưởng rằng gắn `@group performance_budget`/`performance_heavy` là đủ để job tương ứng chạy nó — job đó chưa từng chạy được test nào mang 2 tên nhóm này.

## Bằng chứng
Toàn bộ bằng chứng (đọc tĩnh workflow/`phpunit.xml`/annotation, đối chiếu cơ chế PHPUnit 11.5.56 tại `vendor/phpunit/phpunit/src/TextUI/Configuration/Merger.php:712-725`, tái hiện cục bộ, và 2 lượt `workflow_dispatch` sống trên đúng head `main` hiện tại — run `32460823422` cho `automated-testing.yml`, run `32460830217` cho `a11y-perf-testing.yml`) được ghi đầy đủ tại `docs/audits/2026-08-21-gap-041-zero-test-performance-ci-evidence.md`, có phân loại rõ LIVE/STATIC/LOCAL/HISTORICAL cho từng khẳng định. Không có thay đổi mã workflow/test/application nào được thực hiện để tạo bằng chứng này.

## Tác động nếu không xử lý
`performance-tests` tiếp tục báo `success` trên mỗi PR/push vào `main` mà không xác nhận bất kỳ hồi quy hiệu năng nào — tạo cảm giác an toàn giả (false release confidence), dù không phải required check. `performance-budget`/`performance-heavy` tiếp tục không có test nào từng chạy được dưới tên nhóm mà job của chúng dùng để lọc — kể cả sau khi lỗi file-thiếu riêng biệt kia được sửa, hai job này vẫn sẽ xanh giả trở lại trừ khi GAP-041 cũng được xử lý.

## Phạm vi đề xuất
Gate 1 chỉ xác nhận: (1) hiện tượng "job tuyên bố coverage MySQL thật nhưng chọn 0 test mà vẫn xanh" là có thật, đã xác minh SỐNG cho `performance-tests` trên đúng head `main` hiện tại, và được chứng minh bằng cơ chế + tìm kiếm toàn repo cho `performance-budget`/`performance-heavy`; (2) cần một quyết định Gate 2 về cơ chế kỹ thuật để mọi job tuyên bố chạy 1 tập test cụ thể phải hoặc chạy được tập ≥1 test đó, hoặc fail rõ ràng khi lựa chọn ra 0 test ngoài dự kiến. Gate 1 KHÔNG chọn cơ chế kỹ thuật cụ thể (đổi `--group`, đổi annotation test, thêm bước kiểm tra `tests_run > 0`, v.v.) — đó là quyết định của Gate 2.

## Loại trừ rõ ràng
Không đụng đến GAP-040 (đã release qua PR #272, squash `aab48a23`) — không sửa đổi trạng thái của gap đó. Không điều tra hay xử lý GAP-042 (rủi ro RBAC/production-fidelity chưa xác minh) — off-limits theo chỉ đạo Owner. Không sửa bất kỳ file workflow/script/test nào ở Gate 1 này. Không tự ý mở rộng phạm vi để sửa luôn lỗi file `.github/scripts/ci_prepare_testing_env.sh` bị thiếu (§1.4 trong audit) — đây là lỗi khác, được nêu để Owner biết, đề xuất đăng ký thành work item riêng nếu Owner muốn theo dõi, không gộp/sửa dưới GAP-041. Không tối ưu hiệu năng, không đổi ngưỡng performance budget, không đụng RBAC/production schema.

## Đề xuất
Đội kỹ thuật đề xuất: Owner phê duyệt để tiến hành thiết kế chi tiết (Gate 2) cho GAP-041 — làm cho performance CI trung thực: mọi job tuyên bố một tập test cụ thể phải hoặc thực thi tập ≥1 test đó, hoặc fail rõ ràng khi lựa chọn bất ngờ ra 0 test.

## Decision Needed
Owner chọn một trong: Phê duyệt để tiến hành thiết kế Gate 2 / Yêu cầu thay đổi phạm vi / Từ chối.

## What the owner is NOT being asked to decide
Owner không được yêu cầu phê duyệt bất kỳ thay đổi mã nguồn, file workflow CI, hay cơ chế kỹ thuật triển khai cụ thể nào ở bước này — chỉ xác nhận vấn đề là có thật (đã có bằng chứng SỐNG cho `performance-tests`, bằng chứng cơ chế+tìm kiếm toàn repo cho 2 job còn lại) và đáng để thiết kế giải pháp. Owner cũng không quyết định về GAP-040, GAP-042, hay lỗi file thiếu `.github/scripts/ci_prepare_testing_env.sh` trong quyết định này. Không có mã sản xuất, không có Gate 3, không có merge nào được cấp phép bởi tài liệu này.
