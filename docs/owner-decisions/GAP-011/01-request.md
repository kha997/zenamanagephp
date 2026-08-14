---
work_id: GAP-011
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: null
  plan: null
  branch: docs/GAP-011-debug-route-cleanup-gate1-prep
  pr: https://github.com/kha997/zenamanagephp/pull/260
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-13T16:25:30+07:00"
  owner_response_reference: "Owner Gate 1 approval with binding scope clarification, in-session on 2026-08-13: 'GAP-011 — Gate 1 Owner Decision: APPROVE, with binding scope clarification. Tôi xác nhận GAP-011 là vấn đề có thật và đáng xử lý. Gate 1 scope được phê duyệt chỉ gồm Class A + Class B ... Class C is explicitly OUT OF SCOPE for GAP-011 implementation ... Không được bundle Class C vào Gate 2 của GAP-011. Nếu các Class C findings cần remediation, hãy giữ chúng như separate follow-up gap candidate(s) và trình Owner riêng sau; không tự mint Work ID, không tự mở Gate 1 khác, và không sửa register trong GAP-011 chỉ để hấp thụ chúng. Binding governance clarification: Gate 1 xác định scope; Gate 2 chỉ được thiết kế solution trong scope A+B đã duyệt. Gate 2 không được mở rộng scope sang Class C.'"
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-13T14:01:12+07:00"
  updated_at: "2026-08-13T16:25:30+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED — scope Class A + Class B only

Owner phê duyệt GAP-011 Gate 1 trong phiên làm việc ngày 2026-08-13, xác nhận vấn đề có thật và đáng xử lý. **Phạm vi được phê duyệt chỉ gồm Class A (21 route `_debug/*` gated) + Class B (7 compatibility redirect vào `_debug/*`).** Class C (development helper khác ngoài `_debug/*`, xem mục riêng bên dưới) **KHÔNG nằm trong phạm vi implementation của GAP-011** — được giữ lại làm discovered evidence / candidate cho (các) follow-up gap riêng, sẽ trình Owner riêng sau, không tự mint Work ID hay mở Gate 1 khác, không sửa register trong phạm vi GAP-011 chỉ để hấp thụ chúng. **Binding governance clarification:** Gate 1 xác định scope; Gate 2 chỉ được thiết kế solution trong scope A+B đã duyệt, không được mở rộng sang Class C.

## Owner Summary
21 route `/_debug/*` đang thật sự mount ở runtime (Class A), toàn bộ đã được `DebugGateMiddleware` chặn 404 ngoài `local/testing/development` — **không có claim lỗ hổng production**. Cộng thêm 7 compatibility redirect vào `_debug/*` (Class B) nằm ngoài namespace gated nhưng cùng thuộc legacy/debug routing surface. Vấn đề cần Gate 2 xử lý: ranh giới kiến trúc/ownership cho Class A+B, và thiếu invariant tổng quát chống debug-route drift cho 2 lớp này.

## Vấn đề vận hành
Phát biểu chính xác, súc tích theo đúng 3 điểm:
1. **Truy cập production vào 21 endpoint `_debug/*` hiện đã được bảo vệ** — `DebugGateMiddleware` (`app/Http/Middleware/DebugGateMiddleware.php:16-24`, không đổi) chặn toàn bộ 21 route trả 404 ngoài `local/testing/development`, xác nhận từng route một, không suy đoán.
2. **Ranh giới kiến trúc/debug-route chưa hoàn chỉnh**, vì ba lý do cộng gộp: (a) bề mặt debug lớn — không chỉ 21 route `_debug/*`, còn có 7 route redirect vào `_debug/*` nằm ngoài namespace gated, và một nhóm route/helper phát triển khác (`routes/api-simple.php`, `routes/debug_api.php`, `local/dev-login/operator`, các view test/demo trong `web.php`) hoàn toàn nằm ngoài `_debug/*`, được bảo vệ bằng cơ chế khác (env-gate tại thời điểm đăng ký route, không phải middleware runtime); (b) `tests/Feature/DebugRouteDocumentationInvariantTest.php` (đóng cho GAP-027) chỉ so khớp một danh sách URI cố định, không phải ràng buộc tổng quát "mọi route debug hiện tại và tương lai phải qua gate"; (c) một số helper phát triển này thực thi logic có đặc quyền thật (không chỉ redirect) — chi tiết đầy đủ ở mục Bằng chứng.
3. **Không có claim exploit production nào ở đây.** Toàn bộ route/helper thực thi logic đặc quyền được liệt kê dưới đây đều bị chặn đăng ký (không tồn tại trong route table) ngoài `local`/`testing`, xác minh trực tiếp bằng cách chạy `route:list` dưới `APP_ENV=production` (mặc định) và không thấy chúng xuất hiện.

