---
work_id: OWN-2026-024
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/UAT-RECOVERY-01/101-o17h-h0-h1-design.md
  plan: null
  branch: integration/uat-recovery-s1-s4d
  pr: https://github.com/kha997/zenamanagephp/pull/422
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-24T19:25:17+07:00"
  owner_response_reference: "Owner decision in-session on 2026-11-24, verbatim status: 'GATE2_CONDITIONAL_APPROVED / H2A_LOCAL_IMPLEMENTATION_AUTHORIZED'. D1 APPROVE five dedicated platform permission codes divided by operation category, no implicit grants. D2 APPROVE retirement of the 37 mock/legacy routes only after proving they are unused by legitimate workflows, UI navigation and tests. D3 APPROVE explicit notification.manage_rules for the Owner role, restricted to own settings; DO NOT grant task.assign, change_request.create, component.create, component.read, component.update. D4 APPROVE durable revocation records; all relevant seeders incl. production:rbac must respect deliberate revocations. D5 APPROVE Owner-only appointment of another Owner within the same tenant requiring a real canonical grant; no self-promotion, no cross-tenant appointment, no removal of the last active Owner, transactional audit, no tenant-admin escalation, no role-name-only authorization. D6 APPROVE server-only provisioning of Platform Operators; platform authority independent of tenant role-management authority; no HTTP role assignment or self-grant path. D7 CONDITIONALLY APPROVE production H0 inventory: before execution independently review the exact SQL, least-privilege read-only DB account, verify MySQL session read-only behaviour, query/session timeouts, only the 12 approved aggregate SELECTs, no names/emails/identifiers, no mutations, stop on unexpected privilege or SQL/schema mismatch; never run production:rbac during inventory. Given against Draft PR #422 head c94ff67c031c60a862def26e0c5c9f3bc33e2ffc with design 101. Authorizes H2a LOCAL implementation only: no commit, push, merge, deployment, production grant change, bypass removal, or H2b/H3/H4; PR stays Draft and MERGE_NO_GO; not Gate 3."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-24T16:56:00+07:00"
  updated_at: "2026-11-24T19:25:17+07:00"
generated_by: agent
---

# OWN-2026-024 — Quyền cấp rõ ràng thay cho quyền ngầm của "chủ công ty": thiết kế (Gate 2)

## OWNER GATE 2: APPROVED CÓ ĐIỀU KIỆN (2026-11-24)

Owner duyệt trong phiên với trạng thái **GATE2_CONDITIONAL_APPROVED** và cho phép **làm H2a trên máy cục bộ**. Không lưu, không đẩy, không phát hành.

| # | Quyết định của Owner | Điều kiện |
|---|---|---|
| D1 | Duyệt 5 quyền máy chủ riêng, chia theo loại thao tác | Không ai tự động có |
| D2 | Duyệt gỡ bỏ 37 đường dẫn mẫu và trang cũ | **Chỉ** gỡ khi chứng minh không quy trình thật, không menu và không kiểm thử nào dùng |
| D3 | Duyệt cấp rõ ràng "tự chỉnh cài đặt thông báo" cho chủ công ty, chỉ trên cài đặt của chính mình | **Không** cấp: phân công việc, tạo yêu cầu thay đổi kiểu cũ, ba quyền "thành phần" |
| D4 | Duyệt sổ ghi quyền đã thu hồi | Mọi bước nạp quyền, kể cả bước nạp quyền chuẩn trên máy thật, phải tôn trọng sổ này |
| D5 | Duyệt: chỉ chủ công ty gán chủ công ty khác, cùng công ty, và cần quyền cấp rõ ràng | Không tự nâng mình; không gán sang công ty khác; không gỡ người chủ cuối cùng còn hoạt động; nhật ký ghi cùng giao dịch; không cho quản trị viên công ty vượt quyền; không xét theo tên vai trò |
| D6 | Duyệt: người vận hành máy chủ chỉ được cấp trên máy chủ | Tách khỏi quyền quản lý vai trò của công ty; không có đường cấp hoặc tự cấp qua web |
| D7 | Duyệt có điều kiện kiểm kê H0 trên máy thật | Trước khi chạy: xem lại câu lệnh; tài khoản chỉ đọc quyền tối thiểu; kiểm tra phiên chỉ đọc; đặt giới hạn thời gian; chỉ 12 câu lệnh đã duyệt; không lấy tên, thư điện tử, mã định danh; không sửa dữ liệu; dừng khi gặp yêu cầu quyền hoặc cấu trúc khác dự kiến; **không bao giờ** chạy bước nạp quyền chuẩn khi kiểm kê |

