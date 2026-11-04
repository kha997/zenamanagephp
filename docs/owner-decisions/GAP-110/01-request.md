---
work_id: GAP-110
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-11-05-gap-110-oppm-phase-2-3-evidence.md
  plan: null
  branch: docs/GAP-110-oppm-phase-2-3
  pr: https://github.com/kha997/zenamanagephp/pull/400
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-05T06:47:12+07:00"
  owner_response_reference: "Owner decision in-session on 2026-11-05, verbatim: 'APPROVE GAP-110 Gate 1'. Bound to reviewed Draft PR #400 head 4cdf8dd029ee15fb8dc9e5a73cd7604e77f28f0d, canonical base bdf1b5fb27e3487ce25bd0fe61d9c63ee8b986eb. Authorizes preparing Gate 2 only."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-05T01:30:00+07:00"
  updated_at: "2026-11-05T06:47:12+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner approved GAP-110 Gate 1 in-session on 2026-11-05 against Draft PR #400 head
`4cdf8dd029ee15fb8dc9e5a73cd7604e77f28f0d`. This authorizes preparation of Gate 2 only.

## Owner Summary

OPPM giai đoạn 2 và 3 theo issue #248.

**Giai đoạn 1 (đã có, GAP-104):** trang OPPM một trang chỉ đọc. Gồm:
- tiêu đề dự án, chỉ số, nhóm công việc (hạng mục gốc), mốc thời gian;
- việc bị chặn / quá hạn, tài chính tóm tắt;
- bản in ngang.

**Giai đoạn 2 — phần nhập của PM / Owner** (khoảng 20% dữ liệu quản trị):
- cho mỗi nhóm công việc: **kết quả đầu ra**, **trọng số**, **hành động tiếp theo** (ai làm, hạn), **ghi chú quản trị**,
  **thứ tự hiển thị**;
- quyền sửa và nhật ký đầy đủ.

**Giai đoạn 3 — chốt kỳ và quyết định:**
- **chốt báo cáo theo kỳ**: lưu bản chụp không đổi được của trang OPPM;
- **so sánh kỳ này / kỳ trước**;
- **danh sách quyết định cần Owner / PM xử lý**: người quyết, hạn, trạng thái;
- **xuất PDF**.

Khảo sát cho thấy:
- Chưa có bảng nào lưu kết quả đầu ra, trọng số, hành động tiếp theo, ghi chú hay quyết định quản trị.
- Nhóm công việc giai đoạn 1 là "hạng mục gốc". Hạng mục hiện chỉ tạo qua API, nên nhiều dự án chưa có nhóm nào để gắn
  phần nhập.
- Đã có Yêu cầu thay đổi (Change Request) và RFI, nhưng chúng là quy trình kỹ thuật / hợp đồng, không phải "quyết định
  quản trị".
- Đã có bộ xuất PDF dùng trình duyệt ẩn (cho hồ sơ bàn giao), có thể dùng lại cho OPPM.
- Quy tắc SSOT: OPPM không được tự tạo công thức tiến độ mới. Trọng số nên **hiển thị**, không tự đổi % tiến độ, trừ khi
  anh/chị duyệt riêng.

Gate 2 sẽ đưa phương án:
- làm cả hai giai đoạn một lần hay tách giai đoạn 3 sang GAP sau;
- gắn phần nhập vào nhóm công việc hay thêm khối cấp dự án;
- trọng số có ảnh hưởng tiến độ không;
- ai được sửa;
- quyết định là danh sách mới hay dùng lại Change Request / RFI;
- chốt kỳ theo tuần / tháng / tay, ai chốt;
- có làm PDF không.

## Vấn đề vận hành

OPPM hiện chỉ có số liệu tự động. PM / Owner chưa ghi được nhận định, việc cần làm tiếp và quyết định cần xử lý. Cũng chưa
lưu lại được báo cáo từng kỳ để so sánh hay gửi đi.

## Người dùng bị ảnh hưởng

Owner, PM, người quản lý dự án.

## Bằng chứng

`docs/audits/2026-11-05-gap-110-oppm-phase-2-3-evidence.md`.

## Phạm vi đề xuất

Gate 2: thiết kế phần nhập quản trị, danh sách quyết định, chốt kỳ (bản chụp không đổi), so sánh kỳ, PDF; quyền và nhật ký;
migration bảng mới; test (gồm MySQL thật và giới hạn số truy vấn).

## Loại trừ rõ ràng

Trang tổng nhiều dự án (Control Tower), Contract / Finance Control, giao diện tạo hạng mục / mốc, công thức tiến độ mới,
AI dự báo, deploy.

## Khả năng hoàn tác

Revert; migration chỉ tạo bảng mới, có `down()`.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: `APPROVE GAP-110 Gate 1` / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa chọn cách tách, trường dữ liệu, quyền, kỳ chốt, PDF, thiết kế, Gate 2, merge hay phát hành.
