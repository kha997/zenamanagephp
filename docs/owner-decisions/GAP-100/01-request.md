---
work_id: GAP-100
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-10-29-gap-100-test-db-leak-evidence.md
  plan: null
  branch: docs/GAP-100-test-db-leak
  pr: null
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
  created_at: "2026-10-29T02:05:00+07:00"
  updated_at: "2026-10-29T02:05:00+07:00"
generated_by: agent
---

## Owner Summary

Đã kiểm tra thật 10 file test "nghi rò dữ liệu" còn lại từ GAP-065: **8 file là báo nhầm** (đã có cách ly hoặc không chạy
chung tiến trình). Đo toàn bộ ~3.650 test thì chỉ có **2 file thật sự để lại dữ liệu** trong DB test:
`ProjectManagementTest` (bỏ lại 7 bản ghi, làm hỏng 3 test bootstrap khi chạy theo thứ tự khác, đã tái hiện) và
`DeployMigrateCommandTest` (bỏ lại 2 dòng lịch sử migration và 1 bảng tạm). Chưa có cơ chế nào tự phát hiện lỗi này.

## Vấn đề vận hành

Kết quả test phụ thuộc thứ tự chạy: CI hiện xanh chỉ nhờ thứ tự, chạy chọn lọc hoặc đổi thứ tự sẽ đỏ giả. Lỗi cùng kiểu có
thể quay lại mà không ai biết.

## Người dùng bị ảnh hưởng

Đội kỹ thuật (độ tin cậy test/CI). Không ảnh hưởng người dùng cuối hay production.

## Bằng chứng

`docs/audits/2026-10-29-gap-100-test-db-leak-evidence.md`: bảng kết luận 10 file, kết quả đo toàn suite bằng công cụ đếm
bản ghi tạm (đã hoàn tác), lệnh tái hiện 3 test đỏ, thử nghiệm sửa (19/19 xanh, đã hoàn tác).

## Phạm vi đề xuất

Gate 2 chọn giữa: chỉ cách ly 2 file; hoặc cách ly 2 file **và** thêm cơ chế tự phát hiện test để lại dữ liệu (chạy mọi lúc,
hoặc chỉ khi bật).

## Loại trừ rõ ràng

Không sửa mã ứng dụng, không đổi thứ tự chạy CI. Không đụng test Dusk `SubmittalResubmitDirtyStateTest` (CI không chạy) và
test đồng thời chỉ chạy trên MySQL. Không deploy.

## Khả năng hoàn tác

Revert commit (chỉ thay đổi file test).

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: `APPROVE GAP-100 Gate 1` / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa chọn cách sửa, chưa duyệt Gate 2, merge hay phát hành.