**Phạm vi hiện được phép:** chỉ H2a, cục bộ. H2b, H3 và H4 cần Owner duyệt riêng.

**Lưu ý:** H2a **chưa** phải bản sửa bảo mật, vì ngoại lệ theo tên vai trò vẫn còn đến H3.

## Owner Summary

- Quyền của "chủ công ty" sẽ chỉ còn đúng những quyền được cấp, và **thu hồi là có hiệu lực thật**.
- Quản trị máy chủ thành một nhóm quyền riêng. Không chủ công ty nào tự động có nhóm quyền này.

## Trước / Sau

**Trước:**
1. Mang tên vai trò "chủ công ty" là qua mọi cửa kiểm tra quyền, kể cả khi vai trò không còn quyền nào.
2. 80 đường dẫn quản trị máy chủ chỉ mở cho cái tên vai trò đó.
3. Gỡ quyền của chủ công ty xong, chạy lại bước nạp quyền chuẩn thì quyền tự quay lại.
4. Người được giao quản lý vai trò có thể tự cấp quyền máy chủ cho mình, hoặc tự gán mình làm chủ công ty.
5. Hồ sơ tài liệu và cấu hình thanh menu vẫn cho qua theo tên vai trò.

**Sau:**
1. Mỗi cửa kiểm tra chỉ xét quyền đã cấp. Không còn ngoại lệ theo tên vai trò.
2. Nhóm quyền máy chủ mới chia theo loại thao tác:
   - xem thông số;
   - xoá bộ nhớ đệm;
   - bảo trì cơ sở dữ liệu;
   - sao lưu toàn bộ dữ liệu;
   - phát thông báo hệ thống.

   Chỉ cấp cho "người vận hành máy chủ", bằng một thao tác riêng trên máy chủ có ghi nhật ký.
   Chưa ai được cấp thì mọi thao tác máy chủ đều bị từ chối.
3. Quyền đã cố ý thu hồi được ghi lại; bước nạp quyền chuẩn không cấp lại.
4. Không ai tự cấp được quyền máy chủ. Muốn gán vai trò chủ công ty cho người khác thì người gán phải là chủ công ty. Không tự gán cho mình. Có ghi nhật ký.
5. Hồ sơ tài liệu và cấu hình menu cũng chỉ xét quyền đã cấp.

## Vai trò bị ảnh hưởng

- **Chủ công ty:** giữ nguyên 222 quyền đang có. **Mất** 80 thao tác máy chủ, trừ khi được cấp vai trò người vận hành máy chủ. Sáu quyền phụ: xem quyết định 3.
- **Người vận hành máy chủ (mới):** chỉ có quyền máy chủ, không tự động có quyền nghiệp vụ của công ty nào.
- **Người quản lý vai trò được uỷ quyền:** không còn tự nâng quyền được.
- **Quản lý dự án, thành viên, kế toán, kỹ sư công trường:** không đổi.

## Được phép / Không được phép

- **Được:**
  - chủ công ty làm mọi việc trong công ty mình với quyền đã cấp;
  - người vận hành máy chủ làm đúng nhóm thao tác máy chủ được cấp.
- **Không được:**
  - dùng tên vai trò thay cho quyền;
  - chủ công ty tự động có quyền máy chủ;
  - bước nạp quyền chuẩn cấp lại quyền đã thu hồi;
  - tự cấp quyền máy chủ hoặc tự gán làm chủ công ty.

## Trạng thái và bước tiếp theo

Mỗi bước làm riêng và cần Owner duyệt riêng:

| Bước | Nội dung | Trạng thái |
|---|---|---|
| H0 | Kiểm kê chỉ đọc trên máy thật | Thủ tục đã soạn, chưa chạy, cần Owner cho phép |
| H1 | Kiểm thử | Đã viết, chưa lưu vào kho mã |
| H2 | Tạo nhóm quyền máy chủ; gắn lại 80 đường dẫn; sổ ghi quyền đã thu hồi; chặn tự nâng quyền | Chưa làm. Chủ công ty vẫn qua như cũ ở bước này. |
| H3 | Bỏ ngoại lệ theo tên vai trò | Chưa làm |
| H4 | Sửa hồ sơ tài liệu và menu | Chưa làm |

