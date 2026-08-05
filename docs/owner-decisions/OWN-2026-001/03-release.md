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
  updated_at: "2026-08-05T14:00:00+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "Toàn bộ 5 lỗi kỹ thuật phát hiện qua các lần trình trước (2 lỗi phạm vi/bằng chứng ban đầu, 3 lỗi kỹ thuật nhỏ hơn phát sinh ngay trong lúc sửa — bao gồm cả lỗi 'CI đã xanh chưa' tự đối chiếu với chính công việc đang chạy nó) đều đã sửa và kiểm chứng lại bằng CI thật, không phải giả định. 22/22 kiểm tra CI thật trên PR #239 đều đạt (0 pending, 0 fail) tại đầu nhánh hiện tại, bao gồm cả việc Owner Governance Lint tự kiểm tra chính nó thành công ngay trên commit sửa lỗi tự-tham-chiếu này."
technical_evidence:
  subject_sha: "75e4adee6efbc377ca2d4eb9566937f48517e3da"
  implementation_tree_digest: "991370b67126acbae70f6ec9ca7bc95c99a861f8c12060cea0bb68c76cfb7d0c"
  verified_pr_head_sha: "75e4adee6efbc377ca2d4eb9566937f48517e3da"
  verified_at: "2026-08-05T14:00:00+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

## Owner Summary
Năm vấn đề kỹ thuật phát hiện qua các lần trình trước — bao gồm cả những lỗi mà chính hệ thống kiểm tra thật trên GitHub tự bắt được trong lúc sửa lỗi — đều đã sửa và kiểm chứng lại bằng CI thật. Nền tảng quản trị quyết định owner cấp repository sẵn sàng kỹ thuật, đang chờ owner xem qua và quyết định.

## Gói quyết định phát hành — OWN-2026-001: Nền tảng quản trị quyết định owner (repository) — bản đã sửa đầy đủ

**1. Vấn đề đã xảy ra là gì (tổng hợp các lần trình trước)?**
Lần trình đầu tiên có 2 lỗi: (a) PR #239 vô tình mang theo cả các thay đổi mã nguồn sản phẩm của một công việc khác (GAP-031) chưa merge; (b) cách ghi "bằng chứng kỹ thuật" cũ tự làm cũ chính nó ngay khi vừa ghi xong. Trong lúc sửa 2 lỗi này, hệ thống kiểm tra thật trên GitHub tự bắt thêm 3 lỗi kỹ thuật nhỏ hơn: (c) điều kiện kiểm tra "CI đã xanh chưa" bị gọi sai thời điểm, khiến nó tự thất bại trên mọi lần chạy bình thường; (d) việc checkout nhầm một commit tổng hợp (merge commit) thay vì đúng commit đầu PR thật, khiến phép tính bằng chứng đôi lúc âm thầm trả về một giá trị sai trông có vẻ hợp lệ; (e) ngay tại lần tuyên bố sẵn sàng kế tiếp, điều kiện "CI đã xanh chưa" lại tự đối chiếu với chính công việc CI đang chạy nó — về cấu trúc không thể nào xanh được ngay trên commit vừa tuyên bố sẵn sàng, dù điều kiện gọi đã đúng.

**2. Người dùng nào bị ảnh hưởng?**
Chủ doanh nghiệp (owner) — nếu owner phê duyệt dựa trên hồ sơ lỗi, owner có thể vô tình phê duyệt luôn các thay đổi mã nguồn của công việc khác, hoặc tin vào một bằng chứng kỹ thuật không chính xác.

**3. Bây giờ đã sửa những gì?**
- PR #239 xếp chồng lên PR #238 — diff hiển thị chỉ còn đúng phạm vi quản trị owner.
- Bằng chứng kỹ thuật đổi sang mã băm tính theo toàn bộ nội dung Git, loại trừ đúng một file (hồ sơ quyết định này) — không còn tự làm cũ chính nó.
- Điều kiện "CI đã xanh chưa" và điều kiện "bằng chứng còn mới không" đều sửa để chỉ áp dụng đúng lúc hồ sơ thật sự tuyên bố sẵn sàng, không áp dụng nhầm trong lúc đang chuẩn bị.
- Việc tính bằng chứng sửa để lấy đúng commit đầu PR thật, và tự báo lỗi rõ ràng thay vì âm thầm trả về giá trị sai nếu có trục trặc.
- Kiểm tra "CI đã xanh chưa" sửa để loại trừ đúng công việc đang tự kiểm tra chính nó ra khỏi danh sách đếm, và chờ có giới hạn thời gian cho các công việc song song còn lại ổn định trước khi kết luận — không còn tự thất bại vì đối chiếu với chính nó giữa lúc đang chạy.

