---
work_id: GAP-105
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-11-03-gap-105-treasury-boq-fund-chains-receivable-evidence.md
  plan: null
  branch: docs/GAP-105-treasury-boq-fund-chains-receivable
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
  created_at: "2026-11-03T22:10:00+07:00"
  updated_at: "2026-11-03T22:10:00+07:00"
generated_by: agent
---

## Owner Summary

Mở rộng Ngân quỹ với ba phần mà S6 (GAP-097) đã để lại: **liên kết BOQ**, **chuỗi quỹ**, **phải thu nhà đầu tư**.

Khảo sát cho thấy:
- **BOQ:**
  - Đã có BOQ và dòng BOQ (số lượng, đơn giá — đơn giá thường để trống).
  - Chứng từ Ngân quỹ **chưa có trường BOQ**, nên muốn biết "chi cho dòng BOQ nào" phải thêm cột (migration).
  - "Vượt ngân sách BOQ" chỉ tính được khi dòng BOQ có đơn giá; thiếu đơn giá thì ghi "chưa có dữ liệu".
- **Chuỗi quỹ:**
  - Bảng đã có từ GAP-037, nhưng chưa có chức năng tạo, xem hay báo cáo.
  - Phần cơ bản không cần migration.
- **Phải thu nhà đầu tư:**
  - Có **hai nguồn "tiền đã nhận"**: đợt thanh toán hợp đồng (ContractPayment, đã dùng cho công nợ) và tiền vào
    Ngân quỹ (funding).
  - Cộng thẳng thì có thể **đếm trùng** hoặc trừ nhầm hợp đồng. Gate 2 phải chọn một định nghĩa rõ ràng.
  - SSOT quy định ContractPayment vẫn là nguồn chính của công nợ hợp đồng.

## Vấn đề vận hành

Chưa biết tiền Ngân quỹ chi cho hạng mục BOQ nào, chưa truy được dòng tiền đi qua các bước (chuỗi quỹ), và chưa có
một con số "nhà đầu tư còn nợ bao nhiêu" đáng tin trong Ngân quỹ.

## Người dùng bị ảnh hưởng

Owner, kế toán dự án, PM có quyền Ngân quỹ.

## Bằng chứng

`docs/audits/2026-11-03-gap-105-treasury-boq-fund-chains-receivable-evidence.md`.

## Phạm vi đề xuất

Gate 2: thiết kế cả ba phần. Có thể chia thành nhiều lát triển khai (mỗi lát có Gate 3 riêng) nếu Gate 2 thấy cần.
- **BOQ:** gắn chi phí với dòng BOQ; quy tắc cùng dự án; lọc sổ; báo cáo chi theo BOQ; cảnh báo vượt khi có đơn giá.
- **Chuỗi quỹ:** ai tạo, thêm / bỏ thành viên, xem chuỗi, báo cáo truy vết.
- **Phải thu nhà đầu tư:** chọn định nghĩa không đếm trùng; báo cáo.

Kèm migration nếu cần, quyền, test (gồm MySQL thật cho ràng buộc mới).

## Loại trừ rõ ràng

Chứng từ đính kèm; khoá kỳ / import sao kê; ngưỡng cấu hình / hạn tạm ứng (các Work ID khác); đổi vòng đời
ContractPayment hay công nợ hiện có; sửa màn hình BOQ; lợi nhuận kế toán; deploy.

## Khả năng hoàn tác

Revert; migration (nếu có) chỉ thêm cột cho phép trống, có `down()`.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: `APPROVE GAP-105 Gate 1` / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa chọn định nghĩa phải thu, cách gắn BOQ, thiết kế, Gate 2, merge hay phát hành.
