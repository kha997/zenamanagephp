---
work_id: GAP-107
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-11-04-gap-107-treasury-period-lock-bank-import-evidence.md
  plan: null
  branch: docs/GAP-107-treasury-period-lock-bank-import
  pr: https://github.com/kha997/zenamanagephp/pull/397
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
  created_at: "2026-11-04T11:00:00+07:00"
  updated_at: "2026-11-04T11:00:00+07:00"
generated_by: agent
---

## Owner Summary

Hai phần mở rộng Ngân quỹ:

1. **Khoá kỳ**: sau khi chốt sổ (ví dụ hết tháng), không ai ghi thêm / đảo / điều chỉnh khoản tiền nào có ngày nằm trong
   kỳ đã khoá. Quyền `treasury.manage_period_lock` đã có sẵn (chỉ Owner) nhưng chưa dùng.
2. **Import sao kê ngân hàng**: tải file sao kê (CSV / Excel do ngân hàng xuất) cho một ví ngân hàng; hệ thống **gợi ý
   ghép** từng dòng sao kê với giao dịch chưa đối soát (cùng số tiền, chiều, ngày gần nhau); người dùng xác nhận thì dùng
   chức năng đối soát sẵn có (S4a). **Không** kết nối trực tiếp ngân hàng (bản thiết kế gốc loại trừ) và **không** tự tạo
   khoản thu / chi từ dòng sao kê (sẽ bỏ qua bước duyệt).

Khảo sát cho thấy có khoảng 10 lối ghi sổ cần kiểm tra khoá kỳ (tiền nhận, chuyển ví, điều chỉnh, đảo, duyệt chi, duyệt
tạm ứng, nộp lại / quyết toán tạm ứng, chặng tuyến tiền, đối soát). Gate 2 sẽ đưa phương án: khoá theo dự án hay toàn
công ty, có cho mở khoá không, định dạng sao kê (CSV ghép cột hay mẫu từng ngân hàng), có lưu dòng sao kê không.

## Vấn đề vận hành

Số liệu tháng đã chốt vẫn có thể bị ghi thêm / đảo lùi ngày; đối soát ngân hàng phải tick từng giao dịch bằng tay.

## Người dùng bị ảnh hưởng

Owner, kế toán.

## Bằng chứng

`docs/audits/2026-11-04-gap-107-treasury-period-lock-bank-import-evidence.md`.

## Phạm vi đề xuất

Gate 2: thiết kế khoá kỳ (phạm vi, quy tắc, mở khoá, ai làm, nhật ký) và import sao kê (định dạng, ghép gợi ý, xác nhận
qua đối soát sẵn có, chống import trùng, có lưu dòng sao kê hay không); migration nếu cần; test (gồm MySQL thật). Có thể
tách hai phần thành hai lần phát hành nếu Gate 2 thấy cần.

## Loại trừ rõ ràng

Kết nối API ngân hàng; đọc PDF sao kê (OCR); tự tạo chứng từ từ dòng sao kê; ngưỡng / hạn tạm ứng (Work ID khác); khoá sổ
kế toán tổng; deploy.

## Khả năng hoàn tác

Revert; migration (nếu có) chỉ thêm, có `down()`.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: `APPROVE GAP-107 Gate 1` / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa chọn phạm vi khoá, định dạng sao kê, thiết kế, Gate 2, merge hay phát hành.
