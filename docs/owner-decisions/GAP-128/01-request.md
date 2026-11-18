---
work_id: GAP-128
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-11-18-gap-128-websocket-server-evidence.md
  plan: null
  branch: docs/GAP-128-websocket-server
  pr: https://github.com/kha997/zenamanagephp/pull/418
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: null
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-18T16:43:19+07:00"
  updated_at: "2026-11-18T16:43:19+07:00"
generated_by: agent
---

## Owner Summary

Máy chủ "websocket" (cập nhật màn hình theo thời gian thực) của ZENA **không chạy được ở bất kỳ đâu**, và **không trang nào dùng nó**.

- Lệnh `php artisan websocket:serve` dừng ngay khi khởi động vì thiếu thư viện Ratchet (`cboden/ratchet`).
- Khi dựng máy chủ thật hôm 2026-11-18, dịch vụ `zena-websocket` cứ khởi động lại liên tục, nên đã được thay bằng một dịch vụ "giả" không làm gì, chỉ để quy trình deploy (`production.yml`) có thể gọi "khởi động lại".
- Không trang nào đang dùng thật kết nối tới máy chủ này. Thông báo (chuông, hộp thư, Slack) đọc từ cơ sở dữ liệu, không cần websocket.

## Vấn đề vận hành

Không ảnh hưởng người dùng hôm nay. Vấn đề là máy chủ production có một dịch vụ giả, một quyền `sudo` thừa, và runbook dựng máy yêu cầu chạy một server không thể chạy. Người vận hành sau này dễ hiểu nhầm là có tính năng realtime.

## Người dùng bị ảnh hưởng

Không ai. Chỉ người dựng/vận hành máy chủ.

## Bằng chứng

`docs/audits/2026-11-18-gap-128-websocket-server-evidence.md`.

## Các phương án (chọn ở Gate 2)

- **A — Gỡ server websocket chết (khuyến nghị).** Xoá lệnh `websocket:serve` và handler; bỏ 2 dòng "khởi động lại zena-websocket" trong `production.yml`; bỏ dịch vụ websocket và quyền sudo tương ứng khỏi runbook dựng máy. Sau khi deploy, người giữ quyền root tắt và xoá dịch vụ giả trên máy chủ. Người dùng không thấy gì thay đổi.
- **B — Thêm thư viện Ratchet và chạy thật.** Server sẽ chạy, nhưng vẫn không trang nào kết nối tới (trình duyệt dùng giao thức và cổng khác). Muốn màn hình tự cập nhật thật là một tính năng mới, cần yêu cầu nghiệp vụ riêng.
- **C — Để sau.** Giữ dịch vụ giả như hiện tại.

## Loại trừ rõ ràng

- Tính năng cập nhật màn hình thời gian thực (nếu muốn, là GAP riêng).
- Các mã realtime chết khác (API quản trị `api/websocket/*`, `realtime/*`, kiểm tra sức khoẻ cổng 6001, Echo trong layout cũ): đã liệt kê trong bằng chứng, chỉ gỡ nếu Owner yêu cầu ở Gate 2.
- Deploy và thao tác trên máy chủ.

## Khả năng hoàn tác

Revert PR, và dựng lại dịch vụ giả trên máy chủ nếu đã xoá.

## Decision Needed

Owner chọn một: `APPROVE GAP-128 Gate 1` / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Chọn phương án A/B/C (đó là Gate 2), merge, deploy.
