---
work_id: GAP-061
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-09-30-gap-061-e2e-suite-evidence.md
  plan: null
  branch: docs/GAP-061-e2e-suite-never-worked
  pr: https://github.com/kha997/zenamanagephp/pull/330
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-09-30T22:28:11+07:00"
  owner_response_reference: "Owner decisions in-session on 2026-09-30: product choice 'Gỡ bây giờ' (retire the two broken E2E files now; new journey tests, if wanted, later as separate work) and 'APPROVE GAP-061 Gate 1'. Bound to reviewed Draft PR #330 head 18aaf272c5f0eeef30c006d7210fd931c90f087b, canonical base aac94caf890d7229baa3f514860786088e252ee8. Authorizes preparing a Gate-2 retirement design only; not implementation, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-30T22:12:17+07:00"
  updated_at: "2026-09-30T22:28:11+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED — retire now

Owner approved GAP-061 Gate 1 in-session on 2026-09-30 against Draft PR #330
head `18aaf272c5f0eeef30c006d7210fd931c90f087b`, choosing **"Gỡ bây giờ"**: retire `CriticalUserFlowsE2ETest` and
`DashboardE2ETest` now; new journey tests, if wanted, become separate work. This
authorizes preparation of Gate 2 only.

## Owner Summary

Hai file test "end-to-end" (15 test, viết từ tháng 9/2025) **chưa từng chạy
được**, và chúng kiểm tra các luồng của sản phẩm năm 2025 (bảng điều khiển là
trang chính, thêm/sửa/xoá dự án-công việc chung chung), không phải sản phẩm hiện
tại (trang "Hôm nay", giao diện Operator). Phần lớn những gì chúng kiểm tra đã
có test khác chạy hằng ngày. Cần Owner cho biết **những luồng nghiệp vụ nào quan
trọng tới mức cần test end-to-end**.

## Vấn đề vận hành

1. 15/15 test hỏng: thiếu khai báo trong test, dữ liệu người dùng giả không có
   quyền đúng (cùng lỗi gốc GAP-053), dữ liệu RFI mẫu thiếu trường bắt buộc, kỳ
   vọng cũ (trang sau đăng nhập, định dạng lỗi, giới hạn tần suất).
2. Không tìm thấy lỗi thật của ứng dụng; mọi API test gọi vẫn tồn tại.
3. Kiểm tra từng API riêng lẻ đã có ở CI chính; thứ còn thiếu là **kiểm tra
   hành trình nhiều bước** của người dùng thật.

## Người dùng bị ảnh hưởng

Không trực tiếp. Rủi ro: các hành trình quan trọng (ví dụ duyệt tài liệu, chuyển
cơ hội thành dự án) không có test kiểm tra đầu-cuối.

## Bằng chứng

`docs/audits/2026-09-30-gap-061-e2e-suite-evidence.md`.

## Quyết định sản phẩm cần Owner (trước Gate 2)

Chọn hướng:

- **Gỡ** 2 file hỏng (phần kiểm tra API đã có ở CI chính) — đơn giản, không mất
  gì đang chạy.
- **Sửa nguyên trạng** — giữ các hành trình kiểu 2025, có thể không còn khớp
  sản phẩm.
- **Thay bằng hành trình Owner chọn** (tính năng mới) — Owner liệt kê 3–5 hành
  trình quan trọng nhất hiện nay, ví dụ: đăng nhập → "Hôm nay" → duyệt tài liệu;
  khách tiềm năng → cơ hội → dự án; mở RFI → trả lời → đóng.

## Phạm vi đề xuất

Gate 2 thiết kế theo hướng Owner chọn.

## Loại trừ rõ ràng

Không đụng bằng chứng GAP-040 (`TransactionIsolationColdStartTest`); không sửa
mã ứng dụng; không deploy.

## Khả năng hoàn tác

Chỉ tài liệu.

## Đề xuất

Phê duyệt Gate 1. Về sản phẩm, đội kỹ thuật nghiêng về **gỡ bây giờ, và nếu
Owner muốn thì làm hành trình mới như một việc riêng** — vì sửa nguyên trạng là
bảo trì các hành trình không còn phản ánh sản phẩm.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) — kèm hướng ở trên /
Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt cách làm cụ thể, Gate 2, merge hay phát hành.
