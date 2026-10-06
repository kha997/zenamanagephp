---
work_id: GAP-062
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-10-06-gap-062-ssot-test-lint-false-green-evidence.md
  plan: null
  branch: docs/GAP-062-ssot-test-lint-false-green
  pr: https://github.com/kha997/zenamanagephp/pull/334
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: null
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-06T11:09:35+07:00"
  updated_at: "2026-10-06T11:09:35+07:00"
generated_by: agent
---

## Owner Summary

Bước kiểm tra quy ước test (`lint_tests.sh`, chạy trong CI chính) **không thể
báo lỗi trên CI** vì máy CI thiếu công cụ `rg`: công cụ không có thì danh sách
vi phạm rỗng, nên luôn "xanh". Chạy đúng cách thì có **99 vi phạm** tích tụ ở 11
file test, và danh sách "endpoint đã chết" của nó sai (3/5 endpoint thực ra vẫn
chạy và đang được test bảo mật). Đề xuất: phê duyệt Gate 1; hướng thiết kế
**Phương án 1** — làm cho bước kiểm tra trung thực (cài `rg`, thiếu thì báo đỏ),
sửa danh sách endpoint, ghi nhận 99 vi phạm hiện có làm nợ để từ nay vi phạm mới
bị chặn. Không viết lại test.

## Vấn đề vận hành

Một cổng chất lượng trong CI báo xanh giả từ ít nhất 2026-07; quy ước test (dùng
factory, không gọi endpoint chết) không được thực thi.

## Người dùng bị ảnh hưởng

Đội kỹ thuật (độ tin cậy CI). Không ảnh hưởng người dùng cuối.

## Bằng chứng

`docs/audits/2026-10-06-gap-062-ssot-test-lint-false-green-evidence.md` — run
CI `37343750155` (10 dòng `rg: command not found` rồi "passed"), bảng 99 vi
phạm theo nhóm/file, đối chiếu route của denylist.

## Phạm vi đề xuất

Gate 2 chọn giữa 4 phương án trong evidence; đề xuất Phương án 1.

## Loại trừ rõ ràng

Không sửa mã ứng dụng; không viết lại test; không deploy.

## Khả năng hoàn tác

Revert commit.

## Đề xuất

Phê duyệt chuyển sang Gate 2 (hướng Phương án 1).

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt thiết kế, thay đổi, merge hay phát hành.
