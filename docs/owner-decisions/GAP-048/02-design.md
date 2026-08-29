---
work_id: GAP-048
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_changes_or_decline"
references:
  spec: docs/superpowers/specs/2026-08-30-gap-048-crm-classification-ux-gates-design.md
  plan: null
  branch: docs/GAP-048-gate2-crm-classification-design
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-29T18:00:21Z"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-29T18:00:21Z"
  updated_at: "2026-08-29T18:00:21Z"
generated_by: agent
---

## Owner Summary
Sau khi Owner duyệt Gate 1 (vấn đề/bằng chứng), đây là thiết kế Gate 2: cách CRM sẽ phân loại Opportunity một cách trung thực bằng Service Line chuẩn (đa giá trị), cách người dùng xác nhận CONFIRMED một cách tường minh, và các cổng kiểm soát ở pipeline/Quote/WON dựa trên phân loại đã xác nhận — chưa viết code nào.

## Trước / Sau
**Trước:** 1. Opportunity chỉ có 1 trường `service_category` (scalar cũ), bị gán "architecture" âm thầm ở 2 vị trí code khi bỏ trống. 2. Nền tảng Service Line chuẩn (GAP-046) tồn tại nhưng không có UI, không có cách xác nhận CONFIRMED, không có cổng kiểm soát nào đọc nó. 3. Pipeline, Quote (cả luồng nội bộ, cổng khách hàng, và đồng bộ ngoài zena-boq-core), và chuyển đổi WON→Project đều tiến hành mà không kiểm tra phân loại.

**Sau (nếu Gate 2 được duyệt, triển khai ở phiên riêng theo đúng ranh giới):** 1. Trang chi tiết Opportunity có bảng chọn đa giá trị Service Line chuẩn (DESIGN/CONSTRUCTION/INSPECTION) + hành động "Xác nhận phân loại" tường minh, tách biệt khỏi việc chỉ tick chọn. 2. Khi chuyển đổi Lead, hệ thống tự động suy luận Service Line INFERRED từ `service_category` cũ (dùng lại đúng bảng ánh xạ của GAP-046, không viết lại) — nhưng INFERRED một mình không đủ để vượt qua bất kỳ cổng nào. 3. Cột `service_category` trở thành nullable, bỏ default 'architecture' ở DB, gỡ 2 chỗ code tự gán 'architecture'. 4. Cổng kiểm soát: pipeline (vào `scope_defined` và các bước bán hàng tiếp theo, trừ lost/no_bid/nurture), Quote (tại `sendQuote()` — thời điểm "báo giá chính thức" theo đúng SSOT — VÀ tại `createContract()` để chặn cả đường Quote ngoài zena-boq-core), và WON→Project (kiểm tra lại độc lập, không chỉ dựa vào cổng pipeline). 5. `BusinessKpiService`/`DesignItemPageController` tiếp tục đọc `service_category` (không viết lại toàn bộ), nhưng KPI report thêm nhóm "Chưa phân loại" tường minh cho NULL, và gợi ý AI ưu tiên Service Line CONFIRMED khi có, chỉ dùng scalar cũ khi chưa có.

## Vai trò bị ảnh hưởng
Nhân viên sales/CRM (`crm.manage`): thấy bảng chọn Service Line mới, phải xác nhận tường minh trước khi Opportunity có thể tiến vào các bước bán hàng active/gửi báo giá chính thức/chuyển thành dự án. Không có vai trò mới, không có quyền mới — dùng lại đúng `crm.manage` hiện có.

## Được phép / Không được phép
Được phép (nếu duyệt): mở phiên triển khai riêng, đúng ranh giới tài liệu thiết kế §3-§18 (UI phân loại + xác nhận, migration nullable cho `service_category`, cổng pipeline/Quote/WON, cầu nối tương thích hẹp cho 2 consumer cũ). Không được phép: Opportunity→Project Service-Line propagation; Project classification UX/backfill lịch sử; Quote Scope Snapshot persistence; Contract multi-Service-Line; Portfolio; Project Health; Commercial/Finance/Resource Control; OPPM; Control Tower; Treasury; retirement taxonomy cũ; sửa đổi zena-boq-core; GAP-041/042/045.

