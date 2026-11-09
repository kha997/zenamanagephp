---
work_id: GAP-117
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-11-10-gap-117-project-milestones-evidence.md
  plan: null
  branch: docs/GAP-117-project-milestones
  pr: https://github.com/kha997/zenamanagephp/pull/407
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-10T02:32:12+07:00"
  owner_response_reference: "Owner decision in-session on 2026-11-09, verbatim: 'APPROVE GAP-117 Gate 1'. Bound to reviewed Draft PR #407 head 9bf07aebb67148e6ec727102ead9bd29c88a5977, canonical base 41daaca199f5fd1f288a42e7c3ebaa6228c73d71. Authorizes preparing Gate 2 only."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-10T02:30:48+07:00"
  updated_at: "2026-11-10T02:32:12+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner approved GAP-117 Gate 1 in-session on 2026-11-09 against Draft PR #407 head
`9bf07aebb67148e6ec727102ead9bd29c88a5977`. This authorizes preparation of Gate 2 only.

## Owner Summary

Cho PM **tạo và theo dõi mốc tiến độ** (milestone) của dự án, ví dụ "Xong móng 15/12", "Bàn giao 30/03".

**Hiện trạng:**
- Hệ thống đã có bảng lưu mốc, và nhiều màn hình đã sẵn chỗ hiện mốc:
  - OPPM: số mốc đã xong / quá hạn;
  - trang "Hôm nay": mốc sắp đến hạn hoặc quá hạn.
- Nhưng **không có chỗ nào để tạo mốc**: phần tạo/sửa cũ hỏng từ đầu và đã gỡ ngày 22/07.
- Vì vậy mọi dự án đều có 0 mốc, và các ô kia luôn trống.

**Cũng tìm thấy** vài lỗi nhỏ trong phần mốc cũ:
- trạng thái "quá hạn" bị lưu cứng, không tự bỏ đi khi dời ngày;
- một sự kiện "hoàn thành mốc" viết sai, không chạy được;
- mốc chưa có cột tách theo công ty (tenant), chỉ dựa vào dự án.

**Gate 2 sẽ đưa phương án:**
- mốc nằm ở đâu: thẻ trên trang dự án, hay trang riêng;
- ai được sửa (đề xuất: PM của dự án hoặc người có quyền sửa dự án, giống OPPM);
- thao tác: thêm, sửa, đánh dấu xong, huỷ, sắp xếp;
- có thêm cột công ty và tính "quá hạn" theo ngày thực tế không.

## Vấn đề vận hành

PM không ghi được các mốc quan trọng của dự án. OPPM và trang "Hôm nay" không thể cảnh báo mốc trễ hạn.

## Người dùng bị ảnh hưởng

PM, Owner (xem OPPM), nhân viên (trang "Hôm nay").

## Bằng chứng

`docs/audits/2026-11-10-gap-117-project-milestones-evidence.md`.

## Phạm vi đề xuất

Gate 2: trang hoặc thẻ quản lý mốc cho từng dự án, có kiểm tra quyền, tách theo công ty và có test. Các màn hình đã đọc mốc
giữ nguyên, sẽ tự hiện dữ liệu.

## Loại trừ rõ ràng

- Gắn mốc với công việc hoặc mẫu công việc.
- Biểu đồ Gantt.
- Nhắc việc, thông báo.
- Cổng khách hàng.
- Giao diện điện thoại.
- Đổi cách tính tiến độ dự án.
- Khôi phục API cũ.
- Deploy.

## Khả năng hoàn tác

Revert PR. Nếu Gate 2 chọn thêm cột công ty thì có migration loại "expand" (chỉ thêm cột), không xoá dữ liệu.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: `APPROVE GAP-117 Gate 1` / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa chọn phương án, Gate 2, merge.
