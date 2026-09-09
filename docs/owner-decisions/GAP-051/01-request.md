---
work_id: GAP-051
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: null
  authority: null
decision_requested: gate1_approval
references:
  spec: docs/audits/2026-09-09-gap-051-sanctum-bearer-token-test-fidelity-evidence.md
  plan: null
  branch: audit/GAP-051-gate1-sanctum-bearer-token-fidelity
  pr: "https://github.com/kha997/zenamanagephp/pull/306"
  release: null
decision_provenance:
  trust_level: unclaimed
  recorded_by: agent
  recorded_at: "2026-09-09T00:00:00Z"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-09T00:00:00Z"
  updated_at: "2026-09-09T00:00:00Z"
generated_by: agent
---

## Owner Summary

A prior, unrelated Work ID (GAP-050, MySQL transaction isolation / process isolation — already closed and released) surfaced a lead, not a fact: some tests that claim to exercise Bearer-token/Sanctum authentication might actually be authenticating through a leftover `web` session guard rather than the presented token. This Gate-1 audit independently investigated that lead from a fresh canonical `main` checkout (`9585eed9b701cd37a7188789d1754206545ad274`). **Finding: the mechanism is real and was reproduced with a real, executed test** — Laravel Sanctum's own shipped default config (`config/sanctum.php:36`, `'guard' => ['web']`, unmodified by this repo) makes `Laravel\Sanctum\Guard::__invoke()` check the `web` session guard before ever reading a Bearer token. In a PHPUnit test, calling `$this->actingAs($user)` (Laravel's own web-guard test helper) caches a user on that guard for the rest of the test method, so a `auth:sanctum` route hit afterward authenticates via that leftover web-guard state even with **zero** Authorization header. This was proven executable with a disposable evidence test (5/5 passing, output captured in the audit doc). **However, tracing every one of this repo's actual Bearer/Sanctum-claiming test files (117 files using plain `actingAs`, 12 using `Sanctum::actingAs()`, 51+ using real `createToken()`-issued Bearer headers) found zero currently-committed tests that are actually exploiting this — the hazard is a live landmine in the test harness, not a currently-active false-green.** Separately, this audit confirms the hazard has **no production exposure**: `/api/*` routes carry no session middleware in this repo (`app/Providers/RouteServiceProvider.php:34-36`, `app/Http/Kernel.php:40-45`), so the web-guard fallback is inert against any real external request.

## Vấn đề vận hành

Nếu không có bất kỳ cơ chế phòng ngừa hồi quy nào, một người viết test tương lai (hoặc agent) hoàn toàn có thể vô tình viết một test claim là "kiểm tra Bearer token" nhưng thực chất pass nhờ trạng thái `actingAs()` (web guard) còn sót lại trong cùng một test method — do chính hành vi mặc định của Laravel Sanctum (`config('sanctum.guard') = ['web']`), không phải lỗi cấu hình riêng của repo này. Điều này làm giảm độ tin cậy của toàn bộ population test Bearer/Sanctum (54 khai báo middleware `auth:sanctum`, phủ gần như toàn bộ `/api/v1/*` và `/api/zena/*`) nếu không có rào chắn.

## Người dùng bị ảnh hưởng

- Bất kỳ agent/dev nào tương lai viết test mới cho endpoint `auth:sanctum` và tình cờ tái sử dụng một fixture/setUp có gọi `actingAs()` — test sẽ pass sai mà không ai biết.
- Owner/đội vận hành: có thể tin nhầm rằng suite test Bearer/Sanctum đang chứng minh token thật sự được kiểm tra, trong khi cơ chế nền tảng (Sanctum + PHPUnit container) có một lối tắt hợp lệ-nhưng-nguy-hiểm nếu bị lạm dụng.
- KHÔNG ảnh hưởng người dùng cuối/production — xem §3/§6 tài liệu audit: không có đường khai thác thật trên request production.

## Bằng chứng

Đầy đủ, có trích dẫn file:line và kết quả chạy PHPUnit thật (không suy đoán), nằm trong `docs/audits/2026-09-09-gap-051-sanctum-bearer-token-test-fidelity-evidence.md`. Tóm tắt:

