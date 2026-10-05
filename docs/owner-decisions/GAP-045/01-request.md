---
work_id: GAP-045
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-01-gap-045-perf-timing-evidence.md
  plan: null
  branch: docs/GAP-045-perf-timing-gate1
  pr: https://github.com/kha997/zenamanagephp/pull/332
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-01T23:59:36+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-01: 'APPROVE GAP-045 Gate 1'. Bound to reviewed Draft PR #332 head 7c2fbf1491da93aaa31ea94ccea0890f402a0a6f, canonical base 2ca3def397b93a8aa4d632e5d477025b3f62f0bd. The Gate-2 direction (A/B/C/D) was not stated with the approval and is requested separately before Gate 2 is prepared. Authorizes preparing Gate 2 only."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-01T21:39:23+07:00"
  updated_at: "2026-10-01T23:59:36+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner approved GAP-045 Gate 1 in-session on 2026-10-01 against Draft PR #332
head `7c2fbf1491da93aaa31ea94ccea0890f402a0a6f`. The Gate-2 direction (A/B/C/D) is requested separately. This
authorizes preparation of Gate 2 only.

## Owner Summary

Đã đo 10 lần song song trên CI với **cùng một mã nguồn**: thời gian tải danh
sách cảnh báo dao động 265–521ms **tuỳ máy chủ CI được phân**, nên một nửa số
lần chạy trượt ngưỡng 450ms dù code không đổi. Không có dấu hiệu ứng dụng chậm
đi (số truy vấn ổn định). Hai kiểm tra thời gian này vì vậy là "đỏ/xanh theo vận
may", và đang chặn GAP-041. Cần Owner chọn hướng xử lý vì trước đây Owner đã
yêu cầu không đổi ngưỡng 450ms khi chưa có bằng chứng.

## Vấn đề vận hành

1. Máy CI loại AMD EPYC 7763 luôn trượt (512–521ms); máy nhanh hơn luôn đạt
   (265–422ms). Trong cùng một máy, 3 lần đo gần như bằng nhau.
2. Kiểm tra "đánh dấu 100 cảnh báo đã đọc" (ngưỡng 1000ms) cũng sát ngưỡng trên
   máy chậm (996–1029ms).
3. Danh sách cảnh báo trả về **toàn bộ** cảnh báo của người dùng, không giới
   hạn — đây là điểm thiết kế đáng cân nhắc, độc lập với chuyện ngưỡng.

## Người dùng bị ảnh hưởng

Không trực tiếp. Ảnh hưởng: CI đỏ ngẫu nhiên; GAP-041 (làm CI chạy đúng test
hiệu năng) không phát hành được.

## Bằng chứng

`docs/audits/2026-10-01-gap-045-perf-timing-evidence.md` (bảng 10 lần đo, loại
CPU từng máy, số truy vấn).

## Quyết định cần Owner (trước Gate 2)

- **A (đội kỹ thuật đề xuất):** CI chỉ chặn theo tiêu chí ổn định (số truy vấn,
  kích thước dữ liệu); thời gian đo vẫn được ghi ra báo cáo nhưng không làm CI
  đỏ.
- **B:** Đo thêm một "bài chuẩn" trên cùng máy để quy đổi, giữ tinh thần ngưỡng
  450ms/1000ms theo tỉ lệ.
- **C (sản phẩm):** Giới hạn danh sách cảnh báo (ví dụ 50 cái mới nhất, có phân
  trang) — nhanh trên mọi máy, nhưng đổi hợp đồng API; Owner chọn con số.
- **D:** Đổi ngưỡng (ví dụ 650ms/1200ms).

## Phạm vi đề xuất

Gate 2 thiết kế theo hướng Owner chọn.

## Loại trừ rõ ràng

Không đổi gì ở Gate này; không deploy.

## Khả năng hoàn tác

Chỉ tài liệu.

## Đề xuất

Phê duyệt Gate 1, chọn **A** (có thể kết hợp C như việc riêng nếu Owner muốn
giới hạn danh sách cảnh báo).

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) — kèm hướng A/B/C/D /
Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt cách làm cụ thể, Gate 2, merge hay phát hành.
