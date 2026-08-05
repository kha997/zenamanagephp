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
  updated_at: "2026-08-05T00:10:00+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "50/50 kiểm tra CI thật đã đạt (bao gồm cả Owner Governance Lint tự kiểm tra chính nó), 1 việc deploy tự động bỏ qua đúng như thiết kế trên PR. Không có thay đổi mã nguồn sản phẩm hay cấu trúc dữ liệu."
technical_evidence:
  head_sha: "f775d286637a02d8e25f164a78bad8a70281a201"
  evidence_digest: "c3bdff5fd9daefe7e8d1daa9ef53ed5c2142b92f16d73c044feae827423b63f9"
  verified_at: "2026-08-05T00:10:00+07:00"
owner_decision_binding:
  evidence_head_sha: null
  evidence_digest: null
---

## Owner Summary
Hệ thống ghi nhận quyết định của chủ doanh nghiệp (Owner Control Layer) đã được xây dựng xong ở tầng repository — dạng hồ sơ và công cụ kiểm tra tự động, chưa phải màn hình trong ứng dụng. Toàn bộ kiểm tra bắt buộc đã đạt. Không có thay đổi nào tới mã nguồn nghiệp vụ hay dữ liệu. Chưa có gì được bật thành bắt buộc — mọi thứ đang chờ owner xem qua và quyết định.

## Gói quyết định phát hành — OWN-2026-001: Nền tảng quản trị quyết định owner (repository)

**1. Vấn đề đã xảy ra là gì?**
Trước đây, mọi quyết định nghiệp vụ (có làm hay không, thiết kế thế nào, có phát hành hay không) đều nằm rải rác trong hội thoại và tài liệu kỹ thuật — không có một định dạng thống nhất, dễ kiểm tra bằng máy, để xác nhận: quyết định đã được ghi nhận đúng cách, bằng chứng kỹ thuật đi kèm còn mới hay đã cũ, và owner không bị yêu cầu đọc code hay log CI để ra quyết định.

**2. Người dùng nào bị ảnh hưởng?**
Chủ doanh nghiệp (owner) — người ra quyết định kinh doanh — và các agent kỹ thuật soạn hồ sơ trình lên owner.

**3. Bây giờ người dùng có thể làm gì?**
Mỗi thay đổi lớn giờ đi qua đúng 3 cổng: (1) xác nhận vấn đề có thật, (2) duyệt thiết kế nghiệp vụ, (3) duyệt phát hành. Mỗi cổng có một trang tóm tắt ngắn, tiếng Việt dễ hiểu, không cần đọc code. Có công cụ tự động kiểm tra hồ sơ có hợp lệ không, có bị "cũ" (bằng chứng đã thay đổi sau khi quyết định) hay không — mà không cần chờ thông báo, kiểm tra ngay mỗi lần chạy.

**4. Rủi ro nào đã được đóng lại?**
Không có rủi ro dữ liệu hay bảo mật nào bị mở ra — không có thay đổi mã nguồn sản phẩm hay cấu trúc dữ liệu. Rủi ro về "một quyết định owner có vẻ hợp lệ nhưng thực ra không phải owner quyết định" được xử lý trung thực: hệ thống hiện tại KHÔNG tự nhận là đã xác thực danh tính owner — điều này được ghi rõ, không che giấu. Việc bật một lớp bảo vệ mạnh hơn (yêu cầu review từ người khác) bị hoãn có chủ đích, vì hiện tại chỉ có đúng một tài khoản GitHub cho cả kho mã lẫn agent — bật sớm sẽ hoặc làm kẹt cứng, hoặc tạo cảm giác giả về một sự chấp thuận độc lập không có thật.

**5. Đã kiểm thử những gì?**
54 kiểm tra tự động mới (100% đạt). 50 kiểm tra CI thật trên GitHub đều đạt, gồm cả việc tự kiểm tra chính công cụ mới ("Owner Governance Lint"). Trong quá trình chạy CI thật lần đầu, phát hiện và sửa ngay một lỗi đường dẫn thật trong kịch bản kiểm tra bằng chứng — đúng như một hệ thống trung thực nên làm: không giấu lỗi, sửa và kiểm chứng lại công khai.

**6. Điều gì KHÔNG nằm trong phạm vi lần này?**
Chưa có màn hình trong ứng dụng ZENA WebApp (Decision Center) — mới chỉ là hồ sơ dạng file. Chưa bật bất kỳ yêu cầu bắt buộc nào lên GitHub (không có review bắt buộc, không có branch protection mới). Không đổi bất kỳ mã nguồn nghiệp vụ hay dữ liệu nào.

**7. Vì sao một số giới hạn vẫn còn để lại, chưa giải quyết hết?**
Hai giới hạn đã biết trước, ghi nhận công khai ngay trong tài liệu kế hoạch đã duyệt, không phải phát hiện bất ngờ: (a) một tài liệu mới rất hiếm gặp — không có mã định danh trong tên file lẫn không có phần khai báo chuẩn — có thể lọt qua kiểm tra hàng loạt; (b) phần kiểm tra "bằng chứng có bị cũ hay không" khi chạy thật trên một PR đang thay đổi chưa từng được thử nghiệm đầu-cuối với một hồ sơ thật không thuộc diện miễn trừ lịch sử — vì đây chính là hồ sơ thật đầu tiên.

**8. Rủi ro còn lại là gì?**
Thấp. Không mất/lộ dữ liệu. Rủi ro còn lại thuần túy là hai giới hạn đã nêu ở mục 7, đã được ghi nhận công khai, không ảnh hưởng tới tính đúng đắn của các kiểm tra đã chạy.

**9. Có thể hoàn tác không?**
Có, hoàn toàn. Đây chỉ là tài liệu và script — không đổi cấu trúc dữ liệu, không đổi cấu hình GitHub, có thể gỡ bỏ nhánh này bất cứ lúc nào mà không ảnh hưởng gì tới hệ thống đang chạy.

**10. Đề xuất của đội kỹ thuật:** Đã sẵn sàng kỹ thuật. Đề xuất owner xem qua và quyết định — không có gì trong bước này yêu cầu owner phải đọc code, log CI, hay tài liệu kiến trúc.

**Quyết định của chủ doanh nghiệp:** ☐ Phát hành  ☐ Yêu cầu chỉnh sửa nghiệp vụ  ☐ Hoãn phát hành

## What the owner is NOT being asked to decide
Owner không được yêu cầu mở pull request kỹ thuật, đọc log CI, xem mã nguồn, hay đọc bình luận review — mọi kết luận trên đã được đội kỹ thuật xác minh trực tiếp trên hệ thống CI thật. Owner cũng không được yêu cầu quyết định về tên lớp, cấu trúc file, hay chiến lược khóa dữ liệu — chỉ quyết định có phát hành nền tảng quản trị này hay không.
