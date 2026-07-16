# Portal Client Actions — Design Spec

Date: 2026-07-14
Status: approved by user (option A — direct effect, 2026-07-14)
Depends on: Phase 6 client portal (magic-link, `client` guard, `Account` identity) + R-DPM revision cycle.

## Purpose

Goal #6: khách hàng hành động được trên portal thay vì hỏi qua Zalo. Slice 1 = đúng 2 hành động trên DesignItem đang `sent_to_client`: **Duyệt phương án** và **Yêu cầu chỉnh sửa (kèm nội dung)** — có hiệu lực NGAY (option A), bám vào transition graph + revision recording sẵn có; enum `approval_evidence` đã có giá trị `client_portal` chờ sẵn từ Phase 6.

## User-approved decision

**Option A — direct effect**: hành động của khách chuyển trạng thái item ngay lập tức. Chốt an toàn: (1) UI xác nhận 2 lần trước khi Duyệt; (2) thông báo tự động cho người phụ trách item; (3) EventRecord đầy đủ; (4) operator gỡ nhầm lẫn bằng transition ngược sẵn có (`approved → revision_requested`).

## Component 1 — Extract transition authority into a service (pure refactor)

`App\Services\DesignItemStatusService` với method duy nhất:

```php
/** @param array{client_feedback_notes?: string|null, approval_evidence?: string|null,
 *         actor_user_id?: string|null, actor_account_id?: string|null} $options
 *  @throws \Illuminate\Validation\ValidationException  */
public function transition(DesignItem $item, string $to, array $options = []): DesignItem;
```

Chứa NGUYÊN VĂN logic hiện nằm trong `Api\DesignItemController::updateStatus()`: kiểm tra `canTransition`, các điều kiện (feedback bắt buộc khi revision_requested; evidence bắt buộc khi approved; due date + attachment khi sent_to_client), transaction ghi `DesignItemRevision` + `revision_count` + `resolved_at`, và EventRecord (`payload` bổ sung `actor_account_id` khi actor là khách; `actor_user_id` nullable — đã xác minh migration). Controller API giữ HTTP validation/authorize rồi delegate. **Bất biến: service là nguồn chân lý DUY NHẤT cho review_status — docblock của DesignItem cập nhật theo.** Toàn bộ test DesignItem hiện có phải xanh nguyên vẹn sau refactor.

## Component 2 — Portal actions

Routes trong group `portal/{tenantSlug}` + middleware `portal.auth`, thêm limiter mới `portal-actions` (10/phút theo account+IP, khai báo trong `RouteServiceProvider` như `ai-suggest`):

- `GET  /design-items/{id}` → `portal.design-items.show` — trang chi tiết (tên, trạng thái, hạn, lịch sử "Sửa lần N" từ `revisions`).
- `POST /design-items/{id}/approve` → duyệt: service transition → `approved`, `approval_evidence = client_portal`.
- `POST /design-items/{id}/request-revision` — validate `client_feedback_notes` required, max 2000 → service transition → `revision_requested` (revision row tự sinh với feedback nguyên văn của khách; `requested_by` để null — actor là account, ghi trong EventRecord payload).

**Authorization (anti-enumeration như Phase 6):** item chỉ truy cập được khi `project_id` nằm trong tập project của account (query pattern `Opportunity.account_id → converted_project_id` — trích thành helper dùng chung với `PortalDashboardController`). Sai tenant, sai account, không tồn tại → cùng một 404, không phân biệt. Hành động chỉ chấp nhận khi `review_status === sent_to_client` — sai trạng thái trả back-error thân thiện (khách bấm sau khi operator đã đổi).

**Notification:** sau transition thành công, tạo `App\Models\Notification` cho `assigned_to` của item (bỏ qua nếu chưa giao): type `portal_client_action`, title "Khách đã duyệt/yêu cầu sửa: {tên item}", link tới trang design-item operator. 

## Component 3 — Portal UI

- `portal/dashboard`: mỗi DesignItem thành link sang trang chi tiết; badge "Chờ bạn phản hồi" khi `sent_to_client`.
- Trang mới `portal/design-item.blade.php` (theo layout/tông của `portal/dashboard.blade.php`, KHÔNG dùng partial operator): thông tin item, timeline revisions, và khi `sent_to_client` + đúng quyền sở hữu: nút **Duyệt phương án** (form POST + `onsubmit="return confirm('Xác nhận DUYỆT phương án này? Hành động có giá trị xác nhận chính thức.')"`) và form **Yêu cầu chỉnh sửa** (textarea bắt buộc + confirm tương tự). Sau hành động: flash message + trạng thái mới hiển thị ngay.

## Error handling

Cross-account/tenant/không tồn tại → 404 đồng nhất. Sai trạng thái → back error "Phương án không còn ở trạng thái chờ phản hồi." Thiếu nội dung sửa → validation error. Throttle vượt → 429. Mọi transition trong transaction (service).

## Testing

- Refactor: nguyên bộ test DesignItem + AiDesignItemSuggestion + DesignItemRevisionCycle xanh không sửa.
- Portal approve: status→approved, evidence=client_portal, EventRecord có actor_account_id, Notification tạo cho assignee.
- Portal request-revision: revision_requested + DesignItemRevision mới (feedback khách, revision_count+1, requested_by null).
- Sai trạng thái (draft/approved) → không đổi + error; item của account khác → 404; tenant khác → 404; chưa đăng nhập → redirect login portal; throttle 11 request → 429; item không giao ai → không Notification, không lỗi.

## Out of scope (YAGNI — slice sau)

Xác nhận nhận tài liệu, bình luận tự do/chat, xem công nợ chi tiết + chứng chỉ thanh toán trên portal, upload file từ khách, email notification.
