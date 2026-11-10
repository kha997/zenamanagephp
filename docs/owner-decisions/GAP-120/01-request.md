---
work_id: GAP-120
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-11-10-gap-120-month-end-reminders-evidence.md
  plan: null
  branch: docs/GAP-120-month-end-reminders
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
  created_at: "2026-11-10T14:15:18+07:00"
  updated_at: "2026-11-10T14:15:18+07:00"
generated_by: agent
---

## Owner Summary

**Tự động nhắc việc cuối tháng**, để không ai phải tự vào kiểm tra.

**Hiện trạng:**
- Muốn chốt lương tháng, mọi dòng giờ phải được duyệt trước. Hệ thống từ chối chốt nếu còn giờ chờ duyệt (GAP-113).
- Sau khi chốt lương mới chốt được chi phí chung (GAP-114).
- Nhưng **không có gì nhắc**:
  - PM không biết còn giờ chờ mình duyệt;
  - Owner / kế toán không biết tháng trước chưa chốt.

**Hệ thống đã có:**
- **Thông báo trong ứng dụng:** hiện ở mục "Cập nhật chưa đọc" trên trang **"Hôm nay"**. RFI và hồ sơ trình duyệt đang dùng.
- **Gửi email:** đã có cấu hình. Chưa rõ máy chủ thật đã cài email hay chưa.
- **Bộ chạy việc định kỳ:** có, nhưng đang **tắt mặc định** (`ENABLE_SCHEDULER=false`).

**Gate 2 sẽ đưa phương án:**
- **Nhắc ai, nhắc gì:**
  - PM / người duyệt: còn giờ chờ duyệt;
  - Owner / kế toán: tháng chưa chốt lương, chưa chốt chi phí chung;
  - nhân viên (tuỳ chọn): đồng hồ quên tắt, giờ bị từ chối cần sửa.
- **Khi nào nhắc:** mấy ngày cuối tháng và đầu tháng sau, hoặc mỗi ngày khi còn việc; không nhắc trùng.
- **Kênh:** chỉ trong ứng dụng, hay thêm email.
- **Cách chạy:** một lệnh chạy định kỳ, có chế độ chạy thử. Việc bật bộ chạy định kỳ trên máy chủ do anh/chị làm, sẽ ghi vào
  hướng dẫn go-live.

## Vấn đề vận hành

Cuối tháng dễ bị trễ:
- PM quên duyệt giờ, nên kế toán không chốt được lương;
- chốt lương xong lại quên chốt chi phí chung, nên lời lỗ công trình chỉ là "tạm tính".

## Người dùng bị ảnh hưởng

PM, Owner, kế toán; nhân viên nếu chọn nhắc cho họ.

## Bằng chứng

`docs/audits/2026-11-10-gap-120-month-end-reminders-evidence.md`.

## Phạm vi đề xuất

Gate 2:
- lệnh nhắc việc cuối tháng, ghi thông báo trong ứng dụng (và email nếu chọn), có link tới đúng trang;
- lịch chạy;
- test;
- cập nhật hướng dẫn go-live.

## Loại trừ rõ ràng

- Nhắn tin SMS / Zalo / push.
- Đổi quy tắc duyệt hay chốt.
- Nhắc cho các phần khác (RFI, hồ sơ).
- Trang cài đặt thông báo.
- Bật bộ chạy định kỳ trên máy chủ.
- Deploy.

## Khả năng hoàn tác

Revert PR. Không đổi dữ liệu nghiệp vụ. Chỉ thêm dòng thông báo; nếu cần bảng chống nhắc trùng thì là migration loại
"expand".

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: `APPROVE GAP-120 Gate 1` / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa chọn phương án, Gate 2, merge.
