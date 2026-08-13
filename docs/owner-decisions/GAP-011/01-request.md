---
work_id: GAP-011
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_more_info_or_decline_or_defer"
references:
  spec: null
  plan: null
  branch: docs/GAP-011-debug-route-cleanup-gate1-prep
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-13T14:01:12+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-13T14:01:12+07:00"
  updated_at: "2026-08-13T16:00:35+07:00"
generated_by: agent
---

## Owner Summary
21 route `/_debug/*` đang thật sự mount ở runtime (xác nhận bằng `php artisan route:list`, không phải đếm dòng text), toàn bộ đã được `DebugGateMiddleware` chặn 404 ngoài `local/testing/development`. Vấn đề còn lại không phải lỗ hổng production — là thiếu ranh giới kiến trúc rõ ràng cho một bề mặt non-prod đáng kể, và một route redirect nằm ngoài nhóm `_debug/*` không được middleware này che (chi tiết bên dưới).

## Vấn đề vận hành
Đã xác minh chính xác bằng `php artisan route:list --json --path=_debug` trên baseline `origin/main` hiện tại (commit `1024b686`), không dùng `grep` (grep đếm dòng chứa chuỗi `_debug` trong source, không phải route runtime — lần trước dùng nhầm cách này cho ra con số 18 sai). Kết quả runtime chính xác: **21 route** đang active dưới `/_debug/*`, liệt kê đầy đủ ở mục Bằng chứng.

Production đã được bảo vệ đúng — `DebugGateMiddleware` (`app/Http/Middleware/DebugGateMiddleware.php:16-24`, không đổi) chặn toàn bộ 21 route này trả 404 ngoài `local/testing/development`, xác nhận cả 21 route đều có `DebugGateMiddleware` trong middleware stack. **Không có claim nào về lỗ hổng production ở đây.**

Vấn đề còn lại, phát biểu chính xác: (1) một bề mặt `/_debug/*` không nhỏ (21 route) vẫn nằm chung trong `routes/web.php` với route production thật, không có ranh giới file/namespace riêng; (2) `tests/Feature/DebugRouteDocumentationInvariantTest.php` (đã đóng cho GAP-027) chỉ so khớp một danh sách cố định các URI được liệt kê thủ công trong tài liệu (`active` vs `archived`) với route thật đang mount — đây là kiểm tra **từng phần theo danh sách cụ thể**, không phải một ràng buộc tổng quát kiểu "mọi route dưới `_debug/*` (hiện tại và tương lai) đều phải qua `DebugGateMiddleware`". Nếu ai thêm route debug mới mà quên khai báo trong danh sách test đó, test này không tự phát hiện được. (3) Có một route redirect **nằm ngoài nhóm `/_debug/*`, không có `DebugGateMiddleware`** — xem mục Bằng chứng.

## Người dùng bị ảnh hưởng
- Engineering agents/contributors đọc `routes/web.php` lần đầu — dễ nhầm route debug với route thật.
- Không ảnh hưởng người dùng cuối/khách hàng — production đã an toàn theo middleware, kể cả với route redirect ngoài nhóm (giải thích ở mục Bằng chứng).

## Bằng chứng
Xác minh lại trực tiếp trên `origin/main` hiện tại (commit `1024b686`, 2026-08-13), dùng route inventory thật thay vì đếm text:

**Lệnh xác thực:** `php artisan route:list --json --path=_debug` (baseline `1024b686`).

**Số route runtime chính xác: 21**, toàn bộ đều có `App\Http\Middleware\DebugGateMiddleware` trong middleware stack (kiểm tra từng route, không suy đoán):

| Method | URI | Gated bởi DebugGateMiddleware? |
|---|---|---|
| GET\|HEAD | `_debug/admin-dashboard-test` | YES |
| GET\|HEAD | `_debug/dashboard-data` | YES |
| GET\|HEAD | `_debug/final-integration` | YES |
| GET\|HEAD | `_debug/performance-optimization` | YES |
| GET\|HEAD | `_debug/tenant-dashboard-test` | YES |
| GET\|HEAD | `_debug/test` | YES |
| GET\|HEAD | `_debug/test-accessibility` | YES |
| GET\|HEAD | `_debug/test-api-admin-stats` | YES |
| GET\|HEAD | `_debug/test-auth` | YES (+ `Authenticate`) |
| GET\|HEAD | `_debug/test-auth-direct` | YES (+ `Authenticate`) |
| GET\|HEAD | `_debug/test-bypass` | YES |
| POST | `_debug/test-login-simple` | YES |
| GET\|HEAD | `_debug/test-login/{email}` | YES |
| GET\|HEAD | `_debug/test-minimal` | YES |
| GET\|HEAD | `_debug/test-mobile-optimization` | YES |
| GET\|HEAD | `_debug/test-mobile-simple` | YES |
| GET\|HEAD | `_debug/test-permissions` | YES |
| GET\|HEAD | `_debug/test-session-auth` | YES |
| GET\|HEAD | `_debug/test-simple` | YES |
| GET\|HEAD | `_debug/test-web-guard` | YES (+ `Authenticate:web`) |
| GET\|HEAD | `_debug/testing-suite` | YES |