1. `vendor/laravel/sanctum/src/Guard.php:30-38` — `Guard::__invoke()` luôn kiểm tra `config('sanctum.guard')` (repo này: `config/sanctum.php:36` = `['web']`, giá trị mặc định của chính Sanctum, không bị sửa) TRƯỚC khi đọc Bearer token.
2. Disposable evidence harness `tests/Feature/Gap051SanctumWebGuardLeakEvidenceTest.php` (commit trên nhánh này, không phải remediation) chạy thật 5/5 PASS, trong đó Scenario 1 chứng minh: `$this->actingAs($user)` + KHÔNG header Authorization nào + request tới route `auth:sanctum` → **200**, đúng ra phải là 401 nếu token là yếu tố quyết định thật sự.
3. `app/Providers/RouteServiceProvider.php:34-36` + `app/Http/Kernel.php:40-45` — `/api/*` chỉ gắn middleware group `'api'`, không có `StartSession`/`'web'` group nào — nên `Auth::guard('web')->user()` không thể resolve trên request production thật. Rủi ro CHỈ tồn tại trong tiến trình PHPUnit (container dùng chung suốt một test method), không tồn tại trên request stateless thật.
4. Rà soát toàn bộ population test claim Bearer/Sanctum (117 file `actingAs(`, 12 file `Sanctum::actingAs()`, 51+ file `createToken()` thật) — **0 test đang bị false-green theo cơ chế này** (xem bảng truy vết §5 tài liệu audit, đặc biệt file duy nhất trộn cả hai pattern trong cùng file, `tests/Feature/Crm/ServiceLineGateTest.php`, được đọc toàn bộ và xác nhận an toàn vì hai pattern nằm ở các test method riêng biệt).
5. Phát hiện phụ (không phải lỗi, chỉ là nợ đặt tên): các helper test gọi là "JWT" (`generateJwtToken`, `assertValidJwtToken`) thực chất phát hành Sanctum token thật qua `app/Services/AuthenticationService.php:114` (`$user->createToken(...)`) — tên gọi lỗi thời, không gây false-green vì cơ chế thật vẫn đúng là Sanctum.

## Tác động nếu không xử lý

Không có tác động production ngay lập tức (đã chứng minh không khai thác được qua request thật). Tác động nếu bỏ qua: bất kỳ test Bearer/Sanctum mới nào trong tương lai vô tình lặp lại pattern `actingAs()` + assert token-behavior trong cùng method sẽ pass sai mà không ai phát hiện cho tới khi có sự cố thật, làm xói mòn niềm tin vào toàn bộ suite test bảo mật API.

## Phạm vi đề xuất

GAP-051 sẽ (qua Gate 2, không phải Gate này) thiết kế MỘT cơ chế phòng ngừa hồi quy — ứng viên chính: static invariant test theo đúng pattern `RouteMiddlewareSecurityContractTest`/`ZenaRouteSurfaceInvariantTest` đã có sẵn trong repo, phát hiện bất kỳ test method nào trộn `actingAs()` (web guard) với assertion Bearer-token trên route `auth:sanctum`. Có thể kèm theo dọn nợ đặt tên "JWT" ở mục 7/8.4 của tài liệu audit nếu Owner đồng ý gộp vào cùng Gate 2.

## Loại trừ rõ ràng

- Không đụng đến bất kỳ guard/middleware/auth semantics nào — không có thay đổi hành vi xác thực nào được đề xuất ở Gate này.
- Không tái mở phạm vi GAP-050 (MySQL transaction isolation/process isolation) — đã đóng và release, chỉ được trích dẫn làm nguồn gốc của lead này.
- Không mở rộng sang RBAC/Tenant ngoài phạm vi câu hỏi Bearer/Sanctum test-fidelity này.
- Gate 1 này không chọn kiến trúc guard test cụ thể, không viết implementation plan, không sửa bất kỳ test/helper hiện có nào (chỉ thêm 1 file audit + 1 file evidence-harness disposable).

## Đề xuất

Đội đề xuất: tiến hành Gate 2 để thiết kế cơ chế phòng ngừa hồi quy (Remediation Option 1 trong tài liệu audit), vì đây là rào chắn rẻ, không xâm lấn, và đóng hẳn khả năng một hazard đã được chứng minh reproducible biến thành false-green thật trong tương lai — dù hiện tại chưa có test nào đang bị ảnh hưởng.

## Decision Needed

Owner chọn một: Approve để tiến sang Gate 2 (thiết kế cơ chế phòng ngừa hồi quy) / Yêu cầu thay đổi phạm vi bằng chứng / Từ chối (chấp nhận rủi ro ở mức tài liệu hoá, không làm gì thêm).

## What the owner is NOT being asked to decide

Owner không được yêu cầu chọn cơ chế phòng ngừa cụ thể ngay bây giờ (static lint vs runtime guard vs không làm gì — xem 4 phương án ở mục 8 tài liệu audit), không được yêu cầu phê duyệt bất kỳ thay đổi code/test/CI nào, không được yêu cầu xác nhận có tồn tại lỗ hổng production (đã loại trừ ở mục 6 tài liệu audit) — Gate 1 này chỉ hỏi liệu phát hiện có đúng, có đáng để đầu tư một Gate 2 thiết kế cơ chế phòng ngừa hay không.
