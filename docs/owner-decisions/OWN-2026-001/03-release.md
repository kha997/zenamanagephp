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
  updated_at: "2026-08-05T12:00:00+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "Toàn bộ 4 lỗi phát hiện qua các lần trình trước (2 lỗi phạm vi/bằng chứng ban đầu, 2 lỗi kỹ thuật nhỏ hơn phát sinh ngay trong lúc sửa) đều đã sửa và kiểm chứng lại bằng CI thật, không phải giả định. 22/22 kiểm tra CI thật trên PR #239 đều đạt (0 pending, 0 fail) tại đầu nhánh hiện tại, bao gồm cả việc Owner Governance Lint tự kiểm tra chính nó thành công."
technical_evidence:
  subject_sha: "3781d6a725de76f6146bdef8cd0c2c43a7cf7f6b"
  implementation_tree_digest: "bf0378cd2b62ce22abbe39c71b1ee9dafe788713063aa52f9a6f326bbae8c387"
  verified_pr_head_sha: "3781d6a725de76f6146bdef8cd0c2c43a7cf7f6b"
  verified_at: "2026-08-05T12:00:00+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

## Owner Summary
Bốn vấn đề kỹ thuật phát hiện qua các lần trình trước — bao gồm cả những lỗi mà chính hệ thống kiểm tra thật trên GitHub tự bắt được trong lúc sửa lỗi — đều đã sửa và kiểm chứng lại bằng CI thật. Nền tảng quản trị quyết định owner cấp repository sẵn sàng kỹ thuật, đang chờ owner xem qua và quyết định.

## Gói quyết định phát hành — OWN-2026-001: Nền tảng quản trị quyết định owner (repository) — bản đã sửa đầy đủ

**1. Vấn đề đã xảy ra là gì (tổng hợp các lần trình trước)?**
Lần trình đầu tiên có 2 lỗi: (a) PR #239 vô tình mang theo cả các thay đổi mã nguồn sản phẩm của một công việc khác (GAP-031) chưa merge; (b) cách ghi "bằng chứng kỹ thuật" cũ tự làm cũ chính nó ngay khi vừa ghi xong. Trong lúc sửa 2 lỗi này, hệ thống kiểm tra thật trên GitHub tự bắt thêm 2 lỗi kỹ thuật nhỏ hơn trong chính cách tính bằng chứng mới: (c) điều kiện kiểm tra "CI đã xanh chưa" bị gọi sai thời điểm, khiến nó tự thất bại trên mọi lần chạy bình thường; (d) việc kiểm tra checkout nhầm một commit tổng hợp (merge commit) thay vì đúng commit đầu PR thật, khiến phép tính bằng chứng đôi lúc âm thầm trả về một giá trị sai trông có vẻ hợp lệ.

**2. Người dùng nào bị ảnh hưởng?**
Chủ doanh nghiệp (owner) — nếu owner phê duyệt dựa trên hồ sơ lỗi, owner có thể vô tình phê duyệt luôn các thay đổi mã nguồn của công việc khác, hoặc tin vào một bằng chứng kỹ thuật không chính xác.

**3. Bây giờ đã sửa những gì?**
- PR #239 xếp chồng lên PR #238 — diff hiển thị chỉ còn đúng phạm vi quản trị owner.
- Bằng chứng kỹ thuật đổi sang mã băm tính theo toàn bộ nội dung Git, loại trừ đúng một file (hồ sơ quyết định này) — không còn tự làm cũ chính nó.
- Điều kiện "CI đã xanh chưa" và điều kiện "bằng chứng còn mới không" đều sửa để chỉ áp dụng đúng lúc hồ sơ thật sự tuyên bố sẵn sàng, không áp dụng nhầm trong lúc đang chuẩn bị.
- Việc tính bằng chứng sửa để lấy đúng commit đầu PR thật, và tự báo lỗi rõ ràng thay vì âm thầm trả về giá trị sai nếu có trục trặc.

**4. Rủi ro nào đã được đóng lại?**
Rủi ro "owner vô tình phê duyệt nhầm mã nguồn của công việc khác" đã đóng. Rủi ro "bằng chứng tự làm cũ chính nó" đã đóng bằng thiết kế mới. Rủi ro "bằng chứng có thể sai mà không ai biết" cũng đã đóng — hệ thống giờ báo lỗi rõ ràng thay vì âm thầm sai.

**5. Đã kiểm thử những gì?**
67 kiểm tra tự động (gồm 14 kiểm tra mới cho mô hình bằng chứng, trong đó có kiểm tra "phải báo lỗi rõ ràng thay vì âm thầm sai") đều đạt. 22/22 kiểm tra CI thật trên PR #239 đều đạt tại đầu nhánh hiện tại (0 đang chạy, 0 thất bại) — bao gồm cả việc công cụ tự kiểm tra chính nó ba lần liên tiếp trong quá trình sửa lỗi, mỗi lần đều dùng bằng chứng thật, không giả định.

**6. Điều gì KHÔNG nằm trong phạm vi lần này?**
Chưa có màn hình trong ứng dụng ZENA WebApp. Chưa bật bất kỳ yêu cầu bắt buộc nào lên GitHub. Không đổi mã nguồn nghiệp vụ hay dữ liệu. PR #239 vẫn ở trạng thái xếp chồng — không được merge trước PR #238; sau khi PR #238 merge, PR #239 phải đổi lại nhánh gốc về `main` và chạy lại toàn bộ CI trước khi coi là sẵn sàng thật sự cho `main`.

**7. Vì sao owner Gate 3 của PR #239 không phải là phê duyệt PR #238?**
Đây là hai quyết định tách biệt hoàn toàn. Owner phê duyệt PR #239 chỉ có nghĩa là đồng ý với nền tảng quản trị owner — không tự động phê duyệt hay merge PR #238.

**8. Rủi ro còn lại là gì?**
Thấp. Hai giới hạn đã biết trước từ kế hoạch đã duyệt (một tài liệu mới rất hiếm gặp có thể lọt qua kiểm tra hàng loạt; phần kiểm tra "bằng chứng cũ" nay đã tự chứng minh hoạt động đúng qua chính 3 lần sửa lỗi thật ở trên, không còn là giả định chưa kiểm chứng).

**9. Có thể hoàn tác không?**
Có, hoàn toàn. Chỉ là tài liệu, script, và cấu hình CI — không đổi cấu trúc dữ liệu, không đổi cấu hình GitHub.

**10. Đề xuất của đội kỹ thuật:** Đã sẵn sàng kỹ thuật, đã kiểm chứng lại bằng CI thật ba lần liên tiếp sau khi sửa lỗi. Đề xuất owner xem qua và quyết định.

**Quyết định của chủ doanh nghiệp:** ☐ Phát hành  ☐ Yêu cầu chỉnh sửa nghiệp vụ  ☐ Hoãn phát hành

## What the owner is NOT being asked to decide
Owner không được yêu cầu mở pull request kỹ thuật, đọc log CI, xem mã nguồn, hay đọc bình luận review — mọi kết luận trên đã được đội kỹ thuật xác minh trực tiếp trên hệ thống CI thật, bao gồm cả việc tự sửa và tự kiểm chứng lại 4 lỗi phát sinh qua các lần trình trước. Owner cũng không được yêu cầu quyết định về tên lớp, cấu trúc file, thuật toán băm, hay chiến lược khóa dữ liệu — chỉ quyết định có phát hành nền tảng quản trị này hay không, và có chấp nhận việc PR #239 tạm thời xếp chồng lên PR #238 hay không.