## Người dùng bị ảnh hưởng
- Engineering agents/contributors đọc `routes/web.php` lần đầu — dễ nhầm route debug với route thật.
- Không ảnh hưởng người dùng cuối/khách hàng — production đã an toàn theo middleware, kể cả với route redirect ngoài nhóm (giải thích ở mục Bằng chứng).

## Bằng chứng
Baseline: `origin/main` commit `1024b686`, 2026-08-13. Phương pháp: `php artisan route:list --json` (không dùng `grep` — grep đếm dòng source text, không phải route runtime, đã gây sai số 18 vs 21 ở bản trước). **Lưu ý phương pháp quan trọng:** chạy `route:list` không có `.env` mặc định resolve `APP_ENV=production`, nên route chỉ đăng ký có điều kiện trong `local`/`testing` (`if (app()->environment([...]))`) sẽ KHÔNG xuất hiện trong lần quét đầu — đây chính xác là lý do route ngoài nhóm bị bỏ sót ở bản trước. Đã chạy lại có kiểm soát biến môi trường (`APP_ENV=local`, và `APP_ENV=local APP_DEBUG=true`) để lấy đủ toàn bộ inventory, đối chiếu chéo với route table dưới `production` mặc định.

### Class A — Gated `/_debug/*` runtime routes (21)
Toàn bộ đều có `App\Http\Middleware\DebugGateMiddleware` trong middleware stack, xác nhận từng route, active ở mọi environment (route luôn đăng ký, middleware chặn theo môi trường lúc request):

| Method | URI |
|---|---|
| GET\|HEAD | `_debug/admin-dashboard-test`, `_debug/dashboard-data`, `_debug/final-integration`, `_debug/performance-optimization`, `_debug/tenant-dashboard-test`, `_debug/test`, `_debug/test-accessibility`, `_debug/test-api-admin-stats`, `_debug/test-auth`(+`Authenticate`), `_debug/test-auth-direct`(+`Authenticate`), `_debug/test-bypass`, `_debug/test-login/{email}`, `_debug/test-minimal`, `_debug/test-mobile-optimization`, `_debug/test-mobile-simple`, `_debug/test-permissions`, `_debug/test-session-auth`, `_debug/test-simple`, `_debug/test-web-guard`(+`Authenticate:web`), `_debug/testing-suite` |
| POST | `_debug/test-login-simple` |

**Claim sai đã gỡ bỏ:** `_debug/info`, `_debug/projects-test`, `_debug/users-debug`, `_debug/tasks-debug` **KHÔNG active** — `DebugRouteDocumentationInvariantTest.php::test_current_page_tree_archived_debug_claims_do_not_have_runtime_route_evidence()` khẳng định archived; xác nhận độc lập bằng chính 21-route inventory (các URI đó không có mặt).

**Login/auth helper trong Class A** (liệt kê để nhận diện, KHÔNG quyết định disposition): `_debug/test-auth`, `_debug/test-auth-direct`, `_debug/test-bypass`, `_debug/test-login/{email}`, `_debug/test-login-simple`, `_debug/test-web-guard`, `_debug/test-session-auth`, `_debug/test-permissions` — toàn bộ gated.

### Class B — Ungated compatibility redirect vào `/_debug/*` (7, đầy đủ, không chỉ 1)

| Method | URI nguồn | Đích | Môi trường đăng ký | Middleware | Thực thi logic đặc quyền, hay chỉ redirect? |
|---|---|---|---|---|---|
| GET\|HEAD | `/dashboard-data` | `/_debug/dashboard-data` | luôn (mọi env) | `web` | Chỉ redirect (`RedirectController`) |
| GET\|HEAD | `/test-api-admin-dashboard` | `/_debug/test-api-admin-stats` | luôn | `web` | Chỉ redirect |
| GET\|HEAD | `/test-permissions` | `/_debug/test-permissions` | luôn | `web` | Chỉ redirect |
| GET\|HEAD | `/test-api-admin-stats` | `/_debug/test-api-admin-stats` | luôn | `web` | Chỉ redirect |
| GET\|HEAD | `/test-session-auth` | `/_debug/test-session-auth` | luôn | `web` | Chỉ redirect |
| GET\|HEAD | `/test-login/{email}` | `/_debug/test-login/{email}` | luôn | `web` | Chỉ redirect |
| GET\|HEAD | `/debug/{path?}` (wildcard) | `/_debug/{path}` | chỉ `local` | `web` | Chỉ redirect (closure gọi `redirect(...)`) |

