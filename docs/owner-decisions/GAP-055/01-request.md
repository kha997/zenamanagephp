---
work_id: GAP-055
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-09-29-gap-055-http-cache-flush-evidence.md
  plan: null
  branch: docs/GAP-055-admin-clear-cache-flush
  pr: https://github.com/kha997/zenamanagephp/pull/322
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
  created_at: "2026-09-29T06:46:36+07:00"
  updated_at: "2026-09-29T06:47:00+07:00"
generated_by: agent
---

## Owner Summary

Một số thao tác quản trị qua web xoá sạch toàn bộ bộ nhớ đệm của hệ thống — kể
cả bộ đếm chặn dò mật khẩu của **mọi khách hàng**. Đội kỹ thuật đã tái hiện
thật: quản trị viên hệ thống bấm "Clear cache" thì bộ đếm đăng nhập sai của một
khách hàng khác về 0 ngay lập tức. Đề nghị cho phép thiết kế cách sửa.

## Vấn đề vận hành

1. **Nút "Clear cache" trong trang quản trị** xoá toàn bộ bộ nhớ đệm (đã tái
   hiện).
2. **Xem "báo cáo launch"** (một thao tác chỉ-đọc) cũng xoá toàn bộ bộ nhớ đệm,
   vì nó gọi lại bước "chuẩn bị launch".
3. **Một đường xoá bộ nhớ đệm dành cho mọi người dùng** (kể cả khách hàng, người
   chỉ xem) đang "ngủ": hiện không chạy được chỉ vì một lỗi khác làm tính năng
   huy hiệu (badge) ở thanh bên hỏng hoàn toàn. Ai sửa lỗi huy hiệu mà không sửa
   điểm này sẽ vô tình cho khách hàng quyền xoá bộ nhớ đệm của cả hệ thống.

## Người dùng bị ảnh hưởng

Mọi khách hàng: sau mỗi lần xoá, lớp chặn dò mật khẩu, chặn spam lời mời, giới
hạn AI đều quay về 0; hệ thống chậm lại một lúc vì phải nạp lại dữ liệu. Phiên
đăng nhập **không** bị mất (nằm ở vùng lưu trữ khác).

## Bằng chứng

Kiểm tra trên đúng phiên bản chính thức hiện tại, quét toàn bộ mã (17 chỗ có
lệnh xoá), đối chiếu 1171 đường truy cập đang hoạt động, và chạy thử thật bằng
tài khoản quản trị hệ thống, quản trị khách hàng và khách hàng. Chi tiết:
`docs/audits/2026-09-29-gap-055-http-cache-flush-evidence.md`.

## Phát hiện phụ (không sửa trong việc này)

Cùng hàm "chuẩn bị launch" còn **chạy cập nhật cơ sở dữ liệu (migrate) ngay
từ một yêu cầu web**, kể cả khi chỉ xem báo cáo. Nên tách thành một việc riêng.

## Tác động nếu không xử lý

Một cú bấm (hoặc một lần xem báo cáo) mở lại cửa cho kẻ dò mật khẩu trên toàn
hệ thống; đường "đang ngủ" có thể thành lỗ hổng mà người dùng thường khai thác
được ngay khi ai đó sửa tính năng huy hiệu.

## Phạm vi đề xuất

Nếu duyệt, Gate 2 thiết kế cách cho ba đường trên **không bao giờ** xoá bộ nhớ
đệm dùng chung nữa, có kiểm thử tự động chứng minh bộ đếm của khách hàng khác
còn nguyên.

## Loại trừ rõ ràng

- Không sửa việc chạy migrate từ web (đề xuất việc riêng).
- Không khôi phục tính năng huy hiệu ngoài mức cần để đường số 3 không thành lỗ
  hổng.
- Không đụng các đoạn mã xoá bộ nhớ đệm đang không được dùng.
- Không deploy, không đổi cấu hình máy chủ.

## Khả năng hoàn tác

Chỉ là tài liệu ở Gate này.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt cách sửa, Gate 2, merge hay phát hành.
