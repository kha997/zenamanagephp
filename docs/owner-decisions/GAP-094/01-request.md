---
work_id: GAP-094
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-26-gap-094-treasury-s4b-routes-readiness.md
  plan: null
  branch: docs/GAP-094-treasury-s4b-routes
  pr: https://github.com/kha997/zenamanagephp/pull/373
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-27T05:49:41+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-27, verbatim: 'APPROVE GAP-094 Gate 1'. Bound to reviewed Draft PR #373 head 1daa1d182d1e33e3c6ad5bab35594f13efb8ffb9, canonical base 740b84500a805805bea4f9a81758ea6ea2691a30. This is the recommended split option: this Work ID covers S4b-1 (routes for funding / owner contribution / internal transfer, leg record, reversal and cancel, reconciliation of via-route documents); contract-payment traceability is S4b-2, a later Work ID. Authorizes preparing Gate 2 only."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-26T22:49:32+07:00"
  updated_at: "2026-10-27T05:49:41+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner approved GAP-094 Gate 1 in-session on 2026-10-27 (split option: S4b-1 here, S4b-2 later) against Draft
PR #373 head `1daa1d182d1e33e3c6ad5bab35594f13efb8ffb9`. This authorizes preparation of Gate 2 only.

## Owner Summary

Lát **S4b — Tiền đi qua trung gian** của Ngân quỹ (Issue #244), phần còn lại
của S4 sau S4a Đối soát (GAP-067, đã phát hành).

Hiện nay mọi khoản tiền được ghi như đi **thẳng** từ nguồn đến ví đích. Thực tế
tiền hay đi vòng: chủ đầu tư chuyển vào **TK công ty**, kế toán rút ra rồi
**nhờ người mang** tiền mặt ra công trường. S4b cho ghi đúng đường đi đó:

- Một **tuyến tiền** gắn với một khoản tiền nhận / góp vốn / chuyển nội bộ, gồm
  các **chặng** (ví A → ví B) ghi dần khi tiền thực sự đi qua. Ví trung gian là
  **ví trung chuyển trong dự án** (loại "Tài khoản bên trung gian" đã có).
- Số dư từng ví đi theo từng chặng; tiền "đang trên đường" thấy được.
- Tuyến **hoàn tất** khi toàn bộ tiền đã đến ví đích và ví trung gian về 0;
  khi đó chứng từ mới đối soát được.
- **Truy vết thanh toán hợp đồng (tùy chọn):** gắn tuyến vào một đợt thanh toán
  hợp đồng đã "đã thanh toán", có danh sách nhắc các đợt chưa truy vết đủ; tiền
  đã truy vết không được khai lại thành "tiền nhận".

Đề xuất **tách S4b thành hai Work ID** như đã làm với S4:
- **S4b-1 (Work ID này, GAP-094):** tuyến tiền cho tiền nhận / góp vốn / chuyển
  nội bộ; ghi, đảo và huỷ chặng; đối soát chứng từ đi tuyến.
- **S4b-2 (Work ID sau):** truy vết thanh toán hợp đồng và danh sách nhắc.

## Vấn đề vận hành

Không ghi được tiền đi qua trung gian: tiền nằm ở TK công ty hay trong tay người
mang không hiện ở đâu, nên số dư ví lệch với thực tế cho đến khi tiền tới đích.

## Người dùng bị ảnh hưởng

Chủ doanh nghiệp, kế toán (tạo tuyến, ghi mọi chặng); PM/kỹ sư (ghi chặng ra
hoặc vào ví mình giữ).

## Bằng chứng

`docs/audits/2026-10-26-gap-094-treasury-s4b-routes-readiness.md`: bảng tuyến
và chặng đã có từ GAP-037/038, nhưng chưa có chỗ nào tạo tuyến; mọi bút toán
đang ghi `direct`.

## Câu trả lời của Owner đã có (trong phiên, 2026-10-08, ghi ở GAP-067)

1. Trung gian là **ví trung chuyển trong dự án**, không có ví cấp công ty.
2. Chủ DN và kế toán tạo tuyến, ghi mọi chặng; PM/kỹ sư chỉ ghi chặng ra hoặc
   vào ví mình giữ.
3. Truy vết thanh toán hợp đồng **tùy chọn**, có danh sách nhắc, không chặn
   phía hợp đồng; tiền đã truy vết không khai lại thành "tiền nhận".

## Phạm vi đề xuất

Như Owner Summary, theo quy tắc GAP-037 v17 đã duyệt (§4, §5a, §11, §12.1).
Không đổi lược đồ. Chi tiết kỹ thuật (quyền, API, trang, đảo chứng từ đi tuyến)
ở Gate 2.

## Loại trừ rõ ràng

Tạm ứng và hoàn ứng qua tuyến (S5), báo cáo (S6), nhập file sao kê, khoá kỳ;
không sửa `ContractPayment` hay trang hợp đồng; không đổi lược đồ; không mở lại
thiết kế đã duyệt; không deploy. Nếu Owner chọn tách: truy vết thanh toán hợp
đồng thuộc S4b-2.

## Khả năng hoàn tác

Gate 1 chỉ là tài liệu.

## Đề xuất

Phê duyệt Gate 1 với cách tách S4b-1 / S4b-2.

## Decision Needed

Owner chọn một:
- `APPROVE GAP-094 Gate 1` — tách: Work ID này làm S4b-1, truy vết hợp đồng làm
  sau (khuyến nghị);
- `APPROVE GAP-094 Gate 1 không tách` — làm cả S4b trong Work ID này;
- Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt thiết kế chi tiết, merge hay phát hành; chưa duyệt S5, S6.
