---
work_id: GAP-116
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-11-09-gap-116-project-factory-dates-evidence.md
  plan: null
  branch: docs/GAP-116-project-factory-dates
  pr: https://github.com/kha997/zenamanagephp/pull/406
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-09T23:27:28+07:00"
  owner_response_reference: "Owner decision in-session on 2026-11-09, verbatim: 'APPROVE GAP-116 Gate 1'. Bound to reviewed Draft PR #406 head bbab3f49c51b5815848dc64e5cd1348b543ae995, canonical base 9a23ac6abea7a27f8890bc3fbcfa6407b4117813. Authorizes preparing Gate 2 only."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-09T23:40:00+07:00"
  updated_at: "2026-11-09T23:27:28+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner approved GAP-116 Gate 1 in-session on 2026-11-09 against Draft PR #406 head
`bbab3f49c51b5815848dc64e5cd1348b543ae995`. This authorizes preparation of Gate 2 only.

## Owner Summary

Sửa test **`ProjectApiTest` hay đỏ ngẫu nhiên** trên CI.

**Nguyên nhân:**
- Bộ tạo dữ liệu mẫu dự án (factory) chọn ngày bắt đầu và ngày kết thúc ngẫu nhiên. Hai khoảng ngày chạm nhau ở mốc
  "+1 tháng", nên thỉnh thoảng hai ngày trùng nhau.
- Khi đó test "sửa dự án" gửi ngày kết thúc = ngày bắt đầu và bị từ chối với lỗi "ngày kết thúc phải sau ngày bắt đầu".
- Lỗi này **chỉ nằm trong dữ liệu mẫu cho test**, không ảnh hưởng phần mềm thật.

**Cũng tìm thấy:** hai kiểu dữ liệu mẫu "đã xong" và "quá hạn" có thể tạo dự án có ngày kết thúc **trước** ngày bắt đầu.
Hiện chưa test nào dùng hai kiểu này.

**Gate 2 sẽ đưa phương án:**
- chỉ sửa khoảng ngày mặc định, hay sửa cả hai kiểu kia;
- có thêm test canh để lỗi không quay lại không.

## Vấn đề vận hành

CI thỉnh thoảng đỏ vô cớ, làm chậm việc merge và dễ che lỗi thật.

## Người dùng bị ảnh hưởng

Không ai ngoài quy trình phát triển (CI).

## Bằng chứng

`docs/audits/2026-11-09-gap-116-project-factory-dates-evidence.md`.

## Phạm vi đề xuất

Gate 2: sửa `database/factories/ProjectFactory.php`, thêm test canh nếu chọn. Không đổi mã chạy thật.

## Loại trừ rõ ràng

Quy tắc kiểm tra ngày, controller, nội dung test API, các factory khác, deploy.

## Khả năng hoàn tác

Revert. Không migration, không đổi dữ liệu thật.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: `APPROVE GAP-116 Gate 1` / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa chọn phương án, Gate 2, merge.
