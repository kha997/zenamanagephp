---
work_id: GAP-104
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-11-03-gap-104-project-oppm-phase1-evidence.md
  plan: null
  branch: docs/GAP-104-project-oppm-phase1
  pr: https://github.com/kha997/zenamanagephp/pull/394
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-03T20:46:47+07:00"
  owner_response_reference: "Owner decision in-session on 2026-11-03, verbatim: 'APPROVE GAP-104 Gate 1'. Bound to reviewed Draft PR #394 head 28f695dcedba60d041868dbdc764533a47c8d9c0, canonical base 052a5aa39813336ff716919138b8e59945ae8381. Authorizes preparing Gate 2 only."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-03T20:20:00+07:00"
  updated_at: "2026-11-03T20:46:47+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner approved GAP-104 Gate 1 in-session on 2026-11-03 against Draft PR #394 head
`28f695dcedba60d041868dbdc764533a47c8d9c0`. This authorizes preparation of Gate 2 only.

## Owner Summary

**OPPM Phase 1** (issue #248, SSOT OWN-2026-009 §5 / §14 mục 9): một **trang "OPPM một trang" chỉ đọc** cho từng dự án,
mở từ trang chi tiết dự án, gom trên một màn hình: thông tin đầu trang, KPI điều hành, các nhóm công việc chính, mốc,
việc trễ / bị chặn, tóm tắt hợp đồng–tài chính, và chế độ in ngang.

Khảo sát cho thấy:
- **KPI đã có nguồn chung**: GAP-103 vừa merge cung cấp tiến độ (kèm độ tin cậy), trễ hạn, cờ cần chú ý, các số tiền tách
  bạch — OPPM chỉ cần **đọc lại**, không tự tính công thức mới.
- **Nhóm công việc**: có 3 nguồn khả dĩ — hạng mục gốc (component, khớp với cách tính tiến độ GAP-103 nhưng chỉ tạo được
  qua API), giai đoạn (phase, dữ liệu mỏng, dính lớp cũ), hoặc quy trình mẫu (work instance). Gate 2 sẽ đưa phương án để
  anh/chị chọn.
- **Mốc (milestone)**: bảng có, nhưng **hiện không có chức năng nào tạo mốc** (API đã gỡ 22/07) → khối mốc sẽ hiện "Chưa có
  dữ liệu" cho mọi dự án cho tới khi có Work ID riêng làm phần nhập mốc.
- **Phân quyền**: dùng quyền có sẵn (`project.view`; khối Ngân quỹ dùng quyền Ngân quỹ). Không cần quyền mới.
- **In**: chưa có mẫu in nào trong repo — Phase 1 thêm bố cục in ngang bằng CSS, chưa làm PDF.

## Vấn đề vận hành

Owner / PM phải mở nhiều trang (dự án, công việc, Gantt, hợp đồng, Ngân quỹ) rồi tự ghép thông tin để biết một dự án
đang thế nào.

## Người dùng bị ảnh hưởng

Owner, PM, người quản lý xem dự án.

## Bằng chứng

`docs/audits/2026-11-03-gap-104-project-oppm-phase1-evidence.md`.

## Phạm vi đề xuất

Gate 2: thiết kế trang OPPM **chỉ đọc** cho một dự án — route, dịch vụ đọc dùng `ProjectHealthReadService`, nguồn "nhóm
công việc" (kèm giới hạn 8–12 dòng), danh sách trễ / bị chặn có giới hạn, khối tài chính gọn có link chi tiết, bố cục in,
quyền, ngân sách truy vấn và test. Không migration nếu không chứng minh cần. Kèm quyết định về chi phí đếm cảnh báo Ngân
quỹ trên trang này.

## Loại trừ rõ ràng

Nhập liệu quản trị (kết quả đầu ra, trọng số, hành động tiếp, quyết định — Phase 2); snapshot / báo cáo kỳ / PDF (Phase
3); nhập mốc; màn hình tạo hạng mục; CRUD Service Line; Contract / Finance Control toàn công ty; sổ Ngân quỹ; Control
Tower; sửa `ProjectAnalyticsService`; deploy.

## Khả năng hoàn tác

Revert commit; trang mới chỉ đọc, không ghi dữ liệu.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: `APPROVE GAP-104 Gate 1` / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa chọn nguồn nhóm công việc, bố cục, thiết kế, Gate 2, merge hay phát hành.
