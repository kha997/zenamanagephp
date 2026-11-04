---
work_id: GAP-106
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-11-04-gap-106-treasury-evidence-attachments-evidence.md
  plan: null
  branch: docs/GAP-106-treasury-evidence-attachments
  pr: https://github.com/kha997/zenamanagephp/pull/396
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-04T09:10:26+07:00"
  owner_response_reference: "Owner decision in-session on 2026-11-04, verbatim: 'APPROVE GAP-106 Gate 1'. Bound to reviewed Draft PR #396 head a7d67f242e3bfb237776b2815ad16bda3c1360ef, canonical base 71bc0c946c3b1338723658a24adae6e55572905c. Authorizes preparing Gate 2 only."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-04T09:20:00+07:00"
  updated_at: "2026-11-04T09:10:26+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner approved GAP-106 Gate 1 in-session on 2026-11-04 against Draft PR #396 head
`a7d67f242e3bfb237776b2815ad16bda3c1360ef`. This authorizes preparation of Gate 2 only.

## Owner Summary

**Đính kèm chứng từ cho Ngân quỹ**: cho phép đính file (ảnh chuyển khoản, sao kê, hoá đơn, phiếu thu/chi, xác nhận nhận
tiền mặt, xác nhận tổ đội, hợp đồng / đơn hàng…) vào các khoản tiền, biết khoản nào **thiếu chứng từ**, và báo cáo "thiếu
chứng từ" (bản thiết kế Ngân quỹ gốc §7.12, §10.1, §11, §12.7 — S6 đã để lại).

Khảo sát cho thấy:
- Repo đã có hệ thống tài liệu chung (`Document`, có phiên bản) và bản thiết kế gốc khuyên **dùng lại**, không làm hệ lưu
  file thứ hai.
- **Rủi ro lộ thông tin:** trang "Tài liệu" chung (`/app/documents`) liệt kê **mọi** tài liệu của công ty, không kiểm tra
  quyền. Nếu lưu sao kê / phiếu chi như tài liệu thường thì ai vào app cũng thấy, trong khi Ngân quỹ chỉ người có quyền
  mới xem được. Gate 2 phải chọn cách giữ kín (ẩn khỏi danh sách chung + chỉ tải qua quyền Ngân quỹ, hoặc bảng đính kèm
  riêng của Ngân quỹ như phần quy trình mẫu).
- Chưa có cài đặt "bắt buộc chứng từ"; Gate 2 cần chọn: bắt buộc với khoản chi, bật / tắt theo công ty, hay chỉ cảnh báo
  "thiếu chứng từ".

## Vấn đề vận hành

Khoản chi / thu trong Ngân quỹ chưa gắn được bằng chứng; không biết khoản nào thiếu chứng từ; kiểm tra lại phải tìm giấy tờ
ở nơi khác.

## Người dùng bị ảnh hưởng

Owner, kế toán, người lập khoản chi, người duyệt.

## Bằng chứng

`docs/audits/2026-11-04-gap-106-treasury-evidence-attachments-evidence.md`.

## Phạm vi đề xuất

Gate 2: thiết kế đính kèm chứng từ cho chứng từ Ngân quỹ — nơi lưu và cách giữ kín, loại chứng từ, khoản nào cần chứng
từ, quy tắc bắt buộc (nếu có), ai được thêm / gỡ, giới hạn file; thẻ và cảnh báo "thiếu chứng từ", báo cáo "Thiếu chứng
từ", bộ lọc sổ; migration nếu cần; test (gồm quyền tải file, cách ly tenant / dự án).

## Loại trừ rõ ràng

OCR / tự đọc hoá đơn; import sao kê ngân hàng; khoá kỳ; ngưỡng / hạn tạm ứng (Work ID khác); sửa module tài liệu chung
ngoài phần cần để giữ kín chứng từ Ngân quỹ; deploy.

## Khả năng hoàn tác

Revert; migration (nếu có) chỉ thêm, có `down()`.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: `APPROVE GAP-106 Gate 1` / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa chọn cách lưu, quy tắc bắt buộc, thiết kế, Gate 2, merge hay phát hành.