**4. Rủi ro nào đã được đóng lại?**
Rủi ro "owner vô tình phê duyệt nhầm mã nguồn của công việc khác" đã đóng. Rủi ro "bằng chứng tự làm cũ chính nó" đã đóng bằng thiết kế mới. Rủi ro "bằng chứng có thể sai mà không ai biết" cũng đã đóng — hệ thống giờ báo lỗi rõ ràng thay vì âm thầm sai. Rủi ro "kiểm tra CI xanh tự mâu thuẫn về mặt cấu trúc" đã đóng bằng cách loại trừ chính nó khỏi phép đếm và chờ có giới hạn thời gian.

**5. Đã kiểm thử những gì?**
67 kiểm tra tự động (gồm 14 kiểm tra cho mô hình bằng chứng, trong đó có kiểm tra "phải báo lỗi rõ ràng thay vì âm thầm sai") đều đạt. 22/22 kiểm tra CI thật trên PR #239 đều đạt tại đầu nhánh hiện tại (0 đang chạy, 0 thất bại) — bao gồm cả việc công cụ tự kiểm tra chính nó thành công ngay trên chính commit sửa lỗi tự-tham-chiếu này, không phải giả định.

**6. Điều gì KHÔNG nằm trong phạm vi lần này?**
Chưa có màn hình trong ứng dụng ZENA WebApp. Chưa bật bất kỳ yêu cầu bắt buộc nào lên GitHub. Không đổi mã nguồn nghiệp vụ hay dữ liệu. PR #239 vẫn ở trạng thái xếp chồng — không được merge trước PR #238; sau khi PR #238 merge, PR #239 phải đổi lại nhánh gốc về `main` và chạy lại toàn bộ CI trước khi coi là sẵn sàng thật sự cho `main`.

**7. Vì sao owner Gate 3 của PR #239 không phải là phê duyệt PR #238?**
Đây là hai quyết định tách biệt hoàn toàn. Owner phê duyệt PR #239 chỉ có nghĩa là đồng ý với nền tảng quản trị owner — không tự động phê duyệt hay merge PR #238.

**8. Rủi ro còn lại là gì?**
Thấp. Hai giới hạn đã biết trước từ kế hoạch đã duyệt (một tài liệu mới rất hiếm gặp có thể lọt qua kiểm tra hàng loạt; phần kiểm tra "CI đã xanh chưa" nay dùng cách chờ có giới hạn thời gian — nếu một công việc CI bắt buộc chạy quá 5 phút sau các công việc còn lại, kiểm tra sẽ báo lỗi dù công việc đó cuối cùng có thể xanh; đây là đánh đổi có chủ đích, ưu tiên không chặn nhầm hơn là chờ vô hạn).

**9. Có thể hoàn tác không?**
Có, hoàn toàn. Chỉ là tài liệu, script, và cấu hình CI — không đổi cấu trúc dữ liệu, không đổi cấu hình GitHub.

**10. Đề xuất của đội kỹ thuật:** Đã sẵn sàng kỹ thuật, đã kiểm chứng lại bằng CI thật nhiều lần liên tiếp sau khi sửa lỗi. Đề xuất owner xem qua và quyết định.

**Quyết định của chủ doanh nghiệp:** ☐ Phát hành  ☐ Yêu cầu chỉnh sửa nghiệp vụ  ☐ Hoãn phát hành

## What the owner is NOT being asked to decide
Owner không được yêu cầu mở pull request kỹ thuật, đọc log CI, xem mã nguồn, hay đọc bình luận review — mọi kết luận trên đã được đội kỹ thuật xác minh trực tiếp trên hệ thống CI thật, bao gồm cả việc tự sửa và tự kiểm chứng lại 5 lỗi phát sinh qua các lần trình trước. Owner cũng không được yêu cầu quyết định về tên lớp, cấu trúc file, thuật toán băm, hay chiến lược khóa dữ liệu, hay thời gian chờ tối đa của kiểm tra CI — chỉ quyết định có phát hành nền tảng quản trị này hay không, và có chấp nhận việc PR #239 tạm thời xếp chồng lên PR #238 hay không.
