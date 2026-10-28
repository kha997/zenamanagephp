---
work_id: GAP-100
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-29-gap-100-test-db-leak-evidence.md
  plan: null
  branch: docs/GAP-100-test-db-leak
  pr: https://github.com/kha997/zenamanagephp/pull/383
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-29T02:09:23+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-29, verbatim: 'APPROVE GAP-100 Gate 1'. Bound to reviewed Draft PR #383 head ce68784e71be2b8f9c51875ed96f9f4536f648a7, canonical base 01595a7f2df94e2a9eb26d4c97824a306bbb30f8. Authorizes preparing Gate 2 only."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-29T02:05:00+07:00"
  updated_at: "2026-10-29T02:09:23+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner approved GAP-100 Gate 1 in-session on 2026-10-29 against Draft PR #383 head
`ce68784e71be2b8f9c51875ed96f9f4536f648a7`. This authorizes preparation of Gate 2 only.

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
