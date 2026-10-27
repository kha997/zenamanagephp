---
work_id: GAP-096
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-10-27-gap-096-treasury-s5-advances-readiness.md
  plan: null
  branch: docs/GAP-096-treasury-s5-advances
  pr: https://github.com/kha997/zenamanagephp/pull/375
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
  created_at: "2026-10-27T12:55:23+07:00"
  updated_at: "2026-10-27T12:55:23+07:00"
generated_by: agent
---

## OWNER GATE 1: AWAITING OWNER

## Owner Summary

Lát **S5 — Tạm ứng và quyết toán** của Ngân quỹ (Issue #244).

Hiện không ghi được tiền tạm ứng: kỹ sư nhận tiền để mua vật tư, trả nhân công rồi mới quyết toán, nhưng ngân quỹ
không biết ai đang giữ bao nhiêu tiền tạm ứng, đã quyết toán bao nhiêu, còn nợ bao lâu. S5 làm:

- **Đề nghị tạm ứng** (cho nhân viên, tổ đội, nhà cung cấp…) → gửi duyệt → chủ DN / kế toán duyệt thì chi từ ví dự án.
- **Quyết toán bằng chi phí:** người nhận tạm ứng lập đề nghị quyết toán, gắn vào chi phí hợp đồng / phiếu nhập vật tư
  → duyệt → trừ vào số còn nợ (không có tiền đi ra lần nữa).
- **Hoàn tiền thừa:** nộp lại tiền vào một ví dự án → trừ vào số còn nợ.
- **Danh sách tạm ứng còn nợ** theo tuổi nợ: dưới 30, 30–60, 60–90, trên 90 ngày.
- Gỡ sai: quyết toán bằng chi phí gỡ được (có lý do); hoàn tiền và tạm ứng gỡ bằng "Đảo" như S2 — tạm ứng chỉ đảo được
  khi đã gỡ hết quyết toán.

## Vấn đề vận hành

Tiền tạm ứng hiện phải ghi như chi phí hoặc chuyển ví, nên không theo dõi được ai còn nợ, quyết toán vào chi phí nào,
và tạm ứng nào để quá lâu.

## Người dùng bị ảnh hưởng

PM / kỹ sư (đề nghị tạm ứng, quyết toán, hoàn tiền), kế toán và chủ DN (duyệt, theo dõi tuổi nợ).

## Bằng chứng

`docs/audits/2026-10-27-gap-096-treasury-s5-advances-readiness.md`: bảng tạm ứng và quyết toán đã có từ GAP-037/038,
quy tắc v17 §6.5, §7, §2.2d đã duyệt; chưa có chỗ nào tạo.

## Câu trả lời của Owner (trong phiên, 2026-10-27)

1. **Xuất tạm ứng như chi phí S3:** lập đề nghị → gửi duyệt → chủ DN hoặc kế toán duyệt mới chi; tự duyệt được ghi
   nhận như S3.
2. **Quyết toán bằng chi phí:** người nhận tạm ứng lập đề nghị quyết toán gắn vào chi phí → chủ DN / kế toán duyệt →
   trừ vào số còn nợ.
3. **Hoàn tiền thừa:** chỉ nộp thẳng vào ví dự án; qua trung gian để sau.
4. **Tuổi nợ:** mốc 30 / 60 / 90 ngày kể từ ngày xuất.

## Phạm vi đề xuất

Như Owner Summary, theo GAP-037 v17 §6.5, §7.4–§7.8, §2.2b, §2.2d, §11. Không đổi lược đồ. Chi tiết kỹ thuật (quyền,
API, trang) ở Gate 2.

## Loại trừ rõ ràng

Hoàn tiền qua trung gian (tuyến tiền); hạn quyết toán riêng cho từng tạm ứng (cần đổi lược đồ); báo cáo (S6); nhập sao
kê; khoá kỳ; không đổi lược đồ; không deploy.

## Khả năng hoàn tác

Gate 1 chỉ là tài liệu.

## Đề xuất

Phê duyệt Gate 1.

## Decision Needed

Owner chọn một: `APPROVE GAP-096 Gate 1` / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt thiết kế chi tiết, merge hay phát hành; chưa duyệt S6.
