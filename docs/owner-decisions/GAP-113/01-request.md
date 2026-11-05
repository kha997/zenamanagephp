---
work_id: GAP-113
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-11-05-gap-113-project-labour-cost-evidence.md
  plan: null
  branch: docs/GAP-113-project-labour-cost
  pr: https://github.com/kha997/zenamanagephp/pull/403
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
  created_at: "2026-11-05T16:30:00+07:00"
  updated_at: "2026-11-05T16:30:00+07:00"
generated_by: agent
---

## Owner Summary

Phần 3/4 của tính chi phí nhân công: **quy giờ làm ra tiền cho từng công trình**.

**Cách tính (theo hướng đã thống nhất):**
- Chi phí 1 giờ của mỗi người = tổng chi phí công ty trả trong tháng ÷ giờ chuẩn (đã có trong bảng lương GAP-109).
- Chi phí nhân công của công trình = giờ đã duyệt trên công trình × chi phí 1 giờ của người làm (giờ lấy từ GAP-112).
- Phần chi phí trên giờ không ghi cho công trình nào (họp, hành chính, giờ trống) và lương nhân viên văn phòng để riêng,
  sang phần 4 sẽ phân bổ vào chi phí chung.
- Có báo cáo theo công trình / theo người / theo tháng, xuất CSV; công trình có dòng "chi phí nhân công luỹ kế".

**Khảo sát cho thấy:**
- Bảng lương đã có sẵn chi phí công ty và chi phí 1 giờ cho từng người, từng tháng.
- Bảng giờ đã có giờ theo người, ngày, công trình, kèm trạng thái duyệt.
- Hai bảng ghép được qua tài khoản đăng nhập của nhân sự.
- Chưa có chỗ nào tính ra tiền nhân công cho công trình.

**Gate 2 sẽ đưa phương án:**
- tính lúc chốt lương (lưu cố định) hay tính trực tiếp;
- giờ chưa duyệt lúc chốt thì chặn, cảnh báo hay bỏ qua;
- cách xử lý giờ vượt giờ chuẩn;
- nhân viên văn phòng có ghi giờ công trình thì tính thế nào;
- nơi hiển thị;
- ai được xem tiền (chi phí từng người lộ mức lương).

## Vấn đề vận hành

Đã có lương và giờ làm nhưng chưa biết mỗi công trình tốn bao nhiêu tiền nhân công, nên chưa xác định được lời lỗ.

## Người dùng bị ảnh hưởng

Owner, kế toán (báo cáo đầy đủ); quản lý dự án (tổng chi phí nhân công công trình của mình, tuỳ quyền chọn ở Gate 2).

## Bằng chứng

`docs/audits/2026-11-05-gap-113-project-labour-cost-evidence.md`.

## Phạm vi đề xuất

Gate 2: cách tính và thời điểm chốt số, xử lý các trường hợp lệch, báo cáo, hiển thị trên công trình, quyền; migration
bảng mới (nếu lưu cố định); test (gồm MySQL thật và giới hạn số truy vấn).

## Loại trừ rõ ràng

- Phân bổ chi phí chung và lời lỗ công trình (phần 4).
- Bút toán kế toán, ghi sổ Ngân quỹ.
- Sửa cách tính lương hay bảng giờ đã có.
- Deploy.

## Khả năng hoàn tác

Revert; nếu có migration thì chỉ tạo bảng mới, có `down()`.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: `APPROVE GAP-113 Gate 1` / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa chọn cách tính, thời điểm chốt, quyền xem, thiết kế, Gate 2, merge hay phát hành.
