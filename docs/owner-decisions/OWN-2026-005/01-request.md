---
work_id: OWN-2026-005
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: null
  plan: null
  branch: fix/OWN-2026-005-gate2-design-only-exemption
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-07T08:01:21+07:00"
  owner_response_reference: "ChatGPT project conversation — explicit owner directive 2026-08-06/07 authorizing a governance-tooling correction to owner_governance_lint.php's --enforce-gate-ordering check"
  reconciliation_required: true
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-07T08:01:21+07:00"
  updated_at: "2026-08-07T08:01:21+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

The owner approves preparation of a narrowly scoped Owner Control Layer governance-tooling correction, following the structure and precedent of OWN-2026-004.

This approval does not authorize implementing GAP-010b or GAP-034, and does not weaken or bypass `--enforce-gate-ordering`.

## Owner Summary
Khi chuẩn bị hồ sơ Gate 2 cho GAP-010b, công cụ `owner_governance_lint.php --enforce-gate-ordering` từ chối bản thiết kế (spec) tham chiếu đến chính gói Gate 2 của GAP-010b, chỉ vì gói đó đang ở trạng thái "đang chờ owner" (`awaiting_owner`) chứ chưa "đã duyệt" (`approved`) — dù bản thiết kế đó CHÍNH LÀ tài liệu được trình lên để owner ra quyết định đó. Đây là lỗi tương thích trong công cụ quản trị: nó không phân biệt được "tài liệu thiết kế đang chờ chính owner của nó xem xét" với "triển khai thực tế mà lẽ ra phải chờ Gate 2 duyệt xong mới được làm."

## Vấn đề vận hành
`scripts/ssot/owner_governance_lint.php --enforce-gate-ordering` quét mọi file trong `docs/superpowers/plans/*.md` và `docs/superpowers/specs/*.md` có khai báo `owner_gate_2_record`, và yêu cầu gói Gate 2 được tham chiếu phải có `owner_decision.value === 'approved'`, nếu không sẽ báo lỗi `gate-2-not-approved`. Quy tắc này đúng cho một bản kế hoạch triển khai (implementation plan) — không được phép triển khai trước khi Gate 2 duyệt. Nhưng quy tắc này áp dụng y hệt cho một bản THIẾT KẾ (spec) đang được trình lên CHÍNH gói Gate 2 mà nó tham chiếu — một tình huống không thể nào thoả mãn được: không thể có gói Gate 2 nào "đã duyệt" trước khi owner từng nhìn thấy bản thiết kế đi kèm nó.

## Người dùng bị ảnh hưởng
Đội kỹ thuật (không thể commit bất kỳ bản thiết kế Gate 2 nào cho GAP-010b, GAP-034, hay bất kỳ work item nào khác trong tương lai, chừng nào Gate 2 của chính nó chưa được duyệt — một vòng lặp bất khả thi); owner (không nhận được bản thiết kế Gate 2 đầy đủ để xem xét, vì CI sẽ luôn đỏ trước khi owner kịp quyết định).

## Bằng chứng
Chạy thử `php scripts/ssot/owner_governance_lint.php --enforce-gate-ordering` với bản thiết kế GAP-010b thật (`docs/superpowers/specs/2026-08-06-gap-010b-legacy-csv-export-safety-design.md`, tham chiếu `docs/owner-decisions/GAP-010b/02-design.md` đang ở `gate_status: awaiting_owner`) cho kết quả lỗi thật: `owner-governance-lint [gate-2-not-approved]: Document declares work_id 'GAP-010b', whose Gate 2 packet exists but owner_decision.value is not 'approved'.` Đây là bước kiểm tra CI thật (`.github/workflows/owner-governance-lint.yml:57`), không phải suy đoán.

## Tác động nếu không xử lý
Không có cách nào hợp lệ để trình một bản thiết kế Gate 2 hoàn chỉnh lên owner qua PR mà không làm CI đỏ — buộc đội kỹ thuật phải chọn giữa (a) không commit bản thiết kế (như đã làm với GAP-010b, chặn tiến độ) hoặc (b) tìm cách lách qua công cụ kiểm tra (không được phép).

## Phạm vi đề xuất
Sửa `owner_governance_lint.php` để phân biệt "design-only submission" (chỉ thay đổi tài liệu quản trị/thiết kế) với "implementation submission" (có thay đổi mã nguồn/công cụ khác), dựa trên phạm vi file thay đổi thực tế của PR (lấy qua `gh pr view --json files`, cơ chế đã có sẵn trong `check-gate3-before-ready.sh`/`check-evidence-freshness.sh`). Chỉ miễn trừ đúng một trường hợp: gói Gate 2 đang ở `awaiting_owner` VÀ toàn bộ file thay đổi đều nằm trong `docs/owner-decisions/`, `docs/superpowers/specs/`, `docs/superpowers/plans/`. Mọi trường hợp khác (gói bị từ chối/hoãn, thiếu, sai work_id, sai đường dẫn, hoặc diff có bất kỳ file nào ngoài 3 thư mục trên) vẫn bị từ chối y như trước.

## Loại trừ rõ ràng
Không vô hiệu hoá `--enforce-gate-ordering`. Không bỏ bước CI nào. Không tạm ghi bất kỳ gói Gate 2 nào là `approved` để lách kiểm tra. Không sửa workflow để bỏ qua lỗi. Không nới lỏng cho `docs/owner-governance/**` (thay đổi schema/tooling) — đó là loại rủi ro khác, vẫn phải qua đúng quy trình work item riêng như chính việc sửa này. Không đổi hành vi cho trường hợp không có `--changed-files` (giữ nguyên nghiêm ngặt như cũ). Không đụng đến GAP-010b, GAP-034, hay bất kỳ work item nghiệp vụ nào khác.

## Khả năng hoàn tác
Hoàn tác bằng cách revert đúng PR sửa công cụ này — không có dữ liệu, route, hay quyền hạn nào bị ảnh hưởng.

## Đề xuất
Đội kỹ thuật đề xuất: owner phê duyệt Gate 1 và Gate 2 cùng lúc cho việc sửa công cụ quản trị này (phạm vi hẹp, có test hồi quy đầy đủ, không đổi hành vi cho bất kỳ trường hợp nào khác ngoài đúng trường hợp bị chặn), để có thể tiếp tục trình Gate 2 của GAP-010b và GAP-034 theo đúng quy trình chính thức.
