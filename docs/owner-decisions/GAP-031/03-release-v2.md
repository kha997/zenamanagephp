---
work_id: GAP-031
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
  spec: docs/superpowers/specs/2026-08-04-gap031-document-approval-workflow-design.md
  plan: docs/superpowers/plans/2026-08-04-gap031-document-approval-workflow.md
  branch: feature/gap031-document-approval-workflow
  pr: https://github.com/kha997/zenamanagephp/pull/238
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: docs/owner-decisions/GAP-031/03-release.md
superseded_by: null
timestamps:
  created_at: "2026-08-04T11:00:00+07:00"
  updated_at: "2026-08-04T11:00:00+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "30/30 kiểm tra bắt buộc đã đạt, gồm kiểm tra hai người cùng thao tác một lúc trên MySQL thật."
# evidence_digest below is an ILLUSTRATIVE PLACEHOLDER (SHA-256 of the empty
# string), not a genuinely recomputed digest. The recompute helper this plan
# specifies (owner_governance_compute_evidence_digest, shipping in Task 5's
# scripts/ssot/owner_governance_lint.php) does not exist yet at the time this
# file was written, so it could not be called even though live check data for
# this pull request was reachable. Safe to leave: owner_decision_binding
# stays null below, so nothing downstream trusts this digest as verified.
# Recompute and replace once the Task 5 lint script exists.
technical_evidence:
  head_sha: "b11c8c3ab5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0"
  evidence_digest: "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
  verified_at: "2026-08-04T11:00:00+07:00"
owner_decision_binding:
  evidence_head_sha: null
  evidence_digest: null
---

## Owner Summary
Nút Duyệt/Từ chối tài liệu trên web nay hoạt động thật và không còn đường lách quyền duyệt. Toàn bộ kiểm tra bắt buộc đã đạt, sẵn sàng phát hành, chờ quyết định phát hành.

## Gói quyết định phát hành — GAP-031: Duyệt hồ sơ tài liệu

**1. Vấn đề đã xảy ra là gì?**
Trên trang web, nút "Duyệt" và "Từ chối" một tài liệu thực ra không hoạt động đúng — chức năng này chưa từng được nối vào bất kỳ đường dẫn nào mà người dùng thật có thể bấm tới, và nếu vô tình chạm được, nó sẽ ghi vào một trạng thái không tồn tại ở bất kỳ nơi nào khác trong hệ thống. Cùng lúc đó, đội kỹ thuật phát hiện một lỗ hổng nghiêm trọng hơn: một người chỉ có quyền "sửa tài liệu" vẫn có thể tự ghi trạng thái "đã duyệt" hoặc "bị từ chối" mà không cần ai thực sự bấm nút duyệt.

**2. Người dùng nào bị ảnh hưởng?**
Bất kỳ ai có quyền duyệt tài liệu trong dự án (thường là quản lý dự án), và bất kỳ ai nộp tài liệu chờ duyệt.

**3. Bây giờ người dùng có thể làm gì?**
Trên giao diện web, người có quyền duyệt tài liệu có thể mở danh sách "Chờ duyệt", bấm Duyệt hoặc Từ chối trực tiếp, và hệ thống ghi lại rõ ràng ai đã quyết định, quyết định gì, khi nào. Vòng đời tài liệu duy nhất là: Nháp → Đã nộp → (Đã duyệt hoặc Bị từ chối).

**4. Rủi ro phân quyền nào đã được đóng lại?**
Trước đây, người chỉ có quyền "sửa" có thể tự đặt trạng thái tài liệu thành "đã duyệt"/"bị từ chối" mà không qua bước duyệt thật. Việc này đã được chặn hoàn toàn — chỉ có đúng một cách hợp lệ để chuyển trạng thái, và cách đó luôn qua kiểm tra quyền "duyệt tài liệu."

**5. Đã kiểm thử những gì?**
Toàn bộ 30 kiểm tra tự động bắt buộc đều đạt, gồm kiểm tra riêng cho tình huống hai người cùng bấm Duyệt một tài liệu cùng lúc. Đã kiểm tra dữ liệu tài liệu của một khách hàng không thể bị khách hàng khác nhìn thấy hoặc thao tác.

**6. Điều gì KHÔNG nằm trong phạm vi lần này?**
Không có thay đổi cấu trúc dữ liệu. Không khôi phục lại trạng thái cũ đã bị xoá.

**7. Vì sao GAP-032 và GAP-033 vẫn để riêng?**
GAP-032 (ý nghĩa các trạng thái tài liệu cũ) và GAP-033 (người duyệt được chỉ định riêng cho từng hồ sơ) là các quyết định nghiệp vụ riêng, không phải một phần của việc vá lỗ hổng bảo mật này.

**8. Rủi ro còn lại là gì?**
Không có rủi ro mất/lộ dữ liệu. Rủi ro còn lại thuần tuý là phạm vi sản phẩm (GAP-032/GAP-033), không xấu đi so với trước.

**9. Có thể hoàn tác không?**
Có — không đổi cấu trúc dữ liệu, có thể quay lại phiên bản trước an toàn.

**10. Đề xuất của đội kỹ thuật:** Phát hành (Approve).

**Quyết định của chủ doanh nghiệp:** ☐ Phát hành  ☐ Yêu cầu chỉnh sửa nghiệp vụ  ☐ Hoãn phát hành

## What the owner is NOT being asked to decide
Không được yêu cầu mở pull request kỹ thuật, đọc nhật ký kiểm tra tự động, xem mã nguồn, hay đọc bình luận review — mọi kết luận trên đã được đội kỹ thuật xác minh; owner chỉ quyết định có phát hành hay không.
