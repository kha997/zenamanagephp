---
work_id: GAP-032
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
  spec: docs/superpowers/specs/2026-08-10-gap032-document-status-semantics-design.md
  plan: docs/superpowers/plans/2026-08-10-gap032-document-status-semantics.md
  branch: docs/GAP-032-document-status-semantics
  pr: https://github.com/kha997/zenamanagephp/pull/256
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
  created_at: "2026-08-12T13:38:28+07:00"
  updated_at: "2026-08-12T13:38:28+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "8/8 nhiệm vụ triển khai hoàn tất; rà soát toàn nhánh độc lập không còn phát hiện Nghiêm trọng/Quan trọng nào chưa xử lý; toàn bộ kiểm tra bắt buộc trên GitHub thật đều đạt tại đúng đầu nhánh, gồm cả kiểm tra hai tiến trình độc lập cùng thao tác trên MySQL thật."
technical_evidence:
  subject_sha: "95c250e05dcf6b70bebcf755914753365812166d"
  implementation_tree_digest: "6a5cbd15f00f4311c76611c7392ff5dc13e047544bad0e79e6c50a20e8d55a07"
  verified_pr_head_sha: "95c250e05dcf6b70bebcf755914753365812166d"
  verified_at: "2026-08-12T13:38:28+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

## Owner Summary

GAP-032 tách trường "trạng thái tài liệu" vốn đang gánh hai ý nghĩa lẫn lộn thành hai chiều rõ ràng — trạng thái vòng đời (Nháp/Đang xem xét/Đã phát hành/Đã lưu trữ) và trạng thái phê duyệt (Chưa nộp/Chờ duyệt/Đã duyệt/Bị từ chối) — và đóng một lỗ hổng tranh chấp dữ liệu thật: trước đây một phiên bản tài liệu mới có thể được tạo ra ngay trong lúc tài liệu đang chờ duyệt, khiến hồ sơ duyệt và bản nội dung không còn khớp nhau. Toàn bộ 8/8 nhiệm vụ triển khai đã hoàn tất, đã qua rà soát độc lập nhiều vòng, và toàn bộ kiểm tra bắt buộc — bao gồm kiểm tra hai tiến trình thật cùng thao tác đồng thời trên cơ sở dữ liệu MySQL thật — đều đạt tại đúng đầu nhánh hiện tại. Sẵn sàng kỹ thuật để phát hành, đang chờ quyết định của chủ doanh nghiệp.

## Gói quyết định phát hành — GAP-032: Ý nghĩa trạng thái tài liệu

**1. Vấn đề nghiệp vụ nào đã được xử lý?**
Trước đây, một tài liệu chỉ có một ô "trạng thái" duy nhất phải gánh cùng lúc hai câu hỏi khác nhau: "Tài liệu này đang ở giai đoạn biên tập nào?" và "Tài liệu này đã được duyệt hay chưa?". Vì hai câu hỏi này dùng chung một ô, hệ thống không có định nghĩa nghiệp vụ thống nhất, dẫn tới các tình huống vô lý: tài liệu tạo qua API bị kẹt ở trạng thái "active" mà không có đường vào quy trình duyệt; một khi tài liệu đã vào quy trình duyệt, việc sửa thông tin thông thường (đổi tên, sửa mô tả) lại âm thầm bị chặn không rõ lý do. Nghiêm trọng hơn, đội kỹ thuật phát hiện: một tài liệu có thể được tạo phiên bản mới ngay trong lúc đang chờ duyệt, khiến hồ sơ "ai đã duyệt bản nào" không còn đáng tin.

**2. Bây giờ người dùng có thể làm gì?**
Người tạo/biên tập tài liệu có một vòng đời rõ ràng và nhất quán dù tạo từ web hay từ API: Nháp → Đang xem xét → Đã phát hành → Đã lưu trữ (và có thể đưa tài liệu đã lưu trữ trở lại để sửa tiếp). Song song, người nộp duyệt và người duyệt có một quy trình duyệt độc lập, tường minh: Chưa nộp → Chờ duyệt → Đã duyệt/Bị từ chối, với các hành động rõ ràng (Nộp duyệt, Duyệt, Từ chối, Mở lại để sửa). Việc sửa nội dung thông thường không còn vô tình làm mất/sai lệch trạng thái duyệt, và tài liệu cũ (dữ liệu trước GAP-032) vẫn hiển thị, tìm kiếm, lọc được bình thường như trước.

**3. Rủi ro nào đã được đóng lại?**
- **Rủi ro tranh chấp dữ liệu (mới phát hiện, đã đóng):** trước đây có thể tạo phiên bản tài liệu mới ngay trong lúc tài liệu đang chờ duyệt hoặc đang được quyết định — nay bị chặn hoàn toàn ở đúng nơi duy nhất tạo phiên bản, đã được kiểm chứng bằng kiểm tra hai tiến trình thật chạy đồng thời trên MySQL thật (không phải giả lập).
- **Rủi ro giả mạo dữ liệu quy trình duyệt:** trước đây một yêu cầu sửa thông tin chung có thể vô tình cài cắm giá trị trạng thái duyệt/lịch sử duyệt giả — nay các trường này bị lọc bỏ, chỉ có đúng các hành động duyệt tường minh mới được ghi.
- **Rủi ro mất dấu vết kiểm toán:** mọi quyết định duyệt (ai, khi nào, bản nào) tiếp tục được lưu đầy đủ; nếu dữ liệu lịch sử cũ thiếu thông tin cần thiết, hệ thống từ chối xử lý an toàn (fail-closed) thay vì tự suy đoán/giả lập.

