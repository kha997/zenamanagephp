---
work_id: GAP-033
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
  spec: docs/superpowers/specs/2026-08-12-gap033-document-approver-assignment-design.md
  plan: docs/superpowers/plans/2026-08-12-gap033-document-approver-assignment.md
  branch: docs/GAP-033-gate1-prep
  pr: https://github.com/kha997/zenamanagephp/pull/258
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
  created_at: "2026-08-13T10:00:53+07:00"
  updated_at: "2026-08-13T10:00:53+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "8 nhiệm vụ triển khai gốc + Task 9 (đối chiếu quy tắc phạm vi dự án theo yêu cầu Owner) hoàn tất; rà soát toàn nhánh độc lập không còn phát hiện Nghiêm trọng/Quan trọng nào chưa xử lý; toàn bộ kiểm tra bắt buộc trên GitHub thật đều đạt tại đúng đầu nhánh, gồm cả kiểm tra hai tiến trình độc lập cùng thao tác trên MySQL thật."
technical_evidence:
  subject_sha: "47899f73f6f5d28224852f8af7852625e5170996"
  implementation_tree_digest: "16c2e5093c7e4e118d95e9230d211dd9007a987b028de1196e8eac11b64ff686"
  verified_pr_head_sha: "47899f73f6f5d28224852f8af7852625e5170996"
  verified_at: "2026-08-13T10:00:53+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

## Owner Summary

GAP-033 thêm cơ chế "người duyệt được chỉ định" cho từng tài liệu — mặc định tự động theo quản lý dự án, có thể chỉ định riêng khi cần, với các ràng buộc an toàn: chỉ quản lý dự án/Admin được gán, người được gán phải đúng tenant + đúng đội dự án + tự có quyền duyệt, và việc gán không tự cấp quyền duyệt. Toàn bộ 8 nhiệm vụ gốc cộng thêm 1 nhiệm vụ đối chiếu theo yêu cầu làm rõ của Owner đã hoàn tất, đã qua rà soát độc lập nhiều vòng, toàn bộ kiểm tra bắt buộc đều đạt tại đúng đầu nhánh hiện tại. Sẵn sàng kỹ thuật để phát hành, đang chờ quyết định của chủ doanh nghiệp.

## Gói quyết định phát hành — GAP-033: Chỉ định người duyệt tài liệu

**1. Vấn đề nghiệp vụ nào đã được xử lý?**
Trước đây, hệ thống chỉ biết "ai có quyền duyệt tài liệu trong tenant" chứ không biết "tài liệu này cụ thể ai phải duyệt". Không có cách xác định trước người phụ trách, nên tài liệu chờ duyệt không thể xuất hiện trong danh sách việc cần làm cá nhân của đúng người, và khi có nhiều người cùng giữ quyền duyệt, không ai được xem là người chịu trách nhiệm chính.

**2. Hành vi thực tế đã triển khai (Hướng C — kết hợp mặc định tự động và chỉ định riêng):**
- Mỗi tài liệu có một "người duyệt hiệu lực": nếu có chỉ định riêng thì dùng người đó; nếu không, tự động lấy quản lý dự án của dự án chứa tài liệu; nếu dự án cũng chưa có quản lý, tài liệu hoạt động như trước (không có người phụ trách cụ thể, ai có quyền duyệt chung vẫn duyệt được).
- **Chỉ quản lý dự án của đúng dự án đó, hoặc Admin, mới được gán/đổi người duyệt** — người chỉ có quyền sửa tài liệu thông thường không được tự ý chỉ định.
- **Người được gán phải đúng 3 điều kiện cùng lúc:** cùng tenant với tài liệu, là thành viên đang hoạt động của đúng đội dự án chứa tài liệu đó (không tính người đã rời đội), VÀ tự bản thân đã có quyền duyệt tài liệu theo vai trò của họ. Thiếu một trong ba điều kiện, việc gán bị từ chối ngay lập tức — không lưu lại như một trạng thái "chờ hợp lệ".
- **Được gán KHÔNG tự động cấp quyền duyệt.** Đây là điểm quan trọng nhất về an toàn phân quyền: gán tên chỉ có tác dụng nếu người đó độc lập đã có quyền duyệt qua vai trò của họ — không phải một cách "lách" để cấp quyền mới.
- **Khi tài liệu được mở lại để sửa** (sau khi đã duyệt/từ chối), người được gán trước đó tiếp tục giữ nguyên cho tới khi có ai chủ động đổi lại — không tự động xoá.
- **Mọi thay đổi người duyệt đều được ghi lại đầy đủ dấu vết kiểm toán** (ai đổi, đổi từ ai sang ai, khi nào) — không thể sửa hay xoá lịch sử này.