## Ngoại lệ

- Nếu máy thật chỉ có **một** công ty, rủi ro "ảnh hưởng công ty khác" chưa xảy ra. Việc tách quyền vẫn cần, để thu hồi có tác dụng.
- Các màn hình mẫu (số liệu giả) và trang quản trị cũ: đề xuất gỡ bỏ, không chuyển thành quyền máy chủ.

## Hành vi người dùng nhìn thấy

- **Chủ công ty, sau H3:** không còn thấy các trang và nút bảo trì máy chủ, trừ khi được cấp vai trò người vận hành.
- **Người khác:** không đổi.

## Kịch bản chấp nhận

- Cho vai trò chủ công ty bị gỡ hết quyền, khi truy cập bất kỳ chức năng nào, hệ thống phải từ chối.
- Cho chủ công ty đủ quyền nghiệp vụ, khi gọi thao tác bảo trì máy chủ, hệ thống phải từ chối, trừ khi người đó được cấp quyền máy chủ.
- Cho một quyền đã thu hồi khỏi chủ công ty, khi chạy lại bước nạp quyền chuẩn, quyền đó phải vẫn bị thu hồi.
- Cho người quản lý vai trò không phải chủ công ty, khi tự cấp quyền máy chủ hoặc tự gán làm chủ công ty, hệ thống phải từ chối và không lưu gì.
- Cho chủ công ty đủ quyền, các việc đang làm được (xem hợp đồng, tạo khách hàng tiềm năng, duyệt yêu cầu thay đổi, quản lý nhân viên) phải vẫn làm được.
- Không ai xem hoặc sửa được dữ liệu công ty khác.

## Loại trừ phạm vi

Ghi nhận riêng, không sửa ở đây:
- BLK-1: tạo người dùng hàng loạt sang công ty khác;
- AUD-1: thiếu nhật ký thao tác;
- O8-1: cổng phân quyền không tham số chặn quản lý dự án và thành viên;
- O16-1: không ai bổ nhiệm được quản lý dự án trên máy thật;
- CMP-1: xem và sửa "thành phần" của công ty khác.

Không viết lại toàn bộ phân quyền, không tự động đổi vai trò của ai.

## Decision Needed

Owner chooses one: Approve to proceed to implementation / Request changes to the design / Decline.

Cùng lúc, Owner cần chọn (chi tiết ở mục 9 tài liệu 101):

1. **Tên và cách chia nhóm quyền máy chủ.** Đề xuất: 5 quyền theo loại thao tác như trên. Các bước kiểm tra chuẩn bị phát hành hiện chỉ kiểm tra, không chạy gì, nên dùng chung quyền "xem thông số".
2. **Gỡ bỏ màn hình mẫu và trang quản trị cũ** (37 đường dẫn), thay vì chuyển thành quyền máy chủ. Đề xuất: gỡ.
3. **Sáu quyền phụ của chủ công ty.** Đề xuất:
   - **cấp:** "tự chỉnh cài đặt thông báo của mình";
   - **không cấp:**
     - "phân công việc" (việc của quản lý dự án);
     - "tạo yêu cầu thay đổi kiểu cũ" (đã có đường mới);
     - ba quyền "thành phần" (đang có lỗi lộ dữ liệu sang công ty khác).
4. **Cách ghi "quyền đã thu hồi".** Đề xuất: sổ ghi trong cơ sở dữ liệu, có người duyệt và lý do.
5. **Gán vai trò chủ công ty.** Đề xuất: chỉ chủ công ty gán cho người khác; không tự gán; luôn ghi nhật ký.
6. **Cấp vai trò người vận hành máy chủ.** Đề xuất: chỉ qua lệnh trên máy chủ, không qua màn hình web.
7. **Chạy kiểm kê H0 trên máy thật.** Chỉ đọc, chỉ đếm và liệt kê mã quyền; không lấy tên, thư điện tử hay mật khẩu.

## What the owner is NOT being asked to decide

Gate 2 không hỏi về:
- tên lớp hoặc bảng dữ liệu;
- cách viết kiểm thử;
- thứ tự sửa từng tệp.

Owner chỉ quyết định: ai được làm gì, ai cấp, thu hồi thế nào, và gỡ bỏ những gì.
