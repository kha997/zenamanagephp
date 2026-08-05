---
work_id: OWN-2026-001
gate: 3
gate_status: preparing
technical_readiness:
  value: not_checked
  generated_by: engineering_evidence
owner_decision:
  value: none
  authority: human_owner
decision_requested: null
references:
  spec: docs/superpowers/specs/2026-08-04-non-technical-owner-control-layer-design.md
  plan: docs/superpowers/plans/2026-08-04-owner-control-layer-repo-governance-foundation.md
  branch: feature/owner-control-layer-repo-governance-foundation
  pr: https://github.com/kha997/zenamanagephp/pull/239
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-05T00:10:00+07:00"
  updated_at: "2026-08-05T15:00:00+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "Vòng review độc lập toàn nhánh cuối cùng (opus-level) trước khi trình owner tự bắt thêm 1 lỗi Important: cách chọn hồ sơ Gate 3 'mới nhất' dùng sắp xếp chuỗi ký tự (ls | sort | tail -n1) chọn nhầm file superseded (03-release.md) thay vì file đang hiệu lực (03-release-v2.md) của GAP-031, vì '-' đứng trước '.' trong bảng mã ASCII — và cùng lúc phần loại trừ khỏi mã băm bằng chứng lại chỉ cứng '03-release.md', không loại trừ đúng file đang hiệu lực nếu nó có hậu tố phiên bản. Đã sửa bằng một hàm chọn phiên bản dùng chung (so sánh số phiên bản, không so sánh chuỗi ký tự) cho cả hai chỗ, thêm 4 phép thử mới xác nhận trực tiếp trên dữ liệu GAP-031 thật. Bản sửa này thay đổi chính script quản trị nên bằng chứng cũ hết hiệu lực theo đúng thiết kế — đang tính lại bằng chứng mới, chưa đủ điều kiện chờ owner."
technical_evidence:
  subject_sha: null
  implementation_tree_digest: "not_computed_while_preparing"
  verified_pr_head_sha: null
  verified_at: null
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

## PREPARING — OWNER ACTION NOT REQUIRED

**Mục tiêu nghiệp vụ:** Xây dựng nền tảng cấp repository cho Owner Control Layer (hồ sơ quyết định + công cụ kiểm tra tự động).

**Tiến độ:** Năm vấn đề kỹ thuật từ các lần trình trước đã sửa xong và từng được CI thật xác nhận xanh. Trước khi trình owner, một vòng review độc lập toàn nhánh (mức cao nhất, không có bối cảnh từ các lần sửa trước) đã rà lại toàn bộ thay đổi và tự bắt thêm 1 lỗi Important: cách chọn hồ sơ Gate 3 "đang hiệu lực" của một work_id dùng sắp xếp chuỗi ký tự, chọn nhầm hồ sơ đã bị thay thế (03-release.md) thay vì hồ sơ đang hiệu lực thật (03-release-v2.md) — đã tự chứng minh trên chính dữ liệu GAP-031 có sẵn trong kho mã. Nếu không sửa, phần kiểm tra "bằng chứng còn mới không" sẽ âm thầm bỏ qua đúng hồ sơ cần kiểm tra cho bất kỳ work_id nào từng thay thế hồ sơ Gate 3 của mình — đúng mẫu hình mà chính GAP-031 minh hoạ. Đã sửa bằng một hàm chọn phiên bản dùng chung (so sánh số phiên bản, không so sánh chuỗi ký tự) cho cả việc chọn hồ sơ lẫn việc loại trừ khỏi mã băm bằng chứng, thêm 4 phép thử mới xác nhận trực tiếp trên dữ liệu GAP-031 thật, và đang chờ CI xác nhận lại lần nữa trước khi trình owner.

**Vì sao đang ở trạng thái "preparing" (không phải "blocked")?** Không có kiểm tra bắt buộc nào đang đỏ do lỗi nghiệp vụ — bản sửa mới nhất chưa chạy xong CI để xác nhận, và theo đúng thiết kế, việc sửa chính script/quy trình quản trị (không phải hồ sơ quyết định) làm bằng chứng cũ hết hiệu lực, nên hồ sơ tạm quay lại "preparing" cho đến khi có bằng chứng mới, thay vì tiếp tục mang một tuyên bố "sẵn sàng" không còn đúng.

**Cần quyết định từ chủ doanh nghiệp?** Không.