**4. Đã kiểm thử những gì?**
Toàn bộ các luồng nghiệp vụ trên đều có kiểm tra tự động tương ứng và đều đạt, gồm cả kiểm tra chuyên biệt cho tình huống hai người/hai tiến trình cùng thao tác một tài liệu cùng lúc (chạy trên MySQL thật, không phải môi trường giả lập). Ngoài kiểm tra chức năng, còn có rà soát độc lập nhiều vòng (rà soát riêng từng phần việc, rà soát toàn bộ nhánh) để tìm những vấn đề mà bộ kiểm tự động có thể bỏ sót; một vấn đề tìm được ở vòng rà soát cuối (bộ lọc danh sách tài liệu chưa báo lỗi rõ ràng khi nhận giá trị không hợp lệ) đã được sửa và xác nhận lại. Tại đúng đầu nhánh hiện tại, toàn bộ kiểm tra bắt buộc trên hệ thống kiểm tra tự động thật (không phải máy cá nhân) đều đạt.

**5. Điều gì KHÔNG nằm trong phạm vi lần này?**
Không có việc chuyển đổi hàng loạt dữ liệu cũ — các tài liệu chưa từng bị đụng tới vẫn giữ nguyên trạng thái cũ, không bị hệ thống tự ý "chuẩn hoá" lại. Không có thay đổi nào về việc ai được phân công duyệt tài liệu nào (đó là phạm vi của một hạng mục khác, xem mục 6). Không có truy vấn hay thao tác nào trên dữ liệu sản xuất (production) trong suốt quá trình làm việc này.

**6. Vì sao hạng mục "người duyệt được chỉ định riêng" (GAP-033) vẫn để riêng?**
GAP-032 chỉ định nghĩa RÕ quy trình duyệt có những trạng thái gì và chuyển đổi ra sao. Việc "ai là người có quyền duyệt tài liệu này" là một quyết định nghiệp vụ khác, độc lập, chưa được quyết định. GAP-032 cố tình không đụng tới việc phân công người duyệt, bảng người duyệt, hay logic nhắc việc — để không trộn lẫn hai quyết định nghiệp vụ khác nhau vào cùng một lần phát hành.

**7. Có 10 điểm nhỏ được đội kỹ thuật ghi nhận nhưng chưa xử lý — mức độ rủi ro thực tế ra sao?**
Không điểm nào trong 10 điểm này ảnh hưởng tới dữ liệu, bảo mật, hoặc tách biệt dữ liệu giữa các khách hàng (tenant) — tất cả đã được xác minh không có đường khai thác thật ở thời điểm hiện tại. Quy về 4 nhóm:
- **6 điểm là "lưới an toàn có thể siết chặt hơn"** (các bộ kiểm tra tự động dùng để phát hiện vi phạm trong tương lai vẫn còn vài khoảng hẹp về lý thuyết) — không phải lỗi đang xảy ra, mà là cơ hội làm lưới bảo vệ chắc hơn cho các thay đổi sau này.
- **2 điểm là hành vi biên hiếm gặp, tự phục hồi** — ví dụ một cách đánh số phiên bản tài liệu có một trường hợp đặc biệt hiếm khi không có phiên bản nào trước đó, nhưng sẽ tự đúng lại ở lần tạo tiếp theo; không gây mất dữ liệu.
- **1 điểm là dữ liệu lịch sử có sẵn từ trước** (một số bản ghi cũ có thể có ô hiển thị trạng thái không khớp nhau do dữ liệu tồn tại trước GAP-032 — hệ thống cố tình không tự sửa dữ liệu cũ để tránh ghi đè ý định người dùng trước đây, đúng theo nguyên tắc đã được Owner duyệt ở Gate 2).
- **1 điểm nằm ngoài phạm vi GAP-032 hoàn toàn** — một hành động xoá tài liệu thiếu một lớp kiểm tra quyền, nhưng đây là vấn đề có sẵn từ trước khi GAP-032 bắt đầu (đã xác minh bằng cách so sánh với mã nguồn gốc), không phải do lần thay đổi này gây ra; đội kỹ thuật đề xuất mở một hạng mục riêng để xử lý, không trộn vào GAP-032.

**8. Có thể hoàn tác không?**
Có. Thay đổi cấu trúc dữ liệu chỉ thêm cột mới có thể để trống (không bắt buộc), không xoá, không đổi, không ép dữ liệu cũ theo khuôn mới. Vì vậy có thể quay lại phiên bản trước một cách an toàn nếu cần, không mất dữ liệu.

**9. Đề xuất của đội kỹ thuật:** Phát hành (Approve). Toàn bộ tiêu chí sẵn sàng kỹ thuật đã đạt; không còn vấn đề Nghiêm trọng/Quan trọng nào chưa xử lý.

**Quyết định của chủ doanh nghiệp:** ☐ Phát hành  ☐ Yêu cầu chỉnh sửa nghiệp vụ  ☐ Hoãn phát hành

## What the owner is NOT being asked to decide

Chủ doanh nghiệp không được yêu cầu đọc mã nguồn, nhật ký kiểm tra tự động, tên các bước kiểm tra kỹ thuật, hay bình luận rà soát — toàn bộ phần đó đã được đội kỹ thuật xác minh độc lập nhiều vòng. Chủ doanh nghiệp cũng không được yêu cầu quyết định về GAP-033 (người duyệt được chỉ định riêng) — đó là một quyết định nghiệp vụ riêng, sẽ được trình bày ở một gói quyết định khác. Ở đây chỉ cần quyết định một việc: có phát hành thay đổi đã được xác minh này hay không.