## Trạng thái và bước tiếp theo
Gate 1 (approved, đã merge) → **Gate 2 (tài liệu này, awaiting_owner)** → nếu duyệt: một phiên triển khai mới (implementation session, bắt đầu ở phiên riêng sau khi Gate 2 merge, không phải phiên này) → implementation plan → triển khai theo TDD → verify kỹ thuật → Gate 3 `awaiting_owner` → Owner review Gate 3 → chỉ release/merge sau khi Gate 3 được duyệt. Gate 3 chưa bắt đầu; phiên hiện tại (viết + trình Gate 2) không triển khai code.

## Ngoại lệ
Lost/No-bid/Nurture và từ chối Quote KHÔNG BAO GIỜ bị chặn bởi cổng phân loại (một deal phải luôn có thể bị từ chối/hoãn/archive bất kể đã phân loại hay chưa). Opportunity đang xử lý dở (đã ở các bước bán hàng active/đã WON trước khi cổng này tồn tại) không bị chặn hồi tố ở bước hiện tại, nhưng sẽ bị chặn ở bước KẾ TIẾP nếu chưa có CONFIRMED — chính sách ân hạn cho trường hợp này là câu hỏi kinh doanh còn mở, chưa quyết ở Gate 2 này (xem tài liệu thiết kế §15).

## Hành vi người dùng nhìn thấy
Trang chi tiết Opportunity có thêm bảng Service Line (badge trạng thái CONFIRMED/INFERRED) + nút "Xác nhận phân loại". Khi cố gửi báo giá chính thức, chuyển bước bán hàng, hoặc tạo hợp đồng mà chưa có Service Line đã xác nhận, hệ thống báo lỗi rõ ràng thay vì tiến hành âm thầm.

## Kịch bản chấp nhận
Xem mục 16 ("Test strategy") của tài liệu thiết kế — sẽ trở thành checklist Gate 3: (a) Opportunity chưa phân loại bị chặn ở scope_defined/sendQuote()/createContract() (cả đường native lẫn đường external zena-boq); (b) chỉ có INFERRED vẫn bị chặn; (c) có CONFIRMED thì mọi cổng đều cho qua; (d) lost/no_bid/nurture/reject Quote không bao giờ bị chặn; (e) xác nhận INFERRED→CONFIRMED qua hành động mới rồi cổng cho qua; (f) ghi xuyên-tenant bị từ chối (dùng lại `EnforcesServiceLineIntegrity`).

## Loại trừ phạm vi
Kế thừa nguyên vẹn từ Gate 1 + làm rõ ở Gate 2: không có Opportunity→Project Service-Line propagation; không Project classification UX/backfill lịch sử; không Quote Scope Snapshot persistence; không Contract multi-Service-Line; không Portfolio/Project Health/Commercial-Finance-Resource Control/OPPM/Control Tower/Treasury; không retirement taxonomy cũ; không sửa đổi zena-boq-core (cổng được đặt hoàn toàn ở phía `createContract()` trong chính codebase này, không cần hệ thống ngoài thay đổi gì); không GAP-041/042/045.

## Decision Needed
Owner chọn một trong: Approve để tiến sang triển khai (ở phiên riêng, đúng ranh giới) / Yêu cầu sửa đổi thiết kế / Từ chối.

## What the owner is NOT being asked to decide
Owner không được yêu cầu duyệt tên route/controller/method/migration cụ thể, tên event audit cụ thể, hay câu chữ UI chính xác — đó là quyết định triển khai trong ranh giới đã duyệt. Owner ĐƯỢC yêu cầu xác nhận: (1) đặt cổng Quote tại `sendQuote()` + `createContract()` thay vì `storeQuote()`; (2) chiến lược migration nullable cho `service_category` (không phải sentinel value); (3) không mở rộng phạm vi sang zena-boq-core; (4) câu hỏi mở về chính sách ân hạn cho deal dở dang cần input kinh doanh trước khi triển khai — có cần Owner quyết ngay ở Gate 2 này hay có thể để lại cho phiên triển khai/Gate 3 tự đề xuất phương án và Owner duyệt sau.
