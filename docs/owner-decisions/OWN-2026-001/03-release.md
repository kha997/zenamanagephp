---
work_id: OWN-2026-001
gate: 3
gate_status: deferred
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: deferred
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
  recorded_at: "2026-08-05T17:00:00+07:00"
  owner_response_reference: "ChatGPT project conversation — Owner Gate 3 decision on 2026-08-05"
  reconciliation_required: true
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-05T00:10:00+07:00"
  updated_at: "2026-08-05T17:00:00+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "Toàn bộ 6 lỗi kỹ thuật phát hiện qua các lần trình trước và qua vòng review độc lập toàn nhánh cuối cùng (2 lỗi phạm vi/bằng chứng ban đầu, 4 lỗi kỹ thuật nhỏ hơn phát sinh ngay trong lúc sửa — gồm cả lỗi 'CI đã xanh chưa' tự đối chiếu với chính công việc đang chạy nó, và lỗi chọn nhầm hồ sơ Gate 3 khi có phiên bản thay thế) đều đã sửa và kiểm chứng lại bằng CI thật, không phải giả định. 22/22 kiểm tra CI thật trên PR #239 đều đạt (0 pending, 0 fail) tại đầu nhánh hiện tại."
technical_evidence:
  subject_sha: "8ff12dabefeaadcc854a5e6c81ffc2c2a30ed18a"
  implementation_tree_digest: "10f246e46311edcdb7211c7661835090402503d5be065dac410003f9753d8a0a"
  verified_pr_head_sha: "8ff12dabefeaadcc854a5e6c81ffc2c2a30ed18a"
  verified_at: "2026-08-05T16:00:00+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

## DEFERRED — QUYẾT ĐỊNH ĐÃ GHI NHẬN

**Quyết định của chủ doanh nghiệp:** Hoãn phát hành (deferred). Đây KHÔNG phải là từ chối, và KHÔNG yêu cầu chỉnh sửa nghiệp vụ nào đối với nền tảng quản trị này.

**Lý do hoãn (nguyên văn):** Hoãn phát hành vì PR #239 hiện là PR xếp chồng trên PR #238. PR #239 chỉ được trình phê duyệt phát hành cuối cùng sau khi PR #238 được xử lý và merge, PR #239 được đổi base về main, toàn bộ CI bắt buộc chạy lại, phạm vi diff được xác nhận lại và bằng chứng kỹ thuật được tái tạo trên trạng thái tích hợp cuối cùng.

