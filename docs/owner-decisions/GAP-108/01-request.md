---
work_id: GAP-108
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-11-04-gap-108-treasury-thresholds-advance-deadline-evidence.md
  plan: null
  branch: docs/GAP-108-treasury-thresholds-advance-deadline
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
  created_at: "2026-11-04T15:40:00+07:00"
  updated_at: "2026-11-04T15:40:00+07:00"
generated_by: agent
---

## Owner Summary

Phần mở rộng Ngân quỹ cuối cùng:

1. **Ngưỡng cảnh báo cấu hình được**: hiện đang cố định trong mã (theo trả lời của anh/chị ở S6):
   - tiền nhận chưa đối soát **quá 30 ngày** thì cảnh báo;
   - tạm ứng còn nợ **quá 90 ngày** là quá hạn.

   Bản thiết kế gốc muốn các ngưỡng này **cấu hình được**.
2. **Hạn cho từng khoản tạm ứng**: hiện tạm ứng không có hạn hoàn ứng. Bản thiết kế gốc có trường "hạn" và trạng thái
   "quá hạn" khi qua hạn mà còn nợ.

Khảo sát cho thấy:
- Chưa có nơi lưu cài đặt riêng cho Ngân quỹ. GAP-106 đã lưu công tắc "bắt buộc chứng từ" trong cài đặt công ty; có thể
  dùng cùng cách.
- Bảng tạm ứng chưa có cột hạn; thêm được một cột ngày cho phép trống mà không ảnh hưởng ràng buộc cũ.
- Mọi chỗ hiển thị tuổi nợ / cảnh báo tạm ứng (thẻ trang Ngân quỹ, báo cáo tuổi nợ, trang tổng) đều đi qua một hàm, nên
  sửa một chỗ là đồng bộ.

Gate 2 sẽ đưa phương án:
- ngưỡng nào cấu hình được, theo công ty hay theo dự án, ai được đổi;
- hạn tạm ứng nhập ở đâu (khi lập đề nghị / sau khi chi), bắt buộc hay không, có cho sửa sau không;
- khoản không có hạn thì tính quá hạn ra sao.

## Vấn đề vận hành

Ngưỡng cảnh báo không hợp với mọi dự án / công ty; không giao được hạn hoàn ứng cho từng người nên không biết ai trễ.

## Người dùng bị ảnh hưởng

Owner, kế toán, người nhận tạm ứng.

## Bằng chứng

`docs/audits/2026-11-04-gap-108-treasury-thresholds-advance-deadline-evidence.md`.

## Phạm vi đề xuất

Gate 2: thiết kế ngưỡng cấu hình (lưu ở đâu, ai đổi, nhật ký) và hạn tạm ứng (nhập, sửa, quy tắc quá hạn, số ngày quá
hạn trên trang / báo cáo / cảnh báo); migration nếu cần; test (gồm MySQL thật).

## Loại trừ rõ ràng

Nhắc hạn tự động qua email / Slack / Zalo; phạt / lãi trễ hạn; đổi các báo cáo S6 ngoài cột quá hạn; deploy.

## Khả năng hoàn tác

Revert; migration (nếu có) chỉ thêm cột cho phép trống, có `down()`.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: `APPROVE GAP-108 Gate 1` / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa chọn ngưỡng, nơi nhập hạn, thiết kế, Gate 2, merge hay phát hành.