**3. Đã kiểm thử những gì?**
Toàn bộ luồng trên đều có kiểm tra tự động tương ứng và đều đạt, gồm đúng 5 tình huống Owner yêu cầu xác minh: cùng tenant + cùng dự án + có quyền duyệt → gán được; cùng tenant nhưng khác dự án → bị từ chối; khác tenant → bị từ chối; cùng dự án nhưng chưa có quyền duyệt → bị từ chối; đổi người duyệt (reassign) cũng áp dụng đúng các điều kiện trên. Ngoài kiểm tra chức năng, có rà soát độc lập nhiều vòng (rà soát riêng từng phần việc, rà soát toàn bộ nhánh) — một lần rà soát cuối phát hiện quy tắc "đúng phạm vi tenant/project" trong bản duyệt nghiệp vụ trước đó mới chỉ kiểm tra tenant, thiếu kiểm tra đúng đội dự án; đã được báo lại cho Owner làm rõ, Owner xác nhận cần kiểm tra cả hai, và đã bổ sung, kiểm tra lại độc lập, đạt. Tại đúng đầu nhánh hiện tại, toàn bộ kiểm tra bắt buộc trên hệ thống kiểm tra tự động thật (không phải máy cá nhân) đều đạt.

**Xác nhận riêng theo yêu cầu:** phần logic duyệt/từ chối tài liệu hiện tại (`DocumentPolicy::approve()`) và các phần lõi đã có từ trước của GAP-032 (quy trình duyệt, vòng đời tài liệu, quản lý phiên bản tài liệu) **không hề bị thay đổi** trong toàn bộ GAP-033 — đã xác minh trực tiếp bằng cách đọc lại phần khác biệt (diff) của các file đó, xác nhận không có dòng nào bị sửa.

**4. Điều gì KHÔNG nằm trong phạm vi lần này?**
Không có việc chuyển đổi hàng loạt dữ liệu cũ — tài liệu chưa từng được gán người duyệt vẫn hoạt động như trước. Không thiết kế hay triển khai màn hình "việc cần làm hôm nay" cho tài liệu — đó là bước riêng, sau GAP-033. Không xử lý vấn đề tương tự ở RFI (GAP-030, để riêng). Không cho phép nhiều người duyệt cùng lúc/theo trình tự cho một tài liệu. Không có truy vấn hay thao tác nào trên dữ liệu sản xuất trong suốt quá trình làm việc này.

**5. Có 7 điểm nhỏ được đội kỹ thuật ghi nhận nhưng chưa xử lý — mức độ rủi ro thực tế ra sao?**
Không điểm nào ảnh hưởng tới dữ liệu, bảo mật, hay tách biệt dữ liệu giữa các khách hàng — tất cả đã được xác minh không có đường khai thác thật. Đây đều là các ghi chú về khả năng làm lưới kiểm tra tự động chắc hơn cho tương lai (ví dụ: một dòng logic hiển thị trên giao diện có lặp lại một phần quy tắc phân quyền, nếu sau này quy tắc đổi thì giao diện cần cập nhật theo tay), không phải lỗi đang xảy ra trong hành vi hiện tại.

**6. Có thể hoàn tác không?**
Có. Toàn bộ thay đổi cấu trúc dữ liệu chỉ thêm cột/bảng mới, có thể để trống, không xoá, không đổi, không ép dữ liệu cũ theo khuôn mới. Có thể quay lại phiên bản trước một cách an toàn nếu cần, không mất dữ liệu.

**7. Đề xuất của đội kỹ thuật:** Phát hành (Approve). Toàn bộ tiêu chí sẵn sàng kỹ thuật đã đạt; không còn vấn đề Nghiêm trọng/Quan trọng nào chưa xử lý; quy tắc nghiệp vụ đã được xác minh khớp chính xác với quyết định ràng buộc của Owner qua hai vòng làm rõ.

**Quyết định của chủ doanh nghiệp:** ☐ Phát hành  ☐ Yêu cầu chỉnh sửa nghiệp vụ  ☐ Hoãn phát hành

## What the owner is NOT being asked to decide

Chủ doanh nghiệp không được yêu cầu mở pull request kỹ thuật, đọc nhật ký kiểm tra tự động, xem mã nguồn, hay đọc bình luận rà soát — mọi kết luận trên đã được đội kỹ thuật xác minh độc lập nhiều vòng, bao gồm việc xác minh lại đúng 5 tình huống Owner tự đặt ra cho quy tắc phạm vi dự án. Chủ doanh nghiệp cũng không được yêu cầu quyết định về GAP-030 hay việc triển khai "Việc cần làm hôm nay" — đó là các quyết định nghiệp vụ riêng. Ở đây chỉ cần quyết định một việc: có phát hành thay đổi đã được xác minh này hay không.
