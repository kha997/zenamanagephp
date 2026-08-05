---
work_id: OWN-2026-001
gate: 3
gate_status: awaiting_owner
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_correction_or_defer"
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
  updated_at: "2026-08-05T10:30:00+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "Cả hai vấn đề đã được sửa và kiểm chứng lại bằng CI thật: (1) PR #239 nay xếp chồng lên PR #238 (base branch đổi), diff cô lập không còn chứa file mã nguồn sản phẩm nào; (2) mô hình ràng buộc bằng chứng đã đổi sang digest cấu trúc theo cây Git (loại trừ đúng một file hồ sơ Gate 3), không còn tự làm cũ chính nó khi commit hồ sơ. 22/22 kiểm tra CI thật trên PR #239 đều đạt (0 pending, 0 fail) tại đầu nhánh hiện tại."
technical_evidence:
  subject_sha: "8e05126f18b56150f852a3c0ec68f997260cae6b"
  implementation_tree_digest: "16a3b273249c183aa81ba6377e4a0d4a76621fa0b20e0c2032e178a9772b10be"
  verified_pr_head_sha: "8e05126f18b56150f852a3c0ec68f997260cae6b"
  verified_at: "2026-08-05T10:30:00+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

## Owner Summary
Hai vấn đề kỹ thuật phát hiện ở lần trình đầu tiên đã được sửa và kiểm chứng lại bằng CI thật, không phải giả định. Nền tảng quản trị quyết định owner cấp repository sẵn sàng kỹ thuật, đang chờ owner xem qua và quyết định.

## Gói quyết định phát hành — OWN-2026-001: Nền tảng quản trị quyết định owner (repository) — bản đã sửa

**1. Vấn đề đã xảy ra là gì (ở lần trình trước)?**
Lần trình đầu tiên có 2 lỗi: (a) PR #239 vô tình mang theo cả các thay đổi mã nguồn sản phẩm của một công việc khác (GAP-031) chưa merge, vì PR #239 nhánh từ `main` nhưng lịch sử Git của nó đi qua toàn bộ nhánh GAP-031; (b) cách ghi "bằng chứng kỹ thuật" cũ (dựa trên commit đầu nhánh) tự làm cũ chính nó — việc tạo ra hồ sơ quyết định lại chính là một commit mới, nên bằng chứng ghi trong hồ sơ luôn bị lệch ngay khi vừa ghi xong.

**2. Người dùng nào bị ảnh hưởng?**
Chủ doanh nghiệp (owner) — nếu owner phê duyệt dựa trên hồ sơ lỗi, owner sẽ vô tình phê duyệt luôn các thay đổi mã nguồn GAP-031 chưa được xem xét trong phạm vi này.

**3. Bây giờ đã sửa những gì?**
- PR #239 nay xếp chồng lên PR #238 (đổi nhánh gốc từ `main` sang nhánh GAP-031) — diff hiển thị của PR #239 giờ chỉ còn đúng phạm vi quản trị owner, không còn file mã nguồn sản phẩm nào.
- Cách ghi "bằng chứng kỹ thuật" đổi sang một mã băm tính theo toàn bộ nội dung Git tại một thời điểm, loại trừ đúng một file (chính hồ sơ quyết định này) — nên việc cập nhật hồ sơ không còn tự làm cũ chính bằng chứng của nó nữa, trong khi bất kỳ thay đổi thật nào khác (code, quy tắc, cấu hình CI) vẫn làm mã băm đổi và bắt buộc phải làm lại bằng chứng — đã được kiểm chứng bằng 13 phép thử tự động, gồm cả một phép thử dùng chính lịch sử Git thật của PR #239 để chứng minh lỗi cũ không thể tái diễn.

**4. Rủi ro nào đã được đóng lại?**
Rủi ro "owner vô tình phê duyệt nhầm mã nguồn của công việc khác" đã đóng — diff đã cô lập và xác minh lại bằng máy. Rủi ro "bằng chứng tự làm cũ chính nó" đã đóng bằng thiết kế mới, không phải bằng vá tạm.

**5. Đã kiểm thử những gì?**
66 kiểm tra tự động (bao gồm 13 kiểm tra mới cho mô hình bằng chứng) đều đạt. 22/22 kiểm tra CI thật trên PR #239 đều đạt tại đầu nhánh hiện tại (0 đang chạy, 0 thất bại) — bao gồm cả việc công cụ tự kiểm tra chính nó (Owner Governance Lint). Diff cô lập của PR #239 (so với nhánh gốc PR #238) đã xác nhận không còn file nào dưới `app/`, `resources/`, `routes/`, `database/migrations/`, `database/seeders/`.

**6. Điều gì KHÔNG nằm trong phạm vi lần này?**
Chưa có màn hình trong ứng dụng ZENA WebApp. Chưa bật bất kỳ yêu cầu bắt buộc nào lên GitHub. Không đổi mã nguồn nghiệp vụ hay dữ liệu. PR #239 vẫn ở trạng thái xếp chồng — không được merge trước PR #238; sau khi PR #238 merge, PR #239 phải đổi lại nhánh gốc về `main` và chạy lại toàn bộ CI trước khi coi là sẵn sàng thật sự cho `main`.

**7. Vì sao owner Gate 3 của PR #239 không phải là phê duyệt PR #238?**
Đây là hai quyết định tách biệt hoàn toàn. PR #238 (GAP-031) có gói quyết định riêng của chính nó. Owner phê duyệt PR #239 chỉ có nghĩa là đồng ý với nền tảng quản trị owner — không tự động phê duyệt hay merge PR #238.

**8. Rủi ro còn lại là gì?**
Thấp. Hai giới hạn đã biết trước từ kế hoạch đã duyệt (một tài liệu mới rất hiếm gặp có thể lọt qua kiểm tra hàng loạt; phần kiểm tra "bằng chứng cũ" khi chạy thật chưa từng thử với một hồ sơ không thuộc diện miễn trừ lịch sử — đây chính là hồ sơ đầu tiên, và đã tự chứng minh hoạt động đúng qua chính quá trình sửa lỗi ở trên).

**9. Có thể hoàn tác không?**
Có, hoàn toàn. Chỉ là tài liệu, script, và cấu hình CI — không đổi cấu trúc dữ liệu, không đổi cấu hình GitHub.

**10. Đề xuất của đội kỹ thuật:** Đã sẵn sàng kỹ thuật, đã kiểm chứng lại bằng CI thật sau khi sửa lỗi. Đề xuất owner xem qua và quyết định.

**Quyết định của chủ doanh nghiệp:** ☐ Phát hành  ☐ Yêu cầu chỉnh sửa nghiệp vụ  ☐ Hoãn phát hành

## What the owner is NOT being asked to decide
Owner không được yêu cầu mở pull request kỹ thuật, đọc log CI, xem mã nguồn, hay đọc bình luận review — mọi kết luận trên đã được đội kỹ thuật xác minh trực tiếp trên hệ thống CI thật, bao gồm cả việc tự sửa và tự kiểm chứng lại 2 lỗi phát sinh ở lần trình trước. Owner cũng không được yêu cầu quyết định về tên lớp, cấu trúc file, thuật toán băm, hay chiến lược khóa dữ liệu — chỉ quyết định có phát hành nền tảng quản trị này hay không, và có chấp nhận việc PR #239 tạm thời xếp chồng lên PR #238 hay không.
