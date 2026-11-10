---
work_id: GAP-121
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-11-10-gap-121-month-close-mysql-concurrency-evidence.md
  plan: null
  branch: docs/GAP-121-month-close-mysql-concurrency
  pr: https://github.com/kha997/zenamanagephp/pull/411
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
  created_at: "2026-11-10T22:11:04+07:00"
  updated_at: "2026-11-10T22:11:04+07:00"
generated_by: agent
---

## Owner Summary

Thêm **test tự động chạy trên MySQL thật trong CI**, chứng minh việc **chốt tháng** an toàn khi nhiều người thao tác cùng
lúc.

**Hiện trạng:**
- Lúc chốt lương / chốt chi phí chung, hệ thống đã khoá dữ liệu tháng đó. Ví dụ: đang chốt lương thì không ai duyệt hay
  thêm giờ vào tháng đó được.
- Khi làm GAP-113 tôi đã kiểm tra việc này **bằng tay** trên MySQL với 2 phiên cùng lúc.
- Test tự động hiện chỉ chạy **từng thao tác một**, chưa có test nào cho **hai thao tác chạy đồng thời**.
- CI đã có sẵn 4 bộ test kiểu này cho phần khác (RFI, Ngân quỹ…). Có thể làm theo đúng mẫu đó.

**Các tình huống nên kiểm (Gate 2 sẽ chọn):**
1. Đang chốt lương thì có người duyệt hoặc thêm giờ cho tháng đó. Phải bị từ chối, số chi phí nhân công phải khớp.
2. Hai người cùng bấm chốt lương một tháng. Chỉ một lần được, không chia chi phí hai lần.
3. Một người chốt chi phí chung đúng lúc người khác mở lại bảng lương. Không bao giờ ra trạng thái "chi phí chung đã chốt mà
   lương đang mở".
4. Hai người cùng chốt chi phí chung. Chỉ một lần được.

## Vấn đề vận hành

Nếu sau này ai đó sửa phần chốt tháng mà vô tình làm mất khoá dữ liệu, test thường không phát hiện được. Số lương / chi phí
có thể sai âm thầm khi nhiều người làm cùng lúc cuối tháng.

## Người dùng bị ảnh hưởng

Gián tiếp: Owner, kế toán, PM (độ tin cậy số liệu cuối tháng). Không đổi gì trên giao diện.

## Bằng chứng

`docs/audits/2026-11-10-gap-121-month-close-mysql-concurrency-evidence.md`.

## Phạm vi đề xuất

Gate 2:
- test tự động chạy hai tiến trình song song trên MySQL;
- lệnh hỗ trợ test;
- một job CI mới theo mẫu sẵn có.

Không đổi quy tắc chốt. Nếu test phát hiện lỗi thật, tôi sẽ báo và hỏi trước khi sửa.

## Loại trừ rõ ràng

- Đổi quy tắc / khoá của phần chốt.
- Ngân quỹ.
- Deploy.

## Khả năng hoàn tác

Revert PR. Chỉ thêm test, lệnh hỗ trợ test và job CI. Không đổi dữ liệu.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: `APPROVE GAP-121 Gate 1` / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa chọn phương án, Gate 2, merge.
