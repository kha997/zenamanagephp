---
work_id: GAP-060
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-09-30-gap-060-a11y-perf-workflow-evidence.md
  plan: null
  branch: docs/GAP-060-a11y-perf-ci-red
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
  created_at: "2026-09-30T19:55:01+07:00"
  updated_at: "2026-09-30T19:55:01+07:00"
generated_by: agent
---

## Owner Summary

Bộ kiểm thử chạy tự động mỗi đêm "Accessibility & Performance Testing" **chưa
từng xanh trong ít nhất 9 tháng** (200/200 lần gần nhất đều đỏ). Một kiểm tra
luôn đỏ thì không ai còn nhìn, và điều tra cho thấy một số phần của nó, kể cả
khi chạy được, cũng **không kiểm tra gì**. Đề nghị cho phép thiết kế cách xử lý.

## Vấn đề vận hành

1. Hai job hiệu năng gọi một script **chưa từng tồn tại**; và kể cả có script,
   chúng sẽ chạy **0 test** (không test nào thuộc nhóm chúng tìm) → sẽ thành
   "xanh giả".
2. Job Lighthouse (điểm hiệu năng trang) thiếu cấu hình cơ sở dữ liệu; và nếu
   sửa, nó sẽ đo **trang đăng nhập** chứ không phải bảng điều khiển.
3. Job E2E hỏng vì test cũ: sau đăng nhập ứng dụng giờ chuyển tới trang "Hôm
   nay" (`/app/today`), test vẫn đợi `/app/dashboard`. Đây là nơi **duy nhất**
   chạy các test E2E.
4. Job Accessibility: test **đều đạt**, chỉ hỏng vì một cờ dòng lệnh sai; bộ
   test này cũng đã chạy trong CI chính.

## Người dùng bị ảnh hưởng

Không ảnh hưởng người dùng trực tiếp. Ảnh hưởng đội kỹ thuật: tín hiệu chất
lượng hằng đêm vô dụng; test E2E không được chạy thật.

## Bằng chứng

Lịch sử 200 lần chạy, log từng job của lần chạy mới nhất, đối chiếu test và cấu
hình. Chi tiết: `docs/audits/2026-09-30-gap-060-a11y-perf-workflow-evidence.md`.

## Phạm vi đề xuất

Nếu duyệt, Gate 2 thiết kế để workflow này **xanh thật và chỉ chứa kiểm tra có
ý nghĩa**: bỏ các job rỗng/trùng, sửa job còn giá trị (E2E, Lighthouse nếu
giữ), không để job nào "xanh giả".

## Loại trừ rõ ràng

- Không đặt ngân sách hiệu năng mới (quyết định sản phẩm, sẽ hỏi nếu cần).
- Không sửa CI chính.
- Không deploy.

## Khả năng hoàn tác

Chỉ tài liệu.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt cách sửa, Gate 2, merge hay phát hành.
