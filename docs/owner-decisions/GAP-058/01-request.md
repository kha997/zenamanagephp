---
work_id: GAP-058
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-09-29-gap-058-badge-api-evidence.md
  plan: null
  branch: docs/GAP-058-badge-api-500
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: null
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-29T23:02:58+07:00"
  updated_at: "2026-09-29T23:02:58+07:00"
generated_by: agent
---

## Owner Summary

Sổ ghi GAP-058 là "API huy hiệu thanh bên luôn lỗi 500". Kiểm tra kỹ cho thấy
**không người dùng nào đang thấy lỗi này**: giao diện hiện tại (layout
Operator) không hiển thị huy hiệu và không gọi API đó. Cả tính năng huy hiệu là
phần còn sót của hệ thanh bên cũ — kể cả sửa lỗi 500, mọi con số vẫn luôn là 0
vì chưa từng được làm xong. Cần Owner quyết định **về sản phẩm**: có muốn hiển
thị số đếm trên menu hay không.

## Vấn đề vận hành

1. API `/api/badges/*` (8 đường) luôn lỗi 500 do thiếu khai báo trong mã.
2. Sửa xong thì vẫn luôn trả 0: phần tính số thật chưa bao giờ được nối, các
   nguồn số liệu nó định gọi không tồn tại, và token là giá trị giả.
3. Không trang nào đang hiển thị huy hiệu; component thanh bên cũ không được
   layout nào dùng.

## Người dùng bị ảnh hưởng

Hiện không ai (không có giao diện gọi tới). Rủi ro là về bảo trì: một API đang
mở, luôn lỗi và trả số giả, dễ làm người sau hiểu nhầm.

## Bằng chứng

Đọc mã từng lớp, đối chiếu 1171 đường truy cập (không có `/api/metrics`), tìm
mọi nơi dùng component thanh bên (không có). Chi tiết:
`docs/audits/2026-09-29-gap-058-badge-api-evidence.md`.

## Quyết định sản phẩm cần Owner (trước Gate 2)

**Có cần hiển thị số đếm trên menu điều hướng không** (ví dụ "Phê duyệt (3)",
"RFI (2)")?

- **Không cần (ít nhất lúc này):** Gate 2 thiết kế gỡ phần API huy hiệu chết
  (việc kỹ thuật, nhỏ, an toàn).
- **Cần:** đây là tính năng mới chứ không phải sửa lỗi — cần Owner định nghĩa
  mục nào có số, và mỗi số nghĩa là gì theo vai trò (ví dụ "phê duyệt đang chờ
  **tôi**" hay "mọi phê duyệt đang chờ trong công ty"). Mã hiện tại không dùng
  làm nền được.

## Phạm vi đề xuất

Nếu duyệt Gate 1 kèm lựa chọn trên, Gate 2 thiết kế theo hướng Owner chọn.

## Loại trừ rõ ràng

- Không đụng tới toàn bộ hệ thanh bên cũ (sidebar builder, preset, tuỳ chọn
  hiển thị) ngoài API huy hiệu.
- Không thiết kế lại menu Operator.
- Không deploy.

## Khả năng hoàn tác

Chỉ tài liệu.

## Đề xuất

Phê duyệt Gate 1. Về sản phẩm, đội kỹ thuật nghiêng về **"không cần lúc này"**
(gỡ mã chết) vì trang "Việc của tôi" đã cho người dùng thấy việc đang chờ;
nhưng đây là quyết định của Owner.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) — kèm lựa chọn sản phẩm
ở trên / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt cách làm cụ thể, Gate 2, merge hay phát hành.