**Claim sai đã gỡ bỏ (correction bắt buộc):** `_debug/info`, `_debug/projects-test`, `_debug/users-debug`, `_debug/tasks-debug` và các URI tương tự **KHÔNG active** — `tests/Feature/DebugRouteDocumentationInvariantTest.php::test_current_page_tree_archived_debug_claims_do_not_have_runtime_route_evidence()` khẳng định rõ các URI này đã archived và xác nhận vắng mặt khỏi route table thật; route inventory `--path=_debug` ở trên (21 route) xác nhận độc lập cùng kết luận — các URI đó không nằm trong danh sách 21 route runtime.

**Login/auth helper routes (liệt kê riêng để nhận diện, KHÔNG quyết định disposition ở đây):** trong 21 route trên, các route liên quan xác thực/đăng nhập là `_debug/test-auth`, `_debug/test-auth-direct`, `_debug/test-bypass`, `_debug/test-login/{email}`, `_debug/test-login-simple`, `_debug/test-web-guard`, `_debug/test-session-auth`, `_debug/test-permissions` — toàn bộ đều gated bởi `DebugGateMiddleware`.

**Debug-like route NẰM NGOÀI nhóm `/_debug/*` (phát hiện mới, quét toàn bộ `php artisan route:list --json`, 1164 route trong app):**
- `GET|HEAD|POST|PUT|PATCH|DELETE|OPTIONS test-login/{email}` — action là `Illuminate\Routing\RedirectController`, middleware chỉ có `web` (**KHÔNG có `DebugGateMiddleware`**).
- Đối chiếu `routes/web.php:788`: `Route::permanentRedirect('/test-login/{email}', '/_debug/test-login/{email}')`. Đây là redirect 301 nén cứng sang path `_debug/*` đã gated — route này **không tự thực thi logic login-bypass**, nó chỉ chuyển hướng trình duyệt tới đích đã được `DebugGateMiddleware` bảo vệ, nên không có exploit path thật qua route này (đích cuối vẫn bị chặn 404 ngoài local/testing/development). Tuy vậy đây đúng là "route giống-debug tồn tại ngoài nhóm `/_debug/*` được gate" — cần ghi nhận cho Gate 2 xem xét (giữ lại, xoá, hay gate luôn) chứ không tự quyết ở Gate 1 này.
- Không tìm thấy route debug/test/bypass nào khác ngoài nhóm `_debug/*` qua quét từ khoá `debug|test-login|test-bypass|test-auth|bypass-login|dev-login` trên toàn bộ 1164 route.

## Tác động nếu không xử lý
Rủi ro thấp nhưng tích luỹ: mỗi route debug mới thêm vào không có ranh giới rõ sẽ tiếp tục làm `routes/web.php` khó đọc hơn; test bất biến hiện tại chỉ theo danh sách cụ thể nên không tự bắt được route debug mới thiếu gate; route redirect ngoài nhóm (`test-login/{email}`) là ví dụ cụ thể cho việc route liên quan-debug có thể nằm ngoài ranh giới `_debug/*` mà không ai chú ý.

## Phạm vi đề xuất
Thiết kế cụ thể thuộc Gate 2, KHÔNG quyết ở đây. Các hướng có thể cân nhắc ở Gate 2 (liệt kê để tham khảo, không phải danh sách đầy đủ, không ưu tiên hướng nào): tách 21 route `_debug/*` ra file route riêng để ranh giới rõ hơn; nâng invariant test hiện có (theo danh sách cụ thể) thành ràng buộc tổng quát hơn (mọi route dưới `_debug/*` phải qua `DebugGateMiddleware`, không cần liệt kê từng URI); và xử lý riêng route redirect `test-login/{email}` nằm ngoài nhóm gated (giữ/xoá/đưa vào gate).

## Loại trừ rõ ràng
Không đổi hành vi `DebugGateMiddleware` (đã đúng, không cần sửa). Không đụng tới bất kỳ route production thật nào ngoài `_debug/*` và route redirect `test-login/{email}` đã nêu. Không mở rộng sang GAP-024 (đã RESOLVED, không liên quan) hay GAP-027 (đã RESOLVED, đã đóng riêng).

## Đề xuất
Đội kỹ thuật đề xuất: xử lý — rủi ro kỹ thuật thấp, phạm vi nhỏ, production đã an toàn sẵn nên không khẩn cấp nhưng dễ đóng dứt điểm.

## Decision Needed
Owner chọn một: Approve to proceed to design (Gate 2) / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide
Owner không được yêu cầu chọn giữa hướng (a) hay (b) ở bước này — đó là quyết định Gate 2 sau khi có thiết kế chi tiết. Ở đây chỉ cần quyết định: vấn đề này có thật và có đáng xử lý hay không.
