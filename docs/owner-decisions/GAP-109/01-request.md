---
work_id: GAP-109
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-11-04-gap-109-staff-payroll-evidence.md
  plan: null
  branch: docs/GAP-109-staff-payroll
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
  created_at: "2026-11-04T17:40:00+07:00"
  updated_at: "2026-11-04T17:40:00+07:00"
generated_by: agent
---

## Owner Summary

Phần 1/4 của hướng "tính lời lỗ từng công trình theo giờ công" mà anh/chị đã đồng ý:

1. **Danh sách nhân sự**: nhân viên và cộng tác viên, đánh dấu ai thuộc khối văn phòng (sau này tính vào chi phí chung).
2. **Bảng lương tháng chi tiết từng người**: lương, phụ cấp, tăng ca, thưởng, bảo hiểm phần công ty đóng, khấu trừ, thực
   nhận, và **tổng chi phí công ty thực chịu**.
3. **Giờ chuẩn của tháng** và **đơn giá giờ** của từng người = tổng chi phí ÷ giờ chuẩn; chốt (khoá) theo tháng.

Khảo sát cho thấy:
- Hệ thống chưa có bảng nhân sự, bảng lương hay chấm giờ. Người dùng chỉ có chức danh, phòng ban.
- Ngân quỹ có đối tác loại "nhân viên" / "tổ đội" nhưng không có dữ liệu lương.
- Ngân quỹ hiện bắt buộc mọi khoản chi gắn với một dự án, nên **chưa ghi được khoản trả lương cấp công ty**.
- Chưa có quyền riêng cho dữ liệu lương.

Gate 2 sẽ đưa phương án: các cột của bảng lương; nhập số hay tự tính bảo hiểm / thuế; thưởng lớn chia 1/12 hay tính tháng
chi; giờ chuẩn; khoá tháng; chỉ ghi nhận hay chi trả qua Ngân quỹ; ai được xem lương.

## Vấn đề vận hành

Không có số chi phí thật của từng người theo tháng nên không tính được nhân công cho công trình, không biết lời lỗ thật.

## Người dùng bị ảnh hưởng

Owner, kế toán; nhân viên / cộng tác viên (gián tiếp).

## Bằng chứng

`docs/audits/2026-11-04-gap-109-staff-payroll-evidence.md`.

## Phạm vi đề xuất

Gate 2: thiết kế danh sách nhân sự, bảng lương tháng, giờ chuẩn, đơn giá giờ, khoá tháng, quyền xem lương, nhật ký;
migration bảng mới; test (gồm MySQL thật).

## Loại trừ rõ ràng

Ghi giờ theo công việc (phần 2); tính nhân công cho công trình (phần 3); chi phí chung và lời lỗ công trình (phần 4); tự
tính thuế / bảo hiểm theo luật; gửi phiếu lương; file chi lương ngân hàng; deploy.

## Khả năng hoàn tác

Revert; migration chỉ tạo bảng mới, có `down()`.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: `APPROVE GAP-109 Gate 1` / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa chọn cột lương, cách tính, thiết kế, Gate 2, merge hay phát hành.
