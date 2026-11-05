---
work_id: GAP-114
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-11-05-gap-114-overhead-project-pnl-evidence.md
  plan: null
  branch: docs/GAP-114-overhead-project-pnl
  pr: https://github.com/kha997/zenamanagephp/pull/404
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-05T23:01:48+07:00"
  owner_response_reference: "Owner decision in-session on 2026-11-05, verbatim: 'APPROVE GAP-114 Gate 1'. Bound to reviewed Draft PR #404 head 5cd1349575ebf725451e1717948507bebc364417, canonical base b4a9174f104a84be8e1af30c3d8dd2fbb4eb263a. Authorizes preparing Gate 2 only."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-05T23:05:00+07:00"
  updated_at: "2026-11-05T23:01:48+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner approved GAP-114 Gate 1 in-session on 2026-11-05 against Draft PR #404 head
`5cd1349575ebf725451e1717948507bebc364417`. This authorizes preparation of Gate 2 only.

## Owner Summary

Phần 4/4 của tính chi phí: **phân bổ chi phí chung và tính lời lỗ từng công trình**.

**Hướng làm (theo tư vấn đã thống nhất):**
- Nhập **chi phí chung hằng tháng** của công ty: thuê văn phòng, điện, nước, internet, phần mềm…
- Cộng thêm phần **lương chưa vào công trình** từ GAP-113: nhân viên văn phòng, giờ họp / hành chính / giờ trống.
- Chia tổng chi phí chung cho các công trình **theo tỷ lệ giờ đã duyệt** trong tháng.
- **Lời lỗ công trình** = doanh thu − chi phí trực tiếp − chi phí nhân công − chi phí chung được phân bổ.
- Xem theo tháng, luỹ kế, và tổng toàn công ty.

**Khảo sát cho thấy:**
- **Doanh thu** đã có: hợp đồng và các đợt thu tiền của từng công trình.
- **Chi phí trực tiếp** đã có ở 2 nơi:
  - chi phí ghi nhận theo hợp đồng;
  - chi thực tế qua Ngân quỹ.
  - Hai nơi này có thể trùng nhau, nên Gate 2 phải chọn cách lấy.
- **Chi phí nhân công** đã có (GAP-113).
- **Chi phí chung của công ty (thuê nhà, điện nước…): chưa có chỗ nhập.** Ngân quỹ hiện chỉ ghi theo từng công trình.

**Gate 2 sẽ đưa phương án:**
- nhập chi phí chung ở đâu (bảng chi phí tháng mới, hay mở rộng Ngân quỹ);
- chia theo giờ (đã thống nhất) hay cách khác;
- khi nào chốt số;
- doanh thu lấy theo hợp đồng hay theo tiền đã thu;
- chi phí trực tiếp lấy nguồn nào để không cộng trùng;
- bố cục báo cáo lời lỗ;
- ai được xem.

## Vấn đề vận hành

Đã có chi phí nhân công từng công trình, nhưng chưa tính chi phí chung và chưa ra được lời lỗ thật của công trình.

## Người dùng bị ảnh hưởng

Owner, kế toán (nhập chi phí chung, xem lời lỗ); PM (tuỳ quyền chọn ở Gate 2).

## Bằng chứng

`docs/audits/2026-11-05-gap-114-overhead-project-pnl-evidence.md`.

## Phạm vi đề xuất

Gate 2: nơi nhập chi phí chung, cách phân bổ và thời điểm chốt, nguồn doanh thu / chi phí trực tiếp, báo cáo lời lỗ,
quyền; migration bảng mới nếu cần; test (gồm MySQL thật và giới hạn số truy vấn).

## Loại trừ rõ ràng

- Bút toán kế toán, thuế / VAT.
- Ngân sách, dự báo.
- Sửa cách ghi sổ Ngân quỹ, lương hay bảng giờ đã có.
- Deploy.

## Khả năng hoàn tác

Revert. Nếu có migration thì chỉ tạo bảng mới, có `down()`.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: `APPROVE GAP-114 Gate 1` / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa chọn nơi nhập, cách chia, nguồn số liệu, quyền xem, thiết kế, Gate 2, merge hay phát hành.
