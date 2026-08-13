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
  updated_at: "2026-08-13T14:01:12+07:00"
generated_by: agent
---

## Owner Summary
21 route `/_debug/*` vẫn tồn tại trong `routes/web.php`, kể cả một route login-bypass bằng credential cứng. Đã an toàn ở production (bị chặn 404), nhưng vẫn là bề mặt gây nhầm lẫn ở non-prod (local/testing/development) và không có cơ chế nào ngăn nó phình to thêm theo thời gian.

## Vấn đề vận hành
`routes/web.php` hiện có 18 chỗ khai báo route `_debug/*` (một số route đăng ký nhiều path cùng lúc, nên tổng route thực tế là 21 theo audit gốc). `DebugGateMiddleware` chặn các route này trả 404 ở mọi environment khác `local`/`testing`/`development` — đây là lớp bảo vệ đã xác nhận hoạt động đúng, xác minh lại hôm nay (`app/Http/Middleware/DebugGateMiddleware.php:16-24`, không đổi so với lần audit trước). Vấn đề còn lại thuần là vệ sinh kiến trúc: các route debug/test cũ (`_debug/info`, `_debug/projects-test`, `_debug/users-debug`, v.v.) vẫn nằm chung trong `routes/web.php` với route thật, không có ranh giới rõ ràng, dễ khiến người sửa code sau nhầm tưởng đây là surface đang dùng thật, hoặc vô tình thêm route debug mới mà không qua `DebugGateMiddleware`.

## Người dùng bị ảnh hưởng
- Engineering agents/contributors đọc `routes/web.php` lần đầu — dễ nhầm route debug với route thật.
- Không ảnh hưởng người dùng cuối/khách hàng — production đã an toàn theo middleware.

## Bằng chứng
Xác minh lại trực tiếp trên `origin/main` hiện tại (commit `1024b686`, 2026-08-13):
- `grep -c "_debug" routes/web.php` → 18 (không đổi so với lần audit trước).
- `app/Http/Middleware/DebugGateMiddleware.php` → không đổi, vẫn chặn 404 ngoài `local/testing/development`.
- Có một invariant test liên quan (`tests/Feature/DebugRouteDocumentationInvariantTest.php`, đã xác nhận tồn tại và PASS khi đóng GAP-027) nhưng test đó chỉ so khớp tài liệu (`ZENAMANAGE_PAGE_TREE_DIAGRAM.md`) với route thật đang mount — nó KHÔNG phải là cơ chế ngăn route debug mới bị thêm vào hoặc ngăn route debug production-unsafe, đó là phạm vi khác (GAP-027, đã đóng riêng).

## Tác động nếu không xử lý
Rủi ro thấp nhưng tích luỹ: mỗi route debug mới thêm vào không có ranh giới rõ sẽ tiếp tục làm `routes/web.php` khó đọc hơn, tăng khả năng ai đó thêm route debug quên bọc `DebugGateMiddleware` (dù hiện tại middleware áp dụng đúng, đây là rủi ro quy trình về sau, không phải lỗi đang xảy ra).

## Phạm vi đề xuất
Một trong hai hướng (quyết định cụ thể thuộc Gate 2, không quyết ở đây): (a) dọn/archive các route debug không còn dùng thật (theo đúng phân loại active/archived đã có trong `DebugRouteDocumentationInvariantTest.php`), giữ lại route active hợp lệ; hoặc (b) tách toàn bộ route `_debug/*` ra một file route riêng (`routes/debug.php`) để ranh giới rõ ràng hơn, không lẫn với route production thật.

## Loại trừ rõ ràng
Không đổi hành vi `DebugGateMiddleware` (đã đúng, không cần sửa). Không đụng tới bất kỳ route production thật nào ngoài `_debug/*`. Không mở rộng sang GAP-024 (đã RESOLVED, không liên quan) hay GAP-027 (đã RESOLVED, đã đóng riêng).

## Đề xuất
Đội kỹ thuật đề xuất: xử lý — rủi ro kỹ thuật thấp, phạm vi nhỏ, production đã an toàn sẵn nên không khẩn cấp nhưng dễ đóng dứt điểm.

## Decision Needed
Owner chọn một: Approve to proceed to design (Gate 2) / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide
Owner không được yêu cầu chọn giữa hướng (a) hay (b) ở bước này — đó là quyết định Gate 2 sau khi có thiết kế chi tiết. Ở đây chỉ cần quyết định: vấn đề này có thật và có đáng xử lý hay không.
