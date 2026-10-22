---
work_id: GAP-090
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-10-22-gap-090-dusk-flakes-evidence.md
  plan: null
  branch: docs/GAP-090-dusk-flakes
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
  created_at: "2026-10-22T08:57:40+07:00"
  updated_at: "2026-10-22T08:57:40+07:00"
generated_by: agent
---

## OWNER GATE 1: AWAITING OWNER

## Owner Summary

Mình thống kê **25 lần chạy gần nhất** của job browser-tests (Dusk):

- **12/25 lần (48%)** lần chạy đầu hỏng, phải chạy lại mới xanh. Chưa lần nào hỏng cả hai lần.
- Mỗi lần phải chạy lại, job mất **~35 phút** thay vì ~17 phút.
- Lỗi chỉ xảy ra ở test tạo dự án (`tests/Browser/Projects/`), không bao giờ ở test CRM.

**3 nguyên nhân:**
1. **`ERR_CONNECTION_REFUSED`** (13 lần): server PHP dựng sẵn (`php -S`) bị crash. Trình duyệt
   gọi đúng lúc server đang khởi động lại nên bị từ chối kết nối.
2. **Click nút Hủy bị topbar che** (5 lần): Dusk cuộn nút lên sát mép trên, thanh topbar
   cố định đè lên nút.
3. **Hết giờ chờ 10 giây** (7 lần): nhiều khả năng cũng do server chậm hoặc vừa khởi động lại.

## Vấn đề vận hành

CI của mọi PR chậm thêm khoảng 18 phút ở gần một nửa số lần chạy. Lần chạy lại còn có
thể che mất một lỗi thật nếu lỗi đó cũng chập chờn.

## Người dùng bị ảnh hưởng

Người phát triển: thời gian chờ CI.

## Phạm vi đề xuất

Gate 2 thiết kế:
- thay `php -S` bằng server ổn định hơn, hoặc cho test chờ server sẵn sàng;
- sửa cách click nút Hủy;
- quyết định có giữ lần chạy lại hay không;
- cách chứng minh: chạy nhiều lần liên tiếp đều xanh ngay lần đầu.

## Loại trừ rõ ràng

Không đổi giao diện, không đổi logic ứng dụng, không đổi các test khác. Không deploy.

## Khả năng hoàn tác

Gate 1 chỉ là tài liệu.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt thiết kế, sửa workflow hay test, merge, phát hành hay deploy.
