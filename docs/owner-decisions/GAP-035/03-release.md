---
work_id: GAP-035
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
  spec: null
  plan: null
  branch: docs/GAP-035-route-name-collision-gate1-prep
  pr: https://github.com/kha997/zenamanagephp/pull/261
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
  created_at: "2026-08-14T12:12:15+07:00"
  updated_at: "2026-08-14T12:12:15+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "All 27 approved route-name entries verified against the Gate 2 (round 2) contract at PR head 25b8049b995233a2e70dad51baa248f82a5e060c: zero duplicate non-empty route names across the complete route collection under both APP_ENV=testing and APP_ENV=production, including vendor routes; the 27 renamed/preserved entries retain their pre-change method, URI, middleware, and handler/action; the five preserved API-side names (projects.store/show/update/destroy, tasks.store) still resolve to the same endpoints; the permanent generic duplicate-name guard is committed and was mutation-proved on the committed test itself (fresh temporary duplicate introduced, guard failed, reverted byte-clean, guard passed again); php artisan route:cache exits successfully under both environments; a strengthened cache-lifecycle test now proves the cached route:list output is tuple-identical (method, URI, middleware, action) to the uncached output for all 27 entries, per environment independently, not by cross-environment comparison; route:clear/cache-artifact cleanup runs in finally and restoration success is itself asserted. Consumer regression suite (8 files), route-guard CI script, and the full local suite (2169 tests, 0 failures) all pass. All exact-head CI checks are green on PR #261 at this SHA, including Owner Governance Lint, test-routes-guardrails, and Code Quality Analysis (PHPStan)."
technical_evidence:
  subject_sha: "25b8049b995233a2e70dad51baa248f82a5e060c"
  implementation_tree_digest: "8ccacbb418d4ebab4192dda9a977ac80f6d228837363ba22b66ff6e44c6e4098"
  verified_pr_head_sha: "25b8049b995233a2e70dad51baa248f82a5e060c"
  verified_at: "2026-08-14T12:12:15+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

## Owner Summary
Bảy nhóm tên route trùng lặp (27 route bị ảnh hưởng) đã được đổi tên theo đúng bảng tên cuối cùng owner đã duyệt ở Gate 2. Toàn bộ hành vi (đường dẫn, quyền, xử lý) giữ nguyên — chỉ đổi tên nội bộ. Lệnh `route:cache` (bắt buộc khi triển khai production) nay chạy thành công, không còn bị chặn. Đã kiểm chứng: bộ nhớ đệm route không làm sai lệch bất kỳ route nào trong 27 route theo dõi, ở cả môi trường kiểm thử lẫn production. Sẵn sàng phát hành, chờ quyết định.

## Gói quyết định phát hành — GAP-035: Tên route bị trùng chặn triển khai

**1. Vấn đề đã xảy ra là gì?**
Bảy nhóm tên route bị trùng lặp trong hệ thống khiến lệnh `route:cache` — lệnh bắt buộc trong quy trình triển khai production — thất bại ngay lập tức. Vấn đề này được phát hiện trong lúc xác minh một việc khác (GAP-011) và không liên quan đến GAP-011.

**2. Người dùng nào bị ảnh hưởng?**
Không có người dùng cuối nào bị ảnh hưởng trực tiếp trước khi phát hành — đây là rủi ro vận hành (chặn triển khai), không phải lỗi hiển thị. Gián tiếp, mọi người dùng phụ thuộc vào việc triển khai production diễn ra đúng hạn.

**3. Bây giờ hệ thống hoạt động thế nào?**
27 route trong 7 nhóm trùng tên đã được đổi tên duy nhất theo đúng quy ước owner đã duyệt (ví dụ `web.projects.store` thay vì trùng `projects.store` với route API thật). Đường dẫn, quyền truy cập, và hành vi xử lý của từng route giữ nguyên hoàn toàn — chỉ tên nội bộ dùng để tham chiếu route thay đổi. `route:cache` nay chạy thành công ở cả môi trường kiểm thử và production.

**4. Rủi ro nào đã được đóng lại?**
Rủi ro triển khai production bị chặn hoàn toàn bởi `route:cache` thất bại đã được đóng lại. Đồng thời, một guard kiểm tra tự động vĩnh viễn đã được thêm vào để nếu sau này có ai vô tình tạo thêm route trùng tên, hệ thống kiểm thử sẽ báo lỗi ngay, không phải đợi đến lúc triển khai mới phát hiện.

**5. Đã kiểm thử những gì?**
Toàn bộ 27 route được xác minh giữ nguyên phương thức, đường dẫn, quyền truy cập, và bộ xử lý trước/sau khi đổi tên. Guard chống trùng tên tổng quát (không giới hạn 7 nhóm đã biết) đã được kiểm chứng bằng cách tạo một route trùng tên mới, xác nhận guard bắt lỗi, rồi khôi phục sạch. Đã kiểm chứng: sau khi bật cache route, dữ liệu route được lưu trong cache khớp chính xác với dữ liệu route chưa cache — trên cả 27 route theo dõi, ở cả hai môi trường riêng biệt. Bộ kiểm thử liên quan (8 tệp) và toàn bộ bộ kiểm thử hệ thống (2169 kiểm thử) đều đạt. Toàn bộ kiểm tra CI bắt buộc trên GitHub đều đạt tại đúng phiên bản mã này.

**6. Điều gì KHÔNG nằm trong phạm vi lần này?**
Không thay đổi thiết kế route nào ngoài việc đổi tên. Không gộp, xoá, hay chuyển hướng route nào. Không đụng đến GAP-011 (việc dọn dẹp route `_debug`), vốn đang chờ đúng việc phát hành này hoàn tất để tiếp tục.

**7. Rủi ro còn lại là gì?**
Không có rủi ro mất/lộ dữ liệu hay thay đổi hành vi nghiệp vụ. Rủi ro còn lại thấp và thuần kỹ thuật.

**8. Có thể hoàn tác không?**
Có — đây chỉ là đổi tên nội bộ, không đổi cấu trúc dữ liệu, có thể quay lại phiên bản trước an toàn.

**9. Đề xuất của đội kỹ thuật:** Phát hành (Approve).

**Quyết định của chủ doanh nghiệp:** ☐ Phát hành  ☐ Yêu cầu chỉnh sửa nghiệp vụ  ☐ Hoãn phát hành

## What the owner is NOT being asked to decide
Không được yêu cầu mở pull request kỹ thuật, đọc nhật ký kiểm tra tự động, xem mã nguồn, hay đọc bình luận review — mọi kết luận trên đã được đội kỹ thuật xác minh; owner chỉ quyết định có phát hành hay không.
