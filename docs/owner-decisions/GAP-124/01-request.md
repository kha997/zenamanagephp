---
work_id: GAP-124
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-11-11-gap-124-business-notifications-evidence.md
  plan: null
  branch: docs/GAP-124-business-notifications
  pr: https://github.com/kha997/zenamanagephp/pull/415
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
  created_at: "2026-11-11T08:05:52+07:00"
  updated_at: "2026-11-11T08:05:52+07:00"
generated_by: agent
---

## Owner Summary

Làm **thông báo nghiệp vụ** đến được người cần biết. Đây là việc 1 còn mở trong lộ trình.

**Hiện trạng:**
- **Phần mềm đã tạo thông báo trong app** cho nhiều việc:
  - yêu cầu thay đổi, hồ sơ trình duyệt, phiếu không đạt (NCR);
  - hạng mục thiết kế;
  - khách hàng tiềm năng, cơ hội, báo giá;
  - thanh toán;
  - nhắc việc cuối tháng.
- **Nhưng chỉ xem được ở trang "Hôm nay".** Không có chuông báo, không có số chưa đọc trên thanh trên cùng, không có trang
  xem tất cả thông báo.
- **Không có gì gửi ra ngoài.** Kênh email và webhook mới là phần khung, chưa gửi. Chưa có chỗ để công ty khai báo kênh Slack
  của mình.
- **Việc quá hạn không ai được báo,** trừ khi có người tự bấm "báo quá hạn" cho việc khắc phục NCR. PM không được báo khi việc
  của đội trễ hạn.

**Hướng làm (Gate 2 sẽ chọn):**
- **Chuông báo** trên thanh trên cùng, có số chưa đọc, và trang danh sách thông báo.
- **Gửi các sự kiện chọn lọc vào kênh Slack của công ty.** Owner dán đường link Slack (incoming webhook) trong phần cài đặt.
- **Mỗi sáng một bản tóm tắt việc quá hạn,** gửi PM và/hoặc kênh Slack.

## Vấn đề vận hành

- PM và kế toán không biết có hồ sơ chờ duyệt hay việc trễ, nếu không tự mở "Hôm nay".
- Việc chậm phát hiện thì tiến độ và chi phí trễ theo.

## Người dùng bị ảnh hưởng

- Owner, PM, kế toán: nhận thông báo.
- Owner: cài đặt kênh Slack.

## Bằng chứng

`docs/audits/2026-11-11-gap-124-business-notifications-evidence.md`.

## Phạm vi đề xuất

Gate 2:
- những sự kiện nào gửi Slack;
- đường link Slack lưu theo công ty hay theo server;
- tóm tắt việc quá hạn (ai nhận, giờ nào);
- chuông báo và trang danh sách;
- email có làm không.

## Loại trừ rõ ràng

- Zalo OA / ZNS (việc riêng).
- Thông báo đẩy về điện thoại.
- Thông báo tức thời qua websocket.
- Deploy.

## Khả năng hoàn tác

- Revert PR.
- Thông báo đã tạo vẫn nằm trong cơ sở dữ liệu.
- Slack chỉ gửi khi công ty đã khai báo đường link.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: `APPROVE GAP-124 Gate 1` / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa chọn phương án, Gate 2, merge.