**Bằng chứng kỹ thuật bên dưới** phản ánh trạng thái đã kiểm chứng tại thời điểm hoãn (commit `8ff12dab`, phạm vi xếp chồng trên PR #238) — đây là lịch sử kỹ thuật, không phải bằng chứng ràng buộc cho một quyết định phê duyệt. Không có ràng buộc phê duyệt nào được ghi (`owner_decision_binding` vẫn để trống) vì hoãn không phải là phê duyệt.

## Owner Summary
Sáu vấn đề kỹ thuật phát hiện qua các lần trình trước và qua một vòng review độc lập toàn nhánh cuối cùng — bao gồm cả những lỗi mà chính hệ thống kiểm tra thật trên GitHub, và chính người review độc lập, tự bắt được trong lúc sửa lỗi — đều đã sửa và kiểm chứng lại bằng CI thật. Nền tảng quản trị quyết định owner cấp repository sẵn sàng kỹ thuật trong phạm vi xếp chồng hiện tại. Owner đã xem và quyết định hoãn phát hành cho đến khi PR #238 được merge và PR #239 được tái xác minh trên `main`.

## Gói quyết định phát hành — OWN-2026-001: Nền tảng quản trị quyết định owner (repository) — bản đã sửa đầy đủ

**1. Vấn đề đã xảy ra là gì (tổng hợp toàn bộ các lần trình trước)?**
Lần trình đầu tiên có 2 lỗi: (a) PR #239 vô tình mang theo cả các thay đổi mã nguồn sản phẩm của một công việc khác (GAP-031) chưa merge; (b) cách ghi "bằng chứng kỹ thuật" cũ tự làm cũ chính nó ngay khi vừa ghi xong. Trong lúc sửa 2 lỗi này, hệ thống kiểm tra thật trên GitHub tự bắt thêm 3 lỗi kỹ thuật nhỏ hơn: (c) điều kiện kiểm tra "CI đã xanh chưa" bị gọi sai thời điểm; (d) checkout nhầm một commit tổng hợp thay vì đúng commit đầu PR thật; (e) điều kiện "CI đã xanh chưa" tự đối chiếu với chính công việc CI đang chạy nó — về cấu trúc không thể nào xanh được ngay trên commit vừa tuyên bố sẵn sàng. Trước khi trình owner, một vòng review độc lập toàn nhánh (mức cao nhất, không mang theo bối cảnh các lần sửa trước) tự bắt thêm lỗi thứ 6: cách chọn hồ sơ Gate 3 "đang hiệu lực" của một work_id dùng sắp xếp chuỗi ký tự, chọn nhầm hồ sơ đã bị thay thế thay vì hồ sơ đang hiệu lực thật — tự chứng minh trên chính dữ liệu GAP-031 có sẵn trong kho mã.

**2. Người dùng nào bị ảnh hưởng?**
Chủ doanh nghiệp (owner) — nếu owner phê duyệt dựa trên hồ sơ lỗi, owner có thể vô tình phê duyệt luôn các thay đổi mã nguồn của công việc khác, tin vào một bằng chứng kỹ thuật không chính xác, hoặc (với lỗi thứ 6) một work_id tương lai từng thay thế hồ sơ Gate 3 của nó có thể bị bỏ qua kiểm tra bằng chứng còn mới hay không.

**3. Bây giờ đã sửa những gì?**
- PR #239 xếp chồng lên PR #238 — diff hiển thị chỉ còn đúng phạm vi quản trị owner.
- Bằng chứng kỹ thuật đổi sang mã băm tính theo toàn bộ nội dung Git, loại trừ đúng một file (hồ sơ quyết định đang hiệu lực) — không còn tự làm cũ chính nó.
- Điều kiện "CI đã xanh chưa" và điều kiện "bằng chứng còn mới không" đều sửa để chỉ áp dụng đúng lúc hồ sơ thật sự tuyên bố sẵn sàng.
- Việc tính bằng chứng sửa để lấy đúng commit đầu PR thật, và tự báo lỗi rõ ràng thay vì âm thầm trả về giá trị sai nếu có trục trặc.
- Kiểm tra "CI đã xanh chưa" sửa để loại trừ đúng công việc đang tự kiểm tra chính nó ra khỏi danh sách đếm, và chờ có giới hạn thời gian cho các công việc song song còn lại ổn định trước khi kết luận.
- Cách chọn hồ sơ Gate 3 "đang hiệu lực" đổi từ sắp xếp chuỗi ký tự sang so sánh số phiên bản thật, dùng chung một hàm cho cả việc chọn hồ sơ lẫn việc loại trừ khỏi mã băm bằng chứng — không còn thể lệch nhau.

**4. Rủi ro nào đã được đóng lại?**
Rủi ro "owner vô tình phê duyệt nhầm mã nguồn của công việc khác" đã đóng. Rủi ro "bằng chứng tự làm cũ chính nó" đã đóng bằng thiết kế mới. Rủi ro "bằng chứng có thể sai mà không ai biết" đã đóng. Rủi ro "kiểm tra CI xanh tự mâu thuẫn về mặt cấu trúc" đã đóng. Rủi ro "kiểm tra bằng chứng âm thầm bỏ qua đúng hồ sơ cần kiểm tra khi work_id có phiên bản thay thế" đã đóng, có phép thử trực tiếp trên dữ liệu GAP-031 thật chứng minh.

**5. Đã kiểm thử những gì?**
71 kiểm tra tự động (gồm 18 kiểm tra cho mô hình bằng chứng) đều đạt. 22/22 kiểm tra CI thật trên PR #239 đều đạt tại đầu nhánh hiện tại (0 đang chạy, 0 thất bại). Một vòng review độc lập toàn nhánh (mức cao nhất, đọc toàn bộ diff từ đầu nhánh, không có bối cảnh phiên làm việc) đã xác nhận: phạm vi PR xếp chồng sạch (không có file mã nguồn sản phẩm nào), phần loại trừ bằng chứng đúng và hẹp, không có kiểm tra bằng chứng nào bị bỏ qua sai lúc, không có tuyên bố "đã xác thực" nào vượt quá "bản ghi tự khai trong kho mã", không có kẽ hở nào cho work_id mới né qua danh sách work_id cũ, và không có mã nguồn sản phẩm nào lẫn vào — chỉ có đúng 1 lỗi Important (đã sửa như trên) và 2 ghi chú Minor không ảnh hưởng đến tính đúng đắn (đã ghi nhận, rủi ro thấp, không chặn phát hành).

**6. Điều gì KHÔNG nằm trong phạm vi lần này?**
Chưa có màn hình trong ứng dụng ZENA WebApp. Chưa bật bất kỳ yêu cầu bắt buộc nào lên GitHub. Không đổi mã nguồn nghiệp vụ hay dữ liệu. PR #239 vẫn ở trạng thái xếp chồng — không được merge trước PR #238; sau khi PR #238 merge, PR #239 phải đổi lại nhánh gốc về `main` và chạy lại toàn bộ CI trước khi coi là sẵn sàng thật sự cho `main`.

**7. Vì sao owner Gate 3 của PR #239 không phải là phê duyệt PR #238?**
Đây là hai quyết định tách biệt hoàn toàn. Owner phê duyệt PR #239 chỉ có nghĩa là đồng ý với nền tảng quản trị owner — không tự động phê duyệt hay merge PR #238.

**8. Rủi ro còn lại là gì?**
Thấp. Ba giới hạn đã biết: (a) một tài liệu mới rất hiếm gặp có thể lọt qua kiểm tra hàng loạt; (b) kiểm tra "CI đã xanh chưa" dùng cách chờ có giới hạn thời gian (5 phút) — nếu một công việc CI bắt buộc chạy quá thời gian này sau các công việc còn lại, kiểm tra sẽ báo lỗi dù công việc đó cuối cùng có thể xanh, đây là đánh đổi có chủ đích ưu tiên không chặn nhầm hơn chờ vô hạn; (c) việc loại trừ công việc tự-kiểm-tra-chính-nó khỏi phép đếm CI dựa trên tên hiển thị chính xác — nếu một công việc CI khác trùng tên hệt như vậy, nó cũng sẽ bị loại trừ nhầm, nhưng việc đó đòi sửa chính file cấu hình CI (bản thân đã bị quản trị theo dõi).

**9. Có thể hoàn tác không?**
Có, hoàn toàn. Chỉ là tài liệu, script, và cấu hình CI — không đổi cấu trúc dữ liệu, không đổi cấu hình GitHub.

**10. Đề xuất của đội kỹ thuật (tại thời điểm trình):** Đã sẵn sàng kỹ thuật, đã kiểm chứng lại bằng CI thật nhiều lần liên tiếp sau khi sửa lỗi, và đã qua một vòng review độc lập toàn nhánh riêng biệt.

**Quyết định của chủ doanh nghiệp:** ☐ Phát hành  ☐ Yêu cầu chỉnh sửa nghiệp vụ  ☑ Hoãn phát hành

## Bước tiếp theo (sau khi PR #238 merge)
1. Đổi base của PR #239 về `main`.
2. Xác minh lại diff cô lập (phải rỗng với các đường dẫn mã nguồn sản phẩm).
3. Chạy lại toàn bộ kiểm tra CI bắt buộc trên `main`.
4. Tính lại mã băm bằng chứng (implementation-tree digest) trên trạng thái tích hợp cuối cùng.
5. Cập nhật `technical_evidence`.
6. Chuyển Gate 3 từ `deferred` sang `preparing`, rồi sang `awaiting_owner` chỉ sau khi mọi kiểm tra bắt buộc đều xanh.
7. Trình owner một quyết định Gate 3 cuối cùng, riêng biệt.

## What the owner is NOT being asked to decide
Owner không được yêu cầu mở pull request kỹ thuật, đọc log CI, xem mã nguồn, hay đọc bình luận review — mọi kết luận trên đã được đội kỹ thuật xác minh trực tiếp trên hệ thống CI thật và qua một vòng review độc lập riêng biệt, bao gồm cả việc tự sửa và tự kiểm chứng lại 6 lỗi phát sinh qua toàn bộ quá trình. Owner cũng không được yêu cầu quyết định về tên lớp, cấu trúc file, thuật toán băm, chiến lược khóa dữ liệu, hay thời gian chờ tối đa của kiểm tra CI.
