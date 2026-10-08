---
work_id: GAP-070
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-10-08-gap-070-composer-security-advisories-evidence.md
  plan: null
  branch: docs/GAP-070-composer-security-advisories
  pr: null
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
  created_at: "2026-10-08T15:46:22+07:00"
  updated_at: "2026-10-08T15:46:22+07:00"
generated_by: agent
---

## Owner Summary

Bước 1 của kế hoạch xử lý cảnh báo bot. `composer audit` báo **20 lỗ hổng** ở 4
thư viện:

- `league/commonmark`: 12 lỗ hổng, trong đó 9 mức cao.
- `guzzlehttp/guzzle`: 6, trong đó 1 mức cao.
- `laravel/framework`: 1, mức thấp.
- `league/flysystem`: 1, mức thấp.

Cả 20 đều đã có bản vá **nằm trong giới hạn phiên bản hiện tại**. Chỉ cần
cập nhật 6 gói trong `composer.lock`, không sửa `composer.json` hay code. Sửa
xong thì báo cáo bảo mật của bot về 0, và phần Guzzle trong báo cáo Docker cũng
hết. Đề xuất: phê duyệt Gate 1 để thiết kế.

## Vấn đề vận hành

Thư viện đang khoá ở các phiên bản có lỗ hổng đã công bố (từ chối dịch vụ khi
gặp Markdown được tạo ác ý, XSS, lộ cookie hoặc header qua HTTP client).

## Người dùng bị ảnh hưởng

Toàn bộ ứng dụng (thư viện dùng chung). Chưa kiểm xem dữ liệu người dùng có đi
vào các đường gây lỗi hay không; bản cập nhật xoá lỗ hổng trong mọi trường hợp.

## Bằng chứng

`docs/audits/2026-10-08-gap-070-composer-security-advisories-evidence.md`:
kết quả `composer audit` và chạy thử `composer update --dry-run`.

## Phạm vi đề xuất

Gate 2 thiết kế: cập nhật đúng 6 gói (lockfile), chạy đủ bộ test và CI,
`composer audit` phải về 0.

## Loại trừ rõ ràng

Không nâng phiên bản chính (Guzzle 8, DBAL 4…), không cập nhật hàng loạt 91 gói
cũ, không sửa Docker image, không đổi CI. Đó là các bước khác của kế hoạch.
Không deploy.

## Khả năng hoàn tác

Gate 1 chỉ là tài liệu.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt thiết kế, thay đổi code, merge hay phát hành.
