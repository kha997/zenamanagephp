---
work_id: GAP-118
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-11-10-gap-118-mobile-my-work-timesheet-evidence.md
  plan: null
  branch: docs/GAP-118-mobile-my-work-timesheet
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-10T08:46:43+07:00"
  updated_at: "2026-11-10T08:46:43+07:00"
generated_by: agent
---

## Owner Summary

Làm cho **"Việc của tôi"** và **"Bảng giờ"** dùng được trên điện thoại, để nhân viên công trường bấm giờ ngay tại chỗ.

**Hiện trạng** (chụp màn hình giả lập iPhone 13):
- **Toàn bộ menu nằm trên đầu trang.** Nội dung bắt đầu cách đầu trang khoảng 1.050 px, tức màn hình đầu tiên chỉ toàn menu.
  Muốn bấm giờ phải cuộn qua hết menu.
- **Thanh trên cùng chiếm nhiều chỗ:** tên người dùng xuống 3 dòng, ô tìm kiếm bị ép thành một ô vuông nhỏ.
- **Bảng bị cắt:**
  - bảng "Các dòng giờ" rộng gấp gần 2 lần màn hình, nên cột thời lượng, trạng thái, nút sửa / xoá bị khuất;
  - bảng việc bị ép, ngày hạn bị cắt.
- **Nút "Bắt đầu" ghi giờ nằm xa,** ở cuối trang "Việc của tôi".
- **Đã ổn:** không bị cuộn ngang cả trang; các ô nhập đã xếp một cột.

**Lưu ý:** menu và thanh trên cùng dùng chung cho khoảng 106 trang. Sửa phần này thì mọi trang đều gọn hơn trên điện thoại.
Giao diện máy tính giữ nguyên.

**Gate 2 sẽ đưa phương án:**
- menu trên điện thoại: nút "Menu" thu gọn, hay thanh tab dưới đáy;
- thanh trên cùng gọn lại;
- bảng đổi thành dạng thẻ trên điện thoại;
- nút "Bắt đầu" ngay trên từng việc, và thanh "Đang ghi giờ… Dừng" luôn hiện khi đồng hồ đang chạy;
- trang "Duyệt giờ" của PM làm luôn hay để sau;
- cách kiểm thử ở khổ điện thoại.

## Vấn đề vận hành

Nhân viên công trường khó bấm giờ trên điện thoại, nên dễ quên hoặc nhập bù sai. Số liệu chi phí nhân công (GAP-113) và lời
lỗ công trình (GAP-114) đều phụ thuộc vào số giờ này.

## Người dùng bị ảnh hưởng

Kỹ sư, nhân viên công trường. PM khi dùng điện thoại. Mọi người dùng giao diện vận hành trên điện thoại (phần menu và thanh
trên cùng).

## Bằng chứng

`docs/audits/2026-11-10-gap-118-mobile-my-work-timesheet-evidence.md`.

## Phạm vi đề xuất

Gate 2:
- menu và thanh trên cùng của khung giao diện chung, chỉ áp dụng khi màn hình hẹp;
- trang "Việc của tôi" và "Bảng giờ" ở khổ điện thoại.

Không đổi giao diện máy tính, không đổi dữ liệu.

## Loại trừ rõ ràng

- Ứng dụng điện thoại riêng.
- Chạy không cần mạng (offline).
- Thông báo đẩy.
- Thiết kế lại giao diện máy tính.
- Các trang khác ngoài phần khung dùng chung.
- Deploy.

## Khả năng hoàn tác

Revert PR. Không migration, không đổi dữ liệu.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: `APPROVE GAP-118 Gate 1` / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa chọn phương án, Gate 2, merge.