Cả 7 route đều xác nhận (đọc trực tiếp `routes/web.php:781-788` và `:583-587`) chỉ thực hiện redirect 301 tới đích đã gated — không route nào trong nhóm này tự thực thi logic đặc quyền. 6 route đầu đăng ký ở MỌI environment (kể cả production — nhưng đích cuối vẫn bị `DebugGateMiddleware` chặn ngoài local/testing/development, nên không có exploit path thật). Route thứ 7 chỉ đăng ký ở `local`.

### Discovered evidence / separate follow-up candidate(s) — Class C, KHÔNG PHẢI phạm vi implementation của GAP-011

Development helper khác ngoài `/_debug/*`, không phải redirect (đầy đủ, từ 3 nguồn: `routes/web.php`, `routes/api.php`, `routes/debug_api.php`). Owner đã xác nhận rõ: đây là evidence cho thấy development-helper protection đang phân mảnh, nhưng **không được sửa, di chuyển, xoá, harden hay chuẩn hoá dưới GAP-011**. Nếu cần remediation, sẽ là (các) follow-up gap candidate riêng, trình Owner riêng sau — không tự mint Work ID, không tự mở Gate 1 khác trong phiên này.

| Method | URI | Môi trường đăng ký | Middleware | Thực thi logic đặc quyền, hay chỉ redirect/view? |
|---|---|---|---|---|
| GET | `local/dev-login/operator` | `local`+`testing` | `web` | **CÓ ĐẶC QUYỀN** — tra user theo email query param, `Auth::login($user)` trực tiếp, không cần mật khẩu |
| POST | `api/login` (trong `routes/debug_api.php`) | `local`+`testing` **và** `config('app.debug')=true` | `api` | **CÓ ĐẶC QUYỀN** — kiểm tra mật khẩu cứng `zena1234` với danh sách 8 email demo cố định, set session user giả nếu khớp |
| POST | `api/v1/upload-document` (trong `routes/debug_api.php`) | `local`+`testing`+`app.debug` | `api` | **CÓ ĐẶC QUYỀN** — nhận file upload thật, ghi log, có side-effect |
| GET | `admin-dashboard-complete` | `local`+`testing` | `web` | Chỉ render view |
| GET | `admin-layout-system` | `local`+`testing` | `web` | Chỉ render view |
| GET | `test-css-inline` | `local`+`testing` | `web` | Chỉ render view |
| GET | `test-tailwind` | `local`+`testing` | `web` | Chỉ render view |
| GET | `calendar-complete` | `local`+`testing` | `web` | Redirect nội bộ tới route thật (`app.calendar`), không phải `_debug/*` |
| GET | `projects-complete` | `local`+`testing` | `web` | Redirect nội bộ tới route thật (`app.projects`) |
| GET | `tasks-complete` | `local`+`testing` | `web` | Redirect nội bộ tới route thật (`app.tasks`) |
| GET | `api/test` | `local`+`testing` | `api` | Chỉ JSON tĩnh |
| GET | `api/test-controller` | `local`+`testing` | `api` | Gọi controller thật (`getCsrfToken`) — hành vi hợp lệ, không đặc quyền |
| GET | `api/test-simple`, `api/test-error`, `api/documents-simple` (trong `debug_api.php`) | `local`+`testing`+`app.debug` | `api`(một số + `auth:sanctum,tenant.isolation,rbac`) | Chỉ JSON tĩnh/test, một nhóm đã sau `auth:sanctum` nên không phải bypass |

**Đã xác minh không tồn tại trong route table production mặc định** (chạy `route:list` dưới `APP_ENV=production`, không xuất hiện) — đúng như claim môi trường ở trên, không suy đoán.

