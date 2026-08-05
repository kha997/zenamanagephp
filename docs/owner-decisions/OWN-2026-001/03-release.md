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
  updated_at: "2026-08-06T09:00:00+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "Owner da phe duyet phat hanh GAP-031 (PR #238); PR da merge vao main bang squash tai commit 29f7ea5e. Lich su nhanh PR #239 da duoc sua (rebase co uy quyen ro rang cua owner) de dam bao diff co lap that su sach sau khi PR #238 merge bang squash. Sau do CI that con bat them 1 loi that: mot kiem tra chi chay tren PR nham main (chua tung chay tren nhanh nay truoc do) phat hien 1 test tu bo qua co dieu kien thieu dung quy uoc skip-contract cua repo -- da sua va dang ky vao baseline. 50/50 kiem tra CI bat buoc deu dat (1 muc deploy bo qua dung thiet ke) tai dau nhanh hien tai. Mot vong review doc lap toan nhanh moi (sau khi lich su bi viet lai) xac nhan: diff van thuan quan tri, rebase khong lam mat/trung/hong noi dung, ban sua skip-contract dung va du, khong con test tu bo qua nao khac thieu quy uoc, co day du dau vet tai lieu giai thich viec sua lich su -- 0 loi Critical/Important/Minor."
technical_evidence:
  subject_sha: "c7a865359ed3fc9010364d48cf12b8b11020c908"
  implementation_tree_digest: "f5be9486c9fa436db8ee54eee7ff54ab6deff327a24097c399ad72e5f923272d"
  verified_pr_head_sha: "c7a865359ed3fc9010364d48cf12b8b11020c908"
  verified_at: "2026-08-06T09:00:00+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

## Owner Summary
GAP-031 đã được owner phê duyệt và merge vào `main`. Nhánh PR #239 sau đó cần sửa lại lịch sử commit (đã có owner ủy quyền rõ ràng bằng văn bản) để diff cô lập thực sự sạch sau khi GAP-031 merge bằng squash. Toàn bộ CI bắt buộc trên đầu nhánh đã sửa đều đạt, và một vòng review độc lập mới (sau khi lịch sử bị viết lại) xác nhận không có vấn đề gì. Nền tảng quản trị quyết định owner cấp repository sẵn sàng kỹ thuật, đang chờ owner xem qua và quyết định.

## Gói quyết định phát hành — OWN-2026-001: Nền tảng quản trị quyết định owner (repository) — bản đã sửa đầy đủ sau khi GAP-031 merge

**1. Chuyện gì đã xảy ra kể từ lần trình "hoãn" trước?**
Owner đã phê duyệt phát hành GAP-031 (PR #238) qua một quyết định Gate 3 riêng biệt. PR #238 đã merge vào `main` bằng squash (gộp toàn bộ thành 1 commit mới, `29f7ea5e`). Bước tiếp theo — đổi nhánh gốc PR #239 sang `main` — lộ ra một vấn đề kỹ thuật thật: vì merge bằng squash tạo ra một commit mới không cùng dòng lịch sử với các commit GAP-031 gốc, nhánh PR #239 vẫn mang theo toàn bộ các commit GAP-031 gốc (chưa gộp), khiến diff cô lập không còn sạch — đúng vấn đề mà việc đổi nhánh gốc lẽ ra phải giải quyết.

**2. Vấn đề này đã được sửa như thế nào?**
Với sự cho phép rõ ràng bằng văn bản của owner (chỉ định chính xác nhánh, SHA đầu nhánh cũ mong đợi, SHA đầu nhánh mới sau khi sửa): tạo nhánh sao lưu trước khi thay đổi bất kỳ điều gì; rebase lại đúng 33 commit thuộc riêng OWN-2026-001 lên đúng đầu nhánh `main` mới (không đụng đến commit GAP-031 nào); xác minh diff cô lập rỗng cả cục bộ lẫn trên chính các tham chiếu từ xa sau khi đẩy lên bằng `--force-with-lease` có điều kiện bảo vệ (không dùng `--force` trần).

**3. Có phát sinh lỗi kỹ thuật nào thêm không?**
Có 1 lỗi thật, do CI thật tự bắt: một kiểm tra chỉ chạy trên PR nhắm vào `main` (chưa từng chạy trên nhánh này trước đó, vì trước đó nhánh nhắm vào một nhánh khác không phải `main`) phát hiện 1 bài kiểm tra tự bỏ qua có điều kiện (khi 2 commit lịch sử cụ thể không tồn tại) thiếu đúng quy ước bắt buộc của kho mã cho loại kiểm tra này. Đã sửa và đăng ký vào danh sách baseline.

**4. Đã kiểm chứng những gì?**
50/50 kiểm tra CI bắt buộc trên đầu nhánh hiện tại đều đạt (1 mục "deploy" không chạy trên PR theo đúng thiết kế). Một vòng review độc lập toàn nhánh mới (bắt buộc vì lịch sử commit đã bị viết lại) đã xác nhận: diff vẫn thuần quản trị (không có file mã nguồn sản phẩm nào); việc rebase không làm mất, trùng lặp hay hỏng bất kỳ nội dung nào; bản sửa quy ước bỏ-qua-kiểm-tra đúng và đầy đủ; không còn bài kiểm tra tự bỏ qua nào khác thiếu quy ước; có đầy đủ dấu vết tài liệu giải thích rõ vì sao lịch sử bị sửa. Kết quả: 0 lỗi Critical/Important/Minor.

**5. Điều gì KHÔNG nằm trong phạm vi lần này?**
Chưa có màn hình trong ứng dụng ZENA WebApp. Chưa bật bất kỳ yêu cầu bắt buộc nào lên GitHub (CODEOWNERS review, branch protection). Không đổi mã nguồn nghiệp vụ hay dữ liệu.

**6. Rủi ro còn lại là gì?**
Thấp. Các giới hạn đã biết trước từ các lần trình trước vẫn còn nguyên (một tài liệu mới rất hiếm gặp có thể lọt qua kiểm tra hàng loạt; kiểm tra "CI đã xanh chưa" dùng cách chờ có giới hạn thời gian 5 phút; việc loại trừ công việc tự-kiểm-tra-chính-nó dựa trên tên hiển thị chính xác) — không có rủi ro mới phát sinh từ việc sửa lịch sử nhánh.

**7. Có thể hoàn tác không?**
Có, hoàn toàn. Chỉ là tài liệu, script, và cấu hình CI — không đổi cấu trúc dữ liệu, không đổi cấu hình GitHub. Nhánh sao lưu trước khi rebase vẫn còn giữ lại cục bộ.

**8. Đề xuất của đội kỹ thuật:** Đã sẵn sàng kỹ thuật, đã kiểm chứng lại bằng CI thật và một vòng review độc lập mới sau khi sửa lịch sử nhánh. Đề xuất owner xem qua và quyết định.

**Quyết định của chủ doanh nghiệp:** ☐ Phát hành  ☐ Yêu cầu chỉnh sửa nghiệp vụ  ☐ Hoãn phát hành

## What the owner is NOT being asked to decide
Owner không được yêu cầu mở pull request kỹ thuật, đọc log CI, xem mã nguồn, hay đọc bình luận review — mọi kết luận trên đã được đội kỹ thuật xác minh trực tiếp trên hệ thống CI thật và qua một vòng review độc lập riêng biệt. Owner cũng không được yêu cầu quyết định về cách rebase, cấu trúc commit, hay chi tiết kỹ thuật của việc sửa lịch sử nhánh — chỉ quyết định có phát hành nền tảng quản trị này hay không.
