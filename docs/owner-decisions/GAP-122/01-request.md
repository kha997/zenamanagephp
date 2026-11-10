---
work_id: GAP-122
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-11-11-gap-122-mobile-time-review-evidence.md
  plan: null
  branch: docs/GAP-122-mobile-time-review
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
  created_at: "2026-11-11T00:08:30+07:00"
  updated_at: "2026-11-11T00:08:30+07:00"
generated_by: agent
---

## Owner Summary

Làm trang **"Duyệt giờ"** dùng tốt trên điện thoại, cho PM / kế toán duyệt giờ ngay ở công trường.

**Hiện trạng trên điện thoại (iPhone 13):**
- Menu và thanh trên cùng đã gọn sẵn nhờ GAP-118.
- **Bảng bị cắt.** Các cột **công trình, thời lượng, ghi chú** nằm ngoài màn hình, mà đó chính là những thứ cần xem để
  duyệt. Phải vuốt ngang trong bảng mới thấy.
- Tên người và tên việc bị vỡ mỗi chữ một dòng. 6 dòng giờ đã dài hơn 2 màn hình.
- Ô chọn rất nhỏ (16 px).
- **Tất cả các dòng được chọn sẵn.** Nút duyệt / từ chối nằm tận cuối trang. Muốn từ chối 1 dòng phải bỏ chọn từng dòng
  còn lại. Sót một dòng là duyệt hoặc từ chối nhầm.

**Hướng làm (Gate 2 sẽ chọn):**
- Trên điện thoại, mỗi dòng giờ thành **một thẻ**: người, ngày, việc, công trình, **thời lượng nổi bật**, ghi chú.
- **Duyệt / từ chối ngay trên từng thẻ.**
- Có thể gom theo người, kèm tổng giờ mỗi người.

Máy tính giữ nguyên.

## Vấn đề vận hành

Cuối tháng phải duyệt hết giờ thì mới chốt lương được (GAP-113). Nhắc việc cuối tháng (GAP-120) gửi PM đường link tới
trang này, và PM thường mở bằng điện thoại. Trên điện thoại hiện khó xem đủ thông tin và dễ thao tác nhầm.

## Người dùng bị ảnh hưởng

PM và người có quyền duyệt giờ (`project.update`, `payroll.manage`), khi dùng điện thoại.

## Bằng chứng

`docs/audits/2026-11-11-gap-122-mobile-time-review-evidence.md`.

## Phạm vi đề xuất

Gate 2:
- giao diện thẻ cho "Duyệt giờ" trên điện thoại;
- nút duyệt / từ chối trên từng thẻ;
- tuỳ chọn gom theo người;
- cỡ nút cho dễ bấm.

Không đổi quy tắc duyệt.

## Loại trừ rõ ràng

- Quy tắc duyệt, phân quyền, khoá tháng.
- Bảng trên máy tính.
- Trang khác.
- Deploy.

## Khả năng hoàn tác

Revert PR. Chỉ đổi giao diện, không đổi dữ liệu.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: `APPROVE GAP-122 Gate 1` / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa chọn phương án, Gate 2, merge.
