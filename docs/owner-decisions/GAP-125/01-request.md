---
work_id: GAP-125
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-11-11-gap-125-mobile-field-pages-evidence.md
  plan: null
  branch: docs/GAP-125-mobile-field-pages
  pr: https://github.com/kha997/zenamanagephp/pull/416
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-11T13:27:43+07:00"
  owner_response_reference: "Owner decision in-session on 2026-11-11, verbatim: 'APPROVE GAP-125 Gate 1'. Bound to reviewed Draft PR #416 head e68b48ee7e84d172a37389a9ae0ec95d6c42a7af, canonical base c47261e36ad8e8f832a47521f2ac63618aa5877c. Authorizes preparing Gate 2 only."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-11T09:46:37+07:00"
  updated_at: "2026-11-11T13:27:43+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner approved GAP-125 Gate 1 in-session on 2026-11-11 against Draft PR #416 head
`e68b48ee7e84d172a37389a9ae0ec95d6c42a7af`. This authorizes preparation of Gate 2 only.

## Owner Summary

Làm các trang **công trường** dùng tốt trên điện thoại, cho kỹ sư và giám sát ở hiện trường.

**Đã đo 7 trang trên iPhone 13.** 3 trang có lỗi thật:

| Trang | Vấn đề |
|---|---|
| **Nhật ký công trường** (danh sách) | Bảng rộng gấp 3 màn hình: chỉ thấy Ngày và Dự án; nhân lực, trạng thái, nút "Xem" nằm ngoài màn hình |
| **Kiểm định** (danh sách) | Bảng bị cắt: ngày, trạng thái, nút thao tác nằm ngoài |
| **Kiểm định** (chi tiết) | Dài gần 5 màn hình. Trạng thái và người kiểm định nằm tận dưới, sau các đoạn chữ dài và form "Hoàn tất". Bảng NCR bị cắt, tiêu đề vỡ mỗi chữ một dòng |

4 trang còn lại không bị tràn: chi tiết NCR, xem nhật ký, tạo nhật ký, chi tiết công việc.

**Hướng làm (Gate 2 sẽ chọn):**
- Dạng thẻ trên điện thoại cho 3 bảng bị cắt, giống "Việc của tôi" và "Duyệt giờ".
- Sắp lại trang chi tiết kiểm định trên điện thoại: trạng thái và thông tin chính lên đầu; các đoạn chữ dài gập lại; form
  hoàn tất mở bằng nút.
- Máy tính giữ nguyên.

## Vấn đề vận hành

Nhật ký và nghiệm thu được ghi ngay tại công trường, thường bằng điện thoại. Bảng bị cắt thì không thấy trạng thái, không
bấm được nút "Xem".

## Người dùng bị ảnh hưởng

Kỹ sư công trường, giám sát, PM, khi dùng điện thoại.

## Bằng chứng

`docs/audits/2026-11-11-gap-125-mobile-field-pages-evidence.md`.

## Phạm vi đề xuất

Gate 2:
- thẻ cho 3 bảng;
- bố cục điện thoại cho chi tiết kiểm định;
- có đụng tới 4 trang không bị tràn hay không.

## Loại trừ rõ ràng

- Quy tắc, phân quyền, dữ liệu.
- Giao diện máy tính.
- RFI, hồ sơ trình duyệt, mua sắm.
- Deploy.

## Khả năng hoàn tác

Revert PR. Chỉ giao diện.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: `APPROVE GAP-125 Gate 1` / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa chọn phương án, Gate 2, merge.