### Đã kiểm tra và phân loại rõ ràng — KHÔNG bỏ sót, nhưng ngoài phạm vi GAP-011
- **`_dusk/login/{userId}/{guard?}`, `_dusk/logout/{guard?}`, `_dusk/user/{guard?}`** — route do package Laravel Dusk (công cụ test trình duyệt) tự đăng ký ngoài production, không phải mã debug tự viết. Ngoài phạm vi ranh giới kiến trúc debug của GAP-011.
- **`api-simple/*`** (6 route: `projects`, `projects-with-auth`, `projects-with-middleware`, `test`, `test-auth`, `test-new-middleware`) — đã là quyết định bảo mật riêng, tách biệt, xử lý trước đây (`routes/api-simple.php`, chỉ đăng ký ở `local`, xem commit `7d33620e "chore(security): lock down api-simple to local and protect tenant routes"`). Ngoài phạm vi GAP-011, không gộp lại đây.
- **`login`/`logout`/`password/reset` (bare, không prefix, trong `routes/web.php` local/testing block)** — liên quan kiến trúc xác thực cốt lõi, không phải bề mặt debug. Route production cho `/login`/`/logout` bare không xuất hiện trong route table mặc định (chỉ có `portal/{tenantSlug}/login` theo tenant); đây là câu hỏi kiến trúc auth riêng biệt, **không xác minh hay claim gì thêm** về việc operator login production tồn tại qua đường nào khác — nằm ngoài phạm vi điều tra của GAP-011.
- **`routes/debug.php`** (loaded chỉ ở `local`) — hiện **rỗng, toàn bộ route đã bị comment out** ("EMERGENCY: Debug routes completely disabled"), 0 route active. Không đóng góp gì vào bề mặt thật.

## Tác động nếu không xử lý
Rủi ro thấp nhưng tích luỹ và đa dạng hơn ước tính ban đầu: (1) mỗi route debug mới thêm vào `_debug/*` không có ranh giới rõ sẽ tiếp tục làm `routes/web.php` khó đọc hơn; (2) invariant test hiện tại chỉ theo danh sách cụ thể, không tự bắt được route debug mới thiếu gate; (3) 7 route redirect Class B và các helper Class C (đặc biệt 3 route CÓ ĐẶC QUYỀN — `local/dev-login/operator`, `api/login`, `api/v1/upload-document`) chứng minh cụ thể rằng cơ chế bảo vệ hiện tại không đồng nhất — một phần dựa vào `DebugGateMiddleware` (runtime), một phần dựa vào env-gate lúc đăng ký route (compile-time) — hai cơ chế khác nhau, không có nguồn xác thực duy nhất để audit toàn bộ bề mặt debug cùng lúc.

## Phạm vi đề xuất
**Gate 2 thiết kế canonical boundary cho Class A + Class B** — KHÔNG quyết ở Gate 1 này, và KHÔNG được mở rộng sang Class C (binding theo Owner Gate 1 approval ở trên). Gate 2 cần so sánh phương án cho: route nào trong 21 (Class A) cần giữ/xoá dựa trên usage/evidence; có tách debug routing khỏi `routes/web.php` hay không; canonical protection boundary sẽ là gì; 7 compatibility redirect (Class B) sẽ giữ/gate/giới hạn environment/loại bỏ; invariant tổng quát nào đảm bảo mọi route `_debug/*` tương lai không thể xuất hiện ngoài protection boundary; regression/acceptance scenarios cho production, local, testing.

## Loại trừ rõ ràng
Không đổi hành vi `DebugGateMiddleware`, `routes/api-simple.php`, hay bất kỳ route production thật nào. Không mở rộng sang GAP-024 (đã RESOLVED) hay GAP-027 (đã RESOLVED). Không điều tra sâu thêm kiến trúc xác thực cốt lõi (`login`/`logout`/`portal` auth). **Class C nằm ngoài phạm vi GAP-011 hoàn toàn** — không sửa, không di chuyển, không xoá, không harden, không chuẩn hoá dưới work item này; Gate 2 không được bundle Class C vào thiết kế.

## Đề xuất
Đội kỹ thuật đề xuất: xử lý Class A+B — rủi ro kỹ thuật thấp, phạm vi rõ ràng sau Gate 1, production đã an toàn sẵn nên không khẩn cấp nhưng dễ đóng dứt điểm.

## Decision Needed
**Owner đã chọn: Approve to proceed to design (Gate 2), phạm vi Class A + Class B only.**

## What the owner is NOT being asked to decide
Owner không được yêu cầu chọn giải pháp cụ thể cho Class A/B ở bước này — đó là quyết định Gate 2 sau khi có thiết kế chi tiết. Owner cũng không được yêu cầu quyết định gì về Class C ở đây — Class C đã được xác nhận ngoài phạm vi, sẽ là (các) work item riêng nếu cần, không phải một phần của quyết định này.
