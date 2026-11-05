---
work_id: GAP-112
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-11-05-gap-112-time-logging-evidence.md
  plan: null
  branch: docs/GAP-112-time-logging
  pr: https://github.com/kha997/zenamanagephp/pull/402
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-05T14:51:58+07:00"
  owner_response_reference: "Owner decision in-session on 2026-11-05, verbatim: 'APPROVE GAP-112 Gate 1'. Bound to reviewed Draft PR #402 head effba40dd65b8fcd73966d6dd5ba1701f877d1c2, canonical base ef278c5c78961ff97f96f00406f76f3ae89bc76f. Authorizes preparing Gate 2 only."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-05T14:50:00+07:00"
  updated_at: "2026-11-05T14:51:58+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner approved GAP-112 Gate 1 in-session on 2026-11-05 against Draft PR #402 head
`effba40dd65b8fcd73966d6dd5ba1701f877d1c2`. This authorizes preparation of Gate 2 only.

## Owner Summary

Phần 2/4 của tính chi phí nhân công theo công trình: **ghi giờ làm theo công việc** (chưa tính tiền).

**Cách làm (theo hướng anh/chị đã chọn):**
- Nhân viên bấm **Bắt đầu / Dừng** trên công việc đang làm. Hệ thống ghi giờ cho công việc đó và cho công trình của nó.
- Được nhập / sửa tay, bắt buộc ghi lý do.
- Có loại **"việc không thuộc công trình"** (họp nội bộ, đào tạo, hành chính…).
- Quản lý **duyệt** giờ; giờ trong tháng đã chốt lương thì **khoá**.
- Có trang "Bảng giờ của tôi", trang duyệt, và báo cáo giờ theo công trình / theo người (chỉ giờ, chưa có tiền).

**Khảo sát cho thấy:**
- Công việc hiện chỉ có vài ô tổng số giờ (ước tính / thực tế), không biết ai làm, ngày nào. Không dùng được để tính theo
  người, theo ngày.
- Chưa có bảng chấm giờ nào.
- Trang **"Việc của tôi"** đã liệt kê việc đang mở của từng người, hợp để đặt nút Bắt đầu / Dừng.
- Nhân sự (GAP-109) đã nối được với tài khoản đăng nhập, nên phần 3 sẽ ghép giờ với lương được.

**Gate 2 sẽ đưa phương án:**
- quy tắc bấm giờ (một đồng hồ mỗi người, tự dừng cuối ngày / tối đa số giờ);
- nhập tay;
- danh mục việc không thuộc công trình;
- ai duyệt, duyệt theo tuần hay từng dòng;
- khoá theo tháng lương;
- có cập nhật ô "giờ thực tế" của công việc không;
- quyền.

## Vấn đề vận hành

Không biết mỗi người làm bao nhiêu giờ cho từng công trình, nên chưa tính được chi phí nhân công và lời lỗ thật.

## Người dùng bị ảnh hưởng

Toàn bộ nhân viên / cộng tác viên có tài khoản (ghi giờ); quản lý dự án (duyệt); Owner, kế toán (báo cáo).

## Bằng chứng

`docs/audits/2026-11-05-gap-112-time-logging-evidence.md`.

## Phạm vi đề xuất

Gate 2: thiết kế bảng giờ, bấm giờ, nhập tay, duyệt, khoá, các trang và báo cáo giờ, quyền; migration bảng mới; test (gồm
MySQL thật và giới hạn số truy vấn).

## Loại trừ rõ ràng

- Tính tiền (giờ × đơn giá — phần 3).
- Chi phí chung và lời lỗ (phần 4).
- Ứng dụng điện thoại riêng.
- Theo dõi màn hình / tự phát hiện ngồi không.
- Deploy.

## Khả năng hoàn tác

Revert; migration chỉ tạo bảng mới, có `down()`.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: `APPROVE GAP-112 Gate 1` / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa chọn quy tắc bấm giờ, người duyệt, quyền, thiết kế, Gate 2, merge hay phát hành.
